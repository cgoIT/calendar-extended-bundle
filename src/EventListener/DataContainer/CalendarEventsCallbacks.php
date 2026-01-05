<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\calendar-extended-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) Kester Mielke
 * @copyright  Copyright (c) 2024, cgoIT
 * @author     Kester Mielke
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\CalendarExtendedBundle\EventListener\DataContainer;

use Cgoit\CalendarExtendedBundle\Classes\Utils;
use Cgoit\CalendarExtendedBundle\Exception\CalendarExtendedException;
use Contao\Backend;
use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\Database\Result;
use Contao\DataContainer;
use Contao\Date;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;

class CalendarEventsCallbacks extends Backend
{
    /**
     * @var array<mixed>
     */
    private readonly array $arrMonths;

    /**
     * @var array<mixed>
     */
    private readonly array $arrDays;

    public function __construct(
        private readonly Connection $db,
        private readonly RequestStack $requestStack,
        private readonly int $maxRepeatCount,
        private readonly int $maxExceptionsCount,
    ) {
        parent::__construct();

        System::loadLanguageFile('default', 'en', true);
        $this->arrMonths = $GLOBALS['TL_LANG']['MONTHS'];
        $this->arrDays = $GLOBALS['TL_LANG']['DAYS'];
        System::loadLanguageFile('default');
    }

