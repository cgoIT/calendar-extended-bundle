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

namespace Cgoit\CalendarExtendedBundle\Controller\Module;

use Cgoit\CalendarExtendedBundle\Classes\Utils;
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
 * Class ModuleTimeTableExt.
 */
class ModuleTimeTable extends Events
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
    protected $weekBegin;

    /**
     * @var int
     */
    protected $weekEnd;

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
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### '.$GLOBALS['TL_LANG']['FMD']['timetable'][0].' ###';
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
     * Generate module.
     */
    protected function compile(): void
    {
        $month = Input::get('month');
        $week = Input::get('week');
        $day = Input::get('day');

        // Create the date object
        try {
            if (\is_string($month) && is_numeric($month)) {
                $this->Date = new Date((int) $month, 'Ym');
            } elseif (\is_string($week) && is_numeric($week)) {
                $this->Date = $this->getWeekBeginAndEnd($week)[0];
            } elseif (\is_string($day) && is_numeric($day)) {
                $this->Date = new Date((int) $day, 'Ymd');
            } else {
                $this->Date = new Date();
            }
        } catch (\OutOfBoundsException) {
            throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
        }

        // Get the Year and the week of the given date
        $intYear = (int) date('o', $this->Date->tstamp);
        $intWeek = (int) date('W', $this->Date->tstamp);

        $this->setWeekStartAndEnd($intYear, $intWeek);

        // Get total count of weeks of the year
        $weeksTotal = date('W', mktime(0, 0, 0, 12, 31, $intYear));
        if ('1' === $weeksTotal) {
            $weeksTotal = date('W', mktime(0, 0, 0, 12, 24, $intYear));
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

        $firstWeek = $this->getWeekBeginAndEnd(date('YW', min($dateFrom, $time)))[0];
        $lastWeek = $this->getWeekBeginAndEnd(date('YW', max($dateTo, $repeatUntil, $time)))[1];

        // The given month is out of scope
        if ($week) {
            [$weekBegin, $weekEnd] = $this->getWeekBeginAndEnd($week);
            if ($weekBegin->tstamp < $firstWeek->tstamp || $weekEnd->tstamp > $lastWeek->tstamp) {
                throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
            }
        }

        // The given day is out of scope
        if ($day && ($day < date('Ymd', min($dateFrom, $time)) || $day > date('Ymd', max($dateTo, $repeatUntil, $time)))) {
            throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
        }

        /** @var FrontendTemplate|object $objTemplate */
        $objTemplate = new FrontendTemplate($this->cal_ctemplate ?: 'cal_timetable');

        $objTemplate->intYear = $intYear;
        $objTemplate->intWeek = $intWeek;
        $objTemplate->weekBegin = $this->weekBegin;
        $objTemplate->weekEnd = $this->weekEnd;

        $objTemplate->cal_times = $this->cal_times;
        $objTemplate->use_navigation = $this->use_navigation;
        $objTemplate->linkCurrent = $this->linkCurrent;

        // display the navigation if selected
        if ($this->use_navigation) {
            // Get the current year and the week
            if ($this->linkCurrent) {
                $currYear = date('o');
                $currWeek = (int) date('W');
                $lblCurrent = $GLOBALS['TL_LANG']['MSC']['curr_week'];
                $objTemplate->currHref = $this->strUrl.'?week='.$currYear.str_pad((string) $currWeek, 2, '0', STR_PAD_LEFT);
                $objTemplate->currTitle = StringUtil::specialchars($lblCurrent);
                $objTemplate->currLink = $lblCurrent;
                $objTemplate->currLabel = $GLOBALS['TL_LANG']['MSC']['cal_previous'];
            }

            // Previous week
            $prevWeek = str_pad((string) (1 === $intWeek ? $weeksTotal : $intWeek - 1), 2, '0', STR_PAD_LEFT);
            $prevYear = 1 === $intWeek ? $intYear - 1 : $intYear;

            $prevWeekEnd = $this->getWeekBeginAndEnd($prevYear.$prevWeek)[1];
            if ($prevWeekEnd->tstamp > $firstWeek->tstamp) {
                $lblPrevious = $GLOBALS['TL_LANG']['MSC']['calendar_week'].' '.$prevWeek.'/'.$prevYear;
                $objTemplate->prevHref = $this->strUrl.'?week='.$prevYear.$prevWeek;
                $objTemplate->prevTitle = StringUtil::specialchars($lblPrevious);
                $objTemplate->prevLink = $GLOBALS['TL_LANG']['MSC']['cal_previous'].' '.$lblPrevious;
                $objTemplate->prevLabel = $GLOBALS['TL_LANG']['MSC']['cal_previous'];
            }

            $objTemplate->current = $GLOBALS['TL_LANG']['MSC']['calendar_week'].' '.$intWeek.' '.$intYear;

            // Next week
            $nextWeek = str_pad((string) ($intWeek === (int) $weeksTotal ? 1 : $intWeek + 1), 2, '0', STR_PAD_LEFT);
            $nextYear = $intWeek === (int) $weeksTotal ? $intYear + 1 : $intYear;

            $nextWeekBegin = $this->getWeekBeginAndEnd($nextYear.$nextWeek)[0];
            if ($nextWeekBegin->tstamp < $lastWeek->tstamp) {
                $lblNext = $GLOBALS['TL_LANG']['MSC']['calendar_week'].' '.$nextWeek.'/'.$nextYear;
                $objTemplate->nextHref = $this->strUrl.'?week='.$nextYear.$nextWeek;
                $objTemplate->nextTitle = StringUtil::specialchars($lblNext);
                $objTemplate->nextLink = $lblNext.' '.$GLOBALS['TL_LANG']['MSC']['cal_next'];
                $objTemplate->nextLabel = $GLOBALS['TL_LANG']['MSC']['cal_next'];
            }
        }

        // Set week start day
        if (!$this->cal_startDay) {
            $this->cal_startDay = 0;
        }

        [$objTemplate->weekday, $objTemplate->times] = $this->compileDays();

        $this->Template->calendar = $objTemplate->parse();
    }

    /**
     * Return the week days and labels as array.
     *
     * @return array<mixed>
     *
     * @throws \Exception
     */
    protected function compileDays()
    {
        /** @var PageModel */
        global $objPage;

        $arrDays = [];

        // if we start on Sunday we have to go back one day
        if (0 === $this->cal_startDay) {
            $this->weekBegin = strtotime(date('Y-m-d', $this->weekBegin).' -1 day');
        }

        // Get all events
        $arrAllEvents = $this->getAllEvents($this->cal_calendar, $this->weekBegin, $this->weekEnd);

        // we create the array of times
        if ($this->cal_times) {
            $arrTimes = [];
            $arrTimes['start'] = '23:00';
            $arrTimes['stop'] = '00:00';

            for ($i = 0; $i < 7; ++$i) {
                $intKey = date('Ymd', strtotime(date('Y-m-d', $this->weekBegin)." +$i day"));

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

            $timerange = StringUtil::deserialize($this->cal_times_range)[0];

            if ($timerange['time_from']) {
                $arrTimes['start'] = substr((string) $timerange['time_from'], 0, 2);
            }

            if ($timerange['time_to']) {
                $arrTimes['stop'] = substr((string) $timerange['time_to'], 0, 2);
            }

            $cellheight = $this->cellheight ?: 60;

            $arrListTimes = [];
            $counter = 0;

            for ($i = $arrTimes['start']; $i <= $arrTimes['stop']; ++$i) {
                $top = $cellheight * $counter;
                $strHour = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                $arrListTimes[$strHour]['top'] = $top;
                $arrListTimes[$strHour]['class'] = 0 === $counter % 2 ? 'even' : 'odd';
                $arrListTimes[$strHour]['label'] = "$i:00"; // top:".$top."px; position:relative;
                $arrListTimes[$strHour]['style'] = 'height:'.$cellheight.'px;top:'.$top.'px;';
                ++$counter;
            }
        }

        for ($i = 0; $i < 7; ++$i) {
            $intCurrentDay = ($i + $this->cal_startDay) % 7;

            $intKey = date('Ymd', strtotime(date('Y-m-d', $this->weekBegin)." +$i day"));
            $currDay = Date::parse($objPage->dateFormat, strtotime(date('Y-m-d', $this->weekBegin)." +$i day"));

            $class = 0 === $intCurrentDay || 6 === $intCurrentDay ? 'weekend' : 'weekday';
            $class .= 0 === $intCurrentDay % 2 ? ' even' : ' odd';
            $class .= ' '.strtolower((string) $GLOBALS['TL_LANG']['DAYS'][$intCurrentDay]);
            $class .= 0 === $intCurrentDay ? ' last' : '';

            if ($currDay === Date::parse($objPage->dateFormat, strtotime(date('Y-m-d')))) {
                $class .= ' today';
            }

            $arrDays[$intCurrentDay]['label'] = $GLOBALS['TL_LANG']['DAYS'][$intCurrentDay];
            $arrDays[$intCurrentDay]['label_day'] = $GLOBALS['TL_LANG']['DAYS'][$intCurrentDay];
            $arrDays[$intCurrentDay]['label_date'] = $currDay;

            if ($this->showDate) {
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
                if ($this->hideEmptyDays) {
                    unset($arrDays[$intCurrentDay]);
                }
            }
        }

        return [$arrDays, $arrListTimes ?? []];
    }

    private function setWeekStartAndEnd(int $intYear, int $intWeek): void
    {
        $dt = new \DateTime();

        // Set date to the first day of the given week
        $dt->setISODate($intYear, $intWeek);
        $newDate = new Date((int) $dt->format('Ymd'), 'Ymd');
        $newYear = (int) date('Y', $newDate->tstamp);
        $newMonth = (int) date('m', $newDate->tstamp);
        $newDay = (int) date('d', $newDate->tstamp);
        $this->weekBegin = mktime(0, 0, 0, $newMonth, $newDay, $newYear);

        // Set date to the last day of the given week
        $dt->setISODate($intYear, $intWeek, 7);
        $newDate = new Date((int) $dt->format('Ymd'), 'Ymd');
        $newYear = (int) date('Y', $newDate->tstamp);
        $newMonth = (int) date('m', $newDate->tstamp);
        $newDay = (int) date('d', $newDate->tstamp);
        $this->weekEnd = mktime(23, 59, 59, $newMonth, $newDay, $newYear);

        unset($dt);
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
