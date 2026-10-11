<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\calendar-extended-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) Kester Mielke
 * @copyright  Copyright (c) cgoIT
 * @author     Kester Mielke
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\CalendarExtendedBundle\EventListener\DataContainer;

use Cgoit\CalendarExtendedBundle\Classes\Utils;
use Cgoit\CalendarExtendedBundle\Exception\CalendarExtendedException;
use Cgoit\CalendarExtendedBundle\Service\RecurrenceCalculator;
use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Date;
use Contao\Message;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;

class CalendarEventsCallbacks
{
    public function __construct(
        private readonly Connection $db,
        private readonly RecurrenceCalculator $recurrenceCalculator,
    ) {
    }

    /**
     * @param array<mixed> $arrRow
     * @param array<mixed> $labels
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'list.label.label')]
    public function listChildRecords(array $arrRow, string $label, DataContainer $dc, array $labels): string
    {
        $span = Calendar::calculateSpan($arrRow['startTime'], $arrRow['endTime']);

        if ($span > 0) {
            $date = Date::parse(Config::get($arrRow['addTime'] ? 'datimFormat' : 'dateFormat'), $arrRow['startTime']).$GLOBALS['TL_LANG']['MSC']['cal_timeSeparator'].Date::parse(Config::get($arrRow['addTime'] ? 'datimFormat' : 'dateFormat'), $arrRow['endTime']);
        } elseif ($arrRow['startTime'] === $arrRow['endTime']) {
            $date = Date::parse(Config::get('dateFormat'), $arrRow['startTime']).($arrRow['addTime'] ? ' '.Date::parse(Config::get('timeFormat'), $arrRow['startTime']) : '');
        } else {
            $date = Date::parse(Config::get('dateFormat'), $arrRow['startTime']).($arrRow['addTime'] ? ' '.Date::parse(Config::get('timeFormat'), $arrRow['startTime']).$GLOBALS['TL_LANG']['MSC']['cal_timeSeparator'].Date::parse(Config::get('timeFormat'), $arrRow['endTime']) : '');
        }

        $recurringIndicator = '';
        if (!empty($arrRow['recurring']) || !empty($arrRow['recurringExt'])) {
            $recurringAltText = $GLOBALS['TL_LANG']['tl_calendar_events']['cal_series_date_title'];
            $recurringIndicator =
                '<img src="/bundles/cgoitcalendarextended/recurring.svg" width="16px" height="16px" title="'.$recurringAltText.'" style="margin-left: 5px;">';
        }

        return '<div class="tl_content_left">'.$arrRow['title'].' <span class="label-info">['.$date.']</span>'.$recurringIndicator.'</div>';
    }

    /**
     * Checks for overlapping events of the same calendar before the event is saved.
     *
     * @param array<string, mixed> $values the submitted values (only the changed fields)
     *
     * @return array<string, mixed>
     *
     * @throws CalendarExtendedException
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onbeforesubmit')]
    public function checkOverlapping(array $values, DataContainer $dc): array
    {
        // Return if there is no record yet (the form is only rendered)
        if (!$dc->id) {
            return $values;
        }

        // The submitted values are not yet part of the record
        $record = array_merge((array) $dc->getCurrentRecord(), $values);

        // Return if the event is recurring
        if (!empty($record['recurring']) || !empty($record['recurringExt']) || empty($record['startDate'])) {
            return $values;
        }

        if (!CalendarModel::findById((int) $record['pid'])?->uniqueEvents) {
            return $values;
        }

        [$intStart, $intEnd] = Utils::calculateEventRange($record);

        $objEvents = CalendarEventsModel::findCurrentByPid((int) $record['pid'], $intStart, $intEnd, ['return' => 'Collection']);

        $rows = [];

        foreach ($objEvents ?? [] as $objEvent) {
            $rows[] = $objEvent->row();
        }

        // findCurrentByPid also returns recurring events. therefor we have to check the times
        $nonUniqueEvents = Utils::findOverlappingEventIds($intStart, $intEnd, (int) $dc->id, $rows);

        if ([] !== $nonUniqueEvents) {
            // In the multi edit modes all records are rolled back, so the message names the event that is checked
            throw new CalendarExtendedException(\sprintf('"%s" (ID %d): %s (%s)', StringUtil::specialchars((string) ($record['title'] ?? '')), (int) $dc->id, $GLOBALS['TL_LANG']['tl_calendar_events']['nonUniqueEvents'], implode(',', $nonUniqueEvents)));
        }

        return $values;
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'fields.repeatFixedDates.load')]
    public function initializeFixedDatesRepeat(mixed $value, DataContainer $dc): mixed
    {
        if (empty($value)) {
            // An empty value would lead to pre-filled values in the mcw for date and time fields. Therefore
            // we set empty values for one row to prevent that.
            $fixedDatesDefault = [['new_repeat' => '', 'new_start' => '', 'new_end' => '', 'reason' => '']];
            $value = serialize($fixedDatesDefault);
        }

        return $value;
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'fields.repeatExceptionsPer.load')]
    public function initializeRepeatExceptionsPer(mixed $value, DataContainer $dc): mixed
    {
        if (empty($value)) {
            // An empty value would lead to pre-filled values in the mcw for date and time fields. Therefore
            // we set empty values for one row to prevent that.
            $fixedDatesDefault = [['exception' => '', 'exceptionTo' => '']];
            $value = serialize($fixedDatesDefault);
        }

        return $value;
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: -100)]
    public function handleRecurrencesAndExceptions(DataContainer $dc): void
    {
        // Return if there is no active record (override all)
        if (!$dc->id) {
            return;
        }

        $record = (array) $dc->getCurrentRecord();

        // Return if no start date has been set yet
        if (empty($record['startDate'])) {
            return;
        }

        $arrSet['weekday'] = (int) date('w', $record['startDate']);

        if (!empty($record['addTime']) && !empty($record['ignoreEndTime']) && !empty($record['endDate']) && !empty($record['startTime'])) {
            $arrSet['endTime'] = strtotime(date('Y-m-d', $record['endDate']).' '.date('H:i:s', $record['startTime']));
        }

        $arrSet['repeatEnd'] = 0;

        try {
            [$arrAllRecurrences, $arrSet] = $this->recurrenceCalculator->calculate($record, [], [$arrSet['repeatEnd']], $arrSet);
        } catch (\InvalidArgumentException $exception) {
            // The event is saved, but the recurrences cannot be calculated from the invalid data
            Message::addError(\sprintf($GLOBALS['TL_LANG']['tl_calendar_events']['invalidRecurrenceData'], $exception->getMessage()));

            return;
        }

        ksort($arrAllRecurrences);
        $arrSet['allRecurrences'] = serialize($arrAllRecurrences);

        // Execute the update sql
        $this->db->update('tl_calendar_events', $arrSet, ['id' => $dc->id]);
    }

    /**
     * Just check that only one option is active for recurring events.
     *
     * The submitted values are merged into the record, so this works in all edit modes (also editAll/overrideAll).
     *
     * @param array<string, mixed> $values the submitted values (only the changed fields)
     *
     * @return array<string, mixed>
     *
     * @throws CalendarExtendedException
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onbeforesubmit')]
    public function checkRecurrenceTypes(array $values, DataContainer $dc): array
    {
        $record = array_merge((array) $dc->getCurrentRecord(), $values);

        if (!empty($record['recurring']) && !empty($record['recurringExt'])) {
            throw new CalendarExtendedException($GLOBALS['TL_LANG']['tl_calendar_events']['checkRecurring']);
        }

        return $values;
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'fields.repeatEachExt.load')]
    public function defaultRepeatEachExt(mixed $value, DataContainer $dc): mixed
    {
        if (empty($value)) {
            $weekdays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            $startDate = (int) ($dc->getCurrentRecord()['startDate'] ?? 0);

            // Pre-select "first <weekday of the event>"
            $value = serialize(['value' => 'first', 'unit' => $weekdays[$startDate > 0 ? (int) date('w', $startDate) : 1]]);
        }

        return $value;
    }
}
