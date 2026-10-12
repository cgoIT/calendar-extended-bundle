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

namespace Cgoit\CalendarExtendedBundle\Service;

/**
 * The pre-calculated values of an event, see RecurrenceCalculator.
 */
final readonly class RecurrenceResult
{
    /**
     * @param array<int, array<string, mixed>> $recurrences all recurrences by start time (ascending)
     * @param array<string, mixed>             $set         the values to be stored in the event (weekday, repeatEnd,
     *                                                      exceptionList, repeatFixedDates and, if the end time is
     *                                                      ignored, endTime)
     */
    public function __construct(
        public array $recurrences,
        public array $set,
    ) {
    }

    /**
     * The values for the columns of tl_calendar_events, including the serialized recurrences.
     *
     * @return array<string, mixed>
     */
    public function toColumns(): array
    {
        return $this->set + ['allRecurrences' => serialize($this->recurrences)];
    }
}
