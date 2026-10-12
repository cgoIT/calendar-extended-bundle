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

use Cgoit\CalendarExtendedBundle\Events\EventContext;
use Cgoit\CalendarExtendedBundle\Events\RecurringEventsLoader;
use Cgoit\CalendarExtendedBundle\Events\Step\CalendarConfigStep;
use Cgoit\CalendarExtendedBundle\Events\Step\ExceptionStep;
use Cgoit\CalendarExtendedBundle\Events\Step\FilterFieldsStep;
use Cgoit\CalendarExtendedBundle\Events\Step\HolidayStep;
use Cgoit\CalendarExtendedBundle\Events\Step\RepeatWeekdayStep;
use Cgoit\CalendarExtendedBundle\Events\Step\ShowOnlyNextStep;
use Cgoit\CalendarExtendedBundle\Events\Step\UrlParameterStep;
use Cgoit\CalendarExtendedBundle\Hook\GetAllEventsHook;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

final class GetAllEventsHookTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $calls = [];

    public function testTheStepsRunInTheDefinedOrderAndPassOnTheEvents(): void
    {
        $loader = $this->createMock(RecurringEventsLoader::class);
        $loader
            ->method('addExtendedRecurrences')
            ->willReturnCallback($this->record('extended recurrences'))
        ;

        $loader
            ->method('addFixedDates')
            ->willReturnCallback($this->record('fixed dates'))
        ;

        $hook = new GetAllEventsHook(
            $loader,
            $this->step(RepeatWeekdayStep::class, 'repeat weekday'),
            $this->step(CalendarConfigStep::class, 'calendar config'),
            $this->step(ExceptionStep::class, 'exceptions'),
            $this->step(HolidayStep::class, 'holidays'),
            $this->step(ShowOnlyNextStep::class, 'show only next'),
            $this->step(FilterFieldsStep::class, 'filter fields'),
            $this->step(UrlParameterStep::class, 'url parameters'),
            new RequestStack(),
        );

        $result = $hook->process([], new EventContext());

        $expected = [
            'repeat weekday',
            'extended recurrences',
            'fixed dates',
            'calendar config',
            'exceptions',
            'holidays',
            'show only next',
            'filter fields',
            'url parameters',
        ];

        $this->assertSame($expected, $this->calls);

        // Each step received the result of the previous one
        $this->assertSame([1 => [1 => $expected]], $result);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return MockObject&T
     */
    private function step(string $class, string $name): MockObject
    {
        $step = $this->createMock($class);
        $step
            ->method('process')
            ->willReturnCallback($this->record($name))
        ;

        return $step;
    }

    private function record(string $name): \Closure
    {
        return function (array $events) use ($name): array {
            $this->calls[] = $name;
            $events[1][1][] = $name;

            return $events;
        };
    }
}
