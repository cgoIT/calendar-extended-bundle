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

namespace Cgoit\CalendarExtendedBundle\Tests\Controller\FrontendModule;

use Cgoit\CalendarExtendedBundle\Controller\FrontendModule\TimeTableController;
use Contao\CoreBundle\Exception\PageNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;

final class TimeTableControllerTest extends CalendarViewTestCase
{
    private const array MODULE = ['id' => '5', 'use_navigation' => '1', 'linkCurrent' => '1', 'cal_startDay' => '1'];

    public function testWithoutCalendarsNothingIsRendered(): void
    {
        [$response, $data] = $this->render(new TimeTableController($this->provider([])), $this->page(), self::MODULE);

        $this->assertSame('', $response->getContent());
        $this->assertArrayNotHasKey('calendar', $data);
    }

    public function testWithoutPageNothingIsRendered(): void
    {
        [$response] = $this->render(new TimeTableController($this->provider([1])), null, self::MODULE);

        $this->assertSame('', $response->getContent());
    }

    public function testTheGivenWeekIsShownWithNavigation(): void
    {
        $provider = $this->provider([1], [$this->time('2026-01-01'), $this->time('2026-12-31'), 0]);

        [$response, $data] = $this->render(new TimeTableController($provider), $this->page(), self::MODULE, ['week' => '202620']);

        $this->assertSame('rendered', $response->getContent());
        $this->assertSame('@Contao/cal_timetable.html.twig', $data['calendar_template']);

        $calendar = $data['calendar'];
        $this->assertSame(2026, $calendar['intYear']);
        $this->assertSame(20, $calendar['intWeek']);
        $this->assertSame(strtotime('2026-05-11 00:00:00'), $calendar['weekBegin']);
        $this->assertSame(strtotime('2026-05-17 23:59:59'), $calendar['weekEnd']);
        $this->assertSame('/calendar?week=202619', $calendar['prevHref']);
        $this->assertSame('/calendar?week=202621', $calendar['nextHref']);
        $this->assertSame('Week 20 2026', $calendar['current']);
        $this->assertCount(7, $calendar['weekday']);
    }

    public function testTheDayParameterSelectsItsWeek(): void
    {
        $provider = $this->provider([1], [$this->time('2026-01-01'), $this->time('2026-12-31'), 0]);

        [, $data] = $this->render(new TimeTableController($provider), $this->page(), self::MODULE, ['day' => '20260513']);

        $this->assertSame(20, $data['calendar']['intWeek']);
    }

    public function testTheWeekAtTheYearBoundaryBelongsToTheIsoYear(): void
    {
        $provider = $this->provider([1], [$this->time('2020-01-01'), $this->time('2030-12-31'), 0]);

        // 2025 has 52 weeks, 2026 has 53 weeks (2027-01-01 is a Friday in week 53 of 2026)
        [, $data] = $this->render(new TimeTableController($provider), $this->page(), self::MODULE, ['week' => '202601']);
        $this->assertSame('/calendar?week=202552', $data['calendar']['prevHref']);

        [, $data] = $this->render(new TimeTableController($provider), $this->page(), self::MODULE, ['week' => '202653']);
        $this->assertSame('/calendar?week=202701', $data['calendar']['nextHref']);
    }

    public function testTheDaysAreOrderedByTheStartDay(): void
    {
        $provider = $this->provider([1], [$this->time('2026-01-01'), $this->time('2026-12-31'), 0]);

        [, $data] = $this->render(new TimeTableController($provider), $this->page(), ['cal_startDay' => '0'] + self::MODULE, ['week' => '202620']);

        $this->assertSame([0, 1, 2, 3, 4, 5, 6], array_keys($data['calendar']['weekday']));
        $this->assertStringContainsString('weekend', (string) $data['calendar']['weekday'][0]['class']);
    }

    public function testEmptyDaysAreHiddenOnRequest(): void
    {
        $provider = $this->provider([1], [$this->time('2026-01-01'), $this->time('2026-12-31'), 0]);

        [, $data] = $this->render(new TimeTableController($provider), $this->page(), ['hideEmptyDays' => '1'] + self::MODULE, ['week' => '202620']);

        $this->assertSame([], $data['calendar']['weekday']);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function unknownPeriods(): iterable
    {
        yield 'week before the first event' => [['week' => '201001']];
        yield 'week after the last event' => [['week' => '209901']];
        yield 'day before the first event' => [['day' => '20100101']];
        yield 'day after the last event' => [['day' => '20990101']];
        yield 'invalid month' => [['month' => '202613']];
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('unknownPeriods')]
    public function testAnUnknownPeriodIsNotFound(array $query): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->render(new TimeTableController($this->provider([1], [$this->time('2026-01-01'), $this->time('2026-12-31'), 0])), $this->page(), self::MODULE, $query);
    }
}
