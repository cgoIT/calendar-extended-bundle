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
