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

namespace Cgoit\CalendarExtendedBundle\Classes;

class FullCalendarEventMapper
{
    /**
     * Converts an event of the calendar modules to the event object of FullCalendar.
     *
     * @param array<string, mixed> $event
     *
     * @return array{event: array<string, mixed>, multiday: bool}
     */
    public static function map(array $event): array
    {
        $allDay = empty($event['addTime']);
        $end = null;

        if ($allDay) {
            $start = date('Y-m-d', (int) $event['startDate']);

            if (!empty($event['endDate'])) {
                // We have to add one day for fullcalendar to display the event correctly
                $end = date('Y-m-d', (int) $event['endDate'] + 86400);
            }
        } else {
            $start = date('Y-m-d\TH:i:s', (int) $event['begin']);
            $end = date('Y-m-d\TH:i:s', (int) $event['end']);
        }

        // Any kind of recurring event is not a multi day event
        $recurring = !empty($event['recurring'])
            || !empty($event['recurringExt'])
            || !empty($event['useExceptions'])
            || Utils::hasFixedDates($event['repeatFixedDates'] ?? null);

        $multiday = !$recurring && date('dmY', (int) $event['startTime']) !== date('dmY', (int) $event['endTime']);

        return [
            'event' => [
                'id' => $event['id'],
                'calendarId' => $event['pid'],
                // FullCalendar renders the title as text, so it must not be escaped here
                'title' => Utils::getPlainTitle($event),
                'start' => $start,
                'end' => $end,
                'description' => Utils::getPlainTeaser($event['teaser'] ?? ''),
                'allDay' => $allDay,
                'overlap' => false,
                'url' => $event['href'],
                'icon' => $recurring ? 'fa-repeat' : 'fa-calendar-o',
                'backgroundColor' => $event['bgcolor'] ?? '',
                'textColor' => $event['fgcolor'] ?? '',
            ],
            'multiday' => $multiday,
        ];
    }
}
