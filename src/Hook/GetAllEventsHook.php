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

namespace Cgoit\CalendarExtendedBundle\Hook;

use Cgoit\CalendarExtendedBundle\Classes\Utils;
use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Date;
use Contao\Events;
use Contao\PageModel;
use Contao\StringUtil;

#[AsHook(hook: 'getAllEvents', priority: -100)]
class GetAllEventsHook
{
    /**
     * @var array<mixed>
     */
    protected $calConf = [];

    /**
     * @return array<mixed>
     */
    public function __invoke(array $arrEvents, array $arrCalendars, int $timeStart, int $timeEnd, Events $objModule): array
    {
        // Read and apply the calendar config (title and colors)
        $this->getCalendarConfig($objModule);
        $arrEvents = $this->applyCalendarConfig($arrEvents);

        $arrEvents = $this->handleExceptions($arrEvents);

        $arrEvents = $this->hideEventsDuringHolidays($arrEvents);
        $arrEvents = $this->hideHolidayEvents($arrEvents, $objModule);

        $arrEvents = $this->handleShowOnlyNext($arrEvents, $objModule);

        return $this->compactEvents($arrEvents);
    }

    private function getCalendarConfig(Events $objModule): void
    {
        // Get the background and foreground colors of the calendars
        foreach ($objModule->cal_calendar as $cal) {
            $objCalendar = CalendarModel::findById($cal);

            $this->calConf[$cal]['calendar'] = $objCalendar->title;

            if (!empty($objCalendar->bg_color)) {
                [$cssColor, $cssOpacity] = StringUtil::deserialize($objCalendar->bg_color, true);

                if (!empty($cssColor)) {
                    Utils::appendToArrayKey($this->calConf[$cal], 'background', 'background-color:#'.$cssColor.';');
                }

                if (!empty($cssOpacity)) {
                    Utils::appendToArrayKey($this->calConf[$cal], 'background', 'opacity:'.($cssOpacity / 100).';');
                }
            }

            if (!empty($objCalendar->fg_color)) {
                [$cssColor, $cssOpacity] = StringUtil::deserialize($objCalendar->fg_color, true);

                if (!empty($cssColor)) {
                    Utils::appendToArrayKey($this->calConf[$cal], 'foreground', 'color:#'.$cssColor.';');
                }

                if (!empty($cssOpacity)) {
                    Utils::appendToArrayKey($this->calConf[$cal], 'foreground', 'opacity:'.($cssOpacity / 100).';');
                }
            }
        }
    }

    /**
     * @param array<mixed> $arrEvents
     *
     * @return array<mixed>
     */
    private function applyCalendarConfig(array $arrEvents): array
    {
        foreach ($arrEvents as &$timestamp) {
            foreach ($timestamp as &$events) {
                foreach ($events as &$event) {
                    if (isset($this->calConf[$event['pid']])) {
                        $conf = $this->calConf[$event['pid']];
                        $event['calendar_title'] = $conf['calendar'];
                        if (!empty($conf['background'])) {
                            $event['bgstyle'] = $conf['background'];
                        }
                        if (!empty($conf['foreground'])) {
                            $event['fgstyle'] = $conf['foreground'];
                        }
                    }
                }
            }
        }

        return $arrEvents;
    }

    /**
     * @param array<mixed> $arrEvents
     *
     * @return array<mixed>
     */
    private function handleShowOnlyNext(array $arrEvents, Events $objModule): array
    {
        if (!empty($objModule->showOnlyNext)) {
            $currentTimestamp = time();

            $arrRunningEvents = [];

            foreach ($arrEvents as &$eventsOnDay) {
                foreach ($eventsOnDay as $startTime => &$events) {
                    foreach ($events as $pos => &$event) {
                        $timeToCompare = $objModule->hideRunning ? $startTime + $event['endTime'] - $event['startTime'] : $startTime;

                        if ($timeToCompare > $currentTimestamp) {
                            $key = $event['pid'].'_'.$event['id'];
                            if (\array_key_exists($key, $arrRunningEvents)) {
                                $cnt = $arrRunningEvents[$key];
                            } else {
                                $cnt = 0;
                            }

                            $arrRunningEvents[$key] = ++$cnt;
                            if ($cnt > 1) {
                                unset($events[$pos]);
                            }
                        }
                    }
                }
            }
        }

        return $arrEvents;
    }