    /**
     * @param array<mixed> $arrRow
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'list.sorting.child_record')]
    public function listChildRecords(array $arrRow): string
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

    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit')]
    public function checkOverlapping(DataContainer $dc): bool
    {
        // Return if there is no active record (override all)
        if (!$dc->id) {
            return false;
        }

        // @phpstan-ignore-next-line
        $activeRecord = method_exists($dc, 'getCurrentRecord') ? (object) $dc->getCurrentRecord() : $dc->activeRecord;

        // Return if the event is recurring
        if ($activeRecord->recurring || $activeRecord->recurringExt) {
            return false;
        }

        // Set start date
        $intStart = $activeRecord->startDate;
        $intEnd = $activeRecord->startDate;

        $intStart = strtotime(date('Y-m-d', $intStart).' 00:00');

        // Set end date
        if (!empty($activeRecord->endDate) && $activeRecord->endDate > $activeRecord->startDate) { // @phpstan-ignore-line
            $intEnd = $activeRecord->endDate;
        }
        $intEnd = strtotime(date('Y-m-d', $intEnd).' 23:59');

        // Add time
        if ($activeRecord->addTime) {
            $intStart = strtotime(date('Y-m-d', $intStart).' '.date('H:i:s', $activeRecord->startTime));
            $intEnd = strtotime(date('Y-m-d', $intEnd).' '.date('H:i:s', $activeRecord->endTime));
        }

        // Check if we have time overlapping events
        $uniqueEvents = CalendarModel::findById($activeRecord->pid)->uniqueEvents;

        if ($uniqueEvents) {
            // array for events
            $nonUniqueEvents = [];

            // find all events
            $objEvents = CalendarEventsModel::findCurrentByPid(
                (int) $activeRecord->pid,
                (int) $activeRecord->startTime,
                (int) $activeRecord->endTime,
                ['return' => 'Collection'],
            );

            if (!empty($objEvents)) {
                foreach ($objEvents as $objEvent) {
                    // do not add the event with the current id
                    if ($objEvent->id === $activeRecord->id) {
                        continue;
                    }

                    // findCurrentByPid also returns recurring events. therefor we have to check the times
                    if (
                        ($intStart > $objEvent->startTime && $intStart < $objEvent->endTime)
                        || ($intEnd > $objEvent->startTime && $intEnd < $objEvent->endTime)
                        || ($intStart < $objEvent->startTime && $intEnd > $objEvent->endTime)
                        || ($intStart === $objEvent->startTime && $intEnd === $objEvent->endTime)
                    ) {
                        $nonUniqueEvents[] = $objEvent->id;
                    }
                }

                if (!empty($nonUniqueEvents)) {
                    Message::addError($GLOBALS['TL_LANG']['tl_calendar_events']['nonUniqueEvents'].' ('.implode(',', $nonUniqueEvents).')');
                    $this->redirect($this->addToUrl($this->requestStack->getCurrentRequest()->getUri()));
                }
            }
        }

        return true;
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

    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: -100)]
    public function handleRecurrencesAndExceptions(DataContainer $dc): void
    {
        // Return if there is no active record (override all)
        if (!$dc->id) {
            return;
        }

        // @phpstan-ignore-next-line
        $activeRecord = method_exists($dc, 'getCurrentRecord') ? (object) $dc->getCurrentRecord() : $dc->activeRecord;

        // Return if there is no active record (override all) or no start date has been
        // set yet
        if (!$activeRecord->startDate) {
            return;
        }

        $arrSet['weekday'] = (int) date('w', $activeRecord->startDate);

        if ($activeRecord->addTime && $activeRecord->ignoreEndTime && !empty($activeRecord->endDate) && !empty($activeRecord->startTime)) {
            $arrSet['endTime'] = strtotime(date('Y-m-d', $activeRecord->endDate).' '.date('H:i.S', $activeRecord->startTime));
        }

        $arrSet['repeatEnd'] = 0;

        // Array of possible repeatEnd dates...
        $maxRepeatEnd = [];
        $maxRepeatEnd[] = $arrSet['repeatEnd'];

        // Array of all recurrences
        $arrAllRecurrences = [];

        // Process fixed dates
        [$repeatFixedDates, $arrAllRecurrences, $maxRepeatEnd] = $this->getFixedDates($activeRecord, $arrAllRecurrences, $maxRepeatEnd);
        $arrSet['repeatFixedDates'] = null === $repeatFixedDates ? null : serialize($repeatFixedDates);

        // process the default recurring
        [$arrSet, $arrAllRecurrences, $maxRepeatEnd] =
            $this->processDefaultRecurring($activeRecord, $arrSet, $arrAllRecurrences, $maxRepeatEnd);

        // process extended version recurring
        [$arrSet, $arrAllRecurrences, $maxRepeatEnd] =
            $this->processExtendedRecurring($activeRecord, $arrSet, $arrAllRecurrences, $maxRepeatEnd);

        // process exceptions
        $currentEndDate = \count($maxRepeatEnd) > 1 ? max($maxRepeatEnd) : $arrSet['repeatEnd'];
        [$exceptionList, $maxRepeatEnd] =
            $this->processExceptions($activeRecord, $currentEndDate, $maxRepeatEnd);
        $arrSet['exceptionList'] = $exceptionList;

        if (\count($maxRepeatEnd) > 1) {
            $arrSet['repeatEnd'] = max($maxRepeatEnd);
        }

        ksort($arrAllRecurrences);
        $arrSet['allRecurrences'] = serialize($arrAllRecurrences);

        // Execute the update sql
        $this->db->update('tl_calendar_events', $arrSet, ['id' => $dc->id]);
    }

    /**
     * Just check that only one option is active for recurring events.
     *
     * @throws CalendarExtendedException
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.recurring.save')]
    public function checkRecurring(mixed $value, DataContainer $dc): mixed
    {
        // @phpstan-ignore-next-line
        $activeRecord = method_exists($dc, 'getCurrentRecord') ? (object) $dc->getCurrentRecord() : $dc->activeRecord;
        if (!empty($value) && !empty($activeRecord->recurringExt)) {
            Message::addError($GLOBALS['TL_LANG']['tl_calendar_events']['checkRecurring']);
            $activeRecord->recurring = false; // @phpstan-ignore-line

            $this->redirect($this->addToUrl($this->requestStack->getCurrentRequest()->getUri()));
        }

        return $value;
    }

    /**
     * Just check that only one option is active for recurring events.
     *
     * @throws CalendarExtendedException
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.recurringExt.save')]
    public function checkRecurringExt(mixed $value, DataContainer $dc): mixed
    {
        // @phpstan-ignore-next-line
        $activeRecord = method_exists($dc, 'getCurrentRecord') ? (object) $dc->getCurrentRecord() : $dc->activeRecord;
        if (!empty($value) && !empty($activeRecord->recurring)) {
            Message::addError($GLOBALS['TL_LANG']['tl_calendar_events']['checkRecurring']);
            $activeRecord->recurringExt = false; // @phpstan-ignore-line

            $this->redirect($this->addToUrl($this->requestStack->getCurrentRequest()->getUri()));
        }

        return $value;
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'fields.repeatEachExt.load')]
    public function defaultRepeatEachExt(mixed $value, DataContainer $dc): mixed
    {
        if (empty($value)) {
            $value = $this->arrMonths[date('n', time()) - 1];
        }

        return $value;
    }

    /**
     * @param array<mixed> $arrAllRecurrences
     * @param array<mixed> $maxRepeatEnd
     *
     * @return array<mixed>
     */
    private function getFixedDates(Result|\stdClass $activeRecord, array $arrAllRecurrences, array $maxRepeatEnd): array
    {
        $arrFixedDates = StringUtil::deserialize($activeRecord->repeatFixedDates) ?: null;

        if (
            null !== $arrFixedDates
            && 1 === \count($arrFixedDates)
            && '' === $arrFixedDates[0]['new_repeat']
            && '' === $arrFixedDates[0]['new_start']
            && '' === $arrFixedDates[0]['new_end']
        ) {
            // quick return because MCW needs empty strings for an empty record
            return [$arrFixedDates, $arrAllRecurrences, $maxRepeatEnd];
        }

        $intStart = strtotime(date('Y-m-d', $activeRecord->startDate).' '.($activeRecord->addTime ? date('H:i', $activeRecord->startTime) : '00:00'));
        $intEnd = strtotime(date('Y-m-d', $activeRecord->endDate ?: $activeRecord->startDate).' '.($activeRecord->addTime ? date('H:i', $activeRecord->endTime) : '23:59'));
        $arrAllRecurrences[$intStart] = $this->getRecurrenceArray($intStart, $intEnd);

        if (empty($arrFixedDates) || !\is_array($arrFixedDates)) {
            return [null, $arrAllRecurrences, $maxRepeatEnd];
        }

        $arrFixedDates = Utils::sanitizeFixedDates($arrFixedDates);
        usort(
            $arrFixedDates,
            static function ($a, $b) {
                $intTimeStampA = $a['new_repeat'] + (!empty($a['new_start']) && \is_int($a['new_start']) ? $a['new_start'] : 0);
                $intTimeStampB = $b['new_repeat'] + (!empty($b['new_start']) && \is_int($b['new_start']) ? $b['new_start'] : 0);

                return $intTimeStampA <=> $intTimeStampB;
            },
        );

        foreach ($arrFixedDates as $fixedDate) {
            // Check if we have a date
            if (empty($fixedDate['new_repeat'])) {
                continue;
            }

            // Check the date
            try {
                new Date($fixedDate['new_repeat']);
            } catch (\Exception) {
                return [null, $arrAllRecurrences, $maxRepeatEnd];
            }

            $new_fix_date = $fixedDate['new_repeat'];

            // Check if we have a new start time
            $new_fix_start_time = !empty($fixedDate['new_start']) ?
                date('H:i', $fixedDate['new_start']) :
                ($activeRecord->addTime ? date('H:i', $activeRecord->startTime) : '00:00');
            $new_fix_end_time = !empty($fixedDate['new_end']) ?
                date('H:i', $fixedDate['new_end']) :
                ($activeRecord->addTime ? date('H:i', $activeRecord->endTime) : '23:59');

            $new_fix_start_date = strtotime(date('Y-m-d', $new_fix_date).' '.date('H:i', strtotime($new_fix_start_time)));
            $new_fix_end_date = strtotime(date('Y-m-d', $new_fix_date).' '.date('H:i', strtotime($new_fix_end_time)));

            $arrAllRecurrences[$new_fix_start_date] = $this->getRecurrenceArray($new_fix_start_date, $new_fix_end_date);
            $maxRepeatEnd[] = $new_fix_end_date;
        }

        return [empty($arrFixedDates) ? null : $arrFixedDates, $arrAllRecurrences, $maxRepeatEnd];
    }

