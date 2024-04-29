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

namespace Cgoit\CalendarExtendedBundle\Controller\Module;

use Contao\BackendTemplate;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\Date;
use Contao\Environment;
use Contao\Events;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;

/**
 * Class ModuleYearViewExt.
 */
class ModuleYearView extends Events
{
    /**
     * Current date object.
     *
     * @var Date
     */
    protected $Date;

    /**
     * @var int
     */
    protected $yearBegin;

    /**
     * @var int
     */
    protected $yearEnd;

    /**
     * Redirect URL.
     *
     * @var string
     */
    protected $strLink;

    /**
     * Template.
     *
     * @var string
     */
    protected $strTemplate = 'mod_calendar';

    /**
     * Do not show the module if no calendar has been selected.
     *
     * @return string
     *
     * @throws \Exception
     */
    public function generate()
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
            /** @var BackendTemplate|object $objTemplate */
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### '.$GLOBALS['TL_LANG']['FMD']['yearview'][0].' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = StringUtil::specialcharsUrl(System::getContainer()->get('router')->generate('contao_backend', ['do' => 'themes', 'table' => 'tl_module', 'act' => 'edit', 'id' => $this->id]));

            return $objTemplate->parse();
        }

        $this->cal_calendar = $this->sortOutProtected(StringUtil::deserialize($this->cal_calendar, true));

        // Return if there are no calendars
        if (empty($this->cal_calendar) || !\is_array($this->cal_calendar)) {
            return '';
        }

        $this->strUrl = preg_replace('/\?.*$/', '', (string) Environment::get('request'));
        $this->strLink = $this->strUrl;

        if (($objTarget = $this->objModel->getRelated('jumpTo')) instanceof PageModel) {
            /** @var PageModel $objTarget */
            $this->strLink = $objTarget->getFrontendUrl();
        }

        // Tag the calendars (see #2137)
        if (System::getContainer()->has('fos_http_cache.http.symfony_response_tagger')) {
            $responseTagger = System::getContainer()->get('fos_http_cache.http.symfony_response_tagger');
            $responseTagger->addTags(array_map(static fn ($id) => 'contao.db.tl_calendar.'.$id, $this->cal_calendar));
        }

        return parent::generate();
    }

    /**
     * Generate the module.
     */
    protected function compile(): void
    {
        $year = Input::get('year');

        // Create the date object
        try {
            $timeStamp = strtotime($year.'-01-01');
            if (\is_string($year) && false !== $timeStamp) {
                $this->Date = new Date($timeStamp);
            } else {
                $this->Date = new Date();
            }
        } catch (\OutOfBoundsException) {
            throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
        }

        $time = Date::floorToMinute();

        // Find the boundaries
        $blnShowUnpublished = System::getContainer()->get('contao.security.token_checker')->isPreviewMode();
        $objMinMax = $this->Database->query('SELECT MIN(startTime) AS dateFrom, MAX(endTime) AS dateTo, MAX(repeatEnd) AS repeatUntil FROM tl_calendar_events WHERE pid IN('.implode(',', array_map('\intval', $this->cal_calendar)).')'.(!$blnShowUnpublished ? " AND published='1' AND (start='' OR start<=$time) AND (stop='' OR stop>$time)" : ''));
        $dateFrom = $objMinMax->dateFrom;
        $dateTo = $objMinMax->dateTo;
        $repeatUntil = $objMinMax->repeatUntil;

        if (isset($GLOBALS['TL_HOOKS']['findCalendarBoundaries']) && \is_array($GLOBALS['TL_HOOKS']['findCalendarBoundaries'])) {
            foreach ($GLOBALS['TL_HOOKS']['findCalendarBoundaries'] as $callback) {
                $this->import($callback[0]);
                $this->{$callback[0]}->{$callback[1]}($dateFrom, $dateTo, $repeatUntil, $this);
            }
        }

        $firstYear = date('Y', min($dateFrom, $time));
        $lastYear = date('Y', max($dateTo, $repeatUntil, $time));

        // The given year is out of scope
        if ($year && ($year < $firstYear || $year > $lastYear)) {
            throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
        }

        // Get the Year and the week of the given date
        $intYear = (int) date('Y', $this->Date->tstamp);
        $this->yearBegin = mktime(0, 0, 0, 1, 1, $intYear);
        $this->yearEnd = mktime(23, 59, 59, 12, 31, $intYear);

        $objTemplate = new FrontendTemplate($this->cal_ctemplate ?: 'cal_yearview');

        $objTemplate->intYear = $intYear;
        $objTemplate->use_horizontal = $this->use_horizontal;
        $objTemplate->use_navigation = $this->use_navigation;
        $objTemplate->linkCurrent = $this->linkCurrent;

        // display the navigation if selected
        if ($this->use_navigation) {
            // Get the current year and the week
            if ($this->linkCurrent) {
                $currYear = date('Y', time());

                $objTemplate->currHref = $this->strUrl.'?year='.$currYear;
                $objTemplate->currTitle = StringUtil::specialchars($currYear);
                $objTemplate->currLink = $GLOBALS['TL_LANG']['MSC']['curr_year'];
                $objTemplate->currLabel = $GLOBALS['TL_LANG']['MSC']['cal_previous'];
            }

            // Previous year
            $prevYear = $intYear - 1;
            if ($prevYear >= $firstYear) {
                $lblPrevious = $GLOBALS['TL_LANG']['MSC']['calendar_year'].' '.$prevYear;
                $objTemplate->prevHref = $this->strUrl.'?year='.$prevYear;
                $objTemplate->prevTitle = StringUtil::specialchars((string) $prevYear);
                $objTemplate->prevLink = $GLOBALS['TL_LANG']['MSC']['cal_previous'].' '.$lblPrevious;
                $objTemplate->prevLabel = $GLOBALS['TL_LANG']['MSC']['cal_previous'];
            }

            // Current year
            $objTemplate->current = $GLOBALS['TL_LANG']['MSC']['calendar_year'].' '.$intYear;

            // Next year
            $nextYear = $intYear + 1;
            if ($nextYear <= $lastYear) {
                $lblNext = $GLOBALS['TL_LANG']['MSC']['calendar_year'].' '.$nextYear;
                $objTemplate->nextHref = $this->strUrl.'?year='.$nextYear;
                $objTemplate->nextTitle = StringUtil::specialchars((string) $nextYear);
                $objTemplate->nextLink = $lblNext.' '.$GLOBALS['TL_LANG']['MSC']['cal_next'];
                $objTemplate->nextLabel = $GLOBALS['TL_LANG']['MSC']['cal_next'];
            }
        }

        // Set week start day
        if (!$this->cal_startDay) {
            $this->cal_startDay = 0;
        }

        $objTemplate->months = $this->compileMonths();
        $objTemplate->yeardays = $this->compileDays($intYear);
        $objTemplate->data = $this->compileData($objTemplate->months, $objTemplate->yeardays);
        $objTemplate->substr = $GLOBALS['TL_LANG']['MSC']['dayShortLength'];

        $this->Template->calendar = $objTemplate->parse();
    }

    /**
     * Return the name of the months.
     *
     * @return array<mixed>
     */
    protected function compileMonths()
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
     * @return array<mixed>
     *
     * @throws \Exception
     */
    protected function compileDays(int $currYear)
    {
        /** @var PageModel */
        global $objPage;

        $arrDays = [];

        // Get all events
        $arrAllEvents = $this->getAllEvents($this->cal_calendar, $this->yearBegin, $this->yearEnd);

        for ($m = 1; $m <= 12; ++$m) {
            for ($d = 1; $d <= 31; ++$d) {
                if (checkdate($m, $d, $currYear)) {
                    $day = mktime(12, 00, 00, $m, $d, $currYear);

                    $intCurrentDay = (int) date('w', $day);

                    $intKey = date('Ymd', strtotime(date('Y-m-d', $day)));
                    $currDay = Date::parse($objPage->dateFormat, strtotime(date('Y-m-d', $day)));
                    $class = 0 === $intCurrentDay || 6 === $intCurrentDay ? 'weekend' : 'weekday';
                    $class .= 0 === $d % 2 ? ' even' : ' odd';
                    $class .= ' '.strtolower((string) $GLOBALS['TL_LANG']['DAYS'][$intCurrentDay]);

                    if ($currDay === Date::parse($objPage->dateFormat, strtotime(date('Y-m-d')))) {
                        $class .= ' today';
                    }

                    if ($this->use_horizontal) {
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
                    if ($this->use_horizontal) {
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

                if ($this->use_horizontal) {
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
    private function compileData(array $months, array $yeardays): array
    {
        $data = [];
        $header = [];
        $body = [];

        if (!empty($this->use_horizontal)) {
            // months in columns, days in rows
            foreach ($months as $month) {
                $month['attr'] = 'colspan="2"';
                $header[] = $month;
            }

            foreach (range(1, 31) as $day) {
                $row = [];

                foreach (range(1, 12) as $month) {
                    $m = $yeardays[$day][$month];
                    $m['attr'] = 'style="vertical-align: top; text-wrap: nowrap;"';
                    unset($m['events']);
                    $row[] = $m;

                    $row[] = $yeardays[$day][$month]['events'];
                }
                $body[] = $row;
            }
        } else {
            // days in columns, months in rows
            $header[] = ['label' => '', 'class' => 'head', 'attr' => ''];

            foreach (range(1, 31) as $day) {
                $header[] = ['label' => $day, 'class' => 'head', 'attr' => ''];
            }

            foreach (range(1, 12) as $month) {
                $row = [];

                $m = $months[$month];
                $m['attr'] = 'rowspan="2"';
                $row[] = $m;

                foreach (range(1, 31) as $day) {
                    $col = $yeardays[$month][$day];
                    $col['attr'] = 'style="text-wrap: nowrap;"';
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
