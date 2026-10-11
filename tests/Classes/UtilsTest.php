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

namespace Cgoit\CalendarExtendedBundle\Tests\Classes;

use Cgoit\CalendarExtendedBundle\Classes\Utils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UtilsTest extends TestCase
{
    public function testCalculateMovedTimesKeepsTimeAndDuration(): void
    {
        [$start, $end] = Utils::calculateMovedTimes(strtotime('2025-03-03 09:00'), 3600, '+1 day');

        $this->assertSame(strtotime('2025-03-04 09:00'), $start);
        $this->assertSame(strtotime('2025-03-04 10:00'), $end);
    }

    public function testCalculateMovedTimesWithNewTimes(): void
    {
        [$start, $end] = Utils::calculateMovedTimes(strtotime('2025-03-03 09:00'), 3600, '-2 days', '14:30', '16:00');

        $this->assertSame(strtotime('2025-03-01 14:30'), $start);
        $this->assertSame(strtotime('2025-03-01 16:00'), $end);
    }

    public function testCalculateMovedTimesWithEmptyNewTimes(): void
    {
        [$start, $end] = Utils::calculateMovedTimes(strtotime('2025-03-03 09:00'), 5400, '+0 days', '', '');

        $this->assertSame(strtotime('2025-03-03 09:00'), $start);
        $this->assertSame(strtotime('2025-03-03 10:30'), $end);
    }

    public function testAppendMovedClass(): void
    {
        $this->assertSame(' moved', Utils::appendMovedClass(''));
        $this->assertSame(' existing moved', Utils::appendMovedClass(' existing', ''));
        $this->assertSame(' existing moved custom', Utils::appendMovedClass(' existing', ' custom '));
    }

    #[DataProvider('fixedDates')]
    public function testHasFixedDates(bool $expected, mixed $value): void
    {
        $this->assertSame($expected, Utils::hasFixedDates($value));
    }

    /**
     * @return iterable<string, array{bool, mixed}>
     */
    public static function fixedDates(): iterable
    {
        yield 'null' => [false, null];
        yield 'empty string' => [false, ''];
        yield 'empty wizard row' => [false, serialize([['new_repeat' => '', 'new_start' => '', 'new_end' => '']])];
        yield 'one date' => [true, serialize([['new_repeat' => 1741564800, 'new_start' => '', 'new_end' => '']])];
        yield 'date in second row' => [true, serialize([['new_repeat' => '', 'new_start' => ''], ['new_repeat' => 1741564800]])];
        yield 'invalid structure' => [false, serialize(['new_repeat' => 1741564800])];
    }

    #[DataProvider('cssColors')]
    public function testBuildCssColor(string $expected, string $hex, int|string|null $opacity): void
    {
        $this->assertSame($expected, Utils::buildCssColor($hex, $opacity));
    }

    /**
     * @return iterable<string, array{string, string, int|string|null}>
     */
    public static function cssColors(): iterable
    {
        yield 'opaque' => ['#ff0000', 'ff0000', null];
        yield 'leading hash' => ['#ff0000', '#FF0000', ''];
        yield 'short notation' => ['#aabbcc', 'abc', '100'];
        yield 'opacity as string' => ['rgba(255, 0, 0, 0.5)', 'ff0000', '50'];
        yield 'opacity as int' => ['rgba(0, 128, 255, 0.25)', '0080ff', 25];
        yield 'zero opacity is ignored like in the style attribute' => ['#00ff00', '00ff00', 0];
        yield 'invalid color' => ['', 'red', null];
        yield 'empty color' => ['', '', 50];
    }

    public function testAppendToArrayKeyCreatesAndAppends(): void
    {
        $arr = [];

        Utils::appendToArrayKey($arr, 'background', 'a;');
        $this->assertSame(['background' => 'a;'], $arr);

        Utils::appendToArrayKey($arr, 'background', 'b;');
        $this->assertSame(['background' => 'a;b;'], $arr);
    }

    public function testSanitizeFixedDatesConvertsStringsToTimestamps(): void
    {
        $result = Utils::sanitizeFixedDates([
            ['new_repeat' => '2025-03-10 00:00:00', 'new_start' => '2025-03-10 09:30:00', 'new_end' => ''],
        ]);

        $this->assertSame(strtotime('2025-03-10 00:00:00'), $result[0]['new_repeat']);
        $this->assertSame(strtotime('2025-03-10 09:30:00'), $result[0]['new_start']);
        $this->assertSame('', $result[0]['new_end']);
    }

    public function testSanitizeFixedDatesKeepsIntegers(): void
    {
        $result = Utils::sanitizeFixedDates([
            ['new_repeat' => 1741564800, 'new_start' => 0, 'new_end' => 0],
        ]);

        $this->assertSame(1741564800, $result[0]['new_repeat']);
    }

    public function testSanitizeFixedDatesDropsRowsWithoutDate(): void
    {
        $result = Utils::sanitizeFixedDates([
            ['new_repeat' => '', 'new_start' => '', 'new_end' => ''],
            ['new_repeat' => 1741564800, 'new_start' => '', 'new_end' => ''],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(1741564800, array_values($result)[0]['new_repeat']);
    }

    public function testSanitizeFixedDatesResetsUnparsableValuesToZero(): void
    {
        $result = Utils::sanitizeFixedDates([
            ['new_repeat' => 1741564800, 'new_start' => 'not a date', 'new_end' => ''],
        ]);

        $this->assertSame(0, $result[0]['new_start']);
    }
}