    /**
     * @param array<mixed> $arrSet
     * @param array<mixed> $arrAllRecurrences
     * @param array<mixed> $maxRepeatEnd
     *
     * @return array<mixed>
     */
    private function processDefaultRecurring(Result|\stdClass $activeRecord, array $arrSet, array $arrAllRecurrences, array $maxRepeatEnd): array
    {
        if (!empty($activeRecord->recurring)) {
            if (!empty($activeRecord->repeatEnd)) {
                $arrSet['repeatEnd'] = $activeRecord->repeatEnd;
            } else {
                if (0 === $activeRecord->recurrences) {
                    $arrSet['repeatEnd'] = min(4294967295, PHP_INT_MAX);
                } else {
                    $arrRange = StringUtil::deserialize($activeRecord->repeatEach);

                    if (isset($arrRange['unit'], $arrRange['value'])) {
                        $arg = $arrRange['value'] * $activeRecord->recurrences;
                        $unit = $arrRange['unit'];

                        $strtotime = '+ '.$arg.' '.$unit;
                        $arrSet['repeatEnd'] = strtotime($strtotime, $arrSet['endTime']);
                    }
                }
            }
            $arrRange = StringUtil::deserialize($activeRecord->repeatEach, true);

            if (empty($arrRange) || empty($arrRange['value']) || empty($arrRange['unit'])) {
                return [$arrSet, $arrAllRecurrences, $maxRepeatEnd];
            }

            $intStart = strtotime(date('Y-m-d', $activeRecord->startDate).' '.($activeRecord->addTime ? date('H:i', $activeRecord->startTime) : '00:00'));
            $intEnd = strtotime(date('Y-m-d', $activeRecord->endDate ?: $activeRecord->startDate).' '.($activeRecord->addTime ? date('H:i', $activeRecord->endTime) : '23:59'));

            // store the list of dates
            $next = $intStart;
            $nextEnd = $intEnd;

            // array of all recurrences
            $arrAllRecurrences[$next] = $this->getRecurrenceArray($next, $nextEnd);

            // last date of the recurrences
            $end = $arrSet['repeatEnd'];

            while ($next <= $end) {
                $timetoadd = '+ '.$arrRange['value'].' '.$arrRange['unit'];
                $next = strtotime($timetoadd, $next);
                $nextEnd = strtotime($timetoadd, $nextEnd);

                // Check if we are at the end
                if (false === $next) {
                    break;
                }

                // check if we are at the end
                if ($next >= $end) {
                    break;
                }

                $weekday = date('N', $next);
                $arrWeekdays = StringUtil::deserialize($activeRecord->repeatWeekday, true);
                if (!empty($arrWeekdays) && 'days' === $arrRange['unit']) {
                    if (!\in_array($weekday, $arrWeekdays, true)) {
                        continue;
                    }
                }

                if ($activeRecord->hideOnWeekend) {
                    if ((int) $weekday >= 6) {
                        continue;
                    }
                }

                $arrAllRecurrences[$next] = $this->getRecurrenceArray($next, $nextEnd);

                // check if we reached the configured max value
                if (\count($arrAllRecurrences) === $this->maxRepeatCount) {
                    break;
                }
            }
            $maxRepeatEnd[] = $arrSet['repeatEnd'];
        }

        return [$arrSet, $arrAllRecurrences, $maxRepeatEnd];
    }

