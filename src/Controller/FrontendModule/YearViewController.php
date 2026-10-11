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
 * Front end module "yearview": all days of a year in one table.
 */
#[AsFrontendModule('yearview', category: 'events', template: 'frontend_module/yearview')]
class YearViewController extends AbstractCalendarViewController
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
        $year = $request->query->get('year');

        // Create the date object
        try {
            $timeStamp = \is_string($year) ? strtotime($year.'-01-01') : false;
            $date = false !== $timeStamp ? new Date($timeStamp) : new Date();
        } catch (\OutOfBoundsException) {
            throw new PageNotFoundException('Page not found: '.$request->getUri());
        }

        $time = Date::floorToMinute();

        // Find the boundaries
        [$dateFrom, $dateTo, $repeatUntil] = $this->eventsProvider->findBoundaries($calendars);

        $firstYear = (int) date('Y', min($dateFrom, $time));
        $lastYear = (int) date('Y', max($dateTo, $repeatUntil, $time));

        $intYear = $year ? (int) $year : false;

        // The given year is out of scope
        if ($intYear && ($intYear < $firstYear || $intYear > $lastYear)) {
            throw new PageNotFoundException('Page not found: '.$request->getUri());
        }

        // Get the year of the given date
        $intYear = (int) date('Y', $date->tstamp);

        $variables = [
            'intYear' => $intYear,
            'use_horizontal' => $model->use_horizontal,
            'use_navigation' => $model->use_navigation,
            'linkCurrent' => $model->linkCurrent,
        ];

        // display the navigation if selected
        if ($model->use_navigation) {
            // Get the current year
            if ($model->linkCurrent) {
                $currYear = date('Y', time());

                $variables['currHref'] = $strUrl.'?year='.$currYear;
                $variables['currTitle'] = StringUtil::specialchars($currYear);
                $variables['currLink'] = $GLOBALS['TL_LANG']['MSC']['curr_year'];
                $variables['currLabel'] = $GLOBALS['TL_LANG']['MSC']['cal_previous'];
            }

            // Previous year
            $prevYear = $intYear - 1;
            if ($prevYear >= $firstYear) {
                $lblPrevious = $GLOBALS['TL_LANG']['MSC']['calendar_year'].' '.$prevYear;
                $variables['prevHref'] = $strUrl.'?year='.$prevYear;
                $variables['prevTitle'] = StringUtil::specialchars((string) $prevYear);
                $variables['prevLink'] = $GLOBALS['TL_LANG']['MSC']['cal_previous'].' '.$lblPrevious;
                $variables['prevLabel'] = $GLOBALS['TL_LANG']['MSC']['cal_previous'];
            }

            // Current year
            $variables['current'] = $GLOBALS['TL_LANG']['MSC']['calendar_year'].' '.$intYear;

            // Next year
            $nextYear = $intYear + 1;
            if ($nextYear <= $lastYear) {
                $lblNext = $GLOBALS['TL_LANG']['MSC']['calendar_year'].' '.$nextYear;
                $variables['nextHref'] = $strUrl.'?year='.$nextYear;
                $variables['nextTitle'] = StringUtil::specialchars((string) $nextYear);
                $variables['nextLink'] = $lblNext.' '.$GLOBALS['TL_LANG']['MSC']['cal_next'];
                $variables['nextLabel'] = $GLOBALS['TL_LANG']['MSC']['cal_next'];
            }
        }

        $horizontal = (bool) $model->use_horizontal;

        $variables['months'] = $this->compileMonths();
        $variables['yeardays'] = $this->compileDays($model, $page, $calendars, $intYear, $horizontal);
        $variables['data'] = $this->compileData($variables['months'], $variables['yeardays'], $horizontal);
        $variables['substr'] = $GLOBALS['TL_LANG']['MSC']['dayShortLength'];

        $GLOBALS['TL_CSS'][] = 'bundles/cgoitcalendarextended/css/yearview.css';

        return $this->renderCalendar($template, $model, 'cal_yearview', $variables);
    }

    /**
     * Return the name of the months.
     *
     * @return array<mixed>
     */
    private function compileMonths(): array
    {
        $arrMonths = [];

        for ($m = 0; $m < 12; ++$m) {
            $arrMonths[$m + 1]['label'] = $GLOBALS['TL_LANG']['MONTHS'][$m];
            $arrMonths[$m + 1]['class'] = 'head';
        }

        return $arrMonths;
    }

    /**
     * Return the week days and labels as array.
     *
     * @param array<int> $calendars
     *
     * @return array<mixed>
     *
     * @throws \Exception
     */
    private function compileDays(ModuleModel $model, PageModel $page, array $calendars, int $currYear, bool $horizontal): array
    {
        $arrDays = [];

        // Get all events
        $arrAllEvents = $this->eventsProvider->getAllEvents($model, $calendars, mktime(0, 0, 0, 1, 1, $currYear), mktime(23, 59, 59, 12, 31, $currYear));

        for ($m = 1; $m <= 12; ++$m) {
            for ($d = 1; $d <= 31; ++$d) {
                if (checkdate($m, $d, $currYear)) {
                    $day = mktime(12, 00, 00, $m, $d, $currYear);

                    $intCurrentDay = (int) date('w', $day);

                    $intKey = date('Ymd', strtotime(date('Y-m-d', $day)));
                    $currDay = Date::parse($page->dateFormat, strtotime(date('Y-m-d', $day)));
                    $class = 0 === $intCurrentDay || 6 === $intCurrentDay ? 'weekend' : 'weekday';
                    $class .= 0 === $d % 2 ? ' even' : ' odd';
                    $class .= ' '.strtolower((string) $GLOBALS['TL_LANG']['DAYS'][$intCurrentDay]);

                    if ($currDay === Date::parse($page->dateFormat, strtotime(date('Y-m-d')))) {
                        $class .= ' today';
                    }

                    if ($horizontal) {
                        // in horizontal presentation we have 12 days (e.g. always the first day of the
                        // month) in each row.
                        $key1 = $d;
                        $key2 = $m;
                    } else {
                        // in vertical presentation we have up to 31 days (all the days for a month) in
                        // each row.
                        $key1 = $m;
                        $key2 = $d;
                    }
                    $arrDays[$key1][$key2]['label'] = strtoupper(substr((string) $GLOBALS['TL_LANG']['DAYS'][$intCurrentDay], 0, 2)).' '.$d;
                    $arrDays[$key1][$key2]['weekday'] = strtoupper(substr((string) $GLOBALS['TL_LANG']['DAYS'][$intCurrentDay], 0, 2));
                    $arrDays[$key1][$key2]['day'] = $d;
                    $arrDays[$key1][$key2]['class'] = $class;
                } else {
                    if ($horizontal) {
                        // in horizontal presentation we have 12 days (e.g. always the first day of the
                        // month) in each row.
                        $key1 = $d;
                        $key2 = $m;
                    } else {
                        // in vertical presentation we have up to 31 days (all the days for a month) in
                        // each row.
                        $key1 = $m;
                        $key2 = $d;
                    }
                    $arrDays[$key1][$key2]['label'] = '';
                    $arrDays[$key1][$key2]['weekday'] = '';
                    $arrDays[$key1][$key2]['day'] = '';
                    $arrDays[$key1][$key2]['class'] = 'empty';

                    $intKey = 'empty';
                }

                // Get all events of a day
                $arrEvents = [];

                if (\array_key_exists($intKey, $arrAllEvents) && \is_array($arrAllEvents[$intKey])) {
                    foreach ($arrAllEvents[$intKey] as $v) {
                        if (!empty($v) && \is_array($v)) {
                            foreach ($v as &$vv) {
                                // set class recurring
                                if ($vv['recurring'] || $vv['recurringExt']) {
                                    $vv['class'] .= ' recurring';
                                }
                            }
                            $arrEvents = $v;
                        }
                    }
                }

                if ($horizontal) {
                    $arrDays[$d][$m]['events'] = $arrEvents;
                } else {
                    $arrDays[$m][$d]['events'] = $arrEvents;
                }
            }
        }

        return $arrDays;
    }

    /**
     * @param array<mixed> $months
     * @param array<mixed> $yeardays
     *
     * @return array<mixed>
     */
    private function compileData(array $months, array $yeardays, bool $horizontal): array
    {
        $data = [];
        $header = [];
        $body = [];

        if ($horizontal) {
            // months in columns, days in rows
            foreach ($months as $month) {
                $month['colspan'] = 2;
                $header[] = $month;
            }

            foreach (range(1, 31) as $day) {
                $row = [];

                foreach (range(1, 12) as $month) {
                    $m = $yeardays[$day][$month];
                    $m['class'] .= ' nowrap top';
                    unset($m['events']);
                    $row[] = $m;

                    $row[] = $yeardays[$day][$month]['events'];
                }
                $body[] = $row;
            }
        } else {
            // days in columns, months in rows
            $header[] = ['label' => '', 'class' => 'head'];

            foreach (range(1, 31) as $day) {
                $header[] = ['label' => $day, 'class' => 'head'];
            }

            foreach (range(1, 12) as $month) {
                $row = [];

                $m = $months[$month];
                $m['rowspan'] = 2;
                $row[] = $m;

                foreach (range(1, 31) as $day) {
                    $col = $yeardays[$month][$day];
                    $col['class'] .= ' nowrap';
                    $row[] = $col;
                }
                $body[] = $row;

                $row = [];

                foreach (range(1, 31) as $day) {
                    $row[] = $yeardays[$month][$day]['events'];
                }
                $body[] = $row;
            }
        }

        $data['header'] = $header;
        $data['body'] = $body;

        return $data;
    }
}
