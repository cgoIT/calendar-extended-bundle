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

use Cgoit\CalendarExtendedBundle\EventListener\DataContainer\ModuleCallbacks;
use Contao\CoreBundle\DataContainer\PaletteManipulator;

// Palette for calendar
PaletteManipulator::create()->addLegend('config_ext_legend', 'redirect_legend', PaletteManipulator::POSITION_BEFORE)
    ->addField('cal_holiday', 'config_ext_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('show_holiday', 'cal_holiday')
    ->addField('ignore_urlparameter', 'show_holiday')
    ->applyToPalette('calendar', 'tl_module')
;
PaletteManipulator::create()->addLegend('filter_legend', 'template_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('filter_fields', 'filter_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('calendar', 'tl_module')
;

// Palette for timetable
$GLOBALS['TL_DCA']['tl_module']['palettes']['timetable'] = $GLOBALS['TL_DCA']['tl_module']['palettes']['calendar'];
PaletteManipulator::create()->addField('showDate', 'config_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('hideEmptyDays', 'showDate')
    ->addField('use_navigation', 'hideEmptyDays')
    ->addField('linkCurrent', 'use_navigation')
    ->addField('cal_times', 'linkCurrent')
    ->addField('cal_times_range', 'cal_times')
    ->addField('cellhight', 'cal_times_range')
    ->applyToPalette('timetable', 'tl_module')
;

// Palette for yearview
$GLOBALS['TL_DCA']['tl_module']['palettes']['yearview'] = $GLOBALS['TL_DCA']['tl_module']['palettes']['calendar'];
PaletteManipulator::create()->addField('use_horizontal', 'config_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('use_navigation', 'use_horizontal')
    ->addField('linkCurrent', 'use_navigation')
    ->applyToPalette('yearview', 'tl_module')
;

// Palette for eventlist
PaletteManipulator::create()->addLegend('config_ext_legend', 'config_legend')
    ->addField('cal_holiday', 'config_ext_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('show_holiday', 'cal_holiday')
    ->addField('ignore_urlparameter', 'show_holiday')
    ->addField('cal_format_ext', 'ignore_urlparameter')
    ->addField('displayDuration', 'cal_format_ext')
    ->addField('range_date', 'displayDuration')
    ->addField('showRecurrences', 'range_date')
    ->addField('hide_started', 'showRecurrences')
    ->addField('pubTimeRecurrences', 'hide_started')
    ->addField('showOnlyNext', 'pubTimeRecurrences')
    ->applyToPalette('eventlist', 'tl_module')
;
PaletteManipulator::create()->addLegend('filter_legend', 'template_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('filter_fields', 'filter_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('eventlist', 'tl_module')
;

// Palette for eventreader
PaletteManipulator::create()->addField('cal_holiday', 'config_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('eventreader', 'tl_module')
;

$GLOBALS['TL_DCA']['tl_module']['palettes']['fullcalendar'] = '{title_legend},name,headline,type;{config_legend},cal_calendar;{template_legend:hide},cal_ctemplate,cal_startDay,fc_editable,businessHours,weekNumbers,weekNumbersWithinDays;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';

/*
 * Add fields to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['fields']['cal_calendar'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['mandatory' => true, 'multiple' => true],
    'sql' => 'text NULL',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cal_holiday'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['mandatory' => false, 'multiple' => true, 'tl_class' => 'long'],
    'sql' => 'text NULL',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['show_holiday'] = [
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['ignore_urlparameter'] = [
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['pubTimeRecurrences'] = [
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cal_format_ext'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(128) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['displayDuration'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(128) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['showOnlyNext'] = [
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['showRecurrences'] = [
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['use_horizontal'] = [
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['use_navigation'] = [
    'default' => 1,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['showDate'] = [
    'default' => 1,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['linkCurrent'] = [
    'default' => 1,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['hideEmptyDays'] = [
    'default' => 1,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cal_times'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

// list of exceptions
$GLOBALS['TL_DCA']['tl_module']['fields']['cal_times_range'] = [
    'exclude' => true,
    'inputType' => 'multiColumnWizard',
    'eval' => [
        'tl_class' => 'clr w50',
        'columnsCallback' => [ModuleCallbacks::class, 'getTimeRange'],
        'buttons' => ['up' => false, 'down' => false, 'copy' => false],
    ],
    'sql' => 'text NULL',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cellhight'] = [
    'default' => 60,
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(10) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['hide_started'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

// list of exceptions
$GLOBALS['TL_DCA']['tl_module']['fields']['range_date'] = [
    'exclude' => true,
    'inputType' => 'multiColumnWizard',
    'eval' => [
        'columnsCallback' => [ModuleCallbacks::class, 'getRange'],
        'buttons' => ['up' => false, 'down' => false, 'copy' => false],
        'tl_class' => 'clr',
    ],
    'sql' => 'text NULL',
];

/*
 * Fullcalendar
 */
$GLOBALS['TL_DCA']['tl_module']['fields']['cal_ctemplate'] = [
    'default' => 'cal_fc_default',
    'exclude' => true,
    'inputType' => 'select',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(32) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['eventLimit'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'clr w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['fc_editable'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'clr w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['businessHours'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['weekNumbers'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['weekNumbersWithinDays'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['filter_fields'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'long', 'multiple' => true],
    'sql' => 'blob NULL',
];
