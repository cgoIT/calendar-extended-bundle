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

use Contao\ArrayUtil;

// foreach
// ($GLOBALS['TL_DCA']['tl_calendar_events']['config']['onsubmit_callback'] as $k
// => $v) {    if ('tl_calendar_events' === $v[0] && 'adjustTime' === $v[1]) {
// unset($GLOBALS['TL_DCA']['tl_calendar_events']['config']['onsubmit_callback'][$k]);
// 
// ArrayUtil::arrayInsert($GLOBALS['TL_DCA']['tl_calendar_events']['config']['onsubmit_callback'],
// 0, [            ['tl_calendar_events_ext', 'adjustTime'],
// ['tl_calendar_events_ext', 'checkOverlapping'],        ]);    }

foreach ($GLOBALS['TL_DCA']['tl_calendar_events']['palettes'] as &$palette) {
    $palette = str_replace('endDate', 'endDate,hideOnWeekend', (string) $palette);
}

$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'] = str_replace(
    'addTime,',
    'showOnFreeDay,addTime,',
    (string) $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'],
);

$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['article'] = str_replace(
    'addTime,',
    'showOnFreeDay,addTime,',
    (string) $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['article'],
);

$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['internal'] = str_replace(
    'addTime,',
    'showOnFreeDay,addTime,',
    (string) $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['internal'],
);

$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['external'] = str_replace(
    'addTime,',
    'showOnFreeDay,addTime,',
    (string) $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['external'],
);

if (class_exists('leads\leads')) {
    $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'] = str_replace(
        '{recurring_legend},recurring;',
        '{location_legend},location_name,location_str,location_plz,location_ort;{contact_legend},location_link,location_contact,location_mail;{regform_legend},useRegistration;{recurring_legend},recurring;{recurring_legend_ext},recurringExt;{repeatFixedDates_legend},repeatFixedDates;{exception_legend},useExceptions;',
        (string) $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'],
    );
    $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['article'] = str_replace(
        '{recurring_legend},recurring;',
        '{location_legend},location_name,location_str,location_plz,location_ort;{contact_legend},location_link,location_contact,location_mail;{regform_legend},useRegistration;{recurring_legend},recurring;{recurring_legend_ext},recurringExt;{repeatFixedDates_legend},repeatFixedDates;{exception_legend},useExceptions;',
        (string) $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['article'],
    );
    $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['internal'] = str_replace(
        '{recurring_legend},recurring;',
        '{location_legend},location_name,location_str,location_plz,location_ort;{contact_legend},location_link,location_contact,location_mail;{regform_legend},useRegistration;{recurring_legend},recurring;{recurring_legend_ext},recurringExt;{repeatFixedDates_legend},repeatFixedDates;{exception_legend},useExceptions;',
        (string) $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['internal'],
    );
    $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['external'] = str_replace(
        '{recurring_legend},recurring;',
        '{location_legend},location_name,location_str,location_plz,location_ort;{contact_legend},location_link,location_contact,location_mail;{regform_legend},useRegistration;{recurring_legend},recurring;{recurring_legend_ext},recurringExt;{repeatFixedDates_legend},repeatFixedDates;{exception_legend},useExceptions;',
        (string) $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['external'],
    );
} else {
    $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'] = str_replace(
        '{recurring_legend},recurring;',
        '{location_legend},location_name,location_str,location_plz,location_ort;{contact_legend},location_link,location_contact,location_mail;{recurring_legend},recurring;{recurring_legend_ext},recurringExt;{repeatFixedDates_legend},repeatFixedDates;{exception_legend},useExceptions;',
        (string) $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'],
    );
    $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['article'] = str_replace(
        '{recurring_legend},recurring;',
        '{location_legend},location_name,location_str,location_plz,location_ort;{contact_legend},location_link,location_contact,location_mail;{recurring_legend},recurring;{recurring_legend_ext},recurringExt;{repeatFixedDates_legend},repeatFixedDates;{exception_legend},useExceptions;',
        (string) $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['article'],
    );
    $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['internal'] = str_replace(
        '{recurring_legend},recurring;',
        '{location_legend},location_name,location_str,location_plz,location_ort;{contact_legend},location_link,location_contact,location_mail;{recurring_legend},recurring;{recurring_legend_ext},recurringExt;{repeatFixedDates_legend},repeatFixedDates;{exception_legend},useExceptions;',
        (string) $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['internal'],
    );
    $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['external'] = str_replace(
        '{recurring_legend},recurring;',
        '{location_legend},location_name,location_str,location_plz,location_ort;{contact_legend},location_link,location_contact,location_mail;{recurring_legend},recurring;{recurring_legend_ext},recurringExt;{repeatFixedDates_legend},repeatFixedDates;{exception_legend},useExceptions;',
        (string) $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['external'],
    );
}

// change the default palettes
ArrayUtil::arrayInsert($GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['__selector__'], 99, 'recurringExt');
ArrayUtil::arrayInsert($GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['__selector__'], 99, 'useExceptions');
ArrayUtil::arrayInsert($GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['__selector__'], 99, 'useRegistration');

// change the default palettes
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['recurring'] = str_replace(
    'repeatEach,recurrences',
    'hideOnWeekend,repeatEach,recurrences,repeatWeekday,repeatEnd',
    (string) $GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['recurring'],
);

