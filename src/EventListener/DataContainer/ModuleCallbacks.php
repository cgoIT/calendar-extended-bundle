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
use Contao\CalendarBundle\Security\ContaoCalendarPermissions;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
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
     * @return array
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.filter_fields.options')]
    public function getEventField()
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
            if (\strlen((string) $GLOBALS['TL_LANG']['tl_calendar_events'][$k][0])) {
                $label = \strlen((string) $v['label']) ? $v['label'] : $GLOBALS['TL_LANG']['tl_calendar_events'][$k][0];
                $event_fields[$k] = $label;
            }
        }

        return $event_fields;
    }

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
     * Get all calendars and return them as array.
     *
     * @return array<mixed>
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.cal_calendar.options', priority: -100)]
    public function getCalendars(): array
    {
        return $this->doGetCalendars();
    }

    /**
     * Get all holiday calendars and return them as array.
     *
     * @return array<mixed>
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.cal_holiday.options')]
    public function getHolidays(): array
    {
        return $this->doGetCalendars(true);
    }

    /**
     * @return array<mixed>
     */
    public function doGetCalendars(bool $holiday = false): array
    {
        if (!$this->User->isAdmin && !\is_array($this->User->calendars)) {
            return [];
        }

        $arrCalendars = [];
        $objCalendars = $this->Database->execute(sprintf('SELECT id, title FROM tl_calendar WHERE isHolidayCal %s 1 ORDER BY title', $holiday ? '=' : '!='));
        $security = System::getContainer()->get('security.helper');

        while ($objCalendars->next()) {
            if ($security->isGranted(ContaoCalendarPermissions::USER_CAN_EDIT_CALENDAR, $objCalendars->id)) {
                $arrCalendars[$objCalendars->id] = $objCalendars->title;
            }
        }

        return $arrCalendars;
    }
}
