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

use Cgoit\CalendarExtendedBundle\Models\CalendarEventsModelExt;
use Contao\BackendTemplate;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\Events;
use Contao\FrontendTemplate;
use Contao\FrontendUser;
use Contao\Input;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;

/**
 * Front end module "calendar".
 */
class ModuleFullcalendar extends Events
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
            /** @var BackendTemplate|object $objTemplate */
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
                $this->Date = new Date($month, 'Ym');
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
                $this->Date = new Date($day, 'Ymd');
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

        if (isset($_POST['type'])) {
            /**
             * if $_POST['type']) is set then we have to handle ajax calls from fullcalendar.
             *
             * We check if the given $type is an existing method
             * - if yes then call the function
             * - if no just do nothing right now (for the moment)
             */
            $type = $_POST['type'];

            if (method_exists($this, $type)) {
                $this->$type();
            }
        } else {
            // calendar-extended-bundel assets
            $assets_path = 'bundles/cgoitcalendarextended';
            // fullcalendar 3.9.0
            $assets_fc = '/fullcalendar-3.9.0';
            // font-awesome 4.7.0
            $assets_fa = '/font-awesome-4.7.0';

            // Load jQuery if not active
            if ('1' !== $objPage->hasJQuery) {
                $GLOBALS['TL_JAVASCRIPT'][] = $assets_path.$assets_fc.'/lib/jquery.min.js|static';
            }

            // CSS files
            $GLOBALS['TL_CSS'][] = $assets_path.$assets_fa.'/css/font-awesome.min.css';
            $GLOBALS['TL_CSS'][] = $assets_path.$assets_fc.'/fullcalendar.min.css';

            // JS files
            $GLOBALS['TL_JAVASCRIPT'][] = $assets_path.$assets_fc.'/lib/moment.min.js';
            $GLOBALS['TL_JAVASCRIPT'][] = $assets_path.$assets_fc.'/fullcalendar.min.js';
            $GLOBALS['TL_JAVASCRIPT'][] = $assets_path.$assets_fc.'/gcal.min.js';
            $GLOBALS['TL_JAVASCRIPT'][] = $assets_path.$assets_fc.'/locale-all.js';

            /** @var FrontendTemplate|object $objTemplate */
            $objTemplate = new FrontendTemplate($this->cal_ctemplate ?: 'cal_fc_default');

            // Set some fullcalendar options
            $security = System::getContainer()->get('security.helper');

            $objTemplate->url = $this->strLink;
            $objTemplate->locale = $GLOBALS['TL_LANGUAGE'];
            $objTemplate->defaultDate = date('Y-m-d\TH:i:sP', $this->Date->tstamp);
            $objTemplate->firstDay = $this->cal_startDay;
            $objTemplate->editable = $this->fc_editable && $security->getUser() instanceof FrontendUser;
            $objTemplate->businessHours = $this->businessHours;

            $objTemplate->weekNumbers = $this->weekNumbers;
            $objTemplate->weekNumbersWithinDays = $this->weekNumbersWithinDays;
            $objTemplate->eventLimit = $this->eventLimit;

            $objTemplate->confirm_drop = $GLOBALS['TL_LANG']['tl_module']['confirm_drop'];
            $objTemplate->confirm_resize = $GLOBALS['TL_LANG']['tl_module']['confirm_resize'];
            $objTemplate->fetch_error = $GLOBALS['TL_LANG']['tl_module']['fetch_error'];

            $objTemplate->defaultView = match ($this->cal_fcFormat) {
                'cal_fc_month' => 'month',
                'cal_fc_day' => 'agendaDay',
                'cal_fc_list' => 'listDay',
                default => 'agendaWeek',
            };

            // Set the formular $objTemplate->event_formular = \Form::getForm(1);
            // Render the template
            $this->Template->fullcalendar = $objTemplate->parse();
        }

        // Clear the $_GET array (see #2445)
        if ($blnClearInput) {
            Input::setGet('month', null);
            Input::setGet('week', null);
            Input::setGet('day', null);
        }
    }

    /**
     * Fetch all events for the given time range.
     *
     * $_POST['start'] and $_POST['end'] are set by fullcalendar
     */
    protected function fetchEvents(): void
    {
        $security = System::getContainer()->get('security.helper');

        $intStart = Input::post('start') ? strtotime((string) Input::post('start')) : $this->intStart;
        $intEnd = Input::post('end') ? strtotime((string) Input::post('end')) : $this->intEnd;

        // Get all events
        $arrAllEvents = $this->getAllEvents($this->cal_calendar, $intStart, $intEnd);

        // Sort the days
        $sort = 'descending' === $this->cal_order ? 'krsort' : 'ksort';
        $sort($arrAllEvents);

        // Sort the events
        foreach (array_keys($arrAllEvents) as $key) {
            $sort($arrAllEvents[$key]);
        }

        // Step 1: get the current time
        $currTime = Date::floorToMinute();

        // Array of events for JSON output
        $json_events = [];
        $multiday_event = [];

        // Create the JSON of all events
        foreach ($arrAllEvents as $days) {
            foreach ($days as $events) {
                foreach ($events as $event) {
                    // Use repeatEnd if > 0 (see #8447)
                    if (($event['repeatEnd'] ?: $event['end']) < $intStart || $event['begin'] > $intEnd) {
                        continue;
                    }

                    // Hide Events that are already started
                    if ($this->hide_started && $event['begin'] < $currTime) {
                        continue;
                    }

                    // Set start and end of each event to the right format for the fullcalendar
                    $event['datetime_start'] = date('Y-m-d\TH:i:s', $event['begin']);
                    $event['datetime_end'] = date('Y-m-d\TH:i:s', $event['end']);
                    $allDay = $event['addTime'] ? false : true;

                    // Set title
                    $title = StringUtil::specialchars((string) $event['title']);

                    // Some options
                    $editable = $this->fc_editable && $security->getUser() instanceof FrontendUser;
                    $multiday = false;
                    $recurring = false;

                    /*
                     * Editing is allowd if we have a single or multi day event. Any kind of recurring event
                     * is not allowed right now.
                     */
                    // Disable editing if event is recurring...
                    if ($event['recurring'] || $event['recurringExt'] || $event['useExceptions']) {
                        $editable = false;
                        $recurring = true;
                    }

                    $row = StringUtil::deserialize($event['repeatFixedDates']);
                    if (!empty($row) && \is_array($row) && \array_key_exists('new_repeat', $row) && $row[0]['new_repeat'] > 0) {
                        $editable = false;
                        $recurring = true;
                    }

                    // If event is not recurring
                    if (!$recurring) {
                        // Multi day event?
                        if (Date::parse('dmY', $event['startTime']) !== Date::parse('dmY', $event['endTime'])) {
                            $multiday = true;
                        }
                    }

                    // Set the icon
                    $icon = $recurring ? 'fa-repeat' : 'fa-calendar-o';

                    // Ignore if this is not the first multi day entry
                    if (false === array_search($event['id'], $multiday_event, true)) {
                        // Add the event to array of events
                        $json_events[] = [
                            'id' => $event['id'],
                            'title' => $title,
                            'start' => $event['datetime_start'],
                            'end' => $event['datetime_end'],
                            'description' => $event['teaser'],
                            'allDay' => $allDay,
                            'overlap' => false,
                            'url' => $event['href'],
                            'editable' => $editable,
                            'icon' => $icon,
                            'backgroundColor' => $event['bgstyle'] ?? '',
                            'textColor' => $event['fgstyle'] ?? '',
                        ];
                    }

                    // Remember if multi day event
                    if ($multiday && false === array_search($event['id'], $multiday_event, true)) {
                        $multiday_event[] = $event['id'];
                    }
                }
            }
        }

        // Free resources
        unset($event, $events, $arrAllEvents, $multiday_event);

        // Return array of events as json
        echo json_encode($json_events);
        exit;
    }

    /**
     * Get the formular and the event data.
     */
    protected function getEvent(): void
    {
        // Get all edit_* fields from tl_form_field
        $ff = [];
        $fields = Database::getInstance()
            ->prepare('select name, type from tl_form_field where pid = ? and name like ?')
            ->execute(1, 'edit_%')
        ;

        if ($fields->numRows > 0) {
            while ($fields->next()) {
                $ff[$fields->name] = substr((string) $fields->name, 5);
                $ff['t_'.$fields->name] = $fields->type;
            }
        }

        // Get the event
        $id = Input::post('event');
        $event = CalendarEventsModelExt::findById($id);

        // Replace the edit_* value with the db value
        foreach ($ff as $k => $v) {
            $ff[$k] = strip_tags((string) $event->$v);
        }

        // Return the form fields
        echo json_encode($ff);
        exit;
    }

    /**
     * Update date and/or time of the event.
     */
    protected function updateEventTimes(): bool
    {
        if ($event = Input::post('event')) {
            return $this->updateEvent($event);
        }

        return false;
    }

    /**
     * Update event from form data.
     */
    protected function updateEventData(): bool
    {
        if ($event = Input::post('event')) {
            foreach ($event as $k => $v) {
                if (!str_contains((string) $k, 'edit_')) {
                    unset($event[$k]);
                } else {
                    $n = substr((string) $k, 5);
                    $event[$n] = $v;
                    unset($event[$k]);
                }
            }

            return $this->updateEvent($event);
        }

        return false;
    }

    /**
     * Update the event.
     *
     * @param array<mixed> $event
     */
    protected function updateEvent($event): bool
    {
        // Get the id of the event
        $id = $event['id'];
        unset($event['id']);

        // Get allDay value
        $allDay = 'true' === $event['allDay'] ? true : false;
        unset($event['allDay']);

        // Check if it is allowed to edit this event
        $update_event = CalendarEventsModelExt::findById($id);

        if ($update_event->recurring || $update_event->recurringExt || $update_event->useExceptions) {
            return false;
        }

        $row = StringUtil::deserialize($update_event->repeatFixedDates);

        if (!empty($row) && \is_array($row) && \array_key_exists('new_repeat', $row) && $row[0]['new_repeat'] > 0) {
            return false;
        }

        // Set all relevant date and time values
        $event['startDate'] = $event['startDate'] ?: strtotime(date('d.m.Y', $event['startTime']));
        $event['repeatEnd'] = $event['startDate'];

        if ($event['endTime']) {
            $event['repeatEnd'] = $event['endTime'];
            // Set endDate only if it was set before...
            if (\strlen((string) $update_event->endDate)) {
                $event['endDate'] = $event['endDate'] ?: strtotime(date('d.m.Y', $event['endTime']));
            }
        }

        // Check the allDay value
        if ($allDay) {
            $event['addTime'] = '';
            $event['startTime'] = '';
            $event['endTime'] = '';
        } else {
            $event['addTime'] = 1;
        }

        // Update the event
        Database::getInstance()
            ->prepare('update tl_calendar_events %s where id=?')
            ->set($event)->execute($id)
        ;

        return true;
    }
}
