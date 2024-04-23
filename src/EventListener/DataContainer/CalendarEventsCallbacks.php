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

use Cgoit\CalendarExtendedBundle\Exception\CalendarExtendedException;
use Contao\Backend;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\Database\Result;
use Contao\DataContainer;
use Contao\Date;
use Contao\FilesModel;
use Contao\Message;
use Contao\Model;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\RequestStack;

class CalendarEventsCallbacks extends Backend
{
    /**
     * @param array<mixed> $arrMonth
     * @param array<mixed> $arrDay
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly int $maxRepeatCount,
        private readonly int $maxExceptionsCount,
        private readonly array $arrMonth,
        private readonly array $arrDay,
    ) {
        parent::__construct();
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit')]
    public function checkOverlapping(DataContainer $dc): bool
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return false;
        }

        /** @var FilesModel|Model $activeRecord */
        $activeRecord = $dc->activeRecord;

        // Return if the event is recurring
        if ($activeRecord->recurring || $activeRecord->recurringExt) {
            return false;
        }

        // Set start date
        $intStart = $activeRecord->startDate;
        $intEnd = $activeRecord->startDate;

        $intStart = strtotime(date('d.m.Y', $intStart).' 00:00');

        // Set end date
        if (!empty($activeRecord->endDate) && $activeRecord->endDate > $activeRecord->startDate) {
            $intEnd = $activeRecord->endDate;
        }
        $intEnd = strtotime(date('d.m.Y', $intEnd).' 23:59');

