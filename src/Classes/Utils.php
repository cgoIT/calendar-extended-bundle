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
        $arrCalendar = StringUtil::deserialize($objModule->cal_calendar, true);

        // Get the background and foreground colors of the calendars
        foreach ($arrCalendar as $cal) {
            $objCalendar = CalendarModel::findById($cal);

            // the calendar might have been deleted
            if (null === $objCalendar) {
                continue;
            }

            $calConf[$cal]['calendar'] = $objCalendar->title;

            if (!empty($objCalendar->bg_color)) {
                [$cssColor, $cssOpacity] = StringUtil::deserialize($objCalendar->bg_color, true);

                if (!empty($cssColor)) {
                    self::appendToArrayKey($calConf[$cal], 'background', 'background-color:#'.$cssColor.';');
                    $calConf[$cal]['backgroundColor'] = self::buildCssColor($cssColor, $cssOpacity);
                }

                if (!empty($cssOpacity)) {
                    self::appendToArrayKey($calConf[$cal], 'background', 'opacity:'.($cssOpacity / 100).';');
                }
            }

            if (!empty($objCalendar->fg_color)) {
                [$cssColor, $cssOpacity] = StringUtil::deserialize($objCalendar->fg_color, true);

                if (!empty($cssColor)) {
                    self::appendToArrayKey($calConf[$cal], 'foreground', 'color:#'.$cssColor.';');
                    $calConf[$cal]['foregroundColor'] = self::buildCssColor($cssColor, $cssOpacity);
                }

                if (!empty($cssOpacity)) {
                    self::appendToArrayKey($calConf[$cal], 'foreground', 'opacity:'.($cssOpacity / 100).';');
                }
            }
        }

        return $calConf;
    }

    /**
     * Maps a Contao language (e.g. "de", "de_CH", "pt_BR") to the list of locale codes of FullCalendar in
     * order of preference (e.g. ["de-ch", "de"]). FullCalendar uses the first locale that is available.
     *
     * @return array<string>
     */
    public static function getFullCalendarLocales(string $language): array
    {
        $locale = strtolower(str_replace('_', '-', trim($language)));

        if ('' === $locale) {
            return ['en'];
        }

        $locales = [$locale];

        if (str_contains($locale, '-')) {
            $locales[] = explode('-', $locale)[0];
        }

        return $locales;
    }

    /**
     * Appends the parameters "day" and "times" (the concrete occurrence of a recurring event) to an event URL.
     *
     * The URL may already contain a query string or a fragment. It is a plain URL (parameters separated
     * with "&"), escaping is up to the output, like it is for the URLs of the core.
     */
    public static function appendDayAndTimesToUrl(string $url, int $begin, int $end): string
    {
        $fragment = '';
        $pos = strpos($url, '#');

        if (false !== $pos) {
            $fragment = substr($url, $pos);
            $url = substr($url, 0, $pos);
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'day='.date('Ymd', $begin).'&times='.$begin.','.$end.$fragment;
    }

    /**
     * Calculates the start and end of a moved occurrence of an event.
     *
     * The dates are always handled in the ISO format, so the result does not depend on the date format of the page.
     *
     * @param int         $occurrenceStart start of the occurrence that is moved
     * @param int         $duration        duration of the event in seconds, used if no new times are given
     * @param string      $move            relative move, e.g. "+1 day" or "-2 days"
     * @param string|null $newStart        new start time (H:i) or null to keep the time
     * @param string|null $newEnd          new end time (H:i) or null to keep the duration
     * @param int         $endDayOffset    number of days between the start and the end date of the event (e.g. 1 if
     *                                     it ends on the next day), used together with new times
     *
     * @return array{int, int} start and end time stamp
     */
    public static function calculateMovedTimes(int $occurrenceStart, int $duration, string $move, string|null $newStart = null, string|null $newEnd = null, int $endDayOffset = 0): array
    {
        if (null !== $newStart && null !== $newEnd && '' !== $newStart && '' !== $newEnd) {
            $start = strtotime($move, strtotime(date('Y-m-d', $occurrenceStart).' '.$newStart));
            $endDate = (new \DateTimeImmutable(date('Y-m-d', $start)))->modify('+'.max(0, $endDayOffset).' days')->format('Y-m-d');

            return [$start, strtotime($endDate.' '.$newEnd)];
        }

        $start = strtotime($move, $occurrenceStart);

        return [$start, $start + $duration];
    }

    /**
     * Returns the number of calendar days between two time stamps (ignoring the time of the day).
     */
    public static function daysBetween(int $from, int $to): int
    {
        return (int) (new \DateTimeImmutable(date('Y-m-d', $from)))->diff(new \DateTimeImmutable(date('Y-m-d', $to)))->format('%r%a');
    }

    /**
     * Appends the "moved" class and an optional custom class of the exception to the CSS classes of an event.
     *
     * @param string $classes the current classes with a leading space, as used by the event templates
     */
    public static function appendMovedClass(string $classes, string $customClass = ''): string
    {
        $customClass = trim($customClass);

        return $classes.' moved'.('' !== $customClass ? ' '.$customClass : '');
    }

    /**
     * Checks if the (serialized) fixed dates of an event contain at least one date.
     */
    public static function hasFixedDates(mixed $repeatFixedDates): bool
    {
        $rows = StringUtil::deserialize($repeatFixedDates);

        if (empty($rows) || !\is_array($rows)) {
            return false;
        }

        foreach ($rows as $row) {
            // the multi column wizard stores an empty row as empty strings
            if (\is_array($row) && !empty($row['new_repeat'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Builds a plain CSS color value (e.g. for FullCalendar, which does not accept CSS declarations).
     *
     * @param string          $hex     the color as stored by the color picker, with or without leading "#"
     * @param int|string|null $opacity the opacity in percent (0-100), empty for opaque
     *
     * @return string "#rrggbb", "rgba(r, g, b, a)" if an opacity below 100 is set, or an empty string for an invalid color
     */
    public static function buildCssColor(string $hex, int|string|null $opacity = null): string
    {
        $hex = ltrim($hex, '#');

        if (1 !== preg_match('/^([0-9a-f]{3}|[0-9a-f]{6})$/i', $hex)) {
            return '';
        }

        if (3 === \strlen($hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (empty($opacity) || (int) $opacity >= 100 || (int) $opacity < 0) {
            return '#'.strtolower($hex);
        }

        return \sprintf(
            'rgba(%d, %d, %d, %s)',
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
            (string) ((int) $opacity / 100),
        );
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
                $until = ' '.\sprintf($GLOBALS['TL_LANG']['MSC']['cal_until'], Date::parse($objPage->dateFormat, $objEvent->repeatEnd));

                $arrFixedDates = array_map(static fn ($val) => Date::parse($objPage->dateFormat, $val), array_column($arrFixedDates, 'new_repeat'));
                $strDates = self::implodeFixedDates($arrFixedDates);

                $recurring = \sprintf($GLOBALS['TL_LANG']['tl_calendar_events']['cal_repeat_fixed_dates'], $strDates, date('Y-m-d\TH:i:sP', $intStartTime), $strDate.($strTime ? ' '.$strTime : ''));
            }
        } elseif (!empty($objEvent->recurringExt)) {
            $arrRange = StringUtil::deserialize($objEvent->repeatEachExt, true);
            if (!empty($arrRange) && !empty($arrRange['value']) && !empty($arrRange['unit'])) {
                $arg = $arrRange['value'];
                $unit = $arrRange['unit'];

                $repeat = $GLOBALS['TL_LANG']['tl_calendar_events'][$arg].' '.$GLOBALS['TL_LANG']['tl_calendar_events'][$unit];

                if ($objEvent->recurrences > 0) {
                    $until = ' '.\sprintf($GLOBALS['TL_LANG']['MSC']['cal_until'], Date::parse($objPage->dateFormat, $objEvent->repeatEnd));
                }

                if ($objEvent->recurrences > 0 && $objEvent->repeatEnd <= time()) {
                    $recurring = \sprintf($GLOBALS['TL_LANG']['MSC']['cal_repeat_ended'], $repeat, $until);
                } elseif ($objEvent->addTime) {
                    $recurring = \sprintf($GLOBALS['TL_LANG']['MSC']['cal_repeat'], $repeat, $until, date('Y-m-d\TH:i:sP', $intStartTime), $strDate.($strTime ? ' '.$strTime : ''));
                } else {
                    $recurring = \sprintf($GLOBALS['TL_LANG']['MSC']['cal_repeat'], $repeat, $until, date('Y-m-d', $intStartTime), $strDate);
                }
            }
        } elseif (!empty($objEvent->recurring)) {
            $arrRange = StringUtil::deserialize($objEvent->repeatEach, true);

            if (!empty($arrRange) && !empty($arrRange['value']) && !empty($arrRange['unit'])) {
                if (1 === (int) $arrRange['value']) {
                    $repeat = $GLOBALS['TL_LANG']['MSC']['cal_single_'.$arrRange['unit']];
                } else {
                    $repeat = \sprintf($GLOBALS['TL_LANG']['MSC']['cal_multiple_'.$arrRange['unit']], $arrRange['value']);
                }

                if ($objEvent->recurrences > 0) {
                    $until = ' '.\sprintf($GLOBALS['TL_LANG']['MSC']['cal_until'], Date::parse($objPage->dateFormat, $objEvent->repeatEnd));
                }

                if ($objEvent->recurrences > 0 && $objEvent->repeatEnd <= time()) {
                    $recurring = \sprintf($GLOBALS['TL_LANG']['MSC']['cal_repeat_ended'], $repeat, $until);
                } elseif ($objEvent->addTime) {
                    $recurring = \sprintf($GLOBALS['TL_LANG']['MSC']['cal_repeat'], $repeat, $until, date('Y-m-d\TH:i:sP', $intStartTime), $strDate.($strTime ? ' '.$strTime : ''));
                } else {
                    $recurring = \sprintf($GLOBALS['TL_LANG']['MSC']['cal_repeat'], $repeat, $until, date('Y-m-d', $intStartTime), $strDate);
                }
            }
        }

        return [$until, $recurring];
    }

    /**
     * @param array<mixed> $arr the array the value should be appended to a key/the key should be added
     */
    public static function appendToArrayKey(&$arr, mixed $key, mixed $val): void
    {
        if (\array_key_exists($key, $arr)) {
            $arr[$key] .= $val;
        } else {
            $arr[$key] = $val;
        }
    }

    /**
     * @param array<mixed> $arrFixedDates
     *
     * @return array<mixed>
     */
    public static function sanitizeFixedDates(array $arrFixedDates): array
    {
        foreach ($arrFixedDates as $pos => &$fixedDate) {
            foreach (['new_repeat', 'new_start', 'new_end'] as $col) {
                $val = $fixedDate[$col];
                if (!empty($val)) {
                    if (!\is_int($val)) {
                        $intVal = strtotime((string) $val);
                        if (false !== $intVal) {
                            $fixedDate[$col] = $intVal;
                        } else {
                            $fixedDate[$col] = 0;
                        }
                    }
                } else {
                    if ('new_repeat' === $col) {
                        unset($arrFixedDates[$pos]);
                        continue 2;
                    }
                }
            }
        }

        return $arrFixedDates;
    }

    /**
     * Implodes an array of fixed dates into a string.
     *
     * If the input array has more than 4 dates, it will only include the first 4 dates and append the count of other dates.
     * Otherwise, it will implode all the dates with a comma separator and append the last date with an "and" label.
     *
     * @param array<mixed>|null $arrFixedDates Array of fixed dates
     *
     * @return string The imploded string of fixed dates
     */
    private static function implodeFixedDates(array|null $arrFixedDates): string
    {
        if (null === $arrFixedDates) {
            return '';
        }

        if (\count($arrFixedDates) > 4) {
            $cntOtherDates = \count($arrFixedDates) - 4;
            $arrFixedDates = \array_slice($arrFixedDates, 0, 4);

            return implode(', ', $arrFixedDates).' '.
                \sprintf($cntOtherDates > 1 ? $GLOBALS['TL_LANG']['MSC']['count_other_dates_plural'] : $GLOBALS['TL_LANG']['MSC']['count_other_dates_singular'], $cntOtherDates);
        }
        if (1 === \count($arrFixedDates)) {
            return $arrFixedDates[0];
        }

        $last = array_pop($arrFixedDates);

        return implode(', ', $arrFixedDates).' '.$GLOBALS['TL_LANG']['MSC']['and_label'].' '.$last;
    }
}
