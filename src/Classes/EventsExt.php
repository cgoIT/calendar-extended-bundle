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

/**
 * Namespace.
 */

namespace Cgoit\CalendarExtendedBundle\Classes;

use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Date;
use Contao\Events;
use Contao\Model;
use Contao\Model\Collection;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;

/**
 * Class EventExt.
 *
 * @property array<mixed> $filter_fields
 * @property bool         $showOnlyNext
 * @property bool         $show_holiday
 * @property array<mixed> $cal_calendar
 * @property array<mixed> $cal_holiday
 * @property string       $cal_ctemplate
 * @property int          $cal_startDay
 * @property string       $cal_format
 * @property string       $cal_order
 * @property bool         $cal_showQuantity
 * @property string       $cal_template
 * @property string       $com_template
 * @property int          $cal_readerModule
 * @property bool         $cal_ignoreDynamic
 * @property string       $cal_format_ext
 * @property array<mixed> $range_date
 * @property bool         $showRecurrences
 * @property bool         $pubTimeRecurrences
 * @property string       $displayDuration
 * @property bool         $hide_started
 * @property int          $cal_limit
 * @property bool         $fc_editable
 * @property bool         $businessHours
 * @property bool         $weekNumbers
 * @property bool         $weekNumbersWithinDays
 * @property int          $eventLimit
 * @property bool         $cal_times
 * @property bool         $use_navigation
 * @property bool         $linkCurrent
 * @property array<mixed> $cal_times_range
 * @property string       $cellhight
 * @property bool         $showDate
 * @property bool         $hideEmptyDays
 * @property bool         $use_horizontal
 */
abstract class EventsExt extends Events
{
    /**
     * Template.
     *
     * @var string
     */
    protected $strTemplate = '';

    /**
     * @param array<mixed> $arrMonth
     */
    public function __construct(
        private readonly array $arrMonth,
        Collection|Model|ModuleModel $objModule,
        string $strColumn = 'main',
    ) {
        parent::__construct($objModule, $strColumn);
    }

    /**
     * Get all events of a certain period.
     *
     * @phpstan-param array<mixed> $arrCalendars
     *
     * @param array $arrCalendars
     * @param int   $intStart
     * @param int   $intEnd
     * @param bool  $blnFeatured
     *
     * @throws \Exception
     *
     * @phpstan-return array<mixed>
     */
    protected function getAllEvents($arrCalendars, $intStart, $intEnd, $blnFeatured = null): array
    {
        return $this->getAllEventsExt($arrCalendars, $intStart, $intEnd, [null, true], $blnFeatured);
    }

