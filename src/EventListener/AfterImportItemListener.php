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

        $maxRepeatEnd = [];
        $maxRepeatEnd[] = $calendarEvent->repeatEnd;

        $arrAllRecurrences = [];

        [$arrAllRecurrences] = $this->recurrenceCalculator
            ->calculate($calendarEvent->row(), $arrAllRecurrences, $maxRepeatEnd, [])
        ;

        ksort($arrAllRecurrences);
        $calendarEvent->allRecurrences = serialize($arrAllRecurrences);

        $calendarEvent->save();
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
