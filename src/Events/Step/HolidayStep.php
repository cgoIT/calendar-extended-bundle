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

namespace Cgoit\CalendarExtendedBundle\Events\Step;

use Cgoit\CalendarExtendedBundle\Events\EventContext;
use Cgoit\CalendarExtendedBundle\Events\EventTree;

/**
 * Applies the holiday calendars: on days with a holiday only the holiday and the events marked with "show on
 * free day" are visible. The module can also hide the holiday events themselves.
 */
class HolidayStep
{
    /**
     * @param array<mixed> $events
     *
     * @return array<mixed>
     */
    public function process(array $events, EventContext $context): array
    {
        if ([] === $context->holidayCalendars) {
            return $events;
        }

        $events = $this->hideEventsDuringHolidays($events, $context->holidayCalendars);

        if ($context->hideHoliday) {
            $events = EventTree::map($events, fn (array $event): array|null => $this->isHolidayEvent($event, $context->holidayCalendars) ? null : $event);
        }

        return $events;
    }

    /**
     * The events are grouped by start time, but a holiday affects the whole day.
     *
     * @param array<mixed> $events
     * @param array<int>   $holidayCalendars
     *
     * @return array<mixed>
     */
    private function hideEventsDuringHolidays(array $events, array $holidayCalendars): array
    {
        foreach ($events as $day => $eventsOnDay) {
            if (!$this->isHoliday($eventsOnDay, $holidayCalendars)) {
                continue;
            }

            foreach ($eventsOnDay as $startTime => $eventsOfTime) {
                foreach ($eventsOfTime as $pos => $event) {
                    if (!$this->isHolidayEvent($event, $holidayCalendars) && empty($event['showOnFreeDay'])) {
                        unset($events[$day][$startTime][$pos]);
                    }
                }
            }
        }

        return $events;
    }

    /**
     * @param array<mixed> $eventsOnDay
     * @param array<int>   $holidayCalendars
     */
    private function isHoliday(array $eventsOnDay, array $holidayCalendars): bool
    {
        foreach ($eventsOnDay as $events) {
            foreach ($events as $event) {
                if ($this->isHolidayEvent($event, $holidayCalendars)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The pid might be a string, depending on the database driver.
     *
     * @param array<string, mixed> $event
     * @param array<int>           $holidayCalendars
     */
    private function isHolidayEvent(array $event, array $holidayCalendars): bool
    {
        return \in_array((int) $event['pid'], $holidayCalendars, true);
    }
}
