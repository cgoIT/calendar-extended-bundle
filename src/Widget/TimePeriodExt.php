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

namespace Cgoit\CalendarExtendedBundle\Widget;

use Contao\StringUtil;
use Contao\Widget;

/**
 * Class TimePeriodExt.
 */
class TimePeriodExt extends Widget
{
    /**
     * Submit user input.
     *
     * @var bool
     */
    protected $blnSubmitInput = true;

    /**
     * Template.
     *
     * @var string
     */
    protected $strTemplate = 'be_widget';

    /**
     * Values.
     *
     * @var array<mixed>
     */
    protected $arrValues = [];

    /**
     * Units.
     *
     * @var array<mixed>
     */
    protected $arrUnits = [];

    /**
     * Add specific attributes.
     *
     * @param string $strKey
     */
    public function __set($strKey, $varValue): void
    {
        switch ($strKey) {
            case 'maxlength':
                if ($varValue > 0) {
                    $this->arrAttributes['maxlength'] = $varValue;
                }
                break;

            case 'mandatory':
                if ($varValue) {
                    $this->arrAttributes['required'] = 'required';
                } else {
                    unset($this->arrAttributes['required']);
                }
                parent::__set($strKey, $varValue);
                break;

            case 'options':
                $varValue = StringUtil::deserialize($varValue);
                $this->arrValues = $varValue[0];
                $this->arrUnits = $varValue[1];
                break;

            default:
                parent::__set($strKey, $varValue);
                break;
        }
    }

    /**
     * Generate the widget and return it as string.
     *
     * @return string
     */
    public function generate()
    {
        $arrValues = [];
        $arrUnits = [];

        if (empty($this->arrValues)) {
            $this->arrValues = [['value' => '', 'label' => '-']];
        }

        foreach ($this->arrValues as $arrValue) {
            $arrValues[] = \sprintf(
                '<option value="%s"%s>%s</option>',
                StringUtil::specialchars($arrValue['value']),
                $this->isSelectedExt($arrValue, 'value'),
                $arrValue['label'],
            );
        }

        if (empty($this->arrUnits)) {
            $this->arrUnits = [['value' => '', 'label' => '-']];
        }

        foreach ($this->arrUnits as $arrUnit) {
            $arrUnits[] = \sprintf(
                '<option value="%s"%s>%s</option>',
                StringUtil::specialchars($arrUnit['value']),
                $this->isSelectedExt($arrUnit, 'unit'),
                $arrUnit['label'],
            );
        }

        if (!\is_array($this->varValue)) {
            $this->varValue = ['value' => $this->varValue];
        }

        return \sprintf(
            '<select name="%s[value]" class="tl_select_interval" onfocus="Backend.getScrollOffset();"%s>%s</select> <select name="%s[unit]" class="tl_select_interval" onfocus="Backend.getScrollOffset();"%s>%s</select>%s',
            $this->strName,
            $this->getAttribute('disabled'),
            implode('', $arrValues),
            $this->strName,
            $this->getAttribute('disabled'),
            implode('', $arrUnits),
            $this->wizard,
        );
    }

    /**
     * Do not validate unit fields.
     */
    protected function validator($varInput)
    {
        foreach ($varInput as $k => $v) {
            if ('unit' !== $k) {
                $varInput[$k] = parent::validator($v);
            }
        }

        return $varInput;
    }

    /**
     * Only check against the unit values (see #7246).
     *
     * @param array<mixed> $arrOption The options array
     *
     * @return string The "selected" attribute or an empty string
     */
    private function isSelectedExt(array $arrOption, string $strValueKey)
    {
        if (empty($this->varValue) && empty($_POST) && ($arrOption['default'] ?? null)) {
            return Widget::optionSelected('1', 1);
        }

        if (empty($this->varValue) || !\is_array($this->varValue)) {
            return '';
        }

        return Widget::optionSelected($arrOption['value'] ?? null, $this->varValue[$strValueKey] ?? null);
    }
}
