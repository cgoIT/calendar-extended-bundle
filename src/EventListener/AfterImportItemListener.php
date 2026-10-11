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

namespace Cgoit\CalendarExtendedBundle\EventListener;

use Cgoit\CalendarExtendedBundle\Service\RecurrenceCalculator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

// Die Event-Klasse gehört zur optionalen Abhängigkeit cgoit/contao-calendar-ical-bundle und wird
// daher nur als String referenziert.
#[AsEventListener(event: 'Cgoit\\ContaoCalendarIcalBundle\\Event\\AfterImportItemEvent', method: 'calculateAllRecurrences')]
final readonly class AfterImportItemListener
{
    public function __construct(private RecurrenceCalculator $recurrenceCalculator)
    {
    }

    public function calculateAllRecurrences(object $event): void
    {
        $eventClass = 'Cgoit\\ContaoCalendarIcalBundle\\Event\\AfterImportItemEvent';

        if (!class_exists($eventClass) || !is_a($event, $eventClass)) {
            return;
        }

        $payload = $this->toStdClass($event);
        $calendarEvent = $payload->calendarEventModel;

        $columns = $this->calculateColumns($calendarEvent->row());

        if (null === $columns) {
            return;
        }

        foreach ($columns as $column => $value) {
            $calendarEvent->$column = $value;
        }

        $calendarEvent->save();
    }

    /**
     * The same pre-calculated values as when saving the event in the back end.
     *
     * @param array<string, mixed> $row the row of the imported event
     *
     * @return array<string, mixed>|null null if there is no start date or the recurrences cannot be calculated from the data of the event
     */
    public function calculateColumns(array $row): array|null
    {
        // like in the back end, nothing can be calculated without a start date
        if (empty($row['startDate'])) {
            return null;
        }

        try {
            return $this->recurrenceCalculator->calculate($row)->toColumns();
        } catch (\InvalidArgumentException) {
            // the import keeps the event as it is
            return null;
        }
    }

    private function toStdClass(object $event): \stdClass
    {
        $data = new \stdClass();

        foreach (get_object_vars($event) as $name => $value) {
            $data->$name = $value;
        }

        return $data;
    }
}
