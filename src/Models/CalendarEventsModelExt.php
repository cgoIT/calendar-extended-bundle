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

namespace Cgoit\CalendarExtendedBundle\Models;

use Contao\CalendarEventsModel;
use Contao\Date;
use Contao\Model\Collection;

/**
 * Reads and writes events.
 */
class CalendarEventsModelExt extends CalendarEventsModel
{
    /**
     * Find current events by their parent ID.
     *
     * @param int          $intPid     The parent ID of the events
     * @param int          $intStart   The start timestamp
     * @param int          $intEnd     The end timestamp
     * @param array<mixed> $arrOptions An optional options array
     *
     * @return Collection<CalendarEventsModel>|array<CalendarEventsModel>|CalendarEventsModel|null A collection of models or null if there are no events
     */
    public static function findCurrentByPid($intPid, $intStart, $intEnd, array $arrOptions = [])
    {
        $t = static::$strTable;
        $intStart = (int) $intStart;
        $intEnd = (int) $intEnd;

        $arrColumns = ["$t.pid=? AND (($t.startTime>=$intStart AND $t.startTime<=$intEnd) OR ($t.endTime>=$intStart AND $t.endTime<=$intEnd) OR ($t.startTime<=$intStart AND $t.endTime>=$intEnd) OR (($t.recurringExt=1 OR $t.recurring=1) AND ($t.recurrences=0 OR $t.repeatEnd>=$intStart) AND $t.startTime<=$intEnd))"];

        if (isset($arrOptions['showFeatured'])) {
            if (true === $arrOptions['showFeatured']) {
                $arrColumns[] = "$t.featured=1";
            } elseif (false === $arrOptions['showFeatured']) {
                $arrColumns[] = "$t.featured=0";
            }
        }

        if (!static::isPreviewMode($arrOptions)) {
            $time = Date::floorToMinute();
            $arrColumns[] = "$t.published=1 AND ($t.start='' OR $t.start<=$time) AND ($t.stop='' OR $t.stop>$time)";
        }

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.startTime";
        }

        return static::findBy($arrColumns, [$intPid], $arrOptions);
    }

    /**
     * Find upcoming events by their parent IDs.
     *
     * @param array<mixed> $arrIds     An array of calendar IDs
     * @param int          $intLimit   An optional limit
     * @param array<mixed> $arrOptions An optional options array
     *
     * @return Collection<CalendarEventsModel>|array<CalendarEventsModel>|CalendarEventsModel|null A collection of models or null if there are no events
     */
    public static function findUpcomingByPids($arrIds, $intLimit = 0, array $arrOptions = []): CalendarEventsModel|Collection|array|null
    {
        if (empty($arrIds) || !\is_array($arrIds)) {
            return null;
        }

        $t = static::$strTable;
        $time = Date::floorToMinute();

        // Get upcoming events using endTime instead of startTime (see #3917)
        $arrColumns = ["$t.pid IN(".implode(',', array_map('\intval', $arrIds)).") AND $t.published=1 AND ($t.start='' OR $t.start<=$time) AND ($t.stop='' OR $t.stop>$time) AND ($t.endTime>=$time OR (($t.recurringExt=1 OR $t.recurring=1) AND ($t.recurrences=0 OR $t.repeatEnd>=$time)))"];

        if ($intLimit > 0) {
            $arrOptions['limit'] = $intLimit;
        }

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.startTime";
        }

        return static::findBy($arrColumns, null, $arrOptions);
    }

    /**
     * Find events of the current period by their parent ID.
     *
     * @param int          $intPid     The calendar ID
     * @param int          $intStart   The start date as Unix timestamp
     * @param int          $intEnd     The end date as Unix timestamp
     * @param array<mixed> $arrOptions An optional options array
     *
     * @return Collection<CalendarEventsModel>|array<CalendarEventsModel>|CalendarEventsModel|null A collection of models or null if there are no events
     */
    public static function findOverlappingByPid($intPid, $intStart, $intEnd, array $arrOptions = []): CalendarEventsModel|Collection|array|null
    {
        $t = static::$strTable;
        $intStart = (int) $intStart;
        $intEnd = (int) $intEnd;

        $arrColumns = ["$t.pid=? AND (($t.startTime>=$intStart AND $t.startTime<=$intEnd) OR ($t.endTime>=$intStart AND $t.endTime<=$intEnd) OR ($t.startTime<=$intStart AND $t.endTime>=$intEnd) OR (($t.recurring=1 OR $t.recurringExt=1) AND ($t.recurrences=0 OR $t.repeatEnd>=$intStart) AND $t.startTime<=$intEnd) OR ($t.repeatFixedDates is not null AND $t.repeatEnd>=$intStart))"];

        if (!static::isPreviewMode($arrOptions)) {
            $time = Date::floorToMinute();
            $arrColumns[] = "($t.start='' OR $t.start<='$time') AND ($t.stop='' OR $t.stop>'".($time + 60)."') AND $t.published='1'";
        }

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.startTime";
        }

        return static::findBy($arrColumns, $intPid, $arrOptions);
    }
}