// change the default palettes
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['addTime'] = str_replace(
    'startTime,endTime',
    'ignoreEndTime,startTime,endTime',
    (string) $GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['addTime'],
);

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['repeatWeekday'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['repeatWeekday'],
    'exclude' => true,
    'filter' => true,
    'inputType' => 'checkbox',
    'options' => [1, 2, 3, 4, 5, 6, 0],
    'reference' => &$GLOBALS['TL_LANG']['DAYS'],
    'eval' => ['multiple' => true, 'tl_class' => 'clr'],
    'sql' => "varchar(128) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['repeatFixedDates'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['repeatFixedDates'],
    'exclude' => true,
    'inputType' => 'multiColumnWizard',
    'eval' => [
        'columnsCallback' => [CalendarEventsCallbacks::class, 'listFixedDates'],
        'buttons' => ['up' => false, 'down' => false],
    ],
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['ignoreEndTime'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['ignoreEndTime'],
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'long clr'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['useExceptions'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['useExceptions'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true, 'tl_class' => 'long clr'],
    'sql' => "char(1) NOT NULL default ''",
    ´];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['showOnFreeDay'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['showOnFreeDay'],
    'exclude' => true,
    'filter' => false,
    'inputType' => 'checkbox',
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['weekday'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['weekday'],
    'exclude' => true,
    'filter' => true,
    'inputType' => 'select',
    'options' => [0, 1, 2, 3, 4, 5, 6],
    'reference' => &$GLOBALS['TL_LANG']['DAYS'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['recurring'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['recurring'],
    'exclude' => true,
    'filter' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true, 'tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['repeatEach'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['repeatEach'],
    'default' => 1,
    'exclude' => true,
    'inputType' => 'timePeriod',
    'options' => ['days', 'weeks', 'months', 'years'],
    'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
    'eval' => ['mandatory' => true, 'rgxp' => 'natural', 'minval' => 1, 'tl_class' => 'w50'],
    'sql' => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['hideOnWeekend'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['hideOnWeekend'],
    'exclude' => true,
    'filter' => false,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

// change the default palettes
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['recurringExt'] = 'repeatEachExt,recurrences,repeatEnd';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['useExceptions'] = 'repeatExceptionsInt,repeatExceptionsPer,repeatExceptions';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['useRegistration'] = 'regconfirm,regperson,regform,regstartdate,regenddate';

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['useRegistration'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['useRegistration'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true, 'tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['regconfirm'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['regconfirm'],
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['regperson'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['regperson'],
    'default' => 0,
    'exclude' => true,
    'filter' => false,
    'inputType' => 'multiColumnWizard',
    'eval' => [
        'tl_class' => 'w50 clr',
        'columnsCallback' => [CalendarEventsCallbacks::class, 'setMaxPerson'],
        'buttons' => ['add' => false, 'new' => false, 'up' => false, 'down' => false, 'delete' => false, 'copy' => false],
    ],
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['regform'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['regform'],
    'exclude' => true,
    'filter' => true,
    'inputType' => 'select',
    'eval' => ['mandatory' => true, 'tl_class' => 'w50 m12', 'includeBlankOption' => true, 'chosen' => true],
    'sql' => "int(10) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['regstartdate'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['regstartdate'],
    'default' => time(),
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'datim', 'mandatory' => false, 'doNotCopy' => true, 'datepicker' => true, 'tl_class' => 'w50 wizard'],
    'sql' => 'int(10) unsigned NULL',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['regenddate'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['regenddate'],
    'default' => time(),
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'datim', 'mandatory' => false, 'doNotCopy' => true, 'datepicker' => true, 'tl_class' => 'w50 wizard'],
    'sql' => 'int(10) unsigned NULL',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['recurringExt'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['recurringExt'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true, 'tl_class' => 'long clr'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['location_name'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['location_name'],
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['location_str'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['location_str'],
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['location_plz'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['location_plz'],
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'digit', 'maxlength' => 10, 'tl_class' => 'w50'],
    'sql' => "varchar(10) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['location_ort'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['location_ort'],
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['location_link'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['location_link'],
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'url', 'maxlength' => 255, 'tl_class' => 'long'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['location_contact'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['location_contact'],
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['location_mail'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['location_mail'],
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'email', 'maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

// new repeat options for events
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['repeatEachExt'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['repeatEachExt'],
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
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['recurrences'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'rgxp' => 'digit', 'submitOnChange' => true, 'tl_class' => 'w50'],
    'sql' => "smallint(5) unsigned NOT NULL default '0'",
];

// list of exceptions
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['repeatExceptions'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['repeatExceptions'],
    'exclude' => true,
    'inputType' => 'multiColumnWizard',
    'eval' => [
        'columnsCallback' => [CalendarEventsCallbacks::class, 'listMultiExceptions'],
        'buttons' => ['up' => false, 'down' => false],
    ],
    'sql' => 'blob NULL',
];

// list of exceptions
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['repeatExceptionsInt'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['repeatExceptionsInt'],
    'exclude' => true,
    'inputType' => 'multiColumnWizard',
    'eval' => [
        'columnsCallback' => [CalendarEventsCallbacks::class, 'listMultiExceptions'],
        'buttons' => ['up' => false, 'down' => false],
    ],
    'sql' => 'blob NULL',
];

// list of exceptions
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['repeatExceptionsPer'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['repeatExceptionsPer'],
    'exclude' => true,
    'inputType' => 'multiColumnWizard',
    'eval' => [
        'columnsCallback' => [CalendarEventsCallbacks::class, 'listMultiExceptions'],
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
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['repeatEnd'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['readonly' => true, 'rgxp' => 'date', 'tl_class' => 'clr'],
    'sql' => "int(10) unsigned NOT NULL default '0'",
];

// /* * Class tl_calendar_events_ext. * * Provide miscellaneous methods that are
// used by the data configuration array. * * @copyright  Kester Mielke 2009-2018
// */ class tl_calendar_events extends Backend {    /**     * Import the back end
// user object.     */    public function __construct()    {
// $this->import('BackendUser', 'User');    } public function
// getWeekday($varValue)    {        if ('' === $varValue) {       return 9;
//   }         return $varValue;    }