        // Add time
        if ($activeRecord->addTime) {
            $intStart = strtotime(date('d.m.Y', $intStart).' '.date('H:i:s', $activeRecord->startTime));
            $intEnd = strtotime(date('d.m.Y', $intEnd).' '.date('H:i:s', $activeRecord->endTime));
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

    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: -100)]
    public function adjustTime(DataContainer $dc): void
    {
        // Return if there is no active record (override all) or no start date has been
        // set yet
        if (!$dc->activeRecord || !$dc->activeRecord->startDate) {
            return;
        }

        /** @var FilesModel|Model|Result $activeRecord */
        $activeRecord = $dc->activeRecord;

        $arrSet['weekday'] = (int) date('w', $activeRecord->startDate);
        $arrSet['startTime'] = (int) $activeRecord->startDate;
        $arrSet['endDate'] = (int) $activeRecord->startDate;
        $arrSet['endTime'] = (int) $activeRecord->startDate;

        // Set end date
        if (!empty($activeRecord->endDate) && $activeRecord->endDate > $activeRecord->startDate) {
            $arrSet['endDate'] = (int) $activeRecord->endDate;
            $arrSet['endTime'] = (int) $activeRecord->endDate;
        }

        // Add time
        if ($activeRecord->addTime) {
            $arrSet['startTime'] = strtotime(date('d.m.Y', $arrSet['startTime']).' '.date('H:i:s', $activeRecord->startTime));

            if (!$activeRecord->ignoreEndTime) {
                $arrSet['endTime'] = strtotime(date('d.m.Y', $arrSet['endTime']).' '.date('H:i:s', $activeRecord->endTime));
            }
        }

        // Set endtime to starttime always...
        if ($activeRecord->addTime && $activeRecord->ignoreEndTime) {
            $arrSet['endTime'] = $arrSet['startTime'];
        } // Adjust end time of "all day" events
        elseif ((!empty($activeRecord->endDate) && $arrSet['endDate'] === $arrSet['endTime']) || $arrSet['startTime'] === $arrSet['endTime']) {
            $arrSet['endTime'] = strtotime('+ 1 day', $arrSet['endTime']) - 1;
        }

        $arrSet['repeatEnd'] = 0;

        // Array of possible repeatEnd dates...
        $maxRepeatEnd = [];
        $maxRepeatEnd[] = $arrSet['repeatEnd'];

        // Array of all recurrences
        $arrAllRecurrences = [];

        // Process fixed dates
        [$arrFixDates, $arrAllRecurrences, $maxRepeatEnd] = $this->getFixedDates($activeRecord, $arrSet['startTime'], $arrSet['endTime'], $arrAllRecurrences, $maxRepeatEnd);
        if (null === $arrFixDates) {
            $arrSet['repeatFixedDates'] = null;
        }

        // Array of all dates
        $arrDates = [];

        // process the default recurring
        [$arrSet, $arrAllRecurrences, $maxRepeatEnd, $arrDates] =
            $this->processDefaultRecurring($activeRecord, $arrSet, $arrAllRecurrences, $maxRepeatEnd, $arrDates);

        // process extended version recurring
        [$arrSet, $arrAllRecurrences, $maxRepeatEnd, $arrDates] =
            $this->processExtendedRecurring($activeRecord, $arrSet, $arrAllRecurrences, $maxRepeatEnd, $arrDates);

        // process exceptions
        $currentEndDate = \count($maxRepeatEnd) > 1 ? max($maxRepeatEnd) : $arrSet['repeatEnd'];
        [$exceptionList, $arrSet, $arrAllRecurrences, $maxRepeatEnd, $arrDates] =
            $this->processExceptions($activeRecord, $currentEndDate, $arrSet, $arrAllRecurrences, $maxRepeatEnd, $arrDates);
        $arrSet['exceptionList'] = $exceptionList;

        if (\count($maxRepeatEnd) > 1) {
            $arrSet['repeatEnd'] = max($maxRepeatEnd);
        }

        $arrAllDates = [];
        if (!empty($arrDates)) {
            $arrAllDates += $arrDates;
        }
        if (!empty($arrFixDates)) {
            $arrAllDates += $arrFixDates;
        }
        ksort($arrAllDates);
        $arrSet['repeatDates'] = $arrAllDates;

        ksort($arrAllRecurrences);
        $arrSet['allRecurrences'] = $arrAllRecurrences;

        // Execute the update sql
        $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($arrSet)->execute($dc->id);
    }

    /**
     * Just check that only one option is active for recurring events.
     *
     * @throws CalendarExtendedException
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.recurring.save')]
    public function checkRecurring(mixed $value, DataContainer $dc): mixed
    {
        if (!empty($value)) {
            if (!empty($dc->activeRecord->recurring) && !empty($dc->activeRecord->recurringExt)) {
                throw new CalendarExtendedException($GLOBALS['TL_LANG']['tl_calendar_events']['checkRecurring']);
            }
        }

        return $value;
    }

    /**
     * Just check if any kind of recurring is in use.
     *
     * @throws CalendarExtendedException
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.useExceptions.save')]
    public function checkExceptions(mixed $value, DataContainer $dc): mixed
    {
        if (!empty($value)) {
            if (!empty($dc->activeRecord->recurring) && !empty($dc->activeRecord->recurringExt)) {
                throw new CalendarExtendedException($GLOBALS['TL_LANG']['tl_calendar_events']['checkExceptions']);
            }
        }

        return $value;
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'fields.repeatEachExt.load')]
    public function defaultRepeatEachExt(mixed $value, DataContainer $dc): mixed
    {
        if (empty($value)) {
            $value = $this->arrMonth[date('w', time())];
        }

        return $value;
    }

    /**
     * @param array<mixed> $arrAllRecurrences
     * @param array<mixed> $maxRepeatEnd
     *
     * @return array<mixed>
     */
    private function getFixedDates(FilesModel|Model|Result $activeRecord, int|false $startTime, int|false $endTime, array $arrAllRecurrences, array $maxRepeatEnd): array
    {
        $arrFixDates = [];
        $arrayFixedDates = StringUtil::deserialize($activeRecord->repeatFixedDates) ?: null;

        if (!empty($arrayFixedDates)) {
            usort(
                $arrayFixedDates,
                static function ($a, $b) {
                    $intTimeStampA = strtotime($a['new_repeat'].$a['new_start']);
                    $intTimeStampB = strtotime($b['new_repeat'].$b['new_start']);

                    return $intTimeStampA <=> $intTimeStampB;
                },
            );

            foreach ($arrayFixedDates as $fixedDate) {
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

                // Check if we have a new start time new_fix_start_time =
                $new_fix_start_time = !empty($fixedDate['new_start']) ? date('H:i', $fixedDate['new_start']) : date('H:i', $startTime);
                $new_fix_end_time = !empty($fixedDate['new_end']) ? date('H:i', $fixedDate['new_end']) : date('H:i', $endTime);

                $new_fix_start_date = strtotime(date('d.m.Y', $new_fix_date).' '.date('H:i', strtotime($new_fix_start_time)));
                $new_fix_end_date = strtotime(date('d.m.Y', $new_fix_date).' '.date('H:i', strtotime($new_fix_end_time)));

                $arrFixDates[$new_fix_start_date] = date('d.m.Y H:i', $new_fix_start_date);
                $arrAllRecurrences[$new_fix_start_date] = $this->getRecurrenceArray($new_fix_start_date, $new_fix_end_date);
                $maxRepeatEnd[] = $new_fix_end_date;
            }
        }

        return empty($arrFixDates) ? [null, $arrAllRecurrences, $maxRepeatEnd] : [$arrFixDates, $arrAllRecurrences, $maxRepeatEnd];
    }

    /**
     * @param array<mixed> $arrSet
     * @param array<mixed> $arrAllRecurrences
     * @param array<mixed> $maxRepeatEnd
     * @param array<mixed> $arrDates
     *
     * @return array<mixed>
     */
    private function processDefaultRecurring(FilesModel|Model|Result $activeRecord, array $arrSet, array $arrAllRecurrences, array $maxRepeatEnd, array $arrDates): array
    {
        if (!empty($activeRecord->recurring)) {
            $arrRange = StringUtil::deserialize($activeRecord->repeatEach, true);

            $arg = $arrRange['value'] * $activeRecord->recurrences;
            $unit = $arrRange['unit'];

            $strtotime = '+ '.$arg.' '.$unit;
            $arrSet['repeatEnd'] = strtotime($strtotime, $arrSet['endTime']);

            // store the list of dates
            $next = $arrSet['startTime'];
            $nextEnd = $arrSet['endTime'];
            $count = $activeRecord->recurrences;

            // array of the exception dates
            $arrDates[$next] = date('d.m.Y H:i', $next);

            // array of all recurrences
            $arrAllRecurrences[$next] = $this->getRecurrenceArray($next, $nextEnd);

            if (0 === $count) {
                $arrSet['repeatEnd'] = min(4294967295, PHP_INT_MAX);
            }

            // last date of the recurrences
            $end = $arrSet['repeatEnd'];

            while ($next <= $end) {
                $timetoadd = '+ '.$arrRange['value'].' '.$unit;
                $strtotime = strtotime($timetoadd, $next);

                // Check if we are at the end
                if (false === $strtotime) {
                    break;
                }

                $next = $strtotime;
                //                $weekday = date('w', $next); //check if we are at the end
                if ($next >= $end) {
                    break;
                }
                // TODO check what this is doing, $store is never read afterwards $value = (int)
                // $arrRange['value'];                $wdays =
                // is_array(StringUtil::deserialize($activeRecord->repeatWeekday))         ?
                // StringUtil::deserialize($activeRecord->repeatWeekday)         : false; if
                // ('days' === $unit && 1 === $value && $wdays) {   $wday = date('N', $next);
                // $store = in_array($wday, $wdays, true);     } $store = true;       if
                // ($activeRecord->hideOnWeekend) {          if (0 === $weekday || 6 ===
                // $weekday) { $store = false;      }                }
                $arrDates[$next] = date('d.m.Y H:i', $next);
                // array of all recurrences
                $strtotime = strtotime($timetoadd, $nextEnd);
                $nextEnd = $strtotime;
                $arrAllRecurrences[$next] = $this->getRecurrenceArray($next, $nextEnd);

                // check if we reached the configured max value
                if (\count($arrDates) === $this->maxRepeatCount) {
                    break;
                }
            }
            $maxRepeatEnd[] = $arrSet['repeatEnd'];
        }

        return [$arrSet, $arrAllRecurrences, $maxRepeatEnd, $arrDates];
    }

    /**
     * @param array<mixed> $arrSet
     * @param array<mixed> $arrAllRecurrences
     * @param array<mixed> $maxRepeatEnd
     * @param array<mixed> $arrDates
     *
     * @return array<mixed>
     */
    private function processExtendedRecurring(FilesModel|Model|Result $activeRecord, array $arrSet, array $arrAllRecurrences, array $maxRepeatEnd, array $arrDates): array
    {
        if (!empty($activeRecord->recurringExt)) {
            $arrRange = StringUtil::deserialize($activeRecord->repeatEachExt, true);

            if (!empty($arrRange)) {
                $arg = $arrRange['value'];
                $unit = $arrRange['unit'];

                // next month of the event
                $month = (int) date('n', $activeRecord->startDate);
                // year of the event
                $year = (int) date('Y', $activeRecord->startDate);
                // search date for the next event
                $next = (int) $arrSet['startTime'];
                $nextEnd = $arrSet['endTime'];

                // last month
                $count = (int) $activeRecord->recurrences;

                // array of the exception dates
                $arrDates[$next] = date('d.m.Y H:i', $next);

                // array of all recurrences
                $arrAllRecurrences[$next] = $this->getRecurrenceArray($next, $nextEnd);

                if ($count > 0) {
                    for ($i = 0; $i < $count; ++$i) {
                        ++$month;

                        if (0 === $month % 13) {
                            $month = 1;
                            ++$year;
                        }

                        $timetoadd = $arg.' '.$unit.' of '.$this->arrMonth[$month].' '.$year;
                        $strtotime = strtotime($timetoadd, $next);

                        if (false === $strtotime) {
                            break;
                        }

                        $next = strtotime(date('d.m.Y', $strtotime).' '.date('H:i', $arrSet['startTime']));
                        $arrDates[$next] = date('d.m.Y H:i', $next);

                        // array of all recurrences
                        $strtotime = strtotime($timetoadd, $nextEnd);
                        $nextEnd = strtotime(date('d.m.Y', $strtotime).' '.date('H:i', $arrSet['endTime']));
                        $arrAllRecurrences[$next] = $this->getRecurrenceArray($next, $nextEnd);

                        // check if have the configured max value
                        if (\count($arrDates) === $this->maxRepeatCount) {
                            break;
                        }
                    }
                    $arrSet['repeatEnd'] = $next;
                } else {
                    $arrSet['repeatEnd'] = min(4294967295, PHP_INT_MAX);
                    $end = $arrSet['repeatEnd'];

                    while ($next <= $end) {
                        $timetoadd = $arg.' '.$unit.' of '.$this->arrMonth[$month].' '.$year;
                        $strtotime = strtotime($timetoadd, $next);

                        if (false === $strtotime) {
                            break;
                        }

                        $next = strtotime(date('d.m.Y', $strtotime).' '.date('H:i', $arrSet['startTime']));
                        $arrDates[$next] = date('d.m.Y H:i', $next);

                        ++$month;

                        if (0 === $month % 13) {
                            $month = 1;
                            ++$year;
                        }

                        // check if we reached the configured max value
                        if (\count($arrDates) === $this->maxRepeatCount) {
                            break;
                        }
                    }
                }

                $maxRepeatEnd[] = $arrSet['repeatEnd'];
            }
        }

        return [$arrSet, $arrAllRecurrences, $maxRepeatEnd, $arrDates];
    }

    /**
     * @param array<mixed> $arrSet
     * @param array<mixed> $arrAllRecurrences
     * @param array<mixed> $maxRepeatEnd
     * @param array<mixed> $arrDates
     *
     * @return array<mixed>
     */
    private function processExceptions(FilesModel|Model|Result $activeRecord, int $currentEndDate, array $arrSet, array $arrAllRecurrences, array $maxRepeatEnd, array $arrDates): array
    {
        $exceptions = null;
        if ($activeRecord->useExceptions) {
            // list of the exception
            $exceptionRows = [];

            // check by interval
            $exceptionRows = $this->checkExceptionsByInterval($activeRecord, $arrSet, $exceptionRows);

            // check by time range
            $exceptionRows = $this->checkExceptionsByTimeRange($activeRecord, $exceptionRows);

            // check by date
            [$arrSet, $maxRepeatEnd, $arrAllRecurrences, $exceptionRows] = $this->checkExceptionsByDate($activeRecord, $currentEndDate, $arrSet, $maxRepeatEnd, $arrDates, $arrAllRecurrences, $exceptionRows);

            if (\count($exceptionRows) > 1) {
                ksort($exceptionRows);
            }
            $exceptions = \count($exceptionRows) > 0 ? serialize($exceptionRows) : null;
        }

        return [$exceptions, $arrSet, $arrAllRecurrences, $maxRepeatEnd, $arrDates];
    }

    /**
     * @param array<mixed> $arrSet
     * @param array<mixed> $exceptionRows
     *
     * @return array<mixed>
     */
    private function checkExceptionsByInterval(FilesModel|Model|Result $activeRecord, array $arrSet, array $exceptionRows): array
    {
        if ($activeRecord->repeatExceptionsInt) {
            // weekday
            $unit = $this->arrDay[$activeRecord->weekday];

            // exception rules
            $rows = StringUtil::deserialize($activeRecord->repeatExceptionsInt, true);

            // run through all dates
            foreach ($rows as $row) {
                if (!$row['exception']) {
                    continue;
                }

                // now we have to find all dates matching the exception rules...
                $arg = $row['exception'];

                $searchNext = $arrSet['startTime'];
                $searchEnd = $arrSet['repeatEnd'];
                $month = (int) date('n', $searchNext);
                $year = (int) date('Y', $searchNext);

                while ($searchNext <= $searchEnd) {
                    $strDateToFind = $arg.' '.$unit.' of '.$this->arrMonth[$month].' '.$year;
                    $strDateToFind = strtotime($strDateToFind);
                    $searchNext = strtotime(date('d.m.Y', $strDateToFind).' '.date('H:i', $arrSet['startTime']));

                    if ($searchNext < $arrSet['startTime']) {
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
    private function checkExceptionsByTimeRange(FilesModel|Model|Result $activeRecord, array $exceptionRows): array
    {
        if (!empty($activeRecord->repeatExceptionsPer)) {
            // exception rules
            $rows = StringUtil::deserialize($activeRecord->repeatExceptionsPer, true);

            // all recurrences...
            $repeatDates = StringUtil::deserialize($activeRecord->repeatDates, true);

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

                foreach (array_keys($repeatDates) as $k) {
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
     * @param array<mixed> $arrSet
     * @param array<mixed> $maxRepeatEnd
     * @param array<mixed> $arrDates
     * @param array<mixed> $arrAllRecurrences
     * @param array<mixed> $exceptionRows
     *
     * @return array<mixed>
     */
    private function checkExceptionsByDate(FilesModel|Model|Result $activeRecord, int $currentEndDate, array $arrSet, array $maxRepeatEnd, array $arrDates, array $arrAllRecurrences, array $exceptionRows): array
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

                $dateToFind = strtotime(date('d.m.Y', (int) $row['exception']).' '.date('H:i', $activeRecord->startTime));
                $dateToSave = strtotime(date('d.m.Y', (int) $row['exception']).' '.$row['new_start']);
                $dateToSaveEnd = strtotime(date('d.m.Y', (int) $row['exception']).' '.$row['new_end']);

                // Set endtime to starttime always...
                if ($activeRecord->ignoreEndTime) {
                    $row['new_end'] = '';
                }

                if ('move' === $row['action']) {
                    $newDate = strtotime((string) $row['new_exception'], (int) $row['exception']);

                    if ($newDate > $currentEndDate) {
                        $arrSet['repeatEnd'] = $newDate;
                        $maxRepeatEnd[] = $arrSet['repeatEnd'];
                    }

                    // Find the date and replace it
                    if (!empty($arrDates) && \array_key_exists($dateToFind, $arrDates)) {
                        $arrDates[$dateToFind] = date('d.m.Y H:i', $dateToSave);
                    }

                    // Find the date and replace it
                    if (\array_key_exists($dateToFind, $arrAllRecurrences)) {
                        $arrAllRecurrences[$dateToFind] = $this->getRecurrenceArray($dateToSave, $dateToSaveEnd, $row['reason'] ?: '');
                    }
                }

                if (\count($exceptionRows) < $this->maxExceptionsCount) {
                    $exceptionRows[(int) $row['exception']] = $row;
                }
            }
        }

        return [$arrSet, $maxRepeatEnd, $arrAllRecurrences, $exceptionRows];
    }

    /**
     * Get the recurrence array.
     */
    private function getRecurrenceArray(int|false $start, int|false $end, string|null $moveReason = null): array
    {
        global $objPage;

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
