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
use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Date;
use Contao\Events;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;

#[AsHook(hook: 'getAllEvents', priority: -100)]
class GetAllEventsHook
{
    /**
     * @var array<mixed>
     */
    private array $calConf = [];

    private int|false $intTodayBegin = false;

    private int|false $intTodayEnd = false;

    /**
     * @param array<mixed> $arrEvents
     * @param array<mixed> $arrCalendars
     *
     * @return array<mixed>
     *
     * @throws \Exception
     */
    public function __invoke(array $arrEvents, array $arrCalendars, int $timeStart, int $timeEnd, Events $objModule): array
    {
        // Add events from recurringExt
        $arrEvents = $this->handleExtendedRecurrences($arrEvents, $arrCalendars, $timeStart, $timeEnd, $objModule);

        // Add fixed dates
        $arrEvents = $this->handleFixedDatesRecurrences($arrEvents, $arrCalendars, $timeStart, $timeEnd, $objModule);

        // Read and apply the calendar config (title and colors)
        $this->calConf = Utils::getCalendarConfig($objModule);
        $arrEvents = $this->applyCalendarConfig($arrEvents);

        $arrEvents = $this->handleExceptions($arrEvents);

        $arrEvents = $this->hideEventsDuringHolidays($arrEvents);
        $arrEvents = $this->hideHolidayEvents($arrEvents, $objModule);

        $arrEvents = $this->handleShowOnlyNext($arrEvents, $objModule);

        $arrEvents = $this->addDayAndTimeUrlParameters($arrEvents, $objModule);

        return $this->compactAndSortEvents($arrEvents);
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
                    $event['bgstyle'] = '';
                    $event['fgstyle'] = '';

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
     * @param array<mixed> $arrCalendars
     *
     * @return array<mixed>
     */
    private function handleExtendedRecurrences(array $arrEvents, array $arrCalendars, int $timeStart, int $timeEnd, Events $objModule): array
    {
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

                        $this->createEvents($objEvent, $objModule, $recurrence['int_start'], $recurrence['int_end'], $arrEvents, false);
                    }
                }
            }
        }

        return $arrEvents;
    }

    /**
     * Handle fixed dates with recurrences.
     *
     * @param array<mixed> $arrEvents    Array of events
     * @param array<int>   $arrCalendars Array of calendar IDs
     * @param int          $timeStart    Start time
     * @param int          $timeEnd      End time
     * @param Events       $objModule    Events module object
     *
     * @return array<mixed> Array of events with fixed dates recurrences
     *
     * @throws \Exception
     */
    private function handleFixedDatesRecurrences(array $arrEvents, array $arrCalendars, int $timeStart, int $timeEnd, Events $objModule): array
    {
        /** @var PageModel */
        global $objPage;

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
                    foreach ($arrFixedDates as $date) {
                        $intStart = $date['new_repeat'];
                        $intEnd = $intStart;

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
                        }

                        if ($intStart >= $timeStart && $intStart <= $timeEnd) {
                            $key = Date::parse('Ymd', $intStart);
                            if (\array_key_exists($key, $arrEvents) && \array_key_exists($intStart, $arrEvents[$key])) {
                                $alreadyInArray = false;

                                foreach ($arrEvents[$key][$intStart] as &$event) {
                                    if ($event['id'] === $objEvent->id) {
                                        $event['recurrences'] = \count($arrFixedDates);
                                        $alreadyInArray = true;
                                        break;
                                    }
                                }

                                if ($alreadyInArray) {
                                    continue;
                                }
                            }

                            $this->createEvents($objEvent, $objModule, $intStart, $intEnd, $arrEvents, true, \count($arrFixedDates));
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
    private function addDayAndTimeUrlParameters(array $arrEvents, Events $objModule): array
    {
        if (empty($objModule->cal_ignoreDynamic)) {
            foreach ($arrEvents as &$eventsOnDay) {
                foreach ($eventsOnDay as &$events) {
                    if (!empty($events)) {
                        foreach ($events as &$event) {
                            if ($event['startTime'] !== $event['begin']) {
                                $url = '?day='
                                    .date('Ymd', $event['begin'])
                                    .'&amp;times='.$event['begin']
                                    .','.$event['end'];
                                $event['href'] .= $url;
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
    private function compactAndSortEvents(array $arrEvents): array
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

        if (!empty($arrEvents)) {
            foreach (array_keys($arrEvents) as $key) {
                ksort($arrEvents[$key]);
            }
        }

        return $arrEvents;
    }

    /**
     * @param array<mixed> $arrEvents
     *
     * @throws \Exception
     */
    private function createEvents(CalendarEventsModel $objEvent, Events $objModule, int $intStart, int $intEnd, array &$arrEvents, bool $isFixedDate, int|null $intRecurrences = null): void
    {
        /** @var PageModel */
        global $objPage;

        if (empty($objPage)) {
            System::getContainer()->get('request_stack')->getCurrentRequest();
            $rootPage = PageModel::findPublishedFallbackByHostname(
                System::getContainer()->get('request_stack')->getCurrentRequest()->getHost(),
                ['fallbackToEmpty' => true],
            );
            $objPage = $rootPage->loadDetails();
        }

        System::loadLanguageFile('tl_calendar_events');

        $intDate = $intStart;
        $strDate = Date::parse($objPage->dateFormat, $intStart);
        $strDay = $GLOBALS['TL_LANG']['DAYS'][date('w', $intStart)];
        $strMonth = $GLOBALS['TL_LANG']['MONTHS'][date('n', $intStart) - 1];
        $span = Calendar::calculateSpan($intStart, $intEnd);

        if ($span > 0) {
            $strDate = Date::parse($objPage->dateFormat, $intStart).$GLOBALS['TL_LANG']['MSC']['cal_timeSeparator'].Date::parse($objPage->dateFormat, $intEnd);
            $strDay = '';
        }

        $strTime = '';

        if ($objEvent->addTime) {
            if ($span > 0) {
                $strDate = Date::parse($objPage->datimFormat, $intStart).$GLOBALS['TL_LANG']['MSC']['cal_timeSeparator'].Date::parse($objPage->datimFormat, $intEnd);
            } elseif ($intStart === $intEnd) {
                $strTime = Date::parse($objPage->timeFormat, $intStart);
            } else {
                $strTime = Date::parse($objPage->timeFormat, $intStart).$GLOBALS['TL_LANG']['MSC']['cal_timeSeparator'].Date::parse($objPage->timeFormat, $intEnd);
            }
        }

        [$until, $recurring] = Utils::getUntilAndRecurring($objEvent, $objPage, $intStart, $strDate, $strTime, $isFixedDate);

        $arrEvent = $objEvent->row();

        $arrEvent['startDate'] = $objEvent->startDate;
        $arrEvent['startTime'] = $objEvent->startTime;
        $arrEvent['endDate'] = $objEvent->endDate;
        $arrEvent['endTime'] = $objEvent->endTime;
        $arrEvent['date'] = $strDate;
        $arrEvent['time'] = $strTime;
        $arrEvent['datetime'] = $objEvent->addTime ? date('Y-m-d\TH:i:sP', $intStart) : date('Y-m-d', $intStart);
        $arrEvent['day'] = $strDay;
        $arrEvent['month'] = $strMonth;
        $arrEvent['parent'] = $objEvent->pid;
        $arrEvent['calendar'] = $objEvent->getRelated('pid');
        $arrEvent['link'] = $objEvent->title;
        $arrEvent['target'] = '';
        $arrEvent['title'] = StringUtil::specialchars($objEvent->title, true);
        $arrEvent['href'] = Events::generateEventUrl($objEvent);
        $arrEvent['class'] = $objEvent->cssClass ? ' '.$objEvent->cssClass : '';
        $arrEvent['recurring'] = $recurring;
        $arrEvent['until'] = $until;
        $arrEvent['begin'] = $intStart;
        $arrEvent['end'] = $intEnd;
        $arrEvent['effectiveEndTime'] = $arrEvent['endTime'];
        $arrEvent['details'] = '';
        $arrEvent['hasTeaser'] = false;

        if (null !== $intRecurrences) {
            $arrEvent['recurrences'] = $intRecurrences;
        }

        // Set open-end events to 23:59:59, so they run until the end of the day (see #4476)
        if ($intStart === $intEnd && $objEvent->addTime) {
            $arrEvent['effectiveEndTime'] = strtotime(date('Y-m-d', $arrEvent['endTime']).' 23:59:59');
        }

        // Override the link target
        if ('external' === $objEvent->source && $objEvent->target) {
            $arrEvent['target'] = ' target="_blank" rel="noreferrer noopener"';
        }

        // Clean the RTE output
        if ($arrEvent['teaser']) {
            $arrEvent['hasTeaser'] = true;
            $arrEvent['teaser'] = StringUtil::encodeEmail($arrEvent['teaser']);
        }

        // Display the "read more" button for external/article links
        if ('default' !== $objEvent->source) {
            $arrEvent['hasDetails'] = true;
        }

        // Compile the event text
        else {
            $id = $objEvent->id;

            $arrEvent['details'] = static function () use ($id) {
                $strDetails = '';
                $objElement = ContentModel::findPublishedByPidAndTable($id, 'tl_calendar_events');

                if (null !== $objElement) {
                    while ($objElement->next()) {
                        $strDetails .= Controller::getContentElement($objElement->current());
                    }
                }

                return $strDetails;
            };

            $arrEvent['hasDetails'] = static fn () => ContentModel::countPublishedByPidAndTable($id, 'tl_calendar_events') > 0;
        }

        // Get today's start and end timestamp
        if (!$this->intTodayBegin) {
            $this->intTodayBegin = strtotime('00:00:00');
        }

        if (!$this->intTodayEnd) {
            $this->intTodayEnd = strtotime('23:59:59');
        }

        // Mark past and upcoming events (see #3692)
        if ($intEnd < $this->intTodayBegin) {
            $arrEvent['class'] .= ' bygone';
        } elseif ($intStart > $this->intTodayEnd) {
            $arrEvent['class'] .= ' upcoming';
        } else {
            $arrEvent['class'] .= ' current';
        }

        if (1 === $arrEvent['featured']) {
            $arrEvent['class'] .= ' featured';
        }

        $this->addEvent($arrEvents, $arrEvent, $intDate);

        // Multi-day event
        if (!$objModule->cal_noSpan) {
            for ($i = 1; $i <= $span; ++$i) {
                $intDate = strtotime('+1 day', $intDate);

                if ($intDate > $intEnd) {
                    break;
                }

                $this->addEvent($arrEvents, $arrEvent, $intDate);
            }
        }
    }

    /**
     * @param array<mixed> $arrEvents
     * @param array<mixed> $event
     */
    private function addEvent(array &$arrEvents, array $event, int|null $startTime = null): void
    {
        $time = $startTime ?? $event['startTime'];
        $day = Date::parse('Ymd', $time);

        if (!\array_key_exists($day, $arrEvents)) {
            $arrEvents[$day] = [];
        }

        if (!\array_key_exists($time, $arrEvents[$day])) {
            $arrEvents[$day][$time] = [];
        }

        $arrEvents[$day][$time][] = $event;
    }

    /**
     * @return array<int>
     */
    private function getHolidayCalendarIds(): array
    {
        $arrHolidayCalendars = [];
        $objHolidayCalendars = CalendarModel::findBy(['isHolidayCal=?'], ['1']);

        if (!empty($objHolidayCalendars)) {
            foreach ($objHolidayCalendars as $objHolidayCalendar) {
                $arrHolidayCalendars[] = (int) $objHolidayCalendar->id;
            }
        }

        return $arrHolidayCalendars;
    }
}
