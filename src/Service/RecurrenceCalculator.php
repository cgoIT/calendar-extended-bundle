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

namespace Cgoit\CalendarExtendedBundle\Service;

use Cgoit\CalendarExtendedBundle\Classes\Utils;
use Contao\Config;
use Contao\Date;
use Contao\StringUtil;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Calculates all recurrences and exceptions of an event. The result is stored in the event when it is saved
 * (see the "Vorberechnung" in CLAUDE.md), the front end only reads it.
 */
class RecurrenceCalculator
{
    /**
     * strtotime() needs the English names, regardless of the language of the back end.
     */
    private const array MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    private const array DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /**
     * The fields of the event that are used for the calculation.
     */
    private const array RECORD_FIELDS = [
        'startDate', 'endDate', 'addTime', 'startTime', 'endTime', 'ignoreEndTime',
        'recurring', 'repeatEach', 'repeatEnd', 'recurrences', 'repeatWeekday', 'hideOnWeekend',
        'recurringExt', 'repeatEachExt', 'repeatFixedDates',
        'useExceptions', 'repeatExceptions', 'repeatExceptionsInt', 'repeatExceptionsPer',
    ];

    public function __construct(
        #[Autowire(param: 'cgoit_calendar_extended.max_repeat_count')]
        private readonly int $maxRepeatCount,
        #[Autowire(param: 'cgoit_calendar_extended.exceptions.max_count')]
        private readonly int $maxExceptionsCount,
    ) {
    }

    /**
     * Calculate all recurrences and exceptions of an event.
     *
     * @param array<string, mixed> $record the row of the event (tl_calendar_events)
     */
    public function calculate(array $record): RecurrenceResult
    {
        $record += array_fill_keys(self::RECORD_FIELDS, null);

        $arrSet = ['weekday' => (int) date('w', (int) $record['startDate']), 'repeatEnd' => 0];

        if (!empty($record['addTime']) && !empty($record['ignoreEndTime']) && !empty($record['endDate']) && !empty($record['startTime'])) {
            $arrSet['endTime'] = strtotime(date('Y-m-d', $record['endDate']).' '.date('H:i:s', $record['startTime']));
        }

        $arrAllRecurrences = [];
        // The number of entries controls the calculation of repeatEnd, the first one is the initial value
        $maxRepeatEnd = [0];

        // Process fixed dates
        [$repeatFixedDates, $arrAllRecurrences, $maxRepeatEnd] = $this->getFixedDates($record, $arrAllRecurrences, $maxRepeatEnd);
        $arrSet['repeatFixedDates'] = null === $repeatFixedDates ? null : serialize($repeatFixedDates);

        // process the default recurring
        [$arrSet, $arrAllRecurrences, $maxRepeatEnd] =
            $this->processDefaultRecurring($record, $arrSet, $arrAllRecurrences, $maxRepeatEnd);

        // process extended version recurring
        [$arrSet, $arrAllRecurrences, $maxRepeatEnd] =
            $this->processExtendedRecurring($record, $arrSet, $arrAllRecurrences, $maxRepeatEnd);

        // process exceptions
        $currentEndDate = \count($maxRepeatEnd) > 1 ? (int) max($maxRepeatEnd) : (int) ($arrSet['repeatEnd'] ?? 0);
        [$exceptionList, $maxRepeatEnd] =
            $this->processExceptions($record, $currentEndDate, $maxRepeatEnd, $arrAllRecurrences);
        $arrSet['exceptionList'] = $exceptionList;

        if (\count($maxRepeatEnd) > 1) {
            $arrSet['repeatEnd'] = max($maxRepeatEnd);
        }

        ksort($arrAllRecurrences);

        return new RecurrenceResult($arrAllRecurrences, $arrSet);
    }

