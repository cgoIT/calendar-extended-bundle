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

use Cgoit\CalendarExtendedBundle\Controller\Module\ModuleCalendar;
use Cgoit\CalendarExtendedBundle\Controller\Module\ModuleEventlist;
use Cgoit\CalendarExtendedBundle\Controller\Module\ModuleEventMenu;
use Cgoit\CalendarExtendedBundle\Controller\Module\ModuleEventReader;
use Cgoit\CalendarExtendedBundle\Controller\Module\ModuleFullcalendar;
use Cgoit\CalendarExtendedBundle\Controller\Module\ModuleTimeTable;
use Cgoit\CalendarExtendedBundle\Controller\Module\ModuleYearView;
use Cgoit\CalendarExtendedBundle\Widget\TimePeriodExt;

/*
 * This file is part of cgoit\calendar-extended-bundle.
 *
 * (c) Kester Mielke
 * (c) Carsten Götzinger
 *
 * @license LGPL-3.0-or-later
 */

/*
 * Front end modules
 */
$GLOBALS['FE_MOD']['events']['timetable'] = ModuleTimeTable::class;
$GLOBALS['FE_MOD']['events']['yearview'] = ModuleYearView::class;
$GLOBALS['FE_MOD']['events']['fullcalendar'] = ModuleFullcalendar::class;

// Replace Contao Module
//$GLOBALS['FE_MOD']['events']['calendar'] = ModuleCalendar::class;
$GLOBALS['FE_MOD']['events']['eventlist'] = ModuleEventlist::class;
$GLOBALS['FE_MOD']['events']['eventmenu'] = ModuleEventMenu::class;
//$GLOBALS['FE_MOD']['events']['eventreader'] = ModuleEventReader::class;

/*
 * BACK END FORM FIELDS
 */
$GLOBALS['BE_FFL']['timePeriodExt'] = TimePeriodExt::class;
