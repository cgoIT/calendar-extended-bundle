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

use Contao\Backend;
use Contao\BackendUser;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\System;

class ModuleCallbacks extends Backend
{
    /**
     * @param array<string> $arrFilterFields
     */
    public function __construct(
        private readonly array $arrFilterFields,
    ) {
        parent::__construct();
        $this->import(BackendUser::class, 'User');
    }

    /**
     * @return array<mixed>
     */
    #[AsCallback(table: 'tl_module', target: 'fields.filter_fields.options')]
    public function getEventField(): array
    {
        // Load tl_calendar_events data
        $this->loadDataContainer('tl_calendar_events');
        System::loadLanguageFile('tl_calendar_events');

        // Get the event fields
        if (!empty($this->arrFilterFields)) {
            $arr_fields = [];

            foreach ($this->arrFilterFields as $field) {
                $arr_fields[$field] = [];
            }
        } else {
            $arr_fields = $GLOBALS['TL_DCA']['tl_calendar_events']['fields'];
        }

        $event_fields = [];

        foreach ($arr_fields as $k => $v) {
            if (
                \array_key_exists($k, $GLOBALS['TL_LANG']['tl_calendar_events'])
                && \is_array($GLOBALS['TL_LANG']['tl_calendar_events'][$k])
                && !empty($GLOBALS['TL_LANG']['tl_calendar_events'][$k][0])
            ) {
                $label = \array_key_exists('label', $v) && !empty($v['label']) ? $v['label'] : $GLOBALS['TL_LANG']['tl_calendar_events'][$k][0];
                $event_fields[$k] = $label;
            }
        }

        return $event_fields;
    }

    /**
     * @return array<mixed>|null
     */
    public function getTimeRange(): array|null
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

    //    /**     * Get all calendars and return them as array.     *     * @return
    // array<mixed>     */    #[AsCallback(table: 'tl_module', target:
    // 'fields.cal_calendar.options')]    public function getCalendars(): array    {
    // return $this->doGetCalendars();    }     /**     * Get all holiday calendars
    // and return them as array.     *     * @return array<mixed>     */
    // #[AsCallback(table: 'tl_module', target: 'fields.cal_holiday.options')] public
    // function getHolidayCalendars(): array    {        return
    // $this->doGetCalendars(true);    }     /**     * @return array<mixed>     */
    // public function doGetCalendars(bool $holiday = false): array    {        if
    // (!$this->User->isAdmin && !\is_array($this->User->calendars)) { return []; }
    // $arrCalendars = [];        $objCalendars =
    // $this->Database->execute(sprintf('SELECT id, title FROM tl_calendar WHERE
    // isHolidayCal %s 1 ORDER BY title', $holiday ? '=' : '!='));        $security =
    // System::getContainer()->get('security.helper');         while
    // ($objCalendars->next()) {            if
    // ($security->isGranted(ContaoCalendarPermissions::USER_CAN_EDIT_CALENDAR,
    // $objCalendars->id)) {                $arrCalendars[$objCalendars->id] =
    // $objCalendars->title;            }        }         return $arrCalendars;    }
}
