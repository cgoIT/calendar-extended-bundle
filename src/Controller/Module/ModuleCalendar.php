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

use Cgoit\CalendarExtendedBundle\Classes\EventsExt;
use Cgoit\CalendarExtendedBundle\Classes\Utils;
use Contao\BackendTemplate;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\Date;
use Contao\Environment;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\Model;
use Contao\Model\Collection;
use Contao\ModuleModel;
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

    public function __construct(Collection|Model|ModuleModel $objModule, string $strColumn = 'main')
    {
        parent::__construct((array) System::getContainer()->getParameter('cgoit_calendar_extended.month_array'), $objModule, $strColumn);
    }

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

            $objTemplate->wildcard = '### '.$GLOBALS['TL_LANG']['FMD']['calendar'][0].' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = StringUtil::specialcharsUrl(System::getContainer()->get('router')->generate('contao_backend', ['do' => 'themes', 'table' => 'tl_module', 'act' => 'edit', 'id' => $this->id]));

            return $objTemplate->parse();
        }

        $this->cal_calendar = $this->sortOutProtected(StringUtil::deserialize($this->cal_calendar, true));
        $this->cal_holiday = $this->sortOutProtected(StringUtil::deserialize($this->cal_holiday, true));

        // Return if there are no calendars
        if (empty($this->cal_calendar) || !\is_array($this->cal_calendar)) {
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

            $this->calConf[$cal]['calendar'] = $objBG->title;

            if ($objBG->bg_color) {
                [$cssColor, $cssOpacity] = StringUtil::deserialize($objBG->bg_color);

                if (!empty($cssColor)) {
                    Utils::appendToArrayKey($this->calConf[$cal], 'background', 'background-color:#'.$cssColor.';');
                }

                if (!empty($cssOpacity)) {
                    Utils::appendToArrayKey($this->calConf[$cal], 'background', 'opacity:'.($cssOpacity / 100).';');
                }
            }

            if ($objBG->fg_color) {
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
        $month = Input::get('month');
        $day = Input::get('day');

        // Create the date object
        try {
            if (\is_string($month)) {
                $this->Date = new Date((int) $month, 'Ym');
            } elseif (\is_string($day)) {
                $this->Date = new Date((int) $day, 'Ymd');
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

        $firstMonth = date('Ym', min($dateFrom, $time));
        $lastMonth = date('Ym', max($dateTo, $repeatUntil, $time));

        // The given month is out of scope
        if ($month && ($month < $firstMonth || $month > $lastMonth)) {
            throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
        }

        // The given day is out of scope
        if ($day && ($day < date('Ymd', min($dateFrom, $time)) || $day > date('Ymd', max($dateTo, $repeatUntil, $time)))) {
            throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
        }

        // Store year and month
        $intYear = (int) date('Y', $this->Date->tstamp);
        $intMonth = (int) date('m', $this->Date->tstamp);

        /** @var FrontendTemplate|object $objTemplate */
        $objTemplate = new FrontendTemplate($this->cal_ctemplate ?: 'cal_default');
        $objTemplate->intYear = $intYear;
        $objTemplate->intMonth = $intMonth;

        // Previous month
        $prevMonth = 1 === $intMonth ? 12 : $intMonth - 1;
        $prevYear = 1 === $intMonth ? $intYear - 1 : $intYear;
        $lblPrevious = $GLOBALS['TL_LANG']['MONTHS'][$prevMonth - 1].' '.$prevYear;
        $intPrevYm = (int) ($prevYear.str_pad((string) $prevMonth, 2, '0', STR_PAD_LEFT));

        // Only generate a link if there are events (see #4160)
        if ($intPrevYm >= $firstMonth) {
            $objTemplate->prevHref = $this->strUrl.'?month='.$intPrevYm;
            $objTemplate->prevTitle = StringUtil::specialchars($lblPrevious);
            $objTemplate->prevLink = $GLOBALS['TL_LANG']['MSC']['cal_previous'].' '.$lblPrevious;
            $objTemplate->prevLabel = $GLOBALS['TL_LANG']['MSC']['cal_previous'];
        }

        // Current month
        $objTemplate->current = $GLOBALS['TL_LANG']['MONTHS'][date('m', $this->Date->tstamp) - 1].' '.date('Y', $this->Date->tstamp);

        // Next month
        $nextMonth = 12 === $intMonth ? 1 : $intMonth + 1;
        $nextYear = 12 === $intMonth ? $intYear + 1 : $intYear;
        $lblNext = $GLOBALS['TL_LANG']['MONTHS'][$nextMonth - 1].' '.$nextYear;
        $intNextYm = $nextYear.str_pad((string) $nextMonth, 2, '0', STR_PAD_LEFT);

        // Only generate a link if there are events (see #4160)
        if ($intNextYm <= $lastMonth) {
            $objTemplate->nextHref = $this->strUrl.'?month='.$intNextYm;
            $objTemplate->nextTitle = StringUtil::specialchars($lblNext);
            $objTemplate->nextLink = $lblNext.' '.$GLOBALS['TL_LANG']['MSC']['cal_next'];
            $objTemplate->nextLabel = $GLOBALS['TL_LANG']['MSC']['cal_next'];
        }

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

        // Handle featured events
        $blnFeatured = null;

        if ('featured' === $this->cal_featured) {
            $blnFeatured = true;
        } elseif ('unfeatured' === $this->cal_featured) {
            $blnFeatured = false;
        }

        $intColumnCount = -1;
        $intNumberOfRows = (int) ceil(($intDaysInMonth + $intFirstDayOffset) / 7);
        $arrAllEvents = $this->getAllEventsExt($this->cal_calendar, $this->Date->monthBegin, $this->Date->monthEnd, [$this->cal_holiday], $blnFeatured);
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

            // Add timestamp to all cells
            $arrDays[$strWeekClass][$i]['timestamp'] = strtotime(($intDay - 1).' day', $this->Date->monthBegin);

            // Empty cell
            if ($intDay < 1 || $intDay > $intDaysInMonth) {
                $arrDays[$strWeekClass][$i]['label'] = '&nbsp;';
                $arrDays[$strWeekClass][$i]['class'] = 'days empty'.$strClass;
                $arrDays[$strWeekClass][$i]['events'] = [];

                continue;
            }

            $intKey = date('Ym', $this->Date->tstamp).(\strlen((string) $intDay) < 2 ? '0'.$intDay : $intDay);
            $strClass .= (int) $intKey === (int) date('Ymd') ? ' today' : '';

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
            $arrDays[$strWeekClass][$i]['href'] = $this->strLink.'?day='.$intKey;
            $arrDays[$strWeekClass][$i]['title'] = sprintf(StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['cal_events']), \count($arrEvents));
            $arrDays[$strWeekClass][$i]['events'] = $arrEvents;
        }

        return $arrDays;
    }
}
