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

namespace Cgoit\CalendarExtendedBundle\Events;

use Cgoit\CalendarExtendedBundle\Classes\Utils;
use Contao\CalendarEventsModel;
use Contao\Date;
use Contao\StringUtil;

/**
 * Adds the occurrences of the extended recurrences and of the fixed dates, which the core does not know.
 */
class RecurringEventsLoader
{
    public function __construct(private readonly EventFactory $eventFactory)
    {
    }

    /**
     * @param array<mixed> $arrEvents
     *
     * @return array<mixed>
     */
    public function addExtendedRecurrences(array $arrEvents, EventContext $context): array
    {
        $arrCalendars = $context->calendars;
        $timeStart = $context->start;
        $timeEnd = $context->end;

        if ([] === $arrCalendars) {
            return $arrEvents;
        }

        $t = 'tl_calendar_events';
        $time = time();
        $arrRecurringEvents = CalendarEventsModel::findBy(
            [
                "$t.pid IN (".implode(', ', $arrCalendars).')',
                "$t.recurringExt=?",
                "$t.published='1' AND ($t.start='' OR $t.start<=$time) AND ($t.stop='' OR $t.stop>$time)",
            ],
            ['1'],
        );
        if (!empty($arrRecurringEvents)) {
            foreach ($arrRecurringEvents as $objEvent) {
                $allRecurrences = StringUtil::deserialize($objEvent->allRecurrences, true);

                foreach ($allRecurrences as $recurrence) {
                    if ($recurrence['int_start'] >= $timeStart && $recurrence['int_start'] <= $timeEnd) {
                        $key = Date::parse('Ymd', $recurrence['int_start']);
                        if (\array_key_exists($key, $arrEvents) && \array_key_exists($recurrence['int_start'], $arrEvents[$key])) {
                            $alreadyInArray = false;

                            foreach ($arrEvents[$key][$recurrence['int_start']] as $event) {
                                if ($event['id'] === $objEvent->id) {
                                    $alreadyInArray = true;
                                    break;
                                }
                            }

                            if ($alreadyInArray) {
                                continue;
                            }
                        }

                        $this->eventFactory->create($objEvent, $context, $recurrence['int_start'], $recurrence['int_end'], $arrEvents, false);
                    }
                }
            }
        }

        return $arrEvents;
    }

    /**
     * Handle fixed dates with recurrences.
     *
     * @param array<mixed> $arrEvents Array of events
     *
     * @return array<mixed> Array of events with fixed dates recurrences
     *
     * @throws \Exception
     */
    public function addFixedDates(array $arrEvents, EventContext $context): array
    {
        $arrCalendars = $context->calendars;
        $timeStart = $context->start;
        $timeEnd = $context->end;

        if ([] === $arrCalendars) {
            return $arrEvents;
        }

        $t = 'tl_calendar_events';
        $time = time();
        $arrEventsWithFixedRepeats = CalendarEventsModel::findBy(
            [
                "$t.pid IN (".implode(', ', $arrCalendars).')',
                "$t.repeatFixedDates IS NOT NULL",
                "$t.repeatFixedDates !=?",
                "$t.published='1' AND ($t.start='' OR $t.start<=$time) AND ($t.stop='' OR $t.stop>$time)",
            ],
            [''],
        );
        if (!empty($arrEventsWithFixedRepeats)) {
            foreach ($arrEventsWithFixedRepeats as $objEvent) {
                $arrFixedDates = StringUtil::deserialize($objEvent->repeatFixedDates);

                if (!empty($arrFixedDates) && \is_array($arrFixedDates)) {
                    $arrFixedDates = Utils::sanitizeFixedDates($arrFixedDates);

                    foreach ($arrFixedDates as $date) {
                        $intStart = $date['new_repeat'];
                        $intEnd = $intStart;
                        $reason = $date['reason'] ?? null;

                        if ($objEvent->addTime) {
                            if (empty($date['new_start'])) {
                                $intStart = strtotime(date('Y-m-d', $date['new_repeat']).' '.date('H:i', $objEvent->startTime));
                            } else {
                                $intStart = strtotime(date('Y-m-d', $date['new_repeat']).' '.date('H:i', $date['new_start']));
                            }

                            if ($objEvent->ignoreEndTime) {
                                $intEnd = $intStart;
                            } else {
                                if (empty($date['new_end'])) {
                                    $intEnd = strtotime(date('Y-m-d', $date['new_repeat']).' '.date('H:i', $objEvent->endTime));
                                } else {
                                    $intEnd = strtotime(date('Y-m-d', $date['new_repeat']).' '.date('H:i', $date['new_end']));
                                }
                            }
                        } elseif (empty($objEvent->endDate)) {
                            // Only start date is set. Event will take place the whole day.
                            $intEnd = $intStart + $objEvent->endTime - $objEvent->startTime;
                        }

                        if ($intStart >= $timeStart && $intStart <= $timeEnd) {
                            $key = Date::parse('Ymd', $intStart);
                            if (\array_key_exists($key, $arrEvents) && \array_key_exists($intStart, $arrEvents[$key])) {
                                $alreadyInArray = false;

                                foreach ($arrEvents[$key][$intStart] as $idx => $event) {
                                    if ($event['id'] === $objEvent->id) {
                                        $arrEvents[$key][$intStart][$idx]['recurrences'] = \count($arrFixedDates);
                                        $alreadyInArray = true;
                                        break;
                                    }
                                }

                                if ($alreadyInArray) {
                                    continue;
                                }
                            }

                            $this->eventFactory->create($objEvent, $context, $intStart, $intEnd, $arrEvents, true, \count($arrFixedDates), $reason);
                        }
                    }
                }
            }
        }

        return $arrEvents;
    }
}
