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

namespace Cgoit\CalendarExtendedBundle\Tests\Events;

use Cgoit\CalendarExtendedBundle\Events\ReaderOccurrences;
use PHPUnit\Framework\TestCase;

final class ReaderOccurrencesTest extends TestCase
{
    public function testNextIsTheFirstOccurrenceFromNow(): void
    {
        $next = ReaderOccurrences::next($this->recurrences(), 150);

        $this->assertNotNull($next);
        $this->assertSame(200, $next['int_start']);
    }

    public function testNextIncludesAnOccurrenceThatStartsNow(): void
    {
        $next = ReaderOccurrences::next($this->recurrences(), 200);

        $this->assertNotNull($next);
        $this->assertSame(200, $next['int_start']);
    }

    public function testNextIsNullWithoutUpcomingOccurrences(): void
    {
        $this->assertNull(ReaderOccurrences::next($this->recurrences(), 1000));
        $this->assertNull(ReaderOccurrences::next([], 0));
    }

    public function testNextIgnoresTheOrderOfTheRecurrences(): void
    {
        $next = ReaderOccurrences::next([300 => ['int_start' => 300, 'int_end' => 310], 200 => ['int_start' => 200, 'int_end' => 210]], 150);

        $this->assertNotNull($next);
        $this->assertSame(200, $next['int_start']);
    }

    public function testUpcomingAreLimitedAndExcludeTheShownOccurrence(): void
    {
        $upcoming = ReaderOccurrences::upcoming($this->recurrences(), 200, 2);

        $this->assertSame([300, 400], array_column($upcoming, 'int_start'));
    }

    public function testPastAreOnlyOccurrencesThatEndedBeforeTheShownOne(): void
    {
        $past = ReaderOccurrences::past($this->recurrences(), 300, 5);

        $this->assertSame([100, 200], array_column($past, 'int_start'));
    }

    public function testNothingIsReturnedForALimitOfZero(): void
    {
        $this->assertSame([], ReaderOccurrences::upcoming($this->recurrences(), 0, 0));
        $this->assertSame([], ReaderOccurrences::past($this->recurrences(), 1000, -1));
    }

    public function testStatusClass(): void
    {
        $this->assertSame('bygone', ReaderOccurrences::statusClass(10, 20, 100, 200));
        $this->assertSame('upcoming', ReaderOccurrences::statusClass(300, 310, 100, 200));
        $this->assertSame('current', ReaderOccurrences::statusClass(150, 160, 100, 200));
        $this->assertSame('current', ReaderOccurrences::statusClass(50, 300, 100, 200));
    }

    /**
     * @return array<int, array{int_start: int, int_end: int}>
     */
    private function recurrences(): array
    {
        return [
            100 => ['int_start' => 100, 'int_end' => 110],
            200 => ['int_start' => 200, 'int_end' => 210],
            300 => ['int_start' => 300, 'int_end' => 310],
            400 => ['int_start' => 400, 'int_end' => 410],
        ];
    }
}
