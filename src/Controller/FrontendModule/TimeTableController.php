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

namespace Cgoit\CalendarExtendedBundle\Controller\FrontendModule;

use Cgoit\CalendarExtendedBundle\Classes\Utils;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Date;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Front end module "timetable": a week view with a time grid.
 */
#[AsFrontendModule('timetable', category: 'events', template: 'frontend_module/timetable')]
class TimeTableController extends AbstractCalendarViewController
{
    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $calendars = $this->getCalendars($model);

        if (null === $calendars) {
            return new Response();
        }

        $page = $this->getPageModel();

        if (!$page instanceof PageModel) {
            return new Response();
        }

        $strUrl = $this->getBaseUrl($request);
        $month = $request->query->get('month');
        $week = $request->query->get('week');
        $day = $request->query->get('day');

        // Create the date object
        try {
            if (\is_string($month) && is_numeric($month)) {
                $date = new Date((int) $month, 'Ym');
            } elseif (\is_string($week) && is_numeric($week)) {
                $date = $this->getWeekBeginAndEnd($week)[0];
            } elseif (\is_string($day) && is_numeric($day)) {
                $date = new Date((int) $day, 'Ymd');
            } else {
                $date = new Date();
            }
        } catch (\OutOfBoundsException) {
            throw new PageNotFoundException('Page not found: '.$request->getUri());
        }

        // Get the Year and the week of the given date
        $intYear = (int) date('o', $date->tstamp);
        $intWeek = (int) date('W', $date->tstamp);

        [$weekBegin, $weekEnd] = $this->getWeekStartAndEnd($intYear, $intWeek);

        // Get total count of weeks of the year
        $weeksTotal = date('W', mktime(0, 0, 0, 12, 31, $intYear));
        if ('1' === $weeksTotal) {
            $weeksTotal = date('W', mktime(0, 0, 0, 12, 24, $intYear));
        }

        $time = Date::floorToMinute();

        // Find the boundaries
        [$dateFrom, $dateTo, $repeatUntil] = $this->eventsProvider->findBoundaries($calendars);

        $firstWeek = $this->getWeekBeginAndEnd(date('YW', min($dateFrom, $time)))[0];
        $lastWeek = $this->getWeekBeginAndEnd(date('YW', max($dateTo, $repeatUntil, $time)))[1];

        // The given week is out of scope
        if (\is_string($week) && is_numeric($week)) {
            [$selectedBegin, $selectedEnd] = $this->getWeekBeginAndEnd($week);
            if ($selectedBegin->tstamp < $firstWeek->tstamp || $selectedEnd->tstamp > $lastWeek->tstamp) {
                throw new PageNotFoundException('Page not found: '.$request->getUri());
            }
        }

        // The given day is out of scope
        $intDay = \is_string($day) && is_numeric($day) ? (int) $day : false;
        if ($intDay && ($intDay < (int) date('Ymd', min($dateFrom, $time)) || $intDay > (int) date('Ymd', max($dateTo, $repeatUntil, $time)))) {
            throw new PageNotFoundException('Page not found: '.$request->getUri());
        }

        $startDay = (int) $model->cal_startDay;

        $variables = [
            'intYear' => $intYear,
            'intWeek' => $intWeek,
            'weekBegin' => $weekBegin,
            'weekEnd' => $weekEnd,
            'cal_times' => $model->cal_times,
            'use_navigation' => $model->use_navigation,
            'linkCurrent' => $model->linkCurrent,
            'timeLabel' => $GLOBALS['TL_LANG']['MSC']['cal_time'],
        ];

