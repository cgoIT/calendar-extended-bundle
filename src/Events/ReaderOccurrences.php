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

/**
 * Selects occurrences of a recurring event for the event reader. The occurrences are the pre-calculated
 * recurrences (column "allRecurrences"): start time => ['int_start' => ..., 'int_end' => ...].
 */
final class ReaderOccurrences
{
    /**
     * The first occurrence that starts at or after the given time.
     *
     * @param array<int|string, array<string, mixed>> $recurrences
     *
     * @return array<string, mixed>|null
     */
    public static function next(array $recurrences, int $now): array|null
    {
        $upcoming = array_filter($recurrences, static fn (array $entry): bool => $entry['int_start'] >= $now);
        ksort($upcoming);

        return [] === $upcoming ? null : reset($upcoming);
    }

    /**
     * The occurrences after the given start, limited to the given number.
     *
     * @param array<int|string, array<string, mixed>> $recurrences
     *
     * @return array<int, array<string, mixed>>
     */
    public static function upcoming(array $recurrences, int $start, int $limit): array
    {
        return \array_slice(array_values(array_filter($recurrences, static fn (array $entry): bool => $entry['int_start'] > $start)), 0, max(0, $limit));
    }

    /**
     * The occurrences that ended before the given start, limited to the given number.
     *
     * @param array<int|string, array<string, mixed>> $recurrences
     *
     * @return array<int, array<string, mixed>>
     */
    public static function past(array $recurrences, int $start, int $limit): array
    {
        return \array_slice(array_values(array_filter($recurrences, static fn (array $entry): bool => $entry['int_end'] < $start)), 0, max(0, $limit));
    }

    /**
     * The CSS class that marks past and upcoming events (see #187).
     */
    public static function statusClass(int $start, int $end, int $todayBegin, int $todayEnd): string
    {
        if ($end < $todayBegin) {
            return 'bygone';
        }

        if ($start > $todayEnd) {
            return 'upcoming';
        }

        return 'current';
    }
}