    /**
     * Get all events of a certain period.
     *
     * @phpstan-param array<mixed> $arrCalendars
     *
     * @param array             $arrCalendars
     * @param int               $intStart
     * @param int               $intEnd
     * @param array<mixed>|null $arrParam
     * @param bool              $blnFeatured
     *
     * @return array<mixed>
     *
     * @throws \Exception
     */
    protected function getAllEventsExt($arrCalendars, $intStart, $intEnd, $arrParam = null, $blnFeatured = null): array
    {
        /** @var PageModel $objPage */
        global $objPage;

        // set default values...
        $arrHolidays = null;
        $showRecurrences = true;

        if (!\is_array($arrCalendars)) {
            return [];
        }

        // Include all events of the day, expired events will be filtered out later
        $intStart = strtotime(date('Y-m-d', $intStart).' 00:00:00');

        $this->arrEvents = [];

        if (null !== $arrParam) {
            $arrHolidays = $arrParam[0];

            if (\count($arrParam) > 1) {
                $showRecurrences = $arrParam[1];
            }
        }

        // Used to collect exception list data for events
        $arrEventSkipInfo = [];

        foreach ($arrCalendars as $id) {
            // Get the events of the current period
            $objEvents = CalendarEventsModel::findCurrentByPid($id, $intStart, $intEnd, ['showFeatured' => $blnFeatured]);

            if (null === $objEvents) {
                continue;
            }

            while ($objEvents->next()) {
                $eventRecurrences = (int) $objEvents->recurrences + 1;

                $initStartTime = $objEvents->startTime;
                $initEndTime = $objEvents->endTime;

                $objEvents->pos_idx = 1;
                $objEvents->pos_cnt = 1;

                if ($objEvents->recurring || $objEvents->recurringExt) {
                    if (0 === $objEvents->recurrences) {
                        $objEvents->pos_cnt = 0;
                    } else {
                        $objEvents->pos_cnt = $eventRecurrences;
                    }
                }

                // get the event filter data
                $filter = [];

                if ($this->filter_fields) {
                    $filter_fields = StringUtil::deserialize($this->filter_fields);

                    foreach ($filter_fields as $field) {
                        $filter[$field] = $objEvents->$field;
                    }
                    // filter_data can be used in the template
                    $objEvents->filter_data = json_encode($filter, JSON_FORCE_OBJECT);
                }

                // Count irregular recurrences
                $arrayFixedDates = StringUtil::deserialize($objEvents->repeatFixedDates) ?: null;

                if (null !== $arrayFixedDates) {
                    foreach ($arrayFixedDates as $fixedDate) {
                        if ($fixedDate['new_repeat']) {
                            ++$objEvents->pos_cnt;
                        }
                    }
                }

                // Check if we have to store the event if it's on weekend
                $weekday = (int) date('w', $objEvents->startTime);
                $store = true;

                if ($objEvents->hideOnWeekend) {
                    if (0 === $weekday || 6 === $weekday) {
                        $store = false;
                    }
                }

                // check the repeat values
                $arrRepeat = null;

                if ($objEvents->recurring) {
                    $arrRepeat = StringUtil::deserialize($objEvents->repeatEach) ?: null;
                }

                if ($objEvents->recurringExt) {
                    $arrRepeat = StringUtil::deserialize($objEvents->repeatEachExt) ?: null;
                }

                // we need a counter for the recurrences if noSpan is set
                $cntRecurrences = 0;
                $dateBegin = date('Ymd', $intStart);
                $dateEnd = date('Ymd', $intEnd);
                $dateNextStart = date('Ymd', $objEvents->startTime);
                $dateNextEnd = date('Ymd', $objEvents->endTime);

                // store the entry if everything is fine...
                if (true === $store) {
                    $eventEnd = $objEvents->endTime;

                    $this->addEvent($objEvents, $objEvents->startTime, $eventEnd, $intStart, $intEnd, $id);

                    // increase $cntRecurrences if event is in scope
                    if ($dateNextStart >= $dateBegin && $dateNextEnd <= $dateEnd) {
                        ++$cntRecurrences;
                    }
                }

                // keep the original values
                $orgDateStart = new Date($objEvents->startTime);
                $orgDateEnd = new Date($objEvents->endTime);
                $orgDateSpan = Calendar::calculateSpan($objEvents->startTime, $objEvents->endTime);

                // keep the css class of the event
                $masterCSSClass = $objEvents->cssClass;

                /*
                 * Recurring events and Ext. Recurring events
                 *
                 * Here we manage the recurrences. We take the repeat option and set the new values
                 * if showRecurrences is false we do not need to go thru all recurring events...
                 */
                if (($objEvents->recurring && $objEvents->repeatEach) || ($objEvents->recurringExt && $objEvents->repeatEachExt) && $showRecurrences) {
                    if (null === $arrRepeat) {
                        continue;
                    }

                    $count = 0;

                    // start and end time of the event
                    $eventStartTime = Date::parse($objPage->timeFormat, $objEvents->startTime);
                    $eventEndTime = Date::parse($objPage->timeFormat, $objEvents->endTime);

                    // now we have to take care about the exception dates to skip
                    if ($objEvents->useExceptions) {
                        $arrEventSkipInfo[$objEvents->id] = StringUtil::deserialize($objEvents->exceptionList);
                    }

                    // get the configured weekdays if any
                    $useWeekdays = ($weekdays = StringUtil::deserialize($objEvents->repeatWeekday)) ? true : false;

                    // time of the next event
                    $nextTime = $objEvents->endTime;

                    while ($nextTime < $intEnd) {
                        ++$objEvents->pos_idx;

                        if (0 === $objEvents->recurrences) {
                            $objEvents->pos_cnt = 0;
                        } else {
                            $objEvents->pos_cnt = $eventRecurrences;
                        }

                        if ($objEvents->recurrences > 0 && $count++ >= $objEvents->recurrences) {
                            break;
                        }

                        $arg = $arrRepeat['value'];
                        $unit = $arrRepeat['unit'];

                        $addmonth = true;

                        if ($objEvents->recurring) {
                            // this is the contao default
                            $strtotime = '+ '.$arg.' '.$unit;
                            $objEvents->startTime = strtotime($strtotime, $objEvents->startTime);
                            $objEvents->endTime = strtotime($strtotime, $objEvents->endTime);
                        } else {
                            // extended version.
                            $intyear = (int) date('Y', $objEvents->startTime);
                            $intmonth = (int) date('n', $objEvents->startTime) + 1;

                            $year = 13 === $intmonth ? $intyear + 1 : $intyear;
                            $month = 13 === $intmonth ? 1 : $intmonth;

                            $strtotime = $arg.' '.$unit.' of '.$this->arrMonth[$month].' '.$year;
                            $startTime = strtotime($strtotime.' '.$eventStartTime, $objEvents->startTime);
                            $endTime = strtotime($strtotime.' '.$eventEndTime, $objEvents->endTime);

                            $chkmonth = (int) date('n', $startTime);

                            if ($chkmonth !== $month) {
                                $addmonth = false;
                                $strtotime = 'first day of '.$this->arrMonth[$month].' '.$year;
                                $objEvents->startTime = strtotime($strtotime.' '.$eventStartTime, $startTime);
                                $objEvents->endTime = strtotime($strtotime.' '.$eventEndTime, $endTime);
                            } else {
                                $objEvents->startTime = $startTime;
                                $objEvents->endTime = $endTime;
                            }
                        }
                        $nextTime = $objEvents->endTime;

                        // check if we have the correct weekday
                        if ($useWeekdays && 'days' === $unit) {
                            if (!\in_array(date('w', $nextTime), $weekdays, true)) {
                                continue;
                            }
                        }

                        $oldDate = null;
                        // check if there is any exception
                        if (\array_key_exists($objEvents->id, $arrEventSkipInfo) && \is_array($arrEventSkipInfo[$objEvents->id])) {
                            // modify the css class of the exceptions
                            $objEvents->cssClass = $masterCSSClass;
                            $objEvents->moveReason = null;

                            // date to search for
                            $findDate = $objEvents->startTime;
                            //  $s = strtotime(date("d.m.Y", $objEvents->startTime)); $searchDate = mktime(0,
                            // 0, 0, date('m', $s), date('d', $s), date('Y', $s)); store old date values for
                            // later reset
                            $oldDate = [];

                            if (\array_key_exists($findDate, $arrEventSkipInfo[$objEvents->id]) && \is_array($arrEventSkipInfo[$objEvents->id][$findDate])) {
                                // $r = $searchDate;
                                $r = $findDate;
                                $action = $arrEventSkipInfo[$objEvents->id][$r]['action'];
                                $cssClass = $arrEventSkipInfo[$objEvents->id][$r]['cssclass'];
                                $objEvents->cssClass .= $cssClass ? $cssClass.' ' : '';

                                if ('hide' === $action) {
                                    // continue the while since we don't want to show the event
                                    continue;
                                }

                                if ('move' === $action) {
                                    // just add the css class to the event
                                    $objEvents->cssClass .= 'moved';

                                    // keep old date. we have to reset it later for the next recurrence
                                    $oldDate['startTime'] = $objEvents->startTime;
                                    $oldDate['endTime'] = $objEvents->endTime;

                                    // also keep the old values in the row
                                    $objEvents->oldDate = Date::parse($objPage->dateFormat, $objEvents->startTime);

                                    // value to add to the old date
                                    $newDate = $arrEventSkipInfo[$objEvents->id][$r]['new_exception'];

                                    // store the reason for the move
                                    $objEvents->moveReason = $arrEventSkipInfo[$objEvents->id][$r]['reason'];

                                    // check if we have to change the time of the event
                                    if ($arrEventSkipInfo[$objEvents->id][$r]['new_start']) {
                                        $objEvents->oldStartTime = Date::parse($objPage->timeFormat, $objEvents->startTime);
                                        $objEvents->oldEndTime = Date::parse($objPage->timeFormat, $objEvents->endTime);

                                        // get the date of the event and add the new time to the new date
                                        $newStart = Date::parse($objPage->dateFormat, $objEvents->startTime)
                                            .' '.$arrEventSkipInfo[$objEvents->id][$r]['new_start'];
                                        $newEnd = Date::parse($objPage->dateFormat, $objEvents->endTime)
                                            .' '.$arrEventSkipInfo[$objEvents->id][$r]['new_end'];

                                        // set the new values
                                        $objEvents->startTime = strtotime((string) $newDate, strtotime($newStart));
                                        $objEvents->endTime = strtotime((string) $newDate, strtotime($newEnd));
                                    } else {
                                        $objEvents->startTime = strtotime((string) $newDate, $objEvents->startTime);
                                        $objEvents->endTime = strtotime((string) $newDate, $objEvents->endTime);
                                    }
                                }
                            }
                        }

                        // Skip events outside the scope
                        if ($objEvents->endTime < $intStart || $objEvents->startTime > $intEnd) {
                            // in case of a move we have to reset the original date
                            if ($oldDate) {
                                $objEvents->startTime = $oldDate['startTime'];
                                $objEvents->endTime = $oldDate['endTime'];
                            }
                            // reset this values...
                            $objEvents->moveReason = null;
                            $objEvents->oldDate = null;
                            $objEvents->oldStartTime = null;
                            $objEvents->oldEndTime = null;
                            continue;
                        }

                        // used for showOnlyNext
                        $dateNextStart = date('Ymd', $objEvents->startTime);
                        $dateNextEnd = date('Ymd', $objEvents->endTime);

                        // stop if we have on event and showOnlyNext is true
                        if ($this->showOnlyNext && $cntRecurrences > 0) {
                            break;
                        }

                        $objEvents->isRecurrence = true;

                        $weekday = date('w', $objEvents->startTime);
                        $store = true;

                        if ($objEvents->hideOnWeekend) {
                            if ('0' === $weekday || '6' === $weekday) {
                                $store = false;
                            }
                        }

                        if (true === $store && true === $addmonth) {
                            $this->addEvent($objEvents, $objEvents->startTime, $objEvents->endTime, $intStart, $intEnd, $id);
                        }

                        // reset this values...
                        $objEvents->moveReason = null;
                        $objEvents->oldDate = null;
                        $objEvents->oldStartTime = null;
                        $objEvents->oldEndTime = null;

                        // in case of a move we have to reset the original date
                        if ($oldDate) {
                            $objEvents->startTime = $oldDate['startTime'];
                            $objEvents->endTime = $oldDate['endTime'];
                        }

                        // increase $cntRecurrences if event is in scope
                        if ($dateNextStart >= $dateBegin && $dateNextEnd <= $dateEnd) {
                            ++$cntRecurrences;
                        }
                    }
                    $objEvents->moveReason = null;
                } // end if recurring...

                /*
                 * next we handle the irregular recurrences
                 *
                 * this is a complete different case
                 */
                if (null !== $arrayFixedDates && $showRecurrences) {
                    foreach ($arrayFixedDates as $fixedDate) {
                        if ($fixedDate['new_repeat']) {
                            // check if we have to stop because of showOnlyNext
                            if ($this->showOnlyNext && $cntRecurrences > 0) {
                                break;
                            }

                            // new start time
                            $strNewDate = $fixedDate['new_repeat'];
                            $strNewTime = !empty($fixedDate['new_start']) ? date('H:i', $fixedDate['new_start']) : $orgDateStart->time;
                            $newDateStart = new Date(strtotime(date('d.m.Y', $strNewDate).' '.$strNewTime), Config::get('datimFormat'));
                            $objEvents->startTime = $newDateStart->tstamp;
                            $dateNextStart = date('Ymd', $objEvents->startTime);

                            // new end time
                            $strNewTime = !empty($fixedDate['new_end']) ? date('H:i', $fixedDate['new_end']) : $orgDateEnd->time;
                            $newDateEnd = new Date(strtotime(date('d.m.Y', $strNewDate).' '.$strNewTime), Config::get('datimFormat'));

                            // use the multi-day span of the event
                            if ($orgDateSpan > 0) {
                                $newDateEnd = new Date(strtotime('+'.$orgDateSpan.' days', $newDateEnd->tstamp), Date::getNumericDatimFormat());
                            }

                            $objEvents->endTime = $newDateEnd->tstamp;
                            $dateNextEnd = date('Ymd', $objEvents->endTime);

                            // set a reason if given...
                            $objEvents->moveReason = $fixedDate['reason'] ?: null;

                            // position of the event
                            ++$objEvents->pos_idx;

                            $this->addEvent($objEvents, $objEvents->startTime, $objEvents->endTime, $intStart, $intEnd, $id);

                            // restore the original values
                            $objEvents->startTime = $orgDateStart->tstamp;
                            $objEvents->endTime = $orgDateEnd->tstamp;

                            // increase $cntRecurrences if event is in scope
                            if ($dateNextStart >= $dateBegin && $dateNextEnd <= $dateEnd) {
                                ++$cntRecurrences;
                            }
                        }
                    }
                    $objEvents->moveReason = null;
                }

                // reset times
                $objEvents->startTime = $initStartTime;
                $objEvents->endTime = $initEndTime;
            }
        }

        if (null !== $arrHolidays) {
            // run through all holiday calendars
            foreach ($arrHolidays as $id) {
                $objAE = $this->Database->prepare('SELECT allowEvents FROM tl_calendar WHERE id = ?')
                    ->limit(1)->execute($id)
                ;
                $allowEvents = 1 === $objAE->allowEvents;

                // Get the events of the current period
                $objEvents = CalendarEventsModel::findCurrentByPid($id, $intStart, $intEnd);

                if (null === $objEvents) {
                    continue;
                }

                while ($objEvents->next()) {
                    // at last we add the free multi-day / holiday or what ever kind of event
                    if (!$this->show_holiday) {
                        $this->addEvent($objEvents, $objEvents->startTime, $objEvents->endTime, $intStart, $intEnd, $id);
                    }

                    /**
                     * Multi-day event first we have to find all free days.
                     */
                    $span = Calendar::calculateSpan($objEvents->startTime, $objEvents->endTime);

                    // unset the first day of the multi-day event
                    $intDate = $objEvents->startTime;
                    $key = date('Ymd', $intDate);
                    // check all events if the calendar allows events on free days
                    if ($this->arrEvents[$key]) { // @phpstan-ignore-line
                        foreach ($this->arrEvents[$key] as $k1 => $events) {
                            foreach ($events as $k2 => $event) {
                                // do not remove events from any holiday calendar
                                $isHolidayEvent = \in_array($event['pid'], $arrHolidays, true);

                                // unset the event if showOnFreeDay is not set
                                if (false === $allowEvents) {
                                    if (false === $isHolidayEvent) {
                                        unset($this->arrEvents[$key][$k1][$k2]); // @phpstan-ignore-line
                                    }
                                } else {
                                    if (false === $isHolidayEvent && !$event['showOnFreeDay']) {
                                        unset($this->arrEvents[$key][$k1][$k2]); // @phpstan-ignore-line
                                    }
                                }
                            }
                        }
                    }

                    // unset all the other days of the multi-day event
                    for ($i = 1; $i <= $span && $intDate <= $intEnd; ++$i) {
                        $intDate = strtotime('+ 1 day', $intDate);
                        $key = date('Ymd', $intDate);
                        // check all events if the calendar allows events on free days
                        if ($this->arrEvents[$key]) { // @phpstan-ignore-line
                            foreach ($this->arrEvents[$key] as $k1 => $events) {
                                foreach ($events as $k2 => $event) {
                                    // do not remove events from any holiday calendar
                                    $isHolidayEvent = \in_array($event['pid'], $arrHolidays, true);

                                    // unset the event if showOnFreeDay is not set
                                    if (false === $allowEvents) {
                                        if (false === $isHolidayEvent) {
                                            unset($this->arrEvents[$key][$k1][$k2]); // @phpstan-ignore-line
                                        }
                                    } else {
                                        if (false === $isHolidayEvent && !$event['showOnFreeDay']) {
                                            unset($this->arrEvents[$key][$k1][$k2]); // @phpstan-ignore-line
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Sort the array
        if (!empty($this->arrEvents)) {
            foreach (array_keys($this->arrEvents) as $key) {
                ksort($this->arrEvents[$key]);
            }
        }

        // HOOK: modify the result set
        if (isset($GLOBALS['TL_HOOKS']['getAllEvents']) && \is_array($GLOBALS['TL_HOOKS']['getAllEvents'])) {
            foreach ($GLOBALS['TL_HOOKS']['getAllEvents'] as $callback) {
                $this->import($callback[0]);
                $this->arrEvents = $this->{$callback[0]}->{$callback[1]}($this->arrEvents, $arrCalendars, $intStart, $intEnd, $this);
            }
        }

        return $this->arrEvents;
    }
}
