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

namespace Cgoit\CalendarExtendedBundle\Tests\Hook;

use Cgoit\CalendarExtendedBundle\Hook\GetAllEventsHook;
use Contao\Events;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GetAllEventsHookTest extends TestCase
{
    public function testFilterDataContainsConfiguredFields(): void
    {
        $events = $this->addFilterFields(
            ['title', 'location_name'],
            [['title' => 'Concert', 'location_name' => 'Hall']],
        );

        $this->assertSame('{"title":"Concert","location_name":"Hall"}', $events[0]['filter_data']);
    }

    public function testFilterDataToleratesMissingEventFields(): void
    {
        $events = $this->addFilterFields(['title', 'location_plz'], [['title' => 'Concert']]);

        $this->assertSame('{"title":"Concert","location_plz":null}', $events[0]['filter_data']);
    }

    #[DataProvider('recurrenceHandlers')]
    public function testRecurrenceHandlersSkipTheQueryWithoutCalendars(string $handler): void
    {
        $hook = (new \ReflectionClass(GetAllEventsHook::class))->newInstanceWithoutConstructor();
        $events = ['20250303' => [1741000000 => [['title' => 'Concert']]]];

        $result = (new \ReflectionMethod($hook, $handler))->invoke($hook, $events, [], 0, 1, $this->createStub(Events::class));

        $this->assertSame($events, $result);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function recurrenceHandlers(): iterable
    {
        yield 'extended recurrences' => ['handleExtendedRecurrences'];
        yield 'fixed dates' => ['handleFixedDatesRecurrences'];
    }

    public function testEventsOfOtherCalendarsAreHiddenDuringHolidays(): void
    {
        // The pid of the events is a string, the holiday calendars are integers
        $events = $this->hideEventsDuringHolidays(
            [
                1741000000 => [
                    ['pid' => '5', 'title' => 'Holiday'],
                    ['pid' => '7', 'title' => 'Concert'],
                    ['pid' => '7', 'title' => 'Fair', 'showOnFreeDay' => 1],
                ],
            ],
            [5],
        );

        $this->assertSame(['Holiday', 'Fair'], array_column($events[1741000000], 'title'));
    }

    public function testHolidayHidesEventsWithDifferentStartTimesOfTheSameDay(): void
    {
        // All day holiday event at 00:00, concert at 09:00 and an event that stays visible at 12:00
        $events = $this->hideEventsDuringHolidays(
            [
                1740960000 => [['pid' => '5', 'title' => 'Holiday']],
                1740992400 => [['pid' => '7', 'title' => 'Concert']],
                1741003200 => [['pid' => '7', 'title' => 'Fair', 'showOnFreeDay' => 1]],
            ],
            [5],
        );

        $this->assertSame(['Holiday'], array_column($events[1740960000], 'title'));
        $this->assertSame([], $events[1740992400]);
        $this->assertSame(['Fair'], array_column($events[1741003200], 'title'));
    }

    /**
     * @param array<string|int> $weekdays
     */
    #[DataProvider('weekdayLists')]
    public function testRepeatWeekdayFilterAcceptsStringsAndIntegers(array $weekdays): void
    {
        $monday = strtotime('2025-03-03 09:00');
        $tuesday = strtotime('2025-03-04 09:00');
        $sunday = strtotime('2025-03-09 09:00');
        $event = ['recurring' => 1, 'repeatWeekday' => serialize($weekdays)];

        $hook = (new \ReflectionClass(GetAllEventsHook::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($hook, 'handleRepeatWeekdaysForStandardRecurrence');

        $result = $method->invoke($hook, [
            '20250303' => [$monday => [$event]],
            '20250304' => [$tuesday => [$event]],
            '20250309' => [$sunday => [$event]],
        ]);

        // Monday (1) and Sunday (0) are selected
        $this->assertCount(1, $result['20250303'][$monday]);
        $this->assertCount(0, $result['20250304'][$tuesday]);
        $this->assertCount(1, $result['20250309'][$sunday]);
    }

    /**
     * @return iterable<string, array{array<string|int>}>
     */
    public static function weekdayLists(): iterable
    {
        yield 'strings like the back end stores them' => [['1', '0']];
        yield 'integers like an import might store them' => [[1, 0]];
    }

    public function testEventsAreKeptOnDaysWithoutHoliday(): void
    {
        $events = $this->hideEventsDuringHolidays([1741000000 => [['pid' => '7', 'title' => 'Concert']]], [5]);

        $this->assertSame(['Concert'], array_column($events[1741000000], 'title'));
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $events    time => events
     * @param array<int>                                   $calendars
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function hideEventsDuringHolidays(array $events, array $calendars): array
    {
        $hook = (new \ReflectionClass(GetAllEventsHook::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($hook, 'hideEventsDuringHolidays');

        return $method->invoke($hook, ['20250303' => $events], $calendars)['20250303'];
    }

    /**
     * @param array<string>                    $filterFields
     * @param array<int, array<string, mixed>> $eventList
     *
     * @return array<int, array<string, mixed>>
     */
    private function addFilterFields(array $filterFields, array $eventList): array
    {
        $module = $this->createMock(Events::class);
        $module
            ->method('__isset')
            ->willReturn(true)
        ;

        $module
            ->method('__get')
            ->with('filter_fields')
            ->willReturn(serialize($filterFields))
        ;

        $hook = (new \ReflectionClass(GetAllEventsHook::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($hook, 'addFilterFields');

        // structure: day => time => events
        $result = $method->invoke($hook, ['20250303' => [1741000000 => $eventList]], $module);

        return $result['20250303'][1741000000];
    }
}
