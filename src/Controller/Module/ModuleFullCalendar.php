<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\calendar-extended-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) Kester Mielke
 * @copyright  Copyright (c) 2026, cgoIT
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
 * Front end module "FullCalendar".
 */
class ModuleFullCalendar extends Events
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
    protected $intStart;

    /**
     * @var int
     */
    protected $intEnd;

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
    protected $strTemplate = 'mod_fc_fullcalendar';

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
            /** @var BackendTemplate $objTemplate */
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### '.$GLOBALS['TL_LANG']['FMD']['fullcalendar'][0].' ###';
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
     * @param array<mixed> $arrCalendars
     *
     * @return array<mixed>
     */
    public function loadEvents(array $arrCalendars, int $intStart, int $intEnd): array
    {
        return $this->getAllEvents($arrCalendars, $intStart, $intEnd);
    }

    /**
     * Generate the module.
     */
    protected function compile(): void
    {
        /** @var PageModel $objPage */
        global $objPage;

        $blnClearInput = false;

        $month = Input::get('month');
        $week = Input::get('week');
        $day = Input::get('day');

        // Jump to the current period
        if (!isset($_GET['month']) && !isset($_GET['week']) && !isset($_GET['day'])) {
            switch ($this->cal_fcFormat) {
                case 'cal_fc_month':
                    $month = date('Ym');
                    break;

                case 'cal_fc_week':
                    $week = date('YW');
                    break;

                case 'cal_fc_day':
                case 'cal_fc_list':
                    $day = date('Ymd');
                    break;
            }

            $blnClearInput = true;
        }

        $blnDynamicFormat = (!$this->cal_ignoreDynamic && \in_array($this->cal_fcFormat, ['cal_fc_list', 'cal_fc_day', 'cal_fc_week', 'cal_fc_month'], true));

        // Create the date object
        try {
            if ($blnDynamicFormat && $month) {
                $this->Date = new Date((int) $month, 'Ym');
                $this->cal_fcFormat = 'cal_fc_month';
                $this->headline .= ' '.Date::parse('F Y', $this->Date->tstamp);
            } elseif ($blnDynamicFormat && $week) {
                $selYear = (int) substr((string) $week, 0, 4);
                $selWeek = (int) substr((string) $week, -2);
                $selDay = 1 === $selWeek ? 4 : 1;
                $dt = new \DateTime();
                $dt->setISODate($selYear, $selWeek, $selDay);
                $this->Date = new Date((int) $dt->format('Ymd'), 'Ymd');
                $this->cal_fcFormat = 'cal_fc_week';
                $this->headline .= ' '.Date::parse('W/Y', $this->Date->tstamp);
            } elseif ($blnDynamicFormat && $day) {
                $this->Date = new Date((int) $day, 'Ymd');
                $this->headline .= ' '.Date::parse($objPage->dateFormat, $this->Date->tstamp);
                if (empty($this->cal_fcFormat)) {
                    $this->cal_fcFormat = 'cal_fc_day';
                }
            } else {
                $this->Date = new Date();
            }
        } catch (\OutOfBoundsException) {
            throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
        }

        // calendar-extended-bundle assets
        $assets_path = 'bundles/cgoitcalendarextended';
        $assets_fc = '/fullcalendar-6.1.11';
        $assets_fa = '/font-awesome-4.7.0';

        // CSS files
        $GLOBALS['TL_CSS'][] = $assets_path.$assets_fa.'/css/font-awesome.min.css';
        $GLOBALS['TL_JAVASCRIPT'][] = $assets_path.$assets_fc.'/dist/index.global.min.js';
        $GLOBALS['TL_JAVASCRIPT'][] = $assets_path.$assets_fc.'/packages/core/locales-all.global.min.js';

        /** @var FrontendTemplate $objTemplate */
        $objTemplate = new FrontendTemplate($this->cal_ctemplate ?: 'cal_fc_default');

        // Set some fullcalendar options
        $objTemplate->url = '/fullcalendar/fetchEvents/'.$this->id;
        $objTemplate->locale = $GLOBALS['TL_LANGUAGE'];
        $objTemplate->initialDate = date('Y-m-d\TH:i:sP', $this->Date->tstamp);
        $objTemplate->firstDay = $this->cal_startDay;

        if (!empty($this->businessHours)) {
            $arrDays = array_map(\intval(...), StringUtil::deserialize($this->businessDays, true));

            $businessHours = new \stdClass();
            $businessHours->daysOfWeek = $arrDays;
            $businessHours->startTime = date('H:i', $this->businessDayStart);
            $businessHours->endTime = date('H:i', $this->businessDayEnd);

            $objTemplate->businessHours = json_encode($businessHours);
        }

        $objTemplate->weekNumbers = $this->weekNumbers;

        $objTemplate->confirm_drop = $GLOBALS['TL_LANG']['tl_module']['confirm_drop'];
        $objTemplate->confirm_resize = $GLOBALS['TL_LANG']['tl_module']['confirm_resize'];
        $objTemplate->fetch_error = $GLOBALS['TL_LANG']['tl_module']['fetch_error'];

        $objTemplate->initialView = match ($this->cal_fcFormat) {
            'cal_fc_month' => 'dayGridMonth',
            'cal_fc_day' => 'timeGridDay',
            'cal_fc_list' => 'listDay',
            default => 'timeGridWeek',
        };

        $objTemplate->requestToken = System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue();

        // Render the template
        $this->Template->fullcalendar = $objTemplate->parse();

        // Clear the $_GET array (see #2445)
        if ($blnClearInput) {
            Input::setGet('month', null);
            Input::setGet('week', null);
            Input::setGet('day', null);
        }
    }
}
