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

namespace Cgoit\CalendarExtendedBundle\Events;

use Contao\CalendarEventsModel;

/**
 * Finds the published events of the calendars that have extended recurrences or fixed dates.
 */
class RecurringEventsFinder
{
    /**
     * @param array<int|string> $calendars the IDs of the calendars
     *
     * @return iterable<CalendarEventsModel>
     */
    public function findWithExtendedRecurrences(array $calendars): iterable
    {
        $t = 'tl_calendar_events';
        $time = time();

        return CalendarEventsModel::findBy(
            [
                "$t.pid IN (".$this->placeholders($calendars).')',
                "$t.recurringExt=?",
                "$t.published=? AND ($t.start='' OR $t.start<=?) AND ($t.stop='' OR $t.stop>?)",
            ],
            [...array_map(intval(...), $calendars), '1', '1', $time, $time],
        ) ?? [];
    }

    /**
     * @param array<int|string> $calendars the IDs of the calendars
     *
     * @return iterable<CalendarEventsModel>
     */
    public function findWithFixedDates(array $calendars): iterable
    {
        $t = 'tl_calendar_events';
        $time = time();

        return CalendarEventsModel::findBy(
            [
                "$t.pid IN (".$this->placeholders($calendars).')',
                "$t.repeatFixedDates IS NOT NULL",
                "$t.repeatFixedDates !=?",
                "$t.published=? AND ($t.start='' OR $t.start<=?) AND ($t.stop='' OR $t.stop>?)",
            ],
            [...array_map(intval(...), $calendars), '', '1', $time, $time],
        ) ?? [];
    }

    /**
     * @param array<int|string> $values
     */
    private function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, \count($values), '?'));
    }
}
