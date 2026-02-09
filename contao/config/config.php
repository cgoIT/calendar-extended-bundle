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

use Cgoit\CalendarExtendedBundle\Controller\Module\ModuleFullCalendar;
use Cgoit\CalendarExtendedBundle\Controller\Module\ModuleTimeTable;
use Cgoit\CalendarExtendedBundle\Controller\Module\ModuleYearView;
use Cgoit\CalendarExtendedBundle\Widget\TimePeriodExt;

$GLOBALS['FE_MOD']['events']['timetable'] = ModuleTimeTable::class;
$GLOBALS['FE_MOD']['events']['yearview'] = ModuleYearView::class;
$GLOBALS['FE_MOD']['events']['fullcalendar'] = ModuleFullCalendar::class;

$GLOBALS['BE_FFL']['timePeriodExt'] = TimePeriodExt::class;
