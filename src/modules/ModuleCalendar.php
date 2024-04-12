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

namespace Cgoit\CalendarExtendedBundle\Modules;

use Contao\BackendTemplate;
use Contao\Config;
use Contao\Date;
use Contao\Environment;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\PageError404;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;

/**
 * Front end module "calendar".
 */
class ModuleCalendar extends EventsExt
{
    /**
     * Current date object.
     *
     * @var Date
     */
    protected $Date;

    /**
     * @var array<mixed>
     */
    protected $calConf = [];

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

            $objTemplate->wildcard = '### '.mb_strtoupper((string) $GLOBALS['TL_LANG']['FMD']['calendar'][0]).' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;

            return $objTemplate->parse();
        }

        $this->cal_calendar = $this->sortOutProtected(StringUtil::deserialize($this->cal_calendar, true));
        $this->cal_holiday = $this->sortOutProtected(StringUtil::deserialize($this->cal_holiday, true));

        // Return if there are no calendars
        if (empty($this->cal_calendar)) {
            return '';
        }

        // Calendar filter
        if (Input::get('cal')) {
            // Create array of cal_id's to filter
            $cals1 = explode(',', (string) Input::get('cal'));
            // Check if the cal_id's are valid for this module
            $cals2 = array_intersect($cals1, $this->cal_calendar);

            if ($cals2) {
                $this->cal_calendar = array_intersect($cals2, $this->cal_calendar);
            }
        }

        // Get the background and foreground colors of the calendars
        foreach (array_merge($this->cal_calendar, $this->cal_holiday) as $cal) {
            $objBG = $this->Database->prepare('select title, bg_color, fg_color from tl_calendar where id = ?')
                ->limit(1)->execute($cal)
            ;

            $this->calConf[$cal]['calendar'] = $objBG->title; // @phpstan-ignore-line

            if ($objBG->bg_color) { // @phpstan-ignore-line
                [$cssColor, $cssOpacity] = StringUtil::deserialize($objBG->bg_color);

                if (!empty($cssColor)) {
                    Utils::appendToArrayKey($this->calConf[$cal], 'background', 'background-color:#'.$cssColor.';');
                }

                if (!empty($cssOpacity)) {
                    Utils::appendToArrayKey($this->calConf[$cal], 'background', 'opacity:'.($cssOpacity / 100).';');
                }
            }

            if ($objBG->fg_color) { // @phpstan-ignore-line
                [$cssColor, $cssOpacity] = StringUtil::deserialize($objBG->fg_color);

                if (!empty($cssColor)) {
                    Utils::appendToArrayKey($this->calConf[$cal], 'foreground', 'color:#'.$cssColor.';');
                }

                if (!empty($cssOpacity)) {
                    Utils::appendToArrayKey($this->calConf[$cal], 'foreground', 'opacity:'.($cssOpacity / 100).';');
                }
            }
        }

        $this->strUrl = preg_replace('/\?.*$/', '', (string) Environment::get('request'));
        $this->strLink = $this->strUrl;

        if ($this->jumpTo && ($objTarget = $this->objModel->getRelated('jumpTo')) !== null) {
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
        // Create the date object
        try {
            if (Input::get('month')) {
                $this->Date = new Date(Input::get('month'), 'Ym');
            } elseif (Input::get('day')) {
                $this->Date = new Date(Input::get('day'), 'Ymd');
            } else {
                $this->Date = new Date();
            }
        } catch (\OutOfBoundsException) {
            /** @var PageModel $objPage */
            global $objPage;

            /** @var PageError404 $objHandler */
            $objHandler = new $GLOBALS['TL_PTY']['error_404']();
            $objHandler->getResponse($objPage);
        }

        /** @var FrontendTemplate|object $objTemplate */
        $objTemplate = new FrontendTemplate($this->cal_ctemplate ?: 'cal_default');

        // Store year and month
        $intYear = date('Y', $this->Date->tstamp);
        $intMonth = date('m', $this->Date->tstamp);
        $objTemplate->intYear = $intYear;
        $objTemplate->intMonth = $intMonth;

        // Previous month
        $prevYear = $intMonth <= 1 ? $intYear - 1 : $intYear;
        $prevMonth = $intMonth <= 1 ? 12 : $intMonth - 1;
        $lblPrevious = $GLOBALS['TL_LANG']['MONTHS'][$prevMonth - 1].' '.$prevYear;
        $intPrevYm = (int) ($prevYear.str_pad((string) $prevMonth, 2, '0', STR_PAD_LEFT));

        // Only generate a link if there are events (see #4160) f ($objMinMax->dateFrom
        // !== null && $intPrevYm >= date('Ym', $objMinMax->dateFrom))
        $objTemplate->prevHref = $this->strUrl.(Config::get('disableAlias') ? '?id='.Input::get('id').'&amp;' : '?').'month='.$intPrevYm;
        $objTemplate->prevTitle = StringUtil::specialchars($lblPrevious);
        $objTemplate->prevLink = $GLOBALS['TL_LANG']['MSC']['cal_previous'].' '.$lblPrevious;
        $objTemplate->prevLabel = $GLOBALS['TL_LANG']['MSC']['cal_previous'];
        // } Current month
        $objTemplate->current = $GLOBALS['TL_LANG']['MONTHS'][date('m', $this->Date->tstamp) - 1].' '.date('Y', $this->Date->tstamp);

        // Next month
        $nextYear = $intMonth >= 12 ? $intYear + 1 : $intYear;
        $nextMonth = $intMonth >= 12 ? 1 : $intMonth + 1;
        $lblNext = $GLOBALS['TL_LANG']['MONTHS'][$nextMonth - 1].' '.$nextYear;
        $intNextYm = $nextYear.str_pad((string) $nextMonth, 2, '0', STR_PAD_LEFT);

        // Only generate a link if there are events (see #4160) f ($objMinMax->dateTo !== null
        // && $intNextYm <= date('Ym', max($objMinMax->dateTo, $objMinMax->repeatUntil)))
        $objTemplate->nextHref = $this->strUrl.(Config::get('disableAlias') ? '?id='.Input::get('id').'&amp;' : '?').'month='.$intNextYm;
        $objTemplate->nextTitle = StringUtil::specialchars($lblNext);
        $objTemplate->nextLink = $lblNext.' '.$GLOBALS['TL_LANG']['MSC']['cal_next'];
        $objTemplate->nextLabel = $GLOBALS['TL_LANG']['MSC']['cal_next'];
        //		}

        // Set the week start day
        if (!$this->cal_startDay) {
            $this->cal_startDay = 0;
        }

        $objTemplate->days = $this->compileDays();
        $objTemplate->weeks = $this->compileWeeks();
        $objTemplate->substr = $GLOBALS['TL_LANG']['MSC']['dayShortLength'];

        $this->Template->calendar = $objTemplate->parse();
    }

    /**
     * Return the week days and labels as array.
     *
     * @return array<mixed>
     */
    protected function compileDays()
    {
        $arrDays = [];

        for ($i = 0; $i < 7; ++$i) {
            $strClass = '';
            $intCurrentDay = ($i + $this->cal_startDay) % 7;

            if (0 === $i) {
                $strClass .= ' col_first';
            } elseif (6 === $i) {
                $strClass .= ' col_last';
            }

            if (0 === $intCurrentDay || 6 === $intCurrentDay) {
                $strClass .= ' weekend';
            }

            $arrDays[$intCurrentDay] = [
                'class' => $strClass,
                'name' => $GLOBALS['TL_LANG']['DAYS'][$intCurrentDay],
            ];
        }

        return $arrDays;
    }

    /**
     * Return all weeks of the current month as array.
     *
     * @return array<mixed>
     *
     * @throws \Exception
     */
    protected function compileWeeks()
    {
        $intDaysInMonth = date('t', $this->Date->monthBegin);
        $intFirstDayOffset = date('w', $this->Date->monthBegin) - $this->cal_startDay;

        if ($intFirstDayOffset < 0) {
            $intFirstDayOffset += 7;
        }

        $intColumnCount = -1;
        $intNumberOfRows = (int) ceil(($intDaysInMonth + $intFirstDayOffset) / 7);
        $arrAllEvents = $this->getAllEventsExt($this->cal_calendar, $this->Date->monthBegin, $this->Date->monthEnd, [$this->cal_holiday]);
        $arrDays = [];

        // Compile days
        for ($i = 1; $i <= $intNumberOfRows * 7; ++$i) {
            $intWeek = (int) floor(++$intColumnCount / 7);
            $intDay = $i - $intFirstDayOffset;
            $intCurrentDay = ($i + $this->cal_startDay) % 7;

            $strWeekClass = 'week_'.$intWeek;
            $strWeekClass .= 0 === $intWeek ? ' first' : '';
            $strWeekClass .= $intWeek === $intNumberOfRows - 1 ? ' last' : '';

            $strClass = $intCurrentDay < 2 ? ' weekend' : '';
            $strClass .= 1 === $i || 8 === $i || 15 === $i || 22 === $i || 29 === $i || 36 === $i ? ' col_first' : '';
            $strClass .= 7 === $i || 14 === $i || 21 === $i || 28 === $i || 35 === $i || 42 === $i ? ' col_last' : '';

            // Empty cell
            if ($intDay < 1 || $intDay > $intDaysInMonth) {
                $arrDays[$strWeekClass][$i]['label'] = '&nbsp;';
                $arrDays[$strWeekClass][$i]['class'] = 'days empty'.$strClass;
                $arrDays[$strWeekClass][$i]['events'] = [];

                continue;
            }

            $intKey = date('Ym', $this->Date->tstamp).(\strlen((string) $intDay) < 2 ? '0'.$intDay : $intDay);
            $strClass .= (int) $intKey === (int) date('Ymd') ? ' today' : '';
            $strClass .= (int) $intKey < (int) date('Ymd') ? ' bygone' : '';
            $strClass .= (int) $intKey > (int) date('Ymd') ? ' upcomming' : '';

            // Mark the selected day (see #1784)
            if ($intKey === Input::get('day')) {
                $strClass .= ' selected';
            }

            // Inactive days
            if (!isset($arrAllEvents[$intKey])) {
                $arrDays[$strWeekClass][$i]['label'] = $intDay;
                $arrDays[$strWeekClass][$i]['class'] = 'days'.$strClass;
                $arrDays[$strWeekClass][$i]['events'] = [];

                continue;
            }

            $arrEvents = [];

            // Get all events of a day
            foreach ($arrAllEvents[$intKey] as $v) {
                foreach ($v as $vv) {
                    $vv['calendar_title'] = $this->calConf[$vv['pid']]['calendar'];

                    if (\array_key_exists('background', $this->calConf[$vv['pid']]) && $this->calConf[$vv['pid']]['background']) {
                        $vv['bgstyle'] = $this->calConf[$vv['pid']]['background'];
                    }

                    if (\array_key_exists('foreground', $this->calConf[$vv['pid']]) && $this->calConf[$vv['pid']]['foreground']) {
                        $vv['fgstyle'] = $this->calConf[$vv['pid']]['foreground'];
                    }
                    $arrEvents[] = $vv;
                }
            }

            $arrDays[$strWeekClass][$i]['label'] = $intDay;
            $arrDays[$strWeekClass][$i]['class'] = 'days active'.$strClass;

            if (\count($arrEvents) > 0) {
                $arrDays[$strWeekClass][$i]['href'] = $this->strLink.(Config::get('disableAlias') ? '&amp;' : '?').'day='.$intKey;
                $arrDays[$strWeekClass][$i]['title'] = sprintf(StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['cal_events']), \count($arrEvents));
                $arrDays[$strWeekClass][$i]['events'] = $arrEvents;
            }
        }

        return $arrDays;
    }
}
