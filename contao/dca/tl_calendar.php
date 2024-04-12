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

/*
 * This file is part of cgoit\calendar-extended-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) Kester Mielke
 * @copyright  Copyright (c) 2024, cgoIT
 * @author     Kester Mielke
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

/*
 * Table tl_calendar
 */
$GLOBALS['TL_DCA']['tl_calendar']['palettes']['default'] = str_replace(
    '{title_legend},title,jumpTo;',
    '{title_legend},title,jumpTo,uniqueEvents;{extended_type_legend},isHolidayCal;{extended_legend},bg_color,fg_color;',
    (string) $GLOBALS['TL_DCA']['tl_calendar']['palettes']['default'],
);

ArrayUtil::arrayInsert($GLOBALS['TL_DCA']['tl_calendar']['palettes']['__selector__'], 99, 'isHolidayCal');
ArrayUtil::arrayInsert($GLOBALS['TL_DCA']['tl_calendar']['subpalettes'], 99, ['isHolidayCal' => 'allowEvents']);

// Hinzufügen der Feld-Konfiguration
$GLOBALS['TL_DCA']['tl_calendar']['fields']['bg_color'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['bg_color'],
    'inputType' => 'text',
    'exclude' => true,
    'eval' => ['maxlength' => 6, 'multiple' => true, 'size' => 2, 'colorpicker' => true, 'isHexColor' => true, 'decodeEntities' => true, 'tl_class' => 'w50 wizard'],
    'sql' => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['fg_color'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['fg_color'],
    'inputType' => 'text',
    'exclude' => true,
    'eval' => ['maxlength' => 6, 'multiple' => true, 'size' => 2, 'colorpicker' => true, 'isHexColor' => true, 'decodeEntities' => true, 'tl_class' => 'w50 wizard'],
    'sql' => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['isHolidayCal'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['isHolidayCal'],
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true, 'tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['allowEvents'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['allowEvents'],
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['uniqueEvents'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['uniqueEvents'],
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];
