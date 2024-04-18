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

use Cgoit\CalendarExtendedBundle\EventListener\DataContainer\CalendarEventsMCWCallbacks;
use Contao\CoreBundle\DataContainer\PaletteManipulator;

foreach ($GLOBALS['TL_DCA']['tl_calendar_events']['palettes'] as $name => $palette) {
    if (!is_string($palette)) {
        continue;
    }

    PaletteManipulator::create()->addField('hideOnWeekend', 'endDate')
        ->applyToPalette($name, 'tl_calendar_events')
    ;
}

foreach (['default', 'article', 'internal', 'external'] as $palette) {
    PaletteManipulator::create()->addField('showOnFreeDay', 'addTime', PaletteManipulator::POSITION_BEFORE)
        ->applyToPalette((string) $palette, 'tl_calendar_events')
    ;
    PaletteManipulator::create()->addLegend('contact_legend', 'recurring_legend', PaletteManipulator::POSITION_BEFORE)
        ->addField('location_link', 'contact_legend', PaletteManipulator::POSITION_APPEND)
        ->addField('location_contact', 'location_link')
        ->addField('location_mail', 'location_contact')
        ->applyToPalette((string) $palette, 'tl_calendar_events')
    ;
    PaletteManipulator::create()->addLegend('location_legend', 'contact_legend', PaletteManipulator::POSITION_BEFORE)
        ->addField('location_name', 'location_legend', PaletteManipulator::POSITION_APPEND)
        ->addField('location_str', 'location_name')
        ->addField('location_ort', 'location_str')
        ->applyToPalette((string) $palette, 'tl_calendar_events')
    ;
    PaletteManipulator::create()->addLegend('recurring_legend_ext', 'recurring_legend')
        ->addField('recurringExt', 'recurring_legend_ext', PaletteManipulator::POSITION_APPEND)
        ->applyToPalette((string) $palette, 'tl_calendar_events')
    ;
    PaletteManipulator::create()->addLegend('repeatFixedDates_legend', 'recurring_legend_ext')
        ->addField('repeatFixedDates', 'repeatFixedDates_legend', PaletteManipulator::POSITION_APPEND)
        ->applyToPalette((string) $palette, 'tl_calendar_events')
    ;
}

$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['__selector__'] = array_merge(
    ['recurringExt', 'useExceptions'],
    $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['__selector__'],
);

PaletteManipulator::create()->addField('hideOnWeekend', 'recurring')
    ->addField('repeatWeekday', 'recurrences', PaletteManipulator::POSITION_APPEND)
    ->addField('repeatEnd', 'repeatWeekday')
    ->applyToSubpalette('recurring', 'tl_calendar_events')
;

PaletteManipulator::create()->addField('ignoreEndTime', 'startTime', PaletteManipulator::POSITION_BEFORE)
    ->applyToSubpalette('addTime', 'tl_calendar_events')
;

$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['recurringExt'] = 'repeatEachExt,recurrences,repeatEnd';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['useExceptions'] = 'repeatExceptionsInt,repeatExceptionsPer,repeatExceptions';

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['repeatWeekday'] = [
    'exclude' => true,
    'filter' => true,
    'inputType' => 'checkbox',
    'options' => [1, 2, 3, 4, 5, 6, 0],
    'reference' => &$GLOBALS['TL_LANG']['DAYS'],
    'eval' => ['multiple' => true, 'tl_class' => 'w50'],
    'sql' => "varchar(128) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['repeatFixedDates'] = [
    'exclude' => true,
    'inputType' => 'multiColumnWizard',
    'eval' => [
        'columnsCallback' => [CalendarEventsMCWCallbacks::class, 'listFixedDates'],
        'buttons' => ['up' => false, 'down' => false],
    ],
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['ignoreEndTime'] = [
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'long clr'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['useExceptions'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true, 'tl_class' => 'long clr'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['showOnFreeDay'] = [
    'exclude' => true,
    'filter' => false,
    'inputType' => 'checkbox',
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['weekday'] = [
    'exclude' => true,
    'filter' => true,
    'inputType' => 'select',
    'options' => [0, 1, 2, 3, 4, 5, 6],
    'reference' => &$GLOBALS['TL_LANG']['DAYS'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['recurring'] = [
    'exclude' => true,
    'filter' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true, 'tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['repeatEach'] = [
    'default' => 1,
    'exclude' => true,
    'inputType' => 'timePeriod',
    'options' => ['days', 'weeks', 'months', 'years'],
    'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
    'eval' => ['mandatory' => true, 'rgxp' => 'natural', 'minval' => 1, 'tl_class' => 'clr w50'],
    'sql' => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['hideOnWeekend'] = [
    'exclude' => true,
    'filter' => false,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['recurringExt'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true, 'tl_class' => 'long clr'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['location_name'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['location_str'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['location_plz'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'digit', 'maxlength' => 10, 'tl_class' => 'w50'],
    'sql' => "varchar(10) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['location_ort'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['location_link'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'url', 'maxlength' => 255, 'tl_class' => 'long'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['location_contact'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['location_mail'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'email', 'maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

// new repeat options for events
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['repeatEachExt'] = [
    'exclude' => true,
    'inputType' => 'timePeriodExt',
    'options' => [
        ['first', 'second', 'third', 'fourth', 'fifth', 'last'],
        ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
    ],
    'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
    'eval' => ['mandatory' => true, 'tl_class' => 'w50'],
    'default' => &$GLOBALS['TL_CONFIG']['tl_calendar_events']['weekdays'][date('w', time())],
    'sql' => 'text NULL',
];

// added submitOnChange to recurrences
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['recurrences'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'rgxp' => 'digit', 'submitOnChange' => true, 'tl_class' => 'w50'],
    'sql' => "smallint(5) unsigned NOT NULL default '0'",
];

// list of exceptions
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['repeatExceptions'] = [
    'exclude' => true,
    'inputType' => 'multiColumnWizard',
    'eval' => [
        'columnsCallback' => [CalendarEventsMCWCallbacks::class, 'listMultiExceptions'],
        'buttons' => ['up' => false, 'down' => false],
    ],
    'sql' => 'blob NULL',
];

// list of exceptions
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['repeatExceptionsInt'] = [
    'exclude' => true,
    'inputType' => 'multiColumnWizard',
    'eval' => [
        'columnsCallback' => [CalendarEventsMCWCallbacks::class, 'listMultiExceptions'],
        'buttons' => ['up' => false, 'down' => false],
    ],
    'sql' => 'blob NULL',
];

// list of exceptions
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['repeatExceptionsPer'] = [
    'exclude' => true,
    'inputType' => 'multiColumnWizard',
    'eval' => [
        'columnsCallback' => [CalendarEventsMCWCallbacks::class, 'listMultiExceptions'],
        'buttons' => ['up' => false, 'down' => false],
    ],
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['repeatDates'] = [
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['allRecurrences'] = [
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['exceptionList'] = [
    'sql' => 'blob NULL',
];

// display the end of the recurrences (read only)
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['repeatEnd'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['readonly' => true, 'rgxp' => 'date', 'tl_class' => 'clr'],
    'sql' => "int(10) unsigned NOT NULL default '0'",
];