        // display the navigation if selected
        if ($model->use_navigation) {
            // Get the current year and the week
            if ($model->linkCurrent) {
                $lblCurrent = $GLOBALS['TL_LANG']['MSC']['curr_week'];
                $variables['currHref'] = $strUrl.'?week='.date('o').str_pad((string) (int) date('W'), 2, '0', STR_PAD_LEFT);
                $variables['currTitle'] = StringUtil::specialchars($lblCurrent);
                $variables['currLink'] = $lblCurrent;
                $variables['currLabel'] = $GLOBALS['TL_LANG']['MSC']['cal_previous'];
            }

            // Previous week
            $prevWeek = str_pad((string) (1 === $intWeek ? $weeksTotal : $intWeek - 1), 2, '0', STR_PAD_LEFT);
            $prevYear = 1 === $intWeek ? $intYear - 1 : $intYear;

            $prevWeekEnd = $this->getWeekBeginAndEnd($prevYear.$prevWeek)[1];
            if ($prevWeekEnd->tstamp > $firstWeek->tstamp) {
                $lblPrevious = $GLOBALS['TL_LANG']['MSC']['calendar_week'].' '.$prevWeek.'/'.$prevYear;
                $variables['prevHref'] = $strUrl.'?week='.$prevYear.$prevWeek;
                $variables['prevTitle'] = StringUtil::specialchars($lblPrevious);
                $variables['prevLink'] = $GLOBALS['TL_LANG']['MSC']['cal_previous'].' '.$lblPrevious;
                $variables['prevLabel'] = $GLOBALS['TL_LANG']['MSC']['cal_previous'];
            }

            $variables['current'] = $GLOBALS['TL_LANG']['MSC']['calendar_week'].' '.$intWeek.' '.$intYear;

            // Next week
            $nextWeek = str_pad((string) ($intWeek === (int) $weeksTotal ? 1 : $intWeek + 1), 2, '0', STR_PAD_LEFT);
            $nextYear = $intWeek === (int) $weeksTotal ? $intYear + 1 : $intYear;

            $nextWeekBegin = $this->getWeekBeginAndEnd($nextYear.$nextWeek)[0];
            if ($nextWeekBegin->tstamp < $lastWeek->tstamp) {
                $lblNext = $GLOBALS['TL_LANG']['MSC']['calendar_week'].' '.$nextWeek.'/'.$nextYear;
                $variables['nextHref'] = $strUrl.'?week='.$nextYear.$nextWeek;
                $variables['nextTitle'] = StringUtil::specialchars($lblNext);
                $variables['nextLink'] = $lblNext.' '.$GLOBALS['TL_LANG']['MSC']['cal_next'];
                $variables['nextLabel'] = $GLOBALS['TL_LANG']['MSC']['cal_next'];
            }
        }

        [$variables['weekday'], $variables['times']] = $this->compileDays($model, $page, $calendars, $weekBegin, $weekEnd, $startDay);

        $GLOBALS['TL_CSS'][] = 'bundles/cgoitcalendarextended/css/timetable.css';

