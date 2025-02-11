<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\calendar-extended-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) Kester Mielke
 * @copyright  Copyright (c) 2025, cgoIT
 * @author     Kester Mielke
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

PaletteManipulator::create()->addField('uniqueEvents', 'jumpTo')
    ->applyToPalette('default', 'tl_calendar')
;
PaletteManipulator::create()->addLegend('extended_type_legend', 'title_legend')
    ->addField('isHolidayCal', 'extended_type_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_calendar')
;
PaletteManipulator::create()->addLegend('extended_legend', 'extended_type_legend')
    ->addField('fg_color', 'extended_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('bg_color', 'extended_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_calendar')
;

$GLOBALS['TL_DCA']['tl_calendar']['palettes']['__selector__'] = array_merge(
    ['isHolidayCal'],
    $GLOBALS['TL_DCA']['tl_calendar']['palettes']['__selector__'],
);
$GLOBALS['TL_DCA']['tl_calendar']['subpalettes']['isHolidayCal'] = 'allowEvents';

// Hinzufügen der Feld-Konfiguration
$GLOBALS['TL_DCA']['tl_calendar']['fields']['bg_color'] = [
    'inputType' => 'text',
    'exclude' => true,
    'eval' => ['maxlength' => 6, 'multiple' => true, 'size' => 2, 'colorpicker' => true, 'isHexColor' => true, 'decodeEntities' => true, 'tl_class' => 'w50 wizard'],
    'sql' => ['type' => 'string', 'length' => 64, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['fg_color'] = [
    'inputType' => 'text',
    'exclude' => true,
    'eval' => ['maxlength' => 6, 'multiple' => true, 'size' => 2, 'colorpicker' => true, 'isHexColor' => true, 'decodeEntities' => true, 'tl_class' => 'w50 wizard'],
    'sql' => ['type' => 'string', 'length' => 64, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['isHolidayCal'] = [
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true, 'tl_class' => 'w50'],
    'sql' => ['type' => 'string', 'length' => 1, 'fixed' => true, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['allowEvents'] = [
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => ['type' => 'string', 'length' => 1, 'fixed' => true, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['uniqueEvents'] = [
    'default' => 0,
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => ['type' => 'string', 'length' => 1, 'fixed' => true, 'default' => ''],
];