    /**
     * @param array<string, mixed> $record
     * @param array<mixed>         $arrAllRecurrences
     * @param array<mixed>         $maxRepeatEnd
     *
     * @return array<mixed>
     */
    private function getFixedDates(array $record, array $arrAllRecurrences, array $maxRepeatEnd): array
    {
        $arrFixedDates = StringUtil::deserialize($record['repeatFixedDates']) ?: null;

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

        $intStart = Utils::parseTime(date('Y-m-d', $record['startDate']).' '.($record['addTime'] ? date('H:i', $record['startTime']) : '00:00'));
        $intEnd = Utils::parseTime(date('Y-m-d', $record['endDate'] ?: $record['startDate']).' '.($record['addTime'] ? date('H:i', $record['endTime']) : '23:59'));
        $arrAllRecurrences[$intStart] = $this->getRecurrenceArray($intStart, $intEnd);

        if (empty($arrFixedDates) || !\is_array($arrFixedDates)) {
            return [null, $arrAllRecurrences, $maxRepeatEnd];
        }

        $arrFixedDates = Utils::sanitizeFixedDates($arrFixedDates);

        // A fixed date keeps the duration of the event: it ends as many calendar days after its date as the event
        $span = max(0, Utils::daysBetween((int) $record['startDate'], (int) ($record['endDate'] ?: $record['startDate'])));

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
                ($record['addTime'] ? date('H:i', $record['startTime']) : '00:00');
            $new_fix_end_time = !empty($fixedDate['new_end']) ?
                date('H:i', $fixedDate['new_end']) :
                ($record['addTime'] ? date('H:i', $record['endTime']) : '23:59');

            $new_fix_start_date = Utils::parseTime(date('Y-m-d', $new_fix_date).' '.date('H:i', Utils::parseTime($new_fix_start_time)));
            $new_fix_end_date = Utils::parseTime(date('Y-m-d', Utils::parseTime('+'.$span.' days', $new_fix_date)).' '.date('H:i', Utils::parseTime($new_fix_end_time)));

            $arrAllRecurrences[$new_fix_start_date] = $this->getRecurrenceArray($new_fix_start_date, $new_fix_end_date);
            $maxRepeatEnd[] = $new_fix_end_date;
        }