    /**
     * @param array<mixed> $arrEvents
     *
     * @return array<mixed>
     */
    private function handleExceptions(array $arrEvents): array
    {
        /** @var PageModel */
        global $objPage;

        $eventsToAdd = [];

        foreach ($arrEvents as &$eventsOnDay) {
            foreach ($eventsOnDay as $startTime => &$events) {
                foreach ($events as $pos => &$event) {
                    if (!empty($event['useExceptions']) && !empty($event['exceptionList'])) {
                        $arrSkipInfo = StringUtil::deserialize($event['exceptionList'], true);

                        if (\array_key_exists($startTime, $arrSkipInfo) && \is_array($arrSkipInfo[$startTime])) {
                            $skipInfo = $arrSkipInfo[$startTime];

                            $action = $skipInfo['action'];

                            if ('move' === $action) {
                                $cssClass = $skipInfo['cssclass'];
                                $event['cssClass'] .= 'moved '.$cssClass;

                                $event['oldStartTime'] = $event['startTime'];
                                $event['oldEndTime'] = $event['endTime'];

                                $dateChangeValue = (string) $skipInfo['new_exception'];
                                $event['moveReason'] = $skipInfo['reason'];

                                // only change the start and end time if addTime is set to true for the event
                                if (!empty($event['addTime']) && !empty($skipInfo['new_start']) && !empty($skipInfo['new_end'])) {
                                    $newStartTime = strtotime($dateChangeValue,
                                        strtotime(Date::parse($objPage->dateFormat, $startTime).' '.$skipInfo['new_start']));
                                    $newEndTime = strtotime(Date::parse($objPage->dateFormat,
                                        $newStartTime + $event['endTime'] - $event['startTime']).' '.$skipInfo['new_end']);
                                } else {
                                    $newStartTime = strtotime($dateChangeValue, $startTime);
                                    $newEndTime = $newStartTime + $event['endTime'] - $event['startTime'];
                                }

                                $event['startTime'] = $newStartTime;
                                $event['endTime'] = $newEndTime;

                                $event['date'] = Date::parse($objPage->dateFormat, $newStartTime);
                                $event['datetime'] = Date::parse('Y-m-d', $newStartTime);
                                $event['day'] = Date::parse('l', $newStartTime);
                                $event['month'] = Date::parse('F', $newStartTime);

                                $weekday = Date::parse('w', $newStartTime);
                                if (empty($event['hideOnWeekend']) || ('0' !== $weekday && '6' !== $weekday)) {
                                    $eventsToAdd[] = $event;
                                }
                            }

                            unset($events[$pos]);
                        }
                    }

                    if (!empty($event['repeatWeekday'])) {
                        $weekdays = StringUtil::deserialize($event['repeatWeekday'], true);
                        $eventWeekday = Date::parse('w', $startTime);
                        if (!\in_array($eventWeekday, $weekdays, true)) {
                            unset($events[$pos]);
                        }
                    }

                    if (!empty($event['hideOnWeekend'])) {
                        $weekday = Date::parse('w', $startTime);
                        if ('0' === $weekday || '6' === $weekday) {
                            unset($events[$pos]);
                        }
                    }
                }
            }
        }

        unset($event);

        foreach ($eventsToAdd as $event) {
            $this->addEvent($arrEvents, $event);
        }

        return $arrEvents;
    }

    /**
     * @param array<mixed> $arrEvents
     *
     * @return array<mixed>
     */
    private function hideEventsDuringHolidays(array $arrEvents): array
    {
        $arrHolidayCalendars = $this->getHolidayCalendarIds();

        if (!empty($arrHolidayCalendars)) {
            foreach ($arrEvents as &$eventsOnDay) {
                foreach ($eventsOnDay as &$events) {
                    $eventPids = array_column($events, 'pid');
                    $dayIsAHoliday = !empty(array_filter($eventPids, static fn ($pid) => \in_array($pid, $arrHolidayCalendars, true)));

                    foreach ($events as $pos => $event) {
                        $isHolidayEvent = \in_array($event['pid'], $arrHolidayCalendars, true);

                        if (!$isHolidayEvent && $dayIsAHoliday && empty($event['showOnFreeDay'])) {
                            unset($events[$pos]);
                        }
                    }
                }
            }
        }

        return $arrEvents;
    }

    /**
     * @param array<mixed> $arrEvents
     *
     * @return array<mixed>
     */
    private function hideHolidayEvents(array $arrEvents, Events $objModule): array
    {
        if (!empty($objModule->hide_holiday)) {
            $arrHolidayCalendars = $this->getHolidayCalendarIds();

            if (!empty($arrHolidayCalendars)) {
                foreach ($arrEvents as &$eventsOnDay) {
                    foreach ($eventsOnDay as &$events) {
                        foreach ($events as $pos => $event) {
                            if (\in_array((int) $event['pid'], $arrHolidayCalendars, true)) {
                                unset($events[$pos]);
                            }
                        }
                    }
                }
            }
        }

        return $arrEvents;
    }

    /**
     * @param array<mixed> $arrEvents
     *
     * @return array<mixed>
     */
    private function compactEvents(array $arrEvents): array
    {
        foreach ($arrEvents as $day => &$eventsOnDay) {
            foreach ($eventsOnDay as $startTime => &$events) {
                if (empty($events)) {
                    unset($eventsOnDay[$startTime]);
                }
            }

            if (empty($eventsOnDay)) {
                unset($arrEvents[$day]);
            }
        }

        return $arrEvents;
    }

    /**
     * @param array<mixed> $arrEvents
     * @param array<mixed> $event
     */
    private function addEvent(array &$arrEvents, array $event): void
    {
        $startTime = $event['startTime'];
        $day = Date::parse('Ymd', $startTime);

        if (!\array_key_exists($day, $arrEvents)) {
            $arrEvents[$day] = [];
        }

        if (!\array_key_exists($startTime, $arrEvents[$day])) {
            $arrEvents[$day][$startTime] = [];
        }

        $arrEvents[$day][$startTime][] = $event;
    }

    /**
     * @return array<int>
     */
    private function getHolidayCalendarIds(): array
    {
        $arrHolidayCalendars = [];
        $objHolidayCalendars = CalendarModel::findBy(['isHolidayCal=?'], ['1']);

        foreach ($objHolidayCalendars as $objHolidayCalendar) {
            $arrHolidayCalendars[] = (int) $objHolidayCalendar->id;
        }

        return $arrHolidayCalendars;
    }
}
