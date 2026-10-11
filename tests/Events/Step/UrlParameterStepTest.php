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
use Cgoit\CalendarExtendedBundle\Events\Step\UrlParameterStep;
use PHPUnit\Framework\TestCase;

final class UrlParameterStepTest extends TestCase
{
    public function testDayAndTimesAreAppendedToTheLinkOfARecurrence(): void
    {
        $begin = strtotime('2025-03-10 09:00');
        $end = strtotime('2025-03-10 10:00');

        $result = $this->process([['startTime' => strtotime('2025-03-03 09:00'), 'begin' => $begin, 'end' => $end, 'href' => '/events/concert.html']], new EventContext());

        $this->assertSame('/events/concert.html?day=20250310&times='.$begin.','.$end, $result[0]['href']);
    }

    public function testTheLinkOfTheFirstOccurrenceIsNotChanged(): void
    {
        $start = strtotime('2025-03-03 09:00');

        $result = $this->process([['startTime' => $start, 'begin' => $start, 'end' => $start + 3600, 'href' => '/events/concert.html']], new EventContext());

        $this->assertSame('/events/concert.html', $result[0]['href']);
    }

    public function testEventsWithoutLinkAreSkipped(): void
    {
        $result = $this->process([['startTime' => 1, 'begin' => 2, 'end' => 3, 'href' => null], ['startTime' => 1, 'begin' => 2, 'end' => 3]], new EventContext());

        $this->assertNull($result[0]['href']);
        $this->assertArrayNotHasKey('href', $result[1]);
    }

    public function testNothingIsAppendedForModulesWithoutDynamicDates(): void
    {
        $result = $this->process([['startTime' => 1, 'begin' => 2, 'end' => 3, 'href' => '/events/concert.html']], new EventContext(ignoreDynamic: true));

        $this->assertSame('/events/concert.html', $result[0]['href']);
    }

    /**
     * @param array<int, array<string, mixed>> $eventList
     *
     * @return array<int, array<string, mixed>>
     */
    private function process(array $eventList, EventContext $context): array
    {
        return (new UrlParameterStep())->process(['20250303' => [1741000000 => $eventList]], $context)['20250303'][1741000000];
    }
}
