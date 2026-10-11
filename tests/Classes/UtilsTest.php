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
use Contao\CalendarEventsModel;
use Contao\PageModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UtilsTest extends TestCase
{
    #[DataProvider('repeatValues')]
    public function testGetUntilAndRecurringUsesSingularTextForOne(int|string $value, string $expectedRepeat): void
    {
        $GLOBALS['TL_LANG']['MSC']['cal_single_days'] = 'daily';
        $GLOBALS['TL_LANG']['MSC']['cal_multiple_days'] = 'every %s days';
        $GLOBALS['TL_LANG']['MSC']['cal_repeat'] = '%s|%s|%s|%s';

        $event = $this->mockModel(CalendarEventsModel::class, [
            'recurring' => 1,
            'repeatEach' => serialize(['value' => $value, 'unit' => 'days']),
            'recurrences' => 0,
            'addTime' => 0,
        ]);
        $page = $this->mockModel(PageModel::class, ['dateFormat' => 'Y-m-d']);

        [, $recurring] = Utils::getUntilAndRecurring($event, $page, strtotime('2025-03-03'), '2025-03-03', '', false);

        $this->assertSame($expectedRepeat.'||2025-03-03|2025-03-03', $recurring);
    }

    /**
     * @return iterable<string, array{int|string, string}>
     */
    public static function repeatValues(): iterable
    {
        yield 'integer one' => [1, 'daily'];
        yield 'string one as stored by the widget' => ['1', 'daily'];
        yield 'string two' => ['2', 'every 2 days'];
    }

    /**
     * @param array<string> $expected
     */
    #[DataProvider('languages')]
    public function testGetFullCalendarLocales(string $language, array $expected): void
    {
        $this->assertSame($expected, Utils::getFullCalendarLocales($language));
    }

    /**
     * @return iterable<string, array{string, array<string>}>
     */
    public static function languages(): iterable
    {
        yield 'language only' => ['de', ['de']];
        yield 'region with underscore' => ['de_CH', ['de-ch', 'de']];
        yield 'region in upper case' => ['pt_BR', ['pt-br', 'pt']];
        yield 'chinese' => ['zh_CN', ['zh-cn', 'zh']];
        yield 'hyphen is kept' => ['en-GB', ['en-gb', 'en']];
        yield 'empty' => ['', ['en']];
    }

    public function testAppendDayAndTimesToUrl(): void
    {
        $begin = strtotime('2025-03-10 09:00');
        $end = strtotime('2025-03-10 10:00');

        $this->assertSame(
            \sprintf('/events/concert.html?day=20250310&times=%d,%d', $begin, $end),
            Utils::appendDayAndTimesToUrl('/events/concert.html', $begin, $end),
        );
    }

    public function testAppendDayAndTimesToUrlWithExistingQuery(): void
    {
        $begin = strtotime('2025-03-10 09:00');
        $end = strtotime('2025-03-10 10:00');

        $this->assertSame(
            \sprintf('/events/concert.html?foo=bar&day=20250310&times=%d,%d', $begin, $end),
            Utils::appendDayAndTimesToUrl('/events/concert.html?foo=bar', $begin, $end),
        );
    }

    public function testAppendDayAndTimesToUrlKeepsFragment(): void
    {
        $begin = strtotime('2025-03-10 09:00');
        $end = strtotime('2025-03-10 10:00');

        $this->assertSame(
            \sprintf('/events/concert.html?day=20250310&times=%d,%d#details', $begin, $end),
            Utils::appendDayAndTimesToUrl('/events/concert.html#details', $begin, $end),
        );
    }

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

    public function testCalculateMovedTimesKeepsEndOnTheSameDayForShortEvents(): void
    {
        // A one hour event moved to 23:00-23:30 must not end on the next day
        [$start, $end] = Utils::calculateMovedTimes(strtotime('2025-03-03 09:00'), 3600, '+0 days', '23:00', '23:30', 0);

        $this->assertSame(strtotime('2025-03-03 23:00'), $start);
        $this->assertSame(strtotime('2025-03-03 23:30'), $end);
    }

    public function testCalculateMovedTimesKeepsEventsOverMidnight(): void
    {
        // The event runs from 22:00 to 02:00 of the next day and is moved to 21:00-03:00
        [$start, $end] = Utils::calculateMovedTimes(strtotime('2025-03-03 22:00'), 4 * 3600, '+1 day', '21:00', '03:00', 1);

        $this->assertSame(strtotime('2025-03-04 21:00'), $start);
        $this->assertSame(strtotime('2025-03-05 03:00'), $end);
    }

    public function testDaysBetween(): void
    {
        $this->assertSame(0, Utils::daysBetween(strtotime('2025-03-03 09:00'), strtotime('2025-03-03 23:59')));
        $this->assertSame(1, Utils::daysBetween(strtotime('2025-03-03 22:00'), strtotime('2025-03-04 02:00')));
        $this->assertSame(-1, Utils::daysBetween(strtotime('2025-03-04'), strtotime('2025-03-03')));
        // across the switch to daylight saving time
        $this->assertSame(2, Utils::daysBetween(strtotime('2025-03-29 12:00'), strtotime('2025-03-31 12:00')));
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
        $this->assertSame(' moved my-class other_1', Utils::appendMovedClass('', "my-class\tother_1"));
        $this->assertSame(' moved ok', Utils::appendMovedClass('', 'a"onmouseover="alert(1) ok'));
        $this->assertSame(' moved', Utils::appendMovedClass('', '"><script>'));
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

    public function testGetPlainTitleUsesTheRawTitle(): void
    {
        $this->assertSame('Fish & Chips', Utils::getPlainTitle(['title' => 'Fish &amp; Chips', 'link' => 'Fish & Chips']));
        $this->assertSame('Fish & Chips', Utils::getPlainTitle(['title' => 'Fish &amp; Chips']));
    }

    public function testGetPlainTeaserStripsHtml(): void
    {
        $this->assertSame('Fish & Chips', Utils::getPlainTeaser('<p>Fish &amp; <b>Chips</b></p>'));
        $this->assertSame('', Utils::getPlainTeaser(null));
    }

    public function testCalculateEventRangeWithoutTime(): void
    {
        [$start, $end] = Utils::calculateEventRange(['startDate' => strtotime('2025-03-03 00:00'), 'endDate' => '', 'addTime' => 0]);

        $this->assertSame(strtotime('2025-03-03 00:00'), $start);
        $this->assertSame(strtotime('2025-03-03 23:59'), $end);
    }

    public function testCalculateEventRangeWithTime(): void
    {
        [$start, $end] = Utils::calculateEventRange([
            'startDate' => strtotime('2025-03-03 00:00'),
            'endDate' => strtotime('2025-03-04 00:00'),
            'addTime' => 1,
            'startTime' => strtotime('1970-01-01 10:00'),
            'endTime' => strtotime('1970-01-01 12:30'),
        ]);

        $this->assertSame(strtotime('2025-03-03 10:00'), $start);
        $this->assertSame(strtotime('2025-03-04 12:30'), $end);
    }

    #[DataProvider('overlappingRanges')]
    public function testRangesOverlap(int $start, int $end, bool $expected): void
    {
        // existing event: 100 - 200
        $this->assertSame($expected, Utils::rangesOverlap($start, $end, 100, 200));
    }

    /**
     * @return iterable<string, array{int, int, bool}>
     */
    public static function overlappingRanges(): iterable
    {
        yield 'identical' => [100, 200, true];
        yield 'same start, shorter' => [100, 150, true];
        yield 'same start, longer' => [100, 300, true];
        yield 'starts inside' => [150, 250, true];
        yield 'ends inside' => [50, 150, true];
        yield 'encloses' => [50, 250, true];
        yield 'inside' => [120, 180, true];
        yield 'touches the end' => [200, 300, false];
        yield 'touches the start' => [0, 100, false];
        yield 'before' => [0, 50, false];
        yield 'after' => [250, 300, false];
    }

    public function testParseTimeRange(): void
    {
        $this->assertSame(
            [strtotime('2025-03-01T00:00:00'), strtotime('2025-04-12T00:00:00')],
            Utils::parseTimeRange('2025-03-01T00:00:00', '2025-04-12T00:00:00', 400 * 86400),
        );
    }

    #[DataProvider('invalidTimeRanges')]
    public function testParseTimeRangeRejectsInvalidValues(string $start, string $end): void
    {
        $this->assertNull(Utils::parseTimeRange($start, $end, 400 * 86400));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidTimeRanges(): iterable
    {
        yield 'empty' => ['', ''];
        yield 'not a date' => ['foo', 'bar'];
        yield 'end missing' => ['2025-03-01', ''];
        yield 'end before start' => ['2025-04-01', '2025-03-01'];
        yield 'too long' => ['2025-01-01', '2027-01-01'];
    }

    public function testFindOverlappingEventIdsIgnoresTheOwnEventAndNeighbours(): void
    {
        $events = [
            ['id' => '1', 'startTime' => '100', 'endTime' => '200'], // own event
            ['id' => '2', 'startTime' => '150', 'endTime' => '250'], // overlaps
            ['id' => '3', 'startTime' => '200', 'endTime' => '300'], // touches
            ['id' => '4', 'startTime' => '0', 'endTime' => '1000'], // encloses
        ];

        $this->assertSame([2, 4], Utils::findOverlappingEventIds(100, 200, 1, $events));
        $this->assertSame([], Utils::findOverlappingEventIds(100, 200, 1, []));
    }

    public function testResolveRequestedTimesRejectsOccurrencesBeyondThePrecalculation(): void
    {
        // Only the stored recurrences are known, a later occurrence of an endless series is ignored
        $recurrences = [1700100000 => ['int_start' => 1700100000, 'int_end' => 1700103600]];

        $this->assertNull(Utils::resolveRequestedTimes('1900000000,1900003600', 1700000000, 1700003600, $recurrences, [], false));
    }

    #[DataProvider('requestedTimes')]
    public function testResolveRequestedTimes(string $times, bool $valid): void
    {
        $recurrences = [
            1700100000 => ['int_start' => 1700100000, 'int_end' => 1700103600],
            1700186400 => ['int_start' => 1700186400, 'int_end' => 1700190000],
        ];

        $result = Utils::resolveRequestedTimes($times, 1700000000, 1700003600, $recurrences, [], true);

        $this->assertSame($valid ? array_map(\intval(...), explode(',', $times)) : null, $result);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function requestedTimes(): iterable
    {
        yield 'event itself' => ['1700000000,1700003600', true];
        yield 'recurrence' => ['1700186400,1700190000', true];
        yield 'unknown occurrence' => ['1700200000,1700203600', false];
        yield 'single value' => ['1700000000', false];
        yield 'three values' => ['1700000000,1700003600,1', false];
        yield 'not numeric' => ['abc,def', false];
        yield 'end before start' => ['1700003600,1700000000', false];
    }

    public function testResolveRequestedTimesAcceptsMovedOccurrence(): void
    {
        $recurrences = [1700100000 => ['int_start' => 1700100000, 'int_end' => 1700103600]];
        $exceptions = [1700100000 => ['action' => 'move', 'new_exception' => '+1 day']];

        $this->assertSame(
            [1700186400, 1700190000],
            Utils::resolveRequestedTimes('1700186400,1700190000', 1700000000, 1700003600, $recurrences, $exceptions, false),
        );
    }

    public function testParseTimeReturnsTheTimestamp(): void
    {
        $this->assertSame(strtotime('2025-03-03 09:00'), Utils::parseTime('2025-03-03 09:00'));
        $this->assertSame(strtotime('2025-03-05 09:00'), Utils::parseTime('+2 days', strtotime('2025-03-03 09:00')));
    }

    public function testParseTimeFailsForInvalidValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Utils::parseTime('not a date');
    }

    public function testMakeTimeReturnsTheTimestamp(): void
    {
        $this->assertSame(strtotime('2025-03-03 09:30:15'), Utils::makeTime(9, 30, 15, 3, 3, 2025));
    }

    public function testParseYearParameter(): void
    {
        $this->assertNull(Utils::parseYearParameter(null));
        $this->assertNull(Utils::parseYearParameter(''));
        $this->assertSame(strtotime('2025-01-01'), Utils::parseYearParameter('2025'));
    }

    #[DataProvider('invalidYears')]
    public function testParseYearParameterRejectsInvalidValues(mixed $year): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Utils::parseYearParameter($year);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidYears(): iterable
    {
        yield 'text' => ['abc'];
        yield 'trailing text' => ['2025x'];
        yield 'two digits' => ['25'];
        yield 'five digits' => ['20255'];
        yield 'array' => [['2025']];
    }

    /**
     * Contao models need a booted container, so the magic accessors are mocked.
     *
     * @template T of object
     *
     * @param class-string<T>      $class
     * @param array<string, mixed> $data
     *
     * @return T&MockObject
     */
    private function mockModel(string $class, array $data): object
    {
        $model = $this->createMock($class);
        $model
            ->method('__isset')
            ->willReturnCallback(static fn (string $key): bool => isset($data[$key]))
        ;

        $model
            ->method('__get')
            ->willReturnCallback(static fn (string $key): mixed => $data[$key] ?? null)
        ;

        return $model;
    }
}