        return $this->renderCalendar($template, $model, 'cal_timetable', $variables);
    }

    /**
     * Return the week days and labels as array.
     *
     * @param array<int> $calendars
     *
     * @return array{array<mixed>, array<mixed>}
     *
     * @throws \Exception
     */
    private function compileDays(ModuleModel $model, PageModel $page, array $calendars, int $weekBegin, int $weekEnd, int $startDay): array
    {
        $arrDays = [];

        // if we start on Sunday we have to go back one day
        if (0 === $startDay) {
            $weekBegin = strtotime(date('Y-m-d', $weekBegin).' -1 day');
        }

        // Get all events
        $arrAllEvents = $this->eventsProvider->getAllEvents($model, $calendars, $weekBegin, $weekEnd);

        // we create the array of times
        if ($model->cal_times) {
            $arrTimes = [];
            $arrTimes['start'] = '23:00';
            $arrTimes['stop'] = '00:00';

            for ($i = 0; $i < 7; ++$i) {
                $intKey = date('Ymd', strtotime(date('Y-m-d', $weekBegin)." +$i day"));

                // Here we have to get the valid times
                if (\array_key_exists($intKey, $arrAllEvents) && \is_array($arrAllEvents[$intKey])) {
                    // Here we have to get the valid times
                    foreach ($arrAllEvents[$intKey] as $v) {
                        foreach ($v as $vv) {
                            // set the times for the timetable
                            if (date('H:i', $vv['startTime']) < $arrTimes['start']) {
                                $arrTimes['start'] = date('H:00', $vv['startTime']);
                            }

                            if (date('H:i', $vv['endTime']) > $arrTimes['stop']) {
                                $h = date('H', $vv['endTime']);
                                $m = date('i', $vv['endTime']);

                                if ($m > 0) {
                                    ++$h;
                                }
                                $arrTimes['stop'] = "$h:00";
                            }
                        }
                    }
                }
            }
            $arrTimes['start'] = substr($arrTimes['start'], 0, 2);
            $arrTimes['stop'] = substr($arrTimes['stop'], 0, 2);

            $timerange = StringUtil::deserialize($model->cal_times_range)[0];

            if ($timerange['time_from']) {
                $arrTimes['start'] = substr(date('H', $timerange['time_from']), 0, 2);
            }

            if ($timerange['time_to']) {
                $arrTimes['stop'] = substr(date('H', $timerange['time_to']), 0, 2);
            }

            $cellheight = $model->cellheight ?: 60;

            $arrListTimes = [];
            $counter = 0;

            for ($i = $arrTimes['start']; $i <= $arrTimes['stop']; ++$i) {
                $top = $cellheight * $counter;
                $strHour = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                $arrListTimes[$strHour]['top'] = $top;
                $arrListTimes[$strHour]['class'] = 0 === $counter % 2 ? 'even' : 'odd';
                $arrListTimes[$strHour]['label'] = "$strHour:00"; // top:".$top."px; position:relative;
                $arrListTimes[$strHour]['style'] = 'height:'.$cellheight.'px;top:'.$top.'px;';
                ++$counter;
            }
        }

        for ($i = 0; $i < 7; ++$i) {
            $intCurrentDay = ($i + $startDay) % 7;

            $intKey = date('Ymd', strtotime(date('Y-m-d', $weekBegin)." +$i day"));
            $currDay = Date::parse($page->dateFormat, strtotime(date('Y-m-d', $weekBegin)." +$i day"));

            $class = 0 === $intCurrentDay || 6 === $intCurrentDay ? 'weekend' : 'weekday';
            $class .= 0 === $intCurrentDay % 2 ? ' even' : ' odd';
            $class .= ' '.strtolower((string) $GLOBALS['TL_LANG']['DAYS'][$intCurrentDay]);
            $class .= 0 === $intCurrentDay ? ' last' : '';

            if ($currDay === Date::parse($page->dateFormat, strtotime(date('Y-m-d')))) {
                $class .= ' today';
            }

            $arrDays[$intCurrentDay]['label'] = $GLOBALS['TL_LANG']['DAYS'][$intCurrentDay];
            $arrDays[$intCurrentDay]['label_day'] = $GLOBALS['TL_LANG']['DAYS'][$intCurrentDay];
            $arrDays[$intCurrentDay]['label_date'] = $currDay;

            if ($model->showDate) {
                $arrDays[$intCurrentDay]['label'] .= '<br/>'.$currDay;
            }
            $arrDays[$intCurrentDay]['class'] = $class;

            // Get all events of a day
            $arrEvents = [];

            if (\array_key_exists($intKey, $arrAllEvents) && \is_array($arrAllEvents[$intKey])) {
                foreach ($arrAllEvents[$intKey] as $v) {
                    foreach ($v as $vv) {
                        // set class recurring
                        if ($vv['recurring'] || $vv['recurringExt']) {
                            $vv['class'] .= ' recurring';
                        }

                        // calculate the position of the event
                        $h = date('H', $vv['startTime']);
                        $m = date('i', $vv['startTime']);

                        // @phpstan-ignore-next-line
                        if (isset($arrListTimes) && \is_array($arrListTimes[$h]) && isset($cellheight)) {
                            // calculate the top of the event
                            $top = $arrListTimes[$h]['top'] + $m * $cellheight / 60;

                            // calculate the height of the event.
                            $d1 = date_create(date('H:i', $vv['startTime']));
                            $d2 = date_create(date('H:i', $vv['endTime']));
                            $d0 = date_diff($d1, $d2);
                            $height = ((int) $d0->format('%h') * $cellheight) + (int) $d0->format('%i') * $cellheight / 60;

                            Utils::appendToArrayKey($vv, 'style', 'position:absolute;top:'.$top.'px;height:'.$height.'px;');
                        }

                        $arrEvents[] = $vv;
                    }
                }
                $arrDays[$intCurrentDay]['events'] = $arrEvents;
            } else {
                $arrDays[$intCurrentDay]['events'] = $arrEvents;

                // Remove day from array if the is no event
                if ($model->hideEmptyDays) {
                    unset($arrDays[$intCurrentDay]);
                }
            }
        }

        return [$arrDays, $arrListTimes ?? []];
    }

    /**
     * @return array{int, int} first and last second of the given week
     */
    private function getWeekStartAndEnd(int $intYear, int $intWeek): array
    {
        $dt = new \DateTime();

        // Set date to the first day of the given week
        $dt->setISODate($intYear, $intWeek);
        $newDate = new Date((int) $dt->format('Ymd'), 'Ymd');
        $weekBegin = mktime(0, 0, 0, (int) date('m', $newDate->tstamp), (int) date('d', $newDate->tstamp), (int) date('Y', $newDate->tstamp));

        // Set date to the last day of the given week
        $dt->setISODate($intYear, $intWeek, 7);
        $newDate = new Date((int) $dt->format('Ymd'), 'Ymd');
        $weekEnd = mktime(23, 59, 59, (int) date('m', $newDate->tstamp), (int) date('d', $newDate->tstamp), (int) date('Y', $newDate->tstamp));

        return [$weekBegin, $weekEnd];
    }

    /**
     * @return array<Date>
     */
    private function getWeekBeginAndEnd(string $week): array
    {
        $selYear = (int) substr($week, 0, 4);
        $selWeek = (int) substr($week, -2);
        $selDay = 1 === $selWeek ? 4 : 1;
        $dt = new \DateTime();

        $dt->setISODate($selYear, $selWeek, $selDay);
        $weekBegin = new Date((int) $dt->format('Ymd'), 'Ymd');

        $dt->setISODate($selYear, $selWeek, 7);
        $weekEnd = new Date((int) $dt->format('Ymd'), 'Ymd');

        return [$weekBegin, $weekEnd];
    }
}
