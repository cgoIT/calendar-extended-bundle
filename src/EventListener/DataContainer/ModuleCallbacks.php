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

namespace Cgoit\CalendarExtendedBundle\EventListener\DataContainer;

use Cgoit\CalendarExtendedBundle\Exception\CalendarExtendedException;
use Contao\Backend;
use Contao\BackendUser;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\System;

class ModuleCallbacks extends Backend
{
    public function __construct()
    {
        parent::__construct();
        $this->import(BackendUser::class, 'User');
    }

    /**
     * @return array
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.filter_fields.options')]
    public function getEventField()
    {
        // Load tl_calendar_events data
        $this->loadDataContainer('tl_calendar_events');
        System::loadLanguageFile('tl_calendar_events');

        // Get the event fields
        $arr_fields = $GLOBALS['TL_CONFIG']['tl_calendar_events']['filter']
            ?: $GLOBALS['TL_DCA']['tl_calendar_events']['fields'];

        $event_fields = [];

        foreach ($arr_fields as $k => $v) {
            if (\strlen((string) $GLOBALS['TL_LANG']['tl_calendar_events'][$k][0])) {
                $label = \strlen((string) $v['label']) ? $v['label'] : $GLOBALS['TL_LANG']['tl_calendar_events'][$k][0];
                $event_fields[$k] = $label;
            }
        }

        return $event_fields;
    }

    //    /**     * @return array     */    public function listNotifications()    {
    // if (!class_exists('leads\leads')) {            return null;        } $return =
    // [];         $objNotifications = Notification::findAll(); if (null !==
    // $objNotifications) {            while ($objNotifications->next()) {
    // $return[$objNotifications->id] = $objNotifications->title;         }      }
    // return $return;    }

    /**
     * @return array|null
     */
    public function getTimeRange()
    {
        return [
            'time_from' => [
                'label' => &$GLOBALS['TL_LANG']['tl_module']['time_range_from'],
                'exclude' => true,
                'default' => null,
                'inputType' => 'text',
                'eval' => ['rgxp' => 'time', 'doNotCopy' => true, 'style' => 'width:120px', 'datepicker' => true, 'tl_class' => 'wizard'],
            ],
            'time_to' => [
                'label' => &$GLOBALS['TL_LANG']['tl_module']['time_range_to'],
                'exclude' => true,
                'default' => null,
                'inputType' => 'text',
                'eval' => ['rgxp' => 'time', 'doNotCopy' => true, 'style' => 'width:120px', 'datepicker' => true, 'tl_class' => 'wizard'],
            ],
        ];
    }

    /**
     * @return array|null
     */
    public function getRange()
    {
        return [
            'date_from' => [
                'label' => &$GLOBALS['TL_LANG']['tl_module']['range_from'],
                'exclude' => true,
                'default' => null,
                'inputType' => 'text',
                'eval' => ['rgxp' => 'datim', 'doNotCopy' => true, 'style' => 'width:120px', 'datepicker' => true, 'tl_class' => 'wizard'],
            ],
            'date_to' => [
                'label' => &$GLOBALS['TL_LANG']['tl_module']['range_to'],
                'exclude' => true,
                'default' => null,
                'inputType' => 'text',
                'eval' => ['rgxp' => 'datim', 'doNotCopy' => true, 'style' => 'width:120px', 'datepicker' => true, 'tl_class' => 'wizard'],
            ],
        ];
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'fields.displayDuration.save')]
    public function checkDuration(mixed $varValue, DataContainer $dc): mixed
    {
        if ('' !== $varValue) {
            if (($timestamp = date('dmY', strtotime((string) $varValue, time()))) === date('dmY', time())) {
                throw new CalendarExtendedException($GLOBALS['TL_LANG']['tl_module']['displayDurationError2'].': '.$timestamp);
            }
        }

        return $varValue;
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'fields.cal_format_ext.save')]
    public function checkCalFormat(mixed $varValue, DataContainer $dc): mixed
    {
        if ('' !== $varValue) {
            if (($timestamp = date('dmYHis', strtotime((string) $varValue, time()))) === date('dmYHis', time())) {
                throw new CalendarExtendedException($GLOBALS['TL_LANG']['tl_module']['displayDurationError2'].': '.$timestamp);
            }
        }

        return $varValue;
    }

    /**
     * Return all calendar templates as array.
     *
     * @return array<mixed>
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.cal_ctemplate.options')]
    public function getCalendarTemplates(): array
    {
        return $this->getTemplateGroup('cal_');
    }

    /**
     * Get all calendars and return them as array.
     *
     * @return array<mixed>
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.cal_calendar.options')]
    public function getCalendars(): array
    {
        if (!$this->User->isAdmin && !\is_array($this->User->calendars)) {
            return [];
        }

        $arrCalendars = [];
        $objCalendars = $this->Database->execute('SELECT id, title FROM tl_calendar WHERE isHolidayCal != 1 ORDER BY title');

        while ($objCalendars->next()) {
            if ($this->User->isAdmin || $this->User->hasAccess($objCalendars->id, 'calendars')) {
                $arrCalendars[$objCalendars->id] = $objCalendars->title;
            }
        }

        return $arrCalendars;
    }

    /**
     * Get all calendars and return them as array.
     *
     * @return array<mixed>
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.cal_holiday.options')]
    public function getHolidays(): array
    {
        if (!$this->User->isAdmin && !\is_array($this->User->calendars)) {
            return [];
        }

        $arrCalendars = [];
        $objCalendars = $this->Database->execute('SELECT id, title FROM tl_calendar WHERE isHolidayCal = 1 ORDER BY title');

        while ($objCalendars->next()) {
            if ($this->User->isAdmin || $this->User->hasAccess($objCalendars->id, 'calendars')) {
                $arrCalendars[$objCalendars->id] = $objCalendars->title;
            }
        }

        return $arrCalendars;
    }
}
