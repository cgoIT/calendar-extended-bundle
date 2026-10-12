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

namespace Cgoit\CalendarExtendedBundle\Tests\Events\Step;

use Cgoit\CalendarExtendedBundle\Events\EventContext;
use Cgoit\CalendarExtendedBundle\Events\Step\HolidayStep;
use PHPUnit\Framework\TestCase;

final class HolidayStepTest extends TestCase
{
    public function testEventsOfOtherCalendarsAreHiddenDuringHolidays(): void
    {
        // The pid of the events is a string, the holiday calendars are integers
        $events = $this->processTimes(
            new HolidayStep(),
            [
                1741000000 => [
                    ['pid' => '5', 'title' => 'Holiday'],
                    ['pid' => '7', 'title' => 'Concert'],
                    ['pid' => '7', 'title' => 'Fair', 'showOnFreeDay' => 1],
                ],
            ],
            new EventContext(holidayCalendars: [5]),
        );

        $this->assertSame(['Holiday', 'Fair'], array_column($events[1741000000], 'title'));
    }

    public function testHolidayHidesEventsWithDifferentStartTimesOfTheSameDay(): void
    {
        // All day holiday event at 00:00, concert at 09:00 and an event that stays visible at 12:00
        $events = $this->processTimes(
            new HolidayStep(),
            [
                1740960000 => [['pid' => '5', 'title' => 'Holiday']],
                1740992400 => [['pid' => '7', 'title' => 'Concert']],
                1741003200 => [['pid' => '7', 'title' => 'Fair', 'showOnFreeDay' => 1]],
            ],
            new EventContext(holidayCalendars: [5]),
        );

        $this->assertSame(['Holiday'], array_column($events[1740960000], 'title'));
        $this->assertSame([], $events[1740992400]);
        $this->assertSame(['Fair'], array_column($events[1741003200], 'title'));
    }

    public function testEventsAreKeptOnDaysWithoutHoliday(): void
    {
        $events = $this->processTimes(new HolidayStep(), [1741000000 => [['pid' => '7', 'title' => 'Concert']]], new EventContext(holidayCalendars: [5]));

        $this->assertSame(['Concert'], array_column($events[1741000000], 'title'));
    }

    public function testHolidayEventsCanBeHidden(): void
    {
        $events = $this->processTimes(
            new HolidayStep(),
            [1741000000 => [['pid' => '5', 'title' => 'Holiday'], ['pid' => '7', 'title' => 'Fair', 'showOnFreeDay' => 1]]],
            new EventContext(hideHoliday: true, holidayCalendars: [5]),
        );

        $this->assertSame(['Fair'], array_column($events[1741000000], 'title'));
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $events time => events
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function processTimes(HolidayStep $step, array $events, EventContext $context): array
    {
        return $step->process(['20250303' => $events], $context)['20250303'];
    }
}