        return [empty($arrFixedDates) ? null : $arrFixedDates, $arrAllRecurrences, $maxRepeatEnd];
    }

    /**
     * @param array<string, mixed> $record
     * @param array<mixed>         $arrSet
     * @param array<mixed>         $arrAllRecurrences
     * @param array<mixed>         $maxRepeatEnd
     *
     * @return array<mixed>
     */
    private function processDefaultRecurring(array $record, array $arrSet, array $arrAllRecurrences, array $maxRepeatEnd): array
    {
        if (!empty($record['recurring'])) {
            if (!empty($record['repeatEnd'])) {
                $arrSet['repeatEnd'] = $record['repeatEnd'];
            } else {
                if (0 === (int) $record['recurrences']) {
                    $arrSet['repeatEnd'] = min(4294967295, PHP_INT_MAX);
                } else {
                    $arrRange = StringUtil::deserialize($record['repeatEach']);

                    if (isset($arrRange['unit'], $arrRange['value'])) {
                        // endTime is only set in $arrSet when the end time is ignored
                        $arrSet['repeatEnd'] = $this->addInterval((int) ($arrSet['endTime'] ?? $record['endTime']), (int) $arrRange['value'] * (int) $record['recurrences'], (string) $arrRange['unit']);
                    }
                }
            }
            $arrRange = StringUtil::deserialize($record['repeatEach'], true);

            if (empty($arrRange) || empty($arrRange['value']) || empty($arrRange['unit'])) {
                return [$arrSet, $arrAllRecurrences, $maxRepeatEnd];
            }

            $intStart = Utils::parseTime(date('Y-m-d', $record['startDate']).' '.($record['addTime'] ? date('H:i', $record['startTime']) : '00:00'));
            $intEnd = Utils::parseTime(date('Y-m-d', $record['endDate'] ?: $record['startDate']).' '.($record['addTime'] ? date('H:i', $record['endTime']) : '23:59'));

            // store the list of dates
            $next = $intStart;
            $nextEnd = $intEnd;

            // array of all recurrences
            $arrAllRecurrences[$next] = $this->getRecurrenceArray($next, $nextEnd);

            // last date of the recurrences
            $end = $arrSet['repeatEnd'];

            $step = 0;

            while ($next <= $end) {
                // Always calculate from the start, otherwise the day of the month drifts (31st -> 3rd, see addInterval())
                ++$step;
                $candidate = $this->addInterval($intStart, $step * (int) $arrRange['value'], (string) $arrRange['unit']);
                $candidateEnd = $this->addInterval($intEnd, $step * (int) $arrRange['value'], (string) $arrRange['unit']);

                // Check if we are at the end
                if (false === $candidate || false === $candidateEnd) {
                    break;
                }

                $next = $candidate;
                $nextEnd = $candidateEnd;

                // check if we are at the end
                if ($next >= $end) {
                    break;
                }

                // The options of repeatWeekday use 0 (Sunday) to 6 (Saturday), like date('w')
                $weekday = (int) date('w', $next);
                $arrWeekdays = array_map(intval(...), StringUtil::deserialize($record['repeatWeekday'], true));
                if (!empty($arrWeekdays) && 'days' === $arrRange['unit']) {
                    if (!\in_array($weekday, $arrWeekdays, true)) {
                        continue;
                    }
                }

                if ($record['hideOnWeekend']) {
                    if (0 === $weekday || 6 === $weekday) {
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
     * @param array<string, mixed> $record
     * @param array<mixed>         $arrSet
     * @param array<mixed>         $arrAllRecurrences
     * @param array<mixed>         $maxRepeatEnd
     *
     * @return array<mixed>
     */
    private function processExtendedRecurring(array $record, array $arrSet, array $arrAllRecurrences, array $maxRepeatEnd): array
    {
        if (!empty($record['recurringExt'])) {
            $arrRange = StringUtil::deserialize($record['repeatEachExt'], true);

            if (!empty($arrRange) && !empty($arrRange['value']) && !empty($arrRange['unit'])) {
                $arg = $arrRange['value'];
                $unit = $arrRange['unit'];

                // next month of the event
                $month = (int) date('n', $record['startDate']);
                // year of the event
                $year = (int) date('Y', $record['startDate']);
                // search date for the next event
                $next = Utils::parseTime(date('Y-m-d', $record['startDate']).' '.($record['addTime'] ? date('H:i', $record['startTime']) : '00:00'));
                $nextEnd = Utils::parseTime(date('Y-m-d', $record['endDate'] ?: $record['startDate']).' '.($record['addTime'] ? date('H:i', $record['endTime']) : '23:59'));
                $eventDuration = $nextEnd - $next;

                // last month
                $count = (int) $record['recurrences'];

                // array of all recurrences
                $arrAllRecurrences[$next] = $this->getRecurrenceArray($next, $nextEnd);

                // Count the occurrences, not the months: months without the weekday (e.g. a fifth Monday) have no occurrence
                $limit = $count > 0 ? $count : PHP_INT_MAX;
                $found = 0;
                $misses = 0;

                // A weekday occurs in at least every third month, so this only guards against endless loops
                while ($found < $limit && $misses < 12) {
                    ++$month;

                    if ($month > 12) {
                        $month = 1;
                        ++$year;
                    }

                    $date = $this->findWeekdayOfMonth((string) $arg, (string) $unit, $month, $year);

                    if (null === $date) {
                        ++$misses;

                        continue;
                    }

                    $misses = 0;
                    ++$found;

                    $next = Utils::parseTime($date.' '.date('H:i', $record['startTime']));
                    $nextEnd = $next + $eventDuration;
                    $arrAllRecurrences[$next] = $this->getRecurrenceArray($next, $nextEnd);

                    // check if we reached the configured max value
                    if (\count($arrAllRecurrences) === $this->maxRepeatCount) {
                        break;
                    }
                }

                $arrSet['repeatEnd'] = $count > 0 ? $next : min(4294967295, PHP_INT_MAX);

                $maxRepeatEnd[] = $arrSet['repeatEnd'];
            }
        }

        return [$arrSet, $arrAllRecurrences, $maxRepeatEnd];
    }

    /**
     * @param array<string, mixed> $record
     * @param array<mixed>         $maxRepeatEnd
     * @param array<mixed>         $arrAllRecurrences
     *
     * @return array<mixed>
     */
    private function processExceptions(array $record, int $currentEndDate, array $maxRepeatEnd, array $arrAllRecurrences): array
    {
        $exceptions = null;
        if ($record['useExceptions']) {
            // list of the exception
            $exceptionRows = [];

            // check by interval
            $exceptionRows = $this->checkExceptionsByInterval($record, $exceptionRows, $record['startTime'], $currentEndDate);

            // check by time range
            $exceptionRows = $this->checkExceptionsByTimeRange($record, $exceptionRows, $arrAllRecurrences);

            // check by date
            [$exceptionRows, $maxRepeatEnd] = $this->checkExceptionsByDate($record, $currentEndDate, $exceptionRows, $maxRepeatEnd);

            if (\count($exceptionRows) > 1) {
                ksort($exceptionRows);
            }
            $exceptions = \count($exceptionRows) > 0 ? serialize($exceptionRows) : null;
        }

        return [$exceptions, $maxRepeatEnd];
    }

    /**
     * @param array<string, mixed> $record
     * @param array<mixed>         $exceptionRows
     *
     * @return array<mixed>
     */
    private function checkExceptionsByInterval(array $record, array $exceptionRows, int $intStart, int $intRepeatEnd): array
    {
        if (!empty($record['repeatExceptionsInt'])) {
            // weekday of the event, derived from the current start date (0 = Sunday)
            $unit = self::DAYS[(int) date('w', $record['startDate'])];

            // exception rules
            $rows = StringUtil::deserialize($record['repeatExceptionsInt'], true);

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
                    $strDateToFind = $arg.' '.$unit.' of '.self::MONTHS[$month - 1].' '.$year;

                    $dateToFind = strtotime($strDateToFind);

                    if (false === $dateToFind) {
                        break;
                    }

                    $searchNext = Utils::parseTime(date('Y-m-d', $dateToFind).' '.date('H:i', $intStart));

                    if ($searchNext < $intStart) {
                        ++$month;

                        if (0 === $month % 13) {
                            $month = 1;
                            ++$year;
                        }
                        continue;
                    }

                    $row['new_start'] = $row['new_start'] ?: date('H:i', $record['startTime']); // '00:00';
                    $row['new_end'] = $row['new_end'] ?: date('H:i', $record['endTime']); // '23:59';

                    // Set endtime to starttime always...
                    if ($record['ignoreEndTime']) {
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
     * @param array<string, mixed> $record
     * @param array<mixed>         $exceptionRows
     * @param array<mixed>         $arrRecurrences the recurrences calculated during the current save operation
     *
     * @return array<mixed>
     */
    private function checkExceptionsByTimeRange(array $record, array $exceptionRows, array $arrRecurrences): array
    {
        if (!empty($record['repeatExceptionsPer'])) {
            // exception rules
            $rows = StringUtil::deserialize($record['repeatExceptionsPer'], true);

            // run through all dates
            foreach ($rows as $row) {
                if (!$row['exception']) {
                    continue;
                }

                $row['new_start'] = $row['new_start'] ?: date('H:i', $record['startTime']); // '00:00';
                // Set endtime to starttime always...
                if ($record['ignoreEndTime']) {
                    $row['new_end'] = '';
                } else {
                    $row['new_end'] = $row['new_end'] ?: date('H:i', $record['endTime']); // '23:59';
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
     * @param array<string, mixed> $record
     * @param array<mixed>         $maxRepeatEnd
     * @param array<mixed>         $exceptionRows
     *
     * @return array<mixed>
     */
    private function checkExceptionsByDate(array $record, int $currentEndDate, array $exceptionRows, array $maxRepeatEnd): array
    {
        if (!empty($record['repeatExceptions'])) {
            $rows = StringUtil::deserialize($record['repeatExceptions'], true);

            // set repeatEnd my be we have an exception move that is later then the repeatEnd
            foreach ($rows as $row) {
                if (!$row['exception']) {
                    continue;
                }

                $row['new_start'] = \array_key_exists('new_start', $row)
                    ? $row['new_start'] ?: date('H:i', $record['startTime'])
                    : date('H:i', $record['startTime']); // '00:00';

                if (!$record['ignoreEndTime']) {
                    $row['new_end'] = \array_key_exists('new_end', $row)
                        ? $row['new_end'] ?: date('H:i', $record['endTime'])
                        : date('H:i', $record['endTime']); // '23:59';
                }

                $row['exception_date'] = date('d.m.Y H:i', (int) $row['exception']);

                if ($record['ignoreEndTime']) {
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
    private function getRecurrenceArray(int $start, int $end, string|null $moveReason = null): array
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

    /**
     * Adds the interval to the time. Months and years are calculated from the given time and keep its day of the
     * month; if the target month is shorter, the last day of the month is used (31st of January + 1 month = 28th of February).
     */
    private function addInterval(int $time, int $amount, string $unit): int|false
    {
        if (!\in_array($unit, ['months', 'years'], true)) {
            return strtotime('+ '.$amount.' '.$unit, $time);
        }

        $months = 'years' === $unit ? $amount * 12 : $amount;
        $date = (new \DateTimeImmutable('@'.$time))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $target = $date->modify('first day of this month')->modify(\sprintf('%+d months', $months));
        $day = min((int) $date->format('j'), (int) $target->format('t'));

        return $target->setDate((int) $target->format('Y'), (int) $target->format('n'), $day)->getTimestamp();
    }

    /**
     * The date (Y-m-d) of the weekday in the given month, e.g. "first monday" or "last friday".
     *
     * @return string|null null if the month has no such day (e.g. no fifth Monday)
     */
    private function findWeekdayOfMonth(string $position, string $weekday, int $month, int $year): string|null
    {
        $timestamp = strtotime($position.' '.$weekday.' of '.self::MONTHS[$month - 1].' '.$year);

        // strtotime() moves a missing day to the following month
        if (false === $timestamp || (int) date('n', $timestamp) !== $month) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }
}
