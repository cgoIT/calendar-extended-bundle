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

/*
 * BACK END FORM FIELDS
 */
$GLOBALS['BE_FFL']['timePeriodExt'] = TimePeriodExt::class;
