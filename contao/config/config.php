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

use Cgoit\CalendarExtendedBundle\Classes\EventUrls;
use Cgoit\CalendarExtendedBundle\Classes\TimePeriodExt;
use Cgoit\CalendarExtendedBundle\Modules\ModuleCalendar;
use Cgoit\CalendarExtendedBundle\Modules\ModuleEventlist;
use Cgoit\CalendarExtendedBundle\Modules\ModuleEventMenu;
use Cgoit\CalendarExtendedBundle\Modules\ModuleEventReader;
use Cgoit\CalendarExtendedBundle\Modules\ModuleEventRegistration;
use Cgoit\CalendarExtendedBundle\Modules\ModuleFullcalendar;
use Cgoit\CalendarExtendedBundle\Modules\ModuleTimeTable;
use Cgoit\CalendarExtendedBundle\Modules\ModuleYearView;
use Contao\ArrayUtil;

/*
 * This file is part of cgoit\calendar-extended-bundle.
 *
 * (c) Kester Mielke
 * (c) Carsten Götzinger
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_CONFIG']['tl_calendar_events']['maxRepeatExceptions'] = 365;

/*
 * the range of days for the move date option
 * 14 means from -14 days to 14 days
 */
$GLOBALS['TL_CONFIG']['tl_calendar_events']['moveDays'] = 7;

/*
 * the start, end and interval of times for the move time option
 * 00:00|23:59|30 means start at 00:00 and add 30 min. to the time
 *
 * this will be used for start and end time
 *
 * examples
 * interval 15: 00:15, 00:30, 00:45, 01:00...
 * interval 30: 00:00, 00:30, 01:00, 01:30...
 */
$GLOBALS['TL_CONFIG']['tl_calendar_events']['moveTimes'] = '10:00|22:00|30';

$GLOBALS['TL_LANG']['DAYS']['sunday'] = 0;
$GLOBALS['TL_LANG']['DAYS']['monday'] = 1;
$GLOBALS['TL_LANG']['DAYS']['tuesday'] = 2;
$GLOBALS['TL_LANG']['DAYS']['wednesday'] = 3;
$GLOBALS['TL_LANG']['DAYS']['thursday'] = 4;
$GLOBALS['TL_LANG']['DAYS']['friday'] = 5;
$GLOBALS['TL_LANG']['DAYS']['saturday'] = 6;

$GLOBALS['TL_CONFIG']['tl_calendar_events']['weekdays'][0] = 'sunday';
$GLOBALS['TL_CONFIG']['tl_calendar_events']['weekdays'][1] = 'monday';
$GLOBALS['TL_CONFIG']['tl_calendar_events']['weekdays'][2] = 'tuesday';
$GLOBALS['TL_CONFIG']['tl_calendar_events']['weekdays'][3] = 'wednesday';
$GLOBALS['TL_CONFIG']['tl_calendar_events']['weekdays'][4] = 'thursday';
$GLOBALS['TL_CONFIG']['tl_calendar_events']['weekdays'][5] = 'friday';
$GLOBALS['TL_CONFIG']['tl_calendar_events']['weekdays'][6] = 'saturday';

// Event Filter
$GLOBALS['TL_CONFIG']['tl_calendar_events']['filter']['title'] = [];
$GLOBALS['TL_CONFIG']['tl_calendar_events']['filter']['location_name'] = [];
$GLOBALS['TL_CONFIG']['tl_calendar_events']['filter']['location_str'] = [];
$GLOBALS['TL_CONFIG']['tl_calendar_events']['filter']['location_plz'] = [];

/*
 * Front end modules
 */
ArrayUtil::arrayInsert($GLOBALS['FE_MOD'], 99, [
    'events' => [
        'timetable' => ModuleTimeTable::class,
        'yearview' => ModuleYearView::class,
        'evr_registration' => ModuleEventRegistration::class,
        'fullcalendar' => ModuleFullcalendar::class,
    ],
]);

// Replace Contao Module
$GLOBALS['FE_MOD']['events']['calendar'] = ModuleCalendar::class;
$GLOBALS['FE_MOD']['events']['eventlist'] = ModuleEventlist::class;
$GLOBALS['FE_MOD']['events']['eventmenu'] = ModuleEventMenu::class;
$GLOBALS['FE_MOD']['events']['eventreader'] = ModuleEventReader::class;

/*
 * BACK END FORM FIELDS
 */

ArrayUtil::arrayInsert($GLOBALS['BE_FFL'], 99, [
    'timePeriodExt' => TimePeriodExt::class,
]);

// config.php
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['event_registration'] = [
    // Type
    'registration_status' => [
        'recipients' => ['recipient_email', 'admin_email'],
        'email_subject' => ['page_title', 'recipient_*'],
        'email_text' => ['recipient_*', 'raw_data'],
        'email_html' => ['recipient_*', 'raw_data'],
        'email_sender_name' => ['admin_email', 'page_title'],
        'email_sender_address' => ['admin_email'],
        'email_recipient_cc' => ['recipient_email'],
        'email_recipient_bcc' => ['recipient_email'],
        'email_replyTo' => ['recipient_email'],
        'file_name' => ['recipient_email'],
        'file_content' => ['recipient_email'],
    ],
];

/*
 * Event Hook
 */
$GLOBALS['TL_HOOKS']['getAllEvents'][] = [EventUrls::class, 'modifyEventUrl'];