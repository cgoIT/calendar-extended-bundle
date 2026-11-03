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
use Contao\Date;
use Contao\StringUtil;

/**
 * Adds the occurrences of the extended recurrences and of the fixed dates, which the core does not know.
 */
class RecurringEventsLoader
{
    public function __construct(
        private readonly RecurringEventsFinder $finder,
        private readonly EventFactory $eventFactory,
    ) {
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

        $arrRecurringEvents = $this->finder->findWithExtendedRecurrences($arrCalendars);

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

        $arrEventsWithFixedRepeats = $this->finder->findWithFixedDates($arrCalendars);

        foreach ($arrEventsWithFixedRepeats as $objEvent) {
            $arrFixedDates = StringUtil::deserialize($objEvent->repeatFixedDates);

            if (!empty($arrFixedDates) && \is_array($arrFixedDates)) {
                $arrFixedDates = Utils::sanitizeFixedDates($arrFixedDates);

                // A fixed date keeps the duration of the event: it ends as many calendar days after its date as the event
                $span = max(0, Utils::daysBetween((int) $objEvent->startTime, (int) $objEvent->endTime));

                foreach ($arrFixedDates as $date) {
                    $intStart = $date['new_repeat'];
                    $strEndDate = date('Y-m-d', Utils::parseTime('+'.$span.' days', $date['new_repeat']));
                    $reason = $date['reason'] ?? null;

                    if ($objEvent->addTime) {
                        if (empty($date['new_start'])) {
                            $intStart = Utils::parseTime(date('Y-m-d', $date['new_repeat']).' '.date('H:i', $objEvent->startTime));
                        } else {
                            $intStart = Utils::parseTime(date('Y-m-d', $date['new_repeat']).' '.date('H:i', $date['new_start']));
                        }

                        if ($objEvent->ignoreEndTime) {
                            $intEnd = Utils::parseTime('+'.$span.' days', $intStart);
                        } else {
                            if (empty($date['new_end'])) {
                                $intEnd = Utils::parseTime($strEndDate.' '.date('H:i', $objEvent->endTime));
                            } else {
                                $intEnd = Utils::parseTime($strEndDate.' '.date('H:i', $date['new_end']));
                            }
                        }
                    } else {
                        // The event takes place the whole day(s) and ends at the end time of the event (23:59:59)
                        $intEnd = Utils::parseTime($strEndDate.' '.date('H:i:s', $objEvent->endTime));
                    }

                    // Like the core, also add occurrences that started before the period and are still running
                    if ($intEnd >= $timeStart && $intStart <= $timeEnd) {
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

        return $arrEvents;
    }
}
