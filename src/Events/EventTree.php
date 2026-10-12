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

use Contao\Date;

/**
 * Helpers for the structure of the events: day (Ymd) => start time => list of events.
 */
final class EventTree
{
    /**
     * Apply the callback to each event. The event is removed if the callback returns null.
     *
     * @param array<mixed>                                                                        $tree
     * @param callable(array<string, mixed>, int|string, int|string): (array<string, mixed>|null) $callback receives the event, the day and the start time
     *
     * @return array<mixed>
     */
    public static function map(array $tree, callable $callback): array
    {
        foreach ($tree as $day => $eventsOnDay) {
            foreach ($eventsOnDay as $startTime => $events) {
                foreach ($events as $pos => $event) {
                    $result = $callback($event, $day, $startTime);

                    if (null === $result) {
                        unset($tree[$day][$startTime][$pos]);
                    } else {
                        $tree[$day][$startTime][$pos] = $result;
                    }
                }
            }
        }

        return $tree;
    }

    /**
     * @param array<mixed> $tree
     * @param array<mixed> $event
     */
    public static function add(array &$tree, array $event, int|null $startTime = null): void
    {
        $time = $startTime ?? $event['startTime'];
        $day = Date::parse('Ymd', $time);

        $tree[$day][$time][] = $event;
    }

    /**
     * Remove the empty entries and sort days and start times.
     *
     * @param array<mixed> $tree
     *
     * @return array<mixed>
     */
    public static function compact(array $tree): array
    {
        foreach ($tree as $day => $eventsOnDay) {
            foreach ($eventsOnDay as $startTime => $events) {
                if (empty($events)) {
                    unset($tree[$day][$startTime]);
                }
            }

            if (empty($tree[$day])) {
                unset($tree[$day]);
                continue;
            }

            ksort($tree[$day]);
        }

        ksort($tree);

        return $tree;
    }
}
