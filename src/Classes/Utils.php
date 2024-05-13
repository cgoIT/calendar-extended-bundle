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

namespace Cgoit\CalendarExtendedBundle\Classes;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Date;
use Contao\Events;
use Contao\PageModel;
use Contao\StringUtil;

class Utils
{
    /**
     * @return array<mixed>
     */
    public static function getCalendarConfig(Events $objModule): array
    {
        $calConf = [];

        // Get the background and foreground colors of the calendars
        foreach ($objModule->cal_calendar as $cal) {
            $objCalendar = CalendarModel::findById($cal);

            $calConf[$cal]['calendar'] = $objCalendar->title;

            if (!empty($objCalendar->bg_color)) {
                [$cssColor, $cssOpacity] = StringUtil::deserialize($objCalendar->bg_color, true);

                if (!empty($cssColor)) {
                    self::appendToArrayKey($calConf[$cal], 'background', 'background-color:#'.$cssColor.';');
                }

                if (!empty($cssOpacity)) {
                    self::appendToArrayKey($calConf[$cal], 'background', 'opacity:'.($cssOpacity / 100).';');
                }
            }

            if (!empty($objCalendar->fg_color)) {
                [$cssColor, $cssOpacity] = StringUtil::deserialize($objCalendar->fg_color, true);

                if (!empty($cssColor)) {
                    self::appendToArrayKey($calConf[$cal], 'foreground', 'color:#'.$cssColor.';');
                }

                if (!empty($cssOpacity)) {
                    self::appendToArrayKey($calConf[$cal], 'foreground', 'opacity:'.($cssOpacity / 100).';');
                }
            }
        }

        return $calConf;
    }

    /**
     * @return array<string>
     */
    public static function getUntilAndRecurring(CalendarEventsModel $objEvent, PageModel $objPage, int $intStartTime, string $strDate, string $strTime, bool $isFixedDate): array
    {
        $until = '';
        $recurring = '';

        if ($isFixedDate) {
            $arrFixedDates = StringUtil::deserialize($objEvent->repeatFixedDates);

            if (!empty($arrFixedDates) && \is_array($arrFixedDates)) {
                $until = ' '.sprintf($GLOBALS['TL_LANG']['MSC']['cal_until'], Date::parse($objPage->dateFormat, $objEvent->repeatEnd));

                $arrFixedDates = array_map(static fn ($val) => Date::parse($objPage->dateFormat, $val), array_column($arrFixedDates, 'new_repeat'));

                if (\count($arrFixedDates) > 4) {
                    $arrFixedDates = array_merge(\array_slice($arrFixedDates, 0, 4), ['...']);
                }

                $strDates = implode(', ', $arrFixedDates);
                $recurring = sprintf($GLOBALS['TL_LANG']['tl_calendar_events']['cal_repeat_fixed_dates'], $strDates, date('Y-m-d\TH:i:sP', $intStartTime), $strDate.($strTime ? ' '.$strTime : ''));
            }
        } elseif (!empty($objEvent->recurringExt)) {
            $arrRange = StringUtil::deserialize($objEvent->repeatEachExt, true);
            if (!empty($arrRange) && !empty($arrRange['value']) && !empty($arrRange['unit'])) {
                $arg = $arrRange['value'];
                $unit = $arrRange['unit'];

                $repeat = $GLOBALS['TL_LANG']['tl_calendar_events'][$arg].' '.$GLOBALS['TL_LANG']['tl_calendar_events'][$unit];

                if ($objEvent->recurrences > 0) {
                    $until = ' '.sprintf($GLOBALS['TL_LANG']['MSC']['cal_until'], Date::parse($objPage->dateFormat, $objEvent->repeatEnd));
                }

                if ($objEvent->recurrences > 0 && $objEvent->repeatEnd <= time()) {
                    $recurring = sprintf($GLOBALS['TL_LANG']['MSC']['cal_repeat_ended'], $repeat, $until);
                } elseif ($objEvent->addTime) {
                    $recurring = sprintf($GLOBALS['TL_LANG']['MSC']['cal_repeat'], $repeat, $until, date('Y-m-d\TH:i:sP', $intStartTime), $strDate.($strTime ? ' '.$strTime : ''));
                } else {
                    $recurring = sprintf($GLOBALS['TL_LANG']['MSC']['cal_repeat'], $repeat, $until, date('Y-m-d', $intStartTime), $strDate);
                }
            }
        } elseif (!empty($objEvent->recurring)) {
            $arrRange = StringUtil::deserialize($objEvent->repeatEach, true);

            if (!empty($arrRange) && !empty($arrRange['value']) && !empty($arrRange['unit'])) {
                if (1 === $arrRange['value']) {
                    $repeat = $GLOBALS['TL_LANG']['MSC']['cal_single_'.$arrRange['unit']];
                } else {
                    $repeat = sprintf($GLOBALS['TL_LANG']['MSC']['cal_multiple_'.$arrRange['unit']], $arrRange['value']);
                }

                if ($objEvent->recurrences > 0) {
                    $until = ' '.sprintf($GLOBALS['TL_LANG']['MSC']['cal_until'], Date::parse($objPage->dateFormat, $objEvent->repeatEnd));
                }

                if ($objEvent->recurrences > 0 && $objEvent->repeatEnd <= time()) {
                    $recurring = sprintf($GLOBALS['TL_LANG']['MSC']['cal_repeat_ended'], $repeat, $until);
                } elseif ($objEvent->addTime) {
                    $recurring = sprintf($GLOBALS['TL_LANG']['MSC']['cal_repeat'], $repeat, $until, date('Y-m-d\TH:i:sP', $intStartTime), $strDate.($strTime ? ' '.$strTime : ''));
                } else {
                    $recurring = sprintf($GLOBALS['TL_LANG']['MSC']['cal_repeat'], $repeat, $until, date('Y-m-d', $intStartTime), $strDate);
                }
            }
        }

        return [$until, $recurring];
    }

    /**
     * @param array<mixed> $arr the array the value should be appended to a key/the key should be added
     * @param mixed        $key the key to look for
     * @param mixed        $val the value which should be append/set for the given key
     */
    public static function appendToArrayKey(&$arr, mixed $key, mixed $val): void
    {
        if (\array_key_exists($key, $arr)) {
            $arr[$key] .= $val;
        } else {
            $arr[$key] = $val;
        }
    }
}
