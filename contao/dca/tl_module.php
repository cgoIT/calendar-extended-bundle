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

// Palette for calendar
$GLOBALS['TL_DCA']['tl_module']['palettes']['calendar'] = str_replace(
    ';{redirect_legend}',
    ';{config_ext_legend},cal_holiday,show_holiday,ignore_urlparameter;{redirect_legend}',
    (string) $GLOBALS['TL_DCA']['tl_module']['palettes']['calendar'],
);
$GLOBALS['TL_DCA']['tl_module']['palettes']['calendar'] .= ';{filter_legend},filter_fields';

// Palette for timetable
$GLOBALS['TL_DCA']['tl_module']['palettes']['timetable'] = $GLOBALS['TL_DCA']['tl_module']['palettes']['calendar'];
$GLOBALS['TL_DCA']['tl_module']['palettes']['timetable'] = str_replace(
    ';{redirect_legend}',
    ',showDate,hideEmptyDays,use_navigation,linkCurrent,cal_times,cal_times_range,cellhight;{redirect_legend}',
    $GLOBALS['TL_DCA']['tl_module']['palettes']['timetable'],
);

// Palette for yearview
$GLOBALS['TL_DCA']['tl_module']['palettes']['yearview'] = $GLOBALS['TL_DCA']['tl_module']['palettes']['calendar'];
$GLOBALS['TL_DCA']['tl_module']['palettes']['yearview'] = str_replace(
    ';{redirect_legend}',
    ',use_horizontal,use_navigation,linkCurrent;{protected_legend:hide}',
    (string) $GLOBALS['TL_DCA']['tl_module']['palettes']['yearview'],
);

// Palette for eventlist
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventlist'] = str_replace(
    ';{template_legend:hide}',
    ';{config_ext_legend},cal_holiday,show_holiday,ignore_urlparameter,cal_format_ext,displayDuration,range_date,showRecurrences,hide_started,pubTimeRecurrences,showOnlyNext;{template_legend:hide}',
    (string) $GLOBALS['TL_DCA']['tl_module']['palettes']['eventlist'],
);
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventlist'] .= ';{filter_legend},filter_fields';

// Palette for eventreader
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventreader'] = str_replace(
    '{config_legend},cal_calendar',
    '{config_legend},cal_calendar,cal_holiday',
    (string) $GLOBALS['TL_DCA']['tl_module']['palettes']['eventreader'],
);

// Palette for registration
$GLOBALS['TL_DCA']['tl_module']['palettes']['evr_registration'] = '{title_legend},name,headline,type;{registration_legend},nc_notification,regtype;{filter_legend},filter_fields';

/*
 * Add fields to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['fields']['regtype'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['regtype'],
    'exclude' => true,
    'filter' => true,
    'default' => 0,
    'inputType' => 'radio',
    'options' => [1, 0],
    'reference' => &$GLOBALS['TL_LANG']['tl_module']['regtypes'],
    'eval' => ['tl_class' => 'w50 m12', 'chosen' => true],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cal_calendar'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cal_calendar'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['mandatory' => true, 'multiple' => true],
    'sql' => 'text NULL',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cal_holiday'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cal_holiday'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['mandatory' => false, 'multiple' => true, 'tl_class' => 'long'],
    'sql' => 'text NULL',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['show_holiday'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['show_holiday'],
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['ignore_urlparameter'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['ignore_urlparameter'],
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['pubTimeRecurrences'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['pubTimeRecurrences'],
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cal_format_ext'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cal_format_ext'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(128) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['displayDuration'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['displayDuration'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(128) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['showOnlyNext'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['showOnlyNext'],
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['showRecurrences'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['showRecurrences'],
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['use_horizontal'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['use_horizontal'],
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['use_navigation'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['use_navigation'],
    'default' => 1,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['showDate'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['showDate'],
    'default' => 1,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['linkCurrent'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['linkCurrent'],
    'default' => 1,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['hideEmptyDays'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['hideEmptyDays'],
    'default' => 1,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cal_times'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cal_times'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

// list of exceptions
$GLOBALS['TL_DCA']['tl_module']['fields']['cal_times_range'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cal_times_range'],
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
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cellhight'],
    'default' => 60,
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(10) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['hide_started'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['hide_started'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

// list of exceptions
$GLOBALS['TL_DCA']['tl_module']['fields']['range_date'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['range_date'],
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
// Palette for fullcalendar
$GLOBALS['TL_DCA']['tl_module']['palettes']['fullcalendar'] = '
    {title_legend},name,headline,type;
    {config_legend},cal_calendar;
    {template_legend:hide},cal_ctemplate,cal_startDay,fc_editable,businessHours,weekNumbers,weekNumbersWithinDays;
    {protected_legend:hide},protected;
    {expert_legend:hide},guests,cssID,space';

$GLOBALS['TL_DCA']['tl_module']['fields']['cal_ctemplate'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cal_ctemplate'],
    'default' => 'cal_fc_default',
    'exclude' => true,
    'inputType' => 'select',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(32) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['eventLimit'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['eventLimit'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'clr w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['fc_editable'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['fc_editable'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'clr w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['businessHours'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['businessHours'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['weekNumbers'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['weekNumbers'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['weekNumbersWithinDays'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['weekNumbersWithinDays'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

/*
 * Filter
 */
$GLOBALS['TL_DCA']['tl_module']['fields']['filter_fields'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['filter_fields'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'long', 'multiple' => true],
    'sql' => 'blob NULL',
];
