<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\calendar-extended-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) Kester Mielke
 * @copyright  Copyright (c) cgoIT
 * @author     Kester Mielke
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

/*
 * Fields
 */
$GLOBALS['TL_LANG']['tl_module']['use_horizontal'] = ['Display horizontal', 'Months will be displayed horizontal.'];
$GLOBALS['TL_LANG']['tl_module']['use_navigation'] = ['Display navigation', 'Week navigation will be displayed if checked'];
$GLOBALS['TL_LANG']['tl_module']['showDate'] = ['Display date', 'Date of weekday will be displayed if checked'];
$GLOBALS['TL_LANG']['tl_module']['showOnlyNext'] = ['Next recurrence only', 'Only the next recurrence will be displayed (for recurrences only).'];
$GLOBALS['TL_LANG']['tl_module']['linkCurrent'] = ['Display link "current date"', 'Link to jump to current date will be displayed if checked'];
$GLOBALS['TL_LANG']['tl_module']['hideEmptyDays'] = ['Hide empty days', 'Weekdays without events will not be displayed if checked'];
$GLOBALS['TL_LANG']['tl_module']['hide_holiday'] = ['Hide holidays', 'Holidays and free days will not be displayed.'];
$GLOBALS['TL_LANG']['tl_module']['cal_times'] = ['Display times', 'Times will be displayed and the events with the same time will be displayed on same level.'];
$GLOBALS['TL_LANG']['tl_module']['businessHours'] = ['Business hours', 'Show business hours.'];
$GLOBALS['TL_LANG']['tl_module']['businessDays'] = ['Business days', 'Days on which business hours apply.'];
$GLOBALS['TL_LANG']['tl_module']['businessDayStart'] = ['Business hours from', 'Start of working hours.'];
$GLOBALS['TL_LANG']['tl_module']['businessDayEnd'] = ['Business hours to', 'End of working hours.'];
$GLOBALS['TL_LANG']['tl_module']['weekNumbers'] = ['Week numbers', 'Determines if week numbers should be displayed on the calendar.'];

$GLOBALS['TL_LANG']['tl_module']['cal_times_range'] = ['Timeframe of timetable.', 'Displays the time al label on the left side.'];
$GLOBALS['TL_LANG']['tl_module']['time_range_from'] = ['Time from', 'Starttime for timetalble.'];
$GLOBALS['TL_LANG']['tl_module']['time_range_to'] = ['Time to', 'Endtime for timetable.'];

$GLOBALS['TL_LANG']['tl_module']['cellheight'] = ['Row height of events', 'Height of the cell of an event in px per hour. Standard is 1px per minute and thus 60px at an interval of 1 hour.'];

$GLOBALS['TL_LANG']['tl_module']['filter_fields'] = ['Filtering events', 'Select fields that can be filtered in the frontend template.'];
$GLOBALS['TL_LANG']['tl_module']['filter_legend'] = 'Filter';

$GLOBALS['TL_LANG']['tl_module']['cal_fcFormat'] = ['Initial view', 'Select the initial view for your calendar.'];
$GLOBALS['TL_LANG']['tl_module']['cal_fc_month'] = 'Monthly overview';
$GLOBALS['TL_LANG']['tl_module']['cal_fc_week'] = 'Weekly overview';
$GLOBALS['TL_LANG']['tl_module']['cal_fc_day'] = 'Daily overview';
$GLOBALS['TL_LANG']['tl_module']['cal_fc_list'] = 'List view';

$GLOBALS['TL_LANG']['tl_module']['confirm_drop'] = 'Are you sure you want to move the event?';
$GLOBALS['TL_LANG']['tl_module']['confirm_resize'] = 'Are you sure you want to change the event?';
$GLOBALS['TL_LANG']['tl_module']['fetch_error'] = 'Error while loading events!';