    /**
     * @param array<mixed> $arrSet
     * @param array<mixed> $arrAllRecurrences
     * @param array<mixed> $maxRepeatEnd
     *
     * @return array<mixed>
     */
    private function processExtendedRecurring(Result|\stdClass $activeRecord, array $arrSet, array $arrAllRecurrences, array $maxRepeatEnd): array
    {
        if (!empty($activeRecord->recurringExt)) {
            $arrRange = StringUtil::deserialize($activeRecord->repeatEachExt, true);

            if (!empty($arrRange) && !empty($arrRange['value']) && !empty($arrRange['unit'])) {
                $arg = $arrRange['value'];
                $unit = $arrRange['unit'];

                // next month of the event
                $month = (int) date('n', $activeRecord->startDate);
                // year of the event
                $year = (int) date('Y', $activeRecord->startDate);
                // search date for the next event
                $next = strtotime(date('Y-m-d', $activeRecord->startDate).' '.($activeRecord->addTime ? date('H:i', $activeRecord->startTime) : '00:00'));
                $nextEnd = strtotime(date('Y-m-d', $activeRecord->endDate).' '.($activeRecord->addTime ? date('H:i', $activeRecord->endTime) : '23:59'));

                // last month
                $count = (int) $activeRecord->recurrences;

                // array of all recurrences
                $arrAllRecurrences[$next] = $this->getRecurrenceArray($next, $nextEnd);

                if ($count > 0) {
                    for ($i = 0; $i < $count; ++$i) {
                        ++$month;

                        if (0 === $month % 13) {
                            $month = 1;
                            ++$year;
                        }

                        $timetoadd = $arg.' '.$unit.' of '.$this->arrMonths[$month - 1].' '.$year;
                        $strtotime = strtotime($timetoadd, $next);

                        if (false === $strtotime) {
                            break;
                        }

                        $next = strtotime(date('Y-m-d', $strtotime).' '.date('H:i', $activeRecord->startTime));

                        // array of all recurrences
                        $strtotime = strtotime($timetoadd, $nextEnd);
                        $nextEnd = strtotime(date('Y-m-d', $strtotime).' '.date('H:i', $activeRecord->endTime));
                        $arrAllRecurrences[$next] = $this->getRecurrenceArray($next, $nextEnd);

                        // check if have the configured max value
                        if (\count($arrAllRecurrences) === $this->maxRepeatCount) {
                            break;
                        }
                    }
                    $arrSet['repeatEnd'] = $next;
                } else {
                    $arrSet['repeatEnd'] = min(4294967295, PHP_INT_MAX);
                    $end = $arrSet['repeatEnd'];

                    while ($next <= $end) {
                        System::loadLanguageFile('default', 'en', true);
                        $timetoadd = $arg.' '.$unit.' of '.$GLOBALS['TL_LANG']['MONTHS'][$month - 1].' '.$year;
                        System::loadLanguageFile('default');
                        $strtotime = strtotime($timetoadd, $next);

                        if (false === $strtotime) {
                            break;
                        }

                        $next = strtotime(date('Y-m-d', $strtotime).' '.date('H:i', $activeRecord->startTime));

                        $strtotime = strtotime($timetoadd, $nextEnd);
                        $nextEnd = strtotime(date('Y-m-d', $strtotime).' '.date('H:i', $activeRecord->endTime));
                        $arrAllRecurrences[$next] = $this->getRecurrenceArray($next, $nextEnd);

                        ++$month;

                        if (0 === $month % 13) {
                            $month = 1;
                            ++$year;
                        }

                        // check if we reached the configured max value
                        if (\count($arrAllRecurrences) === $this->maxRepeatCount) {
                            break;
                        }
                    }
                }

                $maxRepeatEnd[] = $arrSet['repeatEnd'];
            }
        }

        return [$arrSet, $arrAllRecurrences, $maxRepeatEnd];
    }

    /**
     * @param array<mixed> $maxRepeatEnd
     *
     * @return array<mixed>
     */
    private function processExceptions(Result|\stdClass $activeRecord, int $currentEndDate, array $maxRepeatEnd): array
    {
        $exceptions = null;
        if ($activeRecord->useExceptions) {
            // list of the exception
            $exceptionRows = [];

            // check by interval
            $exceptionRows = $this->checkExceptionsByInterval($activeRecord, $exceptionRows, $activeRecord->startTime, $currentEndDate);

            // check by time range
            $exceptionRows = $this->checkExceptionsByTimeRange($activeRecord, $exceptionRows);

            // check by date
            [$exceptionRows, $maxRepeatEnd] = $this->checkExceptionsByDate($activeRecord, $currentEndDate, $exceptionRows, $maxRepeatEnd);

            if (\count($exceptionRows) > 1) {
                ksort($exceptionRows);
            }
            $exceptions = \count($exceptionRows) > 0 ? serialize($exceptionRows) : null;
        }

        return [$exceptions, $maxRepeatEnd];
    }

    /**
     * @param array<mixed> $exceptionRows
     *
     * @return array<mixed>
     */
    private function checkExceptionsByInterval(Result|\stdClass $activeRecord, array $exceptionRows, int $intStart, int $intRepeatEnd): array
    {
        if ($activeRecord->repeatExceptionsInt && $activeRecord->weekday) {
            // weekday
            $unit = $this->arrDays[$activeRecord->weekday];

            // exception rules
            $rows = StringUtil::deserialize($activeRecord->repeatExceptionsInt, true);

            // run through all dates
            foreach ($rows as $row) {
                if (!$row['exception']) {
                    continue;
                }

                // now we have to find all dates matching the exception rules...
                $arg = $row['exception'];

                $searchNext = $intStart;
                $searchEnd = $intRepeatEnd;
                $month = (int) date('n', $searchNext);
                $year = (int) date('Y', $searchNext);

                while ($searchNext <= $searchEnd) {
                    System::loadLanguageFile('default', 'en', true);
                    $strDateToFind = $arg.' '.$unit.' of '.$GLOBALS['TL_LANG']['MONTHS'][$month - 1].' '.$year;
                    System::loadLanguageFile('default');

                    $strDateToFind = strtotime($strDateToFind);
                    $searchNext = strtotime(date('Y-m-d', $strDateToFind).' '.date('H:i', $intStart));

                    if ($searchNext < $intStart) {
                        ++$month;

                        if (0 === $month % 13) {
                            $month = 1;
                            ++$year;
                        }
                        continue;
                    }

                    $row['new_start'] = $row['new_start'] ?: date('H:i', $activeRecord->startTime); // '00:00';
                    $row['new_end'] = $row['new_end'] ?: date('H:i', $activeRecord->endTime); // '23:59';

                    // Set endtime to starttime always...
                    if ($activeRecord->ignoreEndTime) {
                        $row['new_end'] = '';
                    }

                    $row['exception'] = $searchNext;
                    $row['exception_date'] = date('d.m.Y H:i', $searchNext);

                    if (\count($exceptionRows) < $this->maxExceptionsCount) {
                        $exceptionRows[$searchNext] = $row;
                    }

                    ++$month;

                    if (0 === $month % 13) {
                        $month = 1;
                        ++$year;
                    }
                }
            }
        }

        return $exceptionRows;
    }

