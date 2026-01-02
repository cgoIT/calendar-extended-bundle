<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\calendar-extended-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) Kester Mielke
 * @copyright  Copyright (c) 2026, cgoIT
 * @author     Kester Mielke
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\CalendarExtendedBundle\Controller\Route;

use Cgoit\CalendarExtendedBundle\Controller\Module\ModuleFullCalendar;
use Contao\Date;
use Contao\Input;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/fullcalendar/fetchEvents/{moduleId}', name: FetchEventsController::class, defaults: ['_scope' => 'frontend', '_token_check' => true], methods: 'POST')]
class FetchEventsController
{
    public function __invoke(Request $request, int $moduleId): Response
    {
        System::getContainer()->get('contao.framework')->initialize();

        $objModule = ModuleModel::findById($moduleId);
        $moduleFullcalendar = new ModuleFullCalendar($objModule);
        $events = $this->fetchEvents($moduleFullcalendar);

        return new JsonResponse($events);
    }

    /**
     * Fetch all events for the given time range.
     *
     * @return array<mixed>
     */
    private function fetchEvents(ModuleFullCalendar $objModule): array
    {
        $intStart = strtotime((string) Input::post('start'));
        $intEnd = strtotime((string) Input::post('end'));

        // Get all events
        $arrAllEvents = $objModule->loadEvents(StringUtil::deserialize($objModule->cal_calendar, true), $intStart, $intEnd);

        // Sort the days
        $sort = 'descending' === $objModule->cal_order ? 'krsort' : 'ksort';
        $sort($arrAllEvents);

        // Sort the events
        foreach (array_keys($arrAllEvents) as $key) {
            $sort($arrAllEvents[$key]);
        }

        // Step 1: get the current time
        $currTime = Date::floorToMinute();

        // Array of events for JSON output
        $json_events = [];
        $multiday_event = [];

        // Create the JSON of all events
        foreach ($arrAllEvents as $days) {
            foreach ($days as $events) {
                foreach ($events as $event) {
                    // Use repeatEnd if > 0 (see #8447)
                    if (($event['repeatEnd'] ?: $event['end']) < $intStart || $event['begin'] > $intEnd) {
                        continue;
                    }

                    // Hide Events that are already started
                    if ($objModule->hide_started && $event['begin'] < $currTime) {
                        continue;
                    }

                    // Set start and end of each event to the right format for the fullcalendar
                    $event['datetime_start'] = date('Y-m-d\TH:i:s', $event['begin']);
                    $event['datetime_end'] = date('Y-m-d\TH:i:s', $event['end']);
                    $allDay = $event['addTime'] ? false : true;

                    // Set title
                    $title = StringUtil::specialchars((string) $event['title']);

                    // Some options
                    $multiday = false;
                    $recurring = false;

                    /*
                     * Editing is allowd if we have a single or multi day event. Any kind of recurring event
                     * is not allowed right now.
                     */
                    // Disable editing if event is recurring...
                    if ($event['recurring'] || $event['recurringExt'] || $event['useExceptions']) {
                        $recurring = true;
                    }

                    $row = StringUtil::deserialize($event['repeatFixedDates']);
                    if (!empty($row) && \is_array($row) && \array_key_exists('new_repeat', $row) && $row[0]['new_repeat'] > 0) {
                        $recurring = true;
                    }

                    // If event is not recurring
                    if (!$recurring) {
                        // Multi day event?
                        if (Date::parse('dmY', $event['startTime']) !== Date::parse('dmY', $event['endTime'])) {
                            $multiday = true;
                        }
                    }

                    // Set the icon
                    $icon = $recurring ? 'fa-repeat' : 'fa-calendar-o';

                    // Ignore if this is not the first multi day entry
                    if (false === array_search($event['id'], $multiday_event, true)) {
                        // Add the event to array of events
                        $json_events[] = [
                            'id' => $event['id'],
                            'calendarId' => $event['pid'],
                            'title' => $title,
                            'start' => $event['datetime_start'],
                            'end' => $event['datetime_end'],
                            'description' => $event['teaser'],
                            'allDay' => $allDay,
                            'overlap' => false,
                            'url' => $event['href'],
                            'icon' => $icon,
                            'backgroundColor' => $event['bgstyle'] ?? '',
                            'textColor' => $event['fgstyle'] ?? '',
                        ];
                    }

                    // Remember if multi day event
                    if ($multiday && false === array_search($event['id'], $multiday_event, true)) {
                        $multiday_event[] = $event['id'];
                    }
                }
            }
        }

        $json_events = $this->makeUnique($json_events);

        // Free resources
        unset($event, $events, $arrAllEvents, $multiday_event);

        return $json_events;
    }

    /**
     * @param array<mixed> $arrEvents
     *
     * @return array<mixed>
     */
    private function makeUnique(array $arrEvents): array
    {
        $uniqueEvents = [];

        foreach ($arrEvents as $event) {
            $included = array_filter(
                $uniqueEvents,
                static fn ($e) => $event['id'] === $e['id']
                    && $event['start'] === $e['start']
                    && $event['end'] === $e['end'],
            );
            if (empty($included)) {
                $uniqueEvents[] = $event;
            }
        }

        return $uniqueEvents;
    }
}
