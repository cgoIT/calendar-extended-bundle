<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\calendar-extended-bundle.
 *
 * (c) Kester Mielke
 * (c) Carsten Götzinger
 *
 * @license LGPL-3.0-or-later
 */

namespace Cgoit\CalendarExtendedBundle;

use Contao\BackendTemplate;
use Contao\Config;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\System;

/**
 * Class ModuleEventMenuExt.
 *
 * @copyright  Kester Mielke 2010-2013
 */
class ModuleEventMenu extends ModuleCalendar
{
    /**
     * Display a wildcard in the back end.
     *
     * @return string
     */
    public function generate()
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
            /** @var BackendTemplate|object $objTemplate */
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### '.utf8_strtoupper($GLOBALS['TL_LANG']['FMD']['eventmenu'][0]).' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;

            return $objTemplate->parse();
        }

        return parent::generate();
    }

    /**
     * Generate the module.
     */
    protected function compile(): void
    {
        switch ($this->cal_format) {
            case 'cal_year':
                $this->compileYearlyMenu();
                break;

            default:
            case 'cal_month':
                $this->compileMonthlyMenu();
                break;

            case 'cal_day':
                $this->cal_ctemplate = 'cal_mini';
                parent::compile();
                break;
        }
    }

    /**
     * Generate the yearly menu.
     */
    protected function compileYearlyMenu(): void
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        $arrData = [];

        if ($this->customTpl && System::getContainer()->get('contao.routing.scope_matcher')->isFrontendRequest($request)) {
            $strTemplate = $this->customTpl;
        } else {
            $strTemplate = 'mod_eventmenu';
        }

        /** @var FrontendTemplate|object $objTemplate */
        $objTemplate = new FrontendTemplate($strTemplate);

        $this->Template = $objTemplate;
        $arrAllEvents = $this->getAllEventsExt($this->cal_calendar, 0, 2145913200, [$this->cal_holiday]);

        foreach ($arrAllEvents as $intDay => $arrDay) {
            foreach ($arrDay as $arrEvents) {
                $arrData[substr($intDay, 0, 4)] += \count($arrEvents);
            }
        }

        // Sort data
        ('ascending' === $this->cal_order) ? ksort($arrData) : krsort($arrData);

        $arrItems = [];
        $count = 0;
        $limit = \count($arrData);

        // Prepare navigation
        foreach ($arrData as $intYear => $intCount) {
            $intDate = $intYear;
            $quantity = sprintf(($intCount < 2 ? $GLOBALS['TL_LANG']['MSC']['entry'] : $GLOBALS['TL_LANG']['MSC']['entries']), $intCount);

            $arrItems[$intYear]['date'] = $intDate;
            $arrItems[$intYear]['link'] = $intYear;
            $arrItems[$intYear]['href'] = $this->strLink.(Config::get('disableAlias') ? '&amp;' : '?').'year='.$intDate;
            $arrItems[$intYear]['title'] = specialchars($intYear.' ('.$quantity.')');
            $arrItems[$intYear]['class'] = trim((1 === ++$count ? 'first ' : '').($count === $limit ? 'last' : ''));
            $arrItems[$intYear]['isActive'] = (Input::get('year') === $intDate);
            $arrItems[$intYear]['quantity'] = $quantity;
        }

        $this->Template->items = $arrItems;
        $this->Template->showQuantity = !empty($this->cal_showQuantity);
        $this->Template->yearly = true;
    }

    /**
     * Generate the monthly menu.
     */
    protected function compileMonthlyMenu(): void
    {
        $arrData = [];

        /** @var FrontendTemplate|object $objTemplate */
        $objTemplate = new FrontendTemplate('mod_eventmenu');

        $this->Template = $objTemplate;
        $arrAllEvents = $this->getAllEventsExt($this->cal_calendar, 0, 2145913200, [$this->cal_holiday]);

        foreach ($arrAllEvents as $intDay => $arrDay) {
            foreach ($arrDay as $arrEvents) {
                $arrData[substr($intDay, 0, 4)][substr($intDay, 4, 2)] += \count($arrEvents);
            }
        }

        // Sort data
        foreach (array_keys($arrData) as $key) {
            'ascending' === $this->cal_order ? ksort($arrData[$key]) : krsort($arrData[$key]);
        }

        ('ascending' === $this->cal_order) ? ksort($arrData) : krsort($arrData);

        $arrItems = [];

        // Prepare the navigation
        foreach ($arrData as $intYear => $arrMonth) {
            $count = 0;
            $limit = \count($arrMonth);

            foreach ($arrMonth as $intMonth => $intCount) {
                $intDate = $intYear.$intMonth;
                $intMonth = (int) $intMonth - 1;

                $quantity = sprintf(($intCount < 2 ? $GLOBALS['TL_LANG']['MSC']['entry'] : $GLOBALS['TL_LANG']['MSC']['entries']), $intCount);

                $arrItems[$intYear][$intMonth]['date'] = $intDate;
                $arrItems[$intYear][$intMonth]['link'] = $GLOBALS['TL_LANG']['MONTHS'][$intMonth].' '.$intYear;
                $arrItems[$intYear][$intMonth]['href'] = $this->strLink.(Config::get('disableAlias') ? '&amp;' : '?').'month='.$intDate;
                $arrItems[$intYear][$intMonth]['title'] = specialchars($GLOBALS['TL_LANG']['MONTHS'][$intMonth].' '.$intYear.' ('.$quantity.')');
                $arrItems[$intYear][$intMonth]['class'] = trim((1 === ++$count ? 'first ' : '').($count === $limit ? 'last' : ''));
                $arrItems[$intYear][$intMonth]['isActive'] = (Input::get('month') === $intDate);
                $arrItems[$intYear][$intMonth]['quantity'] = $quantity;
            }
        }

        $this->Template->items = $arrItems;
        $this->Template->showQuantity = !empty($this->cal_showQuantity);
        $this->Template->url = $this->strLink.(Config::get('disableAlias') ? '&amp;' : '?');
        $this->Template->activeYear = Input::get('year');
    }
}