    /**
     * @param array<mixed> $exceptionRows
     *
     * @return array<mixed>
     */
    private function checkExceptionsByTimeRange(Result|\stdClass $activeRecord, array $exceptionRows): array
    {
        if (!empty($activeRecord->repeatExceptionsPer)) {
            // exception rules
            $rows = StringUtil::deserialize($activeRecord->repeatExceptionsPer, true);

            // all recurrences...
            $arrRecurrences = StringUtil::deserialize($activeRecord->allRecurrences, true);

            // run through all dates
            foreach ($rows as $row) {
                if (!$row['exception']) {
                    continue;
                }

                $row['new_start'] = $row['new_start'] ?: date('H:i', $activeRecord->startTime); // '00:00';
                // Set endtime to starttime always...
                if ($activeRecord->ignoreEndTime) {
                    $row['new_end'] = '';
                } else {
                    $row['new_end'] = $row['new_end'] ?: date('H:i', $activeRecord->endTime); // '23:59';
                }

                // now we have to find all dates matching the exception rules...
                $dateFrom = strtotime(date('Y-m-d', $row['exception']).' '.$row['new_start']);
                $dateTo = strtotime(date('Y-m-d', $row['exceptionTo'] ?: $row['exception']).' '.$row['new_end']);
                unset($row['exceptionTo']);

                foreach (array_keys($arrRecurrences) as $k) {
                    if ($k >= $dateFrom && $k <= $dateTo) {
                        $row['exception'] = $k;
                        $row['exception_date'] = date('d.m.Y H:i', $k);

                        if (\count($exceptionRows) < $this->maxExceptionsCount) {
                            $exceptionRows[$k] = $row;
                        }
                    }
                }
            }
        }

        return $exceptionRows;
    }

    /**
     * @param array<mixed> $maxRepeatEnd
     * @param array<mixed> $exceptionRows
     *
     * @return array<mixed>
     */
    private function checkExceptionsByDate(Result|\stdClass $activeRecord, int $currentEndDate, array $exceptionRows, array $maxRepeatEnd): array
    {
        if (!empty($activeRecord->repeatExceptions)) {
            $rows = StringUtil::deserialize($activeRecord->repeatExceptions, true);

            // set repeatEnd my be we have an exception move that is later then the repeatEnd
            foreach ($rows as $row) {
                if (!$row['exception']) {
                    continue;
                }

                $row['new_start'] = $row['new_start'] ?: date('H:i', $activeRecord->startTime); // '00:00';

                if (!$activeRecord->ignoreEndTime) {
                    $row['new_end'] = $row['new_end'] ?: date('H:i', $activeRecord->endTime); // '23:59';
                }
                $row['exception_date'] = date('d.m.Y H:i', (int) $row['exception']);

                if ($activeRecord->ignoreEndTime) {
                    $row['new_end'] = '';
                }

                if ('move' === $row['action']) {
                    $newDate = strtotime((string) $row['new_exception'], (int) $row['exception']);

                    if ($newDate > $currentEndDate) {
                        $maxRepeatEnd[] = $newDate;
                    }
                }

                if (\count($exceptionRows) < $this->maxExceptionsCount) {
                    $exceptionRows[(int) $row['exception']] = $row;
                }
            }
        }

        return [$exceptionRows, $maxRepeatEnd];
    }

    /**
     * Get the recurrence array.
     *
     * @return array<mixed>
     */
    private function getRecurrenceArray(int|false $start, int|false $end, string|null $moveReason = null): array
    {
        $arrRecurrence = [
            'int_start' => $start,
            'int_end' => $end,
            'str_start' => Date::parse(Config::get('datimFormat'), $start),
            'str_end' => Date::parse(Config::get('datimFormat'), $end),
        ];

        if (null !== $moveReason) {
            $arrRecurrence['moveReason'] = $moveReason;
        }

        return $arrRecurrence;
    }
}
