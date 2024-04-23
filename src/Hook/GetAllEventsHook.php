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
use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\Date;
use Contao\Events;
use Contao\PageModel;
use Contao\StringUtil;
use Symfony\Component\Security\Core\Security;

#[AsHook(hook: 'getAllEvents', priority: -100)]
class GetAllEventsHook
{
    /**
     * @var array<mixed>
     */
    protected $calConf = [];

    /**
     * @param array<mixed> $arrMonth
     */
    public function __construct(
        private readonly array $arrMonth,
        private readonly Security $securityHelper,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function __invoke(array $arrEvents, array $arrCalendars, int $timeStart, int $timeEnd, Events $objEvents): array
    {
        $objEvents->cal_holiday = $this->sortOutProtected(StringUtil::deserialize($objEvents->cal_holiday, true));

        // Read and apply the calendar config (title and colors)
        $this->getCalendarConfig($objEvents);
        $arrEvents = $this->applyCalendarConfig($arrEvents);

        return $this->handleExceptions($arrEvents);
    }

    private function getCalendarConfig(Events $objEvents): void
    {
        // Get the background and foreground colors of the calendars
        foreach (array_merge($objEvents->cal_calendar, $objEvents->cal_holiday) as $cal) {
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
                        if (!empty($conf['bg_color'])) {
                            $event['bgstyle'] = $conf['background'];
                        }
                        if (!empty($conf['fg_color'])) {
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
    private function handleRecurringExt(array $arrEvents, Events $objEvents, int $intStart, int $intEnd): array
    {
        /** @var PageModel */
        global $objPage;


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

        foreach ($arrEvents as $day => &$eventsOnDay) {
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
                                if (empty($event['hideOnWeekend']) || ($weekday !== '0' && $weekday !== '6')) {
                                    $eventsToAdd[] = $event;
                                }
                            }

                            unset($events[$pos]);
                        }

                        if (!empty($event['repeatWeekday'])) {
                            $weekdays = StringUtil::deserialize($event['repeatWeekday'], true);
                            $eventWeekday = Date::parse('w', $startTime);
                            if (!\in_array($eventWeekday, $weekdays, true)) {
                                unset($events[$pos]);
                            }
                        }
                    }

                    if (!empty($event['hideOnWeekend'])) {
                        $weekday = Date::parse('w', $startTime);
                        if ($weekday === '0' || $weekday === '6') {
                            unset($events[$pos]);
                        }
                    }
                }

                if (empty($events)) {
                    unset($eventsOnDay[$startTime]);
                }
            }

            if (empty($eventsOnDay)) {
                unset($arrEvents[$day]);
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
     * Sort out protected archives.
     *
     * @param array<mixed> $arrCalendars
     *
     * @return array<mixed>
     */
    private function sortOutProtected(array $arrCalendars): array
    {
        if (empty($arrCalendars)) {
            return $arrCalendars;
        }

        $objCalendar = CalendarModel::findMultipleByIds($arrCalendars);
        $arrCalendars = [];

        if (null !== $objCalendar) {
            while ($objCalendar->next()) {
                if ($objCalendar->protected && !$this->securityHelper->isGranted(ContaoCorePermissions::MEMBER_IN_GROUPS, StringUtil::deserialize($objCalendar->groups, true))) {
                    continue;
                }

                $arrCalendars[] = $objCalendar->id;
            }
        }

        return $arrCalendars;
    }
}
