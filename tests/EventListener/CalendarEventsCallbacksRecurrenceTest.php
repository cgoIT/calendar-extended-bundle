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

namespace Cgoit\CalendarExtendedBundle\Tests\EventListener;

use Cgoit\CalendarExtendedBundle\EventListener\DataContainer\CalendarEventsCallbacks;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Tests the pre-calculation of recurrences that runs when an event is saved in the back end.
 */
final class CalendarEventsCallbacksRecurrenceTest extends TestCase
{
    private const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    private const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    /**
     * @var array<string, mixed>
     */
    private array $globalsBackup;

    protected function setUp(): void
    {
        $this->globalsBackup = ['TL_CONFIG' => $GLOBALS['TL_CONFIG'] ?? null, 'TL_LANG' => $GLOBALS['TL_LANG'] ?? null];

        $GLOBALS['TL_CONFIG']['datimFormat'] = 'Y-m-d H:i';
        $GLOBALS['TL_CONFIG']['dateFormat'] = 'Y-m-d';
        $GLOBALS['TL_LANG']['MONTHS'] = self::MONTHS;
    }

    protected function tearDown(): void
    {
        $GLOBALS['TL_CONFIG'] = $this->globalsBackup['TL_CONFIG'];
        $GLOBALS['TL_LANG'] = $this->globalsBackup['TL_LANG'];
    }

    public function testEventWithoutRecurrenceContainsOnlyItself(): void
    {
        [$all, $set] = $this->calculate($this->event());

        $this->assertSame($this->times('2025-03-03'), array_keys($all));
        $this->assertNull($set['exceptionList']);
    }

    public function testDailyRecurrence(): void
    {
        // Monday 2025-03-03, 09:00-10:00, every day until 2025-03-07 12:00
        $event = $this->event([
            'recurring' => 1,
            'repeatEach' => serialize(['value' => 1, 'unit' => 'days']),
            'repeatEnd' => strtotime('2025-03-07 12:00'),
        ]);

        [$all, $set] = $this->calculate($event);

        $this->assertSame($this->times('2025-03-03', '2025-03-04', '2025-03-05', '2025-03-06', '2025-03-07'), array_keys($all));
        $this->assertSame(strtotime('2025-03-07 12:00'), $set['repeatEnd']);

        $first = $all[strtotime('2025-03-03 09:00')];
        $this->assertSame(strtotime('2025-03-03 09:00'), $first['int_start']);
        $this->assertSame(strtotime('2025-03-03 10:00'), $first['int_end']);
        $this->assertSame('2025-03-03 09:00', $first['str_start']);
        $this->assertSame('2025-03-03 10:00', $first['str_end']);
    }

    public function testWeeklyRecurrenceKeepsDuration(): void
    {
        $event = $this->event([
            'recurring' => 1,
            'repeatEach' => serialize(['value' => 1, 'unit' => 'weeks']),
            'repeatEnd' => strtotime('2025-03-25 00:00'),
        ]);

        [$all] = $this->calculate($event);

        $this->assertSame($this->times('2025-03-03', '2025-03-10', '2025-03-17', '2025-03-24'), array_keys($all));

        foreach ($all as $recurrence) {
            $this->assertSame(3600, $recurrence['int_end'] - $recurrence['int_start']);
        }
    }

    public function testRecurrenceOnSelectedWeekdaysOnly(): void
    {
        // Only Monday (1) and Wednesday (3)
        $event = $this->event([
            'recurring' => 1,
            'repeatEach' => serialize(['value' => 1, 'unit' => 'days']),
            'repeatWeekday' => serialize(['1', '3']),
            'repeatEnd' => strtotime('2025-03-15 00:00'),
        ]);

        [$all] = $this->calculate($event);

        $this->assertSame($this->times('2025-03-03', '2025-03-05', '2025-03-10', '2025-03-12'), array_keys($all));
    }

    public function testRecurrenceOnSundayWhenSundayIsSelected(): void
    {
        // The back end stores Sunday as "0" and Saturday as "6"
        $event = $this->event([
            'recurring' => 1,
            'repeatEach' => serialize(['value' => 1, 'unit' => 'days']),
            'repeatWeekday' => serialize(['0', '6']),
            'repeatEnd' => strtotime('2025-03-17 00:00'),
        ]);

        [$all] = $this->calculate($event);

        $this->assertSame($this->times('2025-03-03', '2025-03-08', '2025-03-09', '2025-03-15', '2025-03-16'), array_keys($all));
    }

    public function testHideOnWeekend(): void
    {
        $event = $this->event([
            'recurring' => 1,
            'repeatEach' => serialize(['value' => 1, 'unit' => 'days']),
            'hideOnWeekend' => 1,
            'repeatEnd' => strtotime('2025-03-11 00:00'),
        ]);

        [$all] = $this->calculate($event);

        // 2025-03-08/09 is a weekend
        $this->assertSame($this->times('2025-03-03', '2025-03-04', '2025-03-05', '2025-03-06', '2025-03-07', '2025-03-10'), array_keys($all));
    }

    public function testMaxRepeatCountLimitsRecurrences(): void
    {
        $event = $this->event([
            'recurring' => 1,
            'repeatEach' => serialize(['value' => 1, 'unit' => 'days']),
            'repeatEnd' => strtotime('2025-12-31 00:00'),
        ]);

        [$all] = $this->calculate($event, 5);

        $this->assertCount(5, $all);
    }

    public function testRecurrenceWithoutRepeatEachAddsNothing(): void
    {
        $event = $this->event([
            'recurring' => 1,
            'repeatEach' => '',
            'repeatEnd' => strtotime('2025-03-15 00:00'),
        ]);

        [$all] = $this->calculate($event);

        $this->assertSame($this->times('2025-03-03'), array_keys($all));
    }

    public function testFixedDates(): void
    {
        $event = $this->event([
            'repeatFixedDates' => serialize([
                ['new_repeat' => strtotime('2025-04-20'), 'new_start' => '', 'new_end' => '', 'reason' => ''],
                ['new_repeat' => strtotime('2025-04-10'), 'new_start' => strtotime('1970-01-01 14:30'), 'new_end' => strtotime('1970-01-01 16:00'), 'reason' => ''],
            ]),
        ]);

        [$all, $set] = $this->calculate($event);

        // Original date plus both fixed dates, sorted by date, with the custom time on 2025-04-10
        $this->assertSame(
            [strtotime('2025-03-03 09:00'), strtotime('2025-04-10 14:30'), strtotime('2025-04-20 09:00')],
            array_keys($all),
        );
        $this->assertSame(strtotime('2025-04-10 16:00'), $all[strtotime('2025-04-10 14:30')]['int_end']);
        $this->assertSame(strtotime('2025-04-20 10:00'), $all[strtotime('2025-04-20 09:00')]['int_end']);
        $this->assertSame(strtotime('2025-04-20 10:00'), $set['repeatEnd']);
        $this->assertNotNull($set['repeatFixedDates']);
    }

    public function testEmptyFixedDatesRowFromWizardIsIgnored(): void
    {
        $event = $this->event([
            'repeatFixedDates' => serialize([['new_repeat' => '', 'new_start' => '', 'new_end' => '']]),
        ]);

        [$all] = $this->calculate($event);

        $this->assertSame([], $all);
    }

    public function testExtendedRecurrenceFirstMondayOfMonth(): void
    {
        // Monday 2025-03-03 is the first Monday of March
        $event = $this->event([
            'recurringExt' => 1,
            'repeatEachExt' => serialize(['value' => 'first', 'unit' => 'monday']),
            'recurrences' => 3,
        ]);

        [$all, $set] = $this->calculate($event);

        $this->assertSame($this->times('2025-03-03', '2025-04-07', '2025-05-05', '2025-06-02'), array_keys($all));
        $this->assertSame(strtotime('2025-06-02 09:00'), $set['repeatEnd']);
        $this->assertSame(strtotime('2025-05-05 10:00'), $all[strtotime('2025-05-05 09:00')]['int_end']);
    }

    public function testExtendedRecurrenceWithoutEndDateUsesStartDate(): void
    {
        $event = $this->event([
            'endDate' => null,
            'recurringExt' => 1,
            'repeatEachExt' => serialize(['value' => 'first', 'unit' => 'monday']),
            'recurrences' => 2,
        ]);

        [$all] = $this->calculate($event);

        $this->assertSame($this->times('2025-03-03', '2025-04-07', '2025-05-05'), array_keys($all));

        foreach ($all as $recurrence) {
            $this->assertSame(3600, $recurrence['int_end'] - $recurrence['int_start']);
        }
    }

    public function testExtendedRecurrenceLastFridayWrapsYear(): void
    {
        $event = $this->event([
            'startDate' => strtotime('2025-11-28'),
            'endDate' => strtotime('2025-11-28'),
            'startTime' => strtotime('2025-11-28 09:00'),
            'endTime' => strtotime('2025-11-28 10:00'),
            'recurringExt' => 1,
            'repeatEachExt' => serialize(['value' => 'last', 'unit' => 'friday']),
            'recurrences' => 3,
        ]);

        [$all] = $this->calculate($event);

        $this->assertSame($this->times('2025-11-28', '2025-12-26', '2026-01-30', '2026-02-27'), array_keys($all));
    }

    public function testExtendedRecurrenceRespectsMaxRepeatCount(): void
    {
        $event = $this->event([
            'recurringExt' => 1,
            'repeatEachExt' => serialize(['value' => 'first', 'unit' => 'monday']),
            'recurrences' => 10,
        ]);

        [$all] = $this->calculate($event, 3);

        $this->assertCount(3, $all);
    }

    public function testExceptionsAreIgnoredWithoutUseExceptions(): void
    {
        $event = $this->dailyEvent([
            'repeatExceptions' => serialize([['exception' => strtotime('2025-03-04 09:00'), 'action' => 'hide']]),
        ]);

        [, $set] = $this->calculate($event);

        $this->assertNull($set['exceptionList']);
    }

    public function testHideExceptionByDate(): void
    {
        $event = $this->dailyEvent([
            'useExceptions' => 1,
            'repeatExceptions' => serialize([
                ['exception' => strtotime('2025-03-04 09:00'), 'action' => 'hide', 'new_exception' => '', 'new_start' => '', 'new_end' => '', 'reason' => 'Holiday'],
            ]),
        ]);

        [, $set] = $this->calculate($event);

        $exceptions = unserialize($set['exceptionList']);

        $this->assertSame([strtotime('2025-03-04 09:00')], array_keys($exceptions));
        $this->assertSame('hide', $exceptions[strtotime('2025-03-04 09:00')]['action']);
        $this->assertSame('Holiday', $exceptions[strtotime('2025-03-04 09:00')]['reason']);
        $this->assertSame('04.03.2025 09:00', $exceptions[strtotime('2025-03-04 09:00')]['exception_date']);
    }

    public function testMoveExceptionFillsDefaultTimesFromEvent(): void
    {
        $event = $this->dailyEvent([
            'useExceptions' => 1,
            'repeatExceptions' => serialize([
                ['exception' => strtotime('2025-03-04 09:00'), 'action' => 'move', 'new_exception' => '+1 day', 'new_start' => '', 'new_end' => ''],
            ]),
        ]);

        [, $set] = $this->calculate($event);

        $row = unserialize($set['exceptionList'])[strtotime('2025-03-04 09:00')];

        $this->assertSame('09:00', $row['new_start']);
        $this->assertSame('10:00', $row['new_end']);
    }

    public function testMoveExceptionBeyondRepeatEndExtendsRepeatEnd(): void
    {
        $event = $this->dailyEvent([
            'useExceptions' => 1,
            'repeatExceptions' => serialize([
                ['exception' => strtotime('2025-03-05 09:00'), 'action' => 'move', 'new_exception' => '+10 days', 'new_start' => '', 'new_end' => ''],
            ]),
        ]);

        [, $set] = $this->calculate($event);

        $this->assertSame(strtotime('2025-03-15 09:00'), $set['repeatEnd']);
    }

    public function testIgnoreEndTimeClearsNewEndOfException(): void
    {
        $event = $this->dailyEvent([
            'useExceptions' => 1,
            'ignoreEndTime' => 1,
            'repeatExceptions' => serialize([
                ['exception' => strtotime('2025-03-04 09:00'), 'action' => 'move', 'new_exception' => '+1 day', 'new_start' => '', 'new_end' => '10:30'],
            ]),
        ]);

        [, $set] = $this->calculate($event);

        $this->assertSame('', unserialize($set['exceptionList'])[strtotime('2025-03-04 09:00')]['new_end']);
    }

    public function testExceptionRowsWithoutDateAreSkipped(): void
    {
        $event = $this->dailyEvent([
            'useExceptions' => 1,
            'repeatExceptions' => serialize([['exception' => '', 'action' => 'hide']]),
        ]);

        [, $set] = $this->calculate($event);

        $this->assertNull($set['exceptionList']);
    }

    public function testExceptionsByTimeRangeHideAllRecurrencesInRange(): void
    {
        $event = $this->dailyEvent([
            'useExceptions' => 1,
            // Stale value of the previous save, the freshly calculated recurrences must be used
            'allRecurrences' => serialize(array_fill_keys($this->times('2025-02-03', '2025-02-04'), [])),
            'repeatExceptionsPer' => serialize([
                ['exception' => strtotime('2025-03-04'), 'exceptionTo' => strtotime('2025-03-05'), 'action' => 'hide', 'new_start' => '', 'new_end' => '23:59'],
            ]),
        ]);

        [, $set] = $this->calculate($event);

        $this->assertSame($this->times('2025-03-04', '2025-03-05'), array_keys(unserialize($set['exceptionList'])));
    }

    public function testMaxExceptionsCountLimitsExceptions(): void
    {
        $rows = [];

        foreach (['2025-03-03', '2025-03-04', '2025-03-05', '2025-03-06'] as $date) {
            $rows[] = ['exception' => strtotime($date.' 09:00'), 'action' => 'hide', 'new_exception' => '', 'new_start' => '', 'new_end' => ''];
        }

        $event = $this->dailyEvent(['useExceptions' => 1, 'repeatExceptions' => serialize($rows)]);

        [, $set] = $this->calculate($event, 365, 2);

        $this->assertCount(2, unserialize($set['exceptionList']));
    }

    public function testExceptionByIntervalForWeekdayOfEvent(): void
    {
        // Monday 2025-03-03: first Monday of the month, 5 recurrences
        $event = $this->dailyEvent([
            'recurring' => 0,
            'recurringExt' => 1,
            'repeatEachExt' => serialize(['value' => 'first', 'unit' => 'monday']),
            'recurrences' => 5,
            'useExceptions' => 1,
            'repeatExceptionsInt' => serialize([
                ['exception' => 'second', 'action' => 'hide', 'new_exception' => '', 'new_start' => '', 'new_end' => ''],
            ]),
        ]);

        [, $set] = $this->calculate($event);

        // The second Monday of every month from March to August (the last extended recurrence)
        $this->assertSame($this->times('2025-03-10', '2025-04-14', '2025-05-12', '2025-06-09', '2025-07-14', '2025-08-11'), \array_slice(array_keys(unserialize($set['exceptionList'])), 0, 6));
    }

    public function testExceptionByIntervalForSundayEvent(): void
    {
        // Sunday 2025-03-02: the weekday 0 must not be treated as "no weekday"
        $event = $this->dailyEvent([
            'startDate' => strtotime('2025-03-02'),
            'endDate' => strtotime('2025-03-02'),
            'startTime' => strtotime('2025-03-02 09:00'),
            'endTime' => strtotime('2025-03-02 10:00'),
            'recurring' => 0,
            'recurringExt' => 1,
            'repeatEachExt' => serialize(['value' => 'first', 'unit' => 'sunday']),
            'recurrences' => 3,
            'useExceptions' => 1,
            // Stale value of the previous save, the current weekday must be derived from the start date
            'weekday' => 0,
            'repeatExceptionsInt' => serialize([
                ['exception' => 'second', 'action' => 'hide', 'new_exception' => '', 'new_start' => '', 'new_end' => ''],
            ]),
        ]);

        [, $set] = $this->calculate($event);

        $this->assertSame($this->times('2025-03-09', '2025-04-13', '2025-05-11'), \array_slice(array_keys(unserialize($set['exceptionList'])), 0, 3));
    }

    public function testExceptionByIntervalIgnoresStoredWeekday(): void
    {
        // A new event has no (or an outdated) weekday value yet
        $event = $this->dailyEvent([
            'recurring' => 0,
            'recurringExt' => 1,
            'repeatEachExt' => serialize(['value' => 'first', 'unit' => 'monday']),
            'recurrences' => 2,
            'useExceptions' => 1,
            'weekday' => null,
            'repeatExceptionsInt' => serialize([
                ['exception' => 'second', 'action' => 'hide', 'new_exception' => '', 'new_start' => '', 'new_end' => ''],
            ]),
        ]);

        [, $set] = $this->calculate($event);

        $this->assertSame($this->times('2025-03-10', '2025-04-14'), \array_slice(array_keys(unserialize($set['exceptionList'])), 0, 2));
    }

    public function testIgnoreEndTimeSetsEndTimeToEndDateWithStartTime(): void
    {
        // Event spans 2025-03-03 to 2025-03-05, starts at 09:00, the end time is ignored
        $event = $this->event([
            'id' => 7,
            'endDate' => strtotime('2025-03-05'),
            'ignoreEndTime' => 1,
        ]);

        $update = $this->save($event);

        $this->assertSame(strtotime('2025-03-05 09:00:00'), $update['endTime']);
    }

    /**
     * Runs the onsubmit callback and returns the data written to the database.
     *
     * @return array<string, mixed>
     */
    private function save(\stdClass $event): array
    {
        $dc = $this->createMock(DataContainer::class);
        $dc
            ->method('__get')
            ->with('id')
            ->willReturn($event->id)
        ;

        $dc
            ->method('getCurrentRecord')
            ->willReturn((array) $event)
        ;

        $update = [];
        $db = $this->createMock(Connection::class);
        $db
            ->method('update')
            ->willReturnCallback(
                static function (string $table, array $data) use (&$update): int {
                    $update = $data;

                    return 1;
                },
            )
        ;

        $callbacks = $this->createCallbacks(365, 250);
        (new \ReflectionProperty(CalendarEventsCallbacks::class, 'db'))->setValue($callbacks, $db);

        $callbacks->handleRecurrencesAndExceptions($dc);

        return $update;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function dailyEvent(array $overrides = []): \stdClass
    {
        return $this->event(array_merge([
            'recurring' => 1,
            'repeatEach' => serialize(['value' => 1, 'unit' => 'days']),
            'repeatEnd' => strtotime('2025-03-07 12:00'),
            'ignoreEndTime' => 0,
            'repeatExceptions' => '',
            'repeatExceptionsInt' => '',
            'repeatExceptionsPer' => '',
            'allRecurrences' => '',
            'weekday' => 1,
        ], $overrides));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function event(array $overrides = []): \stdClass
    {
        // Monday 2025-03-03, 09:00 - 10:00
        return (object) array_merge(
            [
                'startDate' => strtotime('2025-03-03'),
                'endDate' => strtotime('2025-03-03'),
                'addTime' => 1,
                'startTime' => strtotime('2025-03-03 09:00'),
                'endTime' => strtotime('2025-03-03 10:00'),
                'recurring' => 0,
                'repeatEach' => '',
                'repeatEnd' => 0,
                'recurrences' => 0,
                'repeatWeekday' => '',
                'hideOnWeekend' => 0,
                'recurringExt' => 0,
                'repeatEachExt' => '',
                'repeatFixedDates' => '',
                'useExceptions' => 0,
                'ignoreEndTime' => 0,
                'repeatExceptions' => '',
                'repeatExceptionsInt' => '',
                'repeatExceptionsPer' => '',
                'allRecurrences' => '',
                'weekday' => 1,
            ],
            $overrides,
        );
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>}
     */
    private function calculate(\stdClass $event, int $maxRepeatCount = 365, int $maxExceptionsCount = 250): array
    {
        $callbacks = $this->createCallbacks($maxRepeatCount, $maxExceptionsCount);

        $arrSet = ['repeatEnd' => 0, 'endTime' => $event->endTime];
        [$all, $set] = $callbacks->processAllRecurrences($event, [], [0], $arrSet);

        ksort($all);

        return [$all, $set];
    }

    /**
     * The constructor extends Contao's Backend and needs a booted kernel, so it is skipped.
     */
    private function createCallbacks(int $maxRepeatCount, int $maxExceptionsCount): CalendarEventsCallbacks
    {
        $reflection = new \ReflectionClass(CalendarEventsCallbacks::class);
        $callbacks = $reflection->newInstanceWithoutConstructor();

        $reflection->getProperty('arrMonths')->setValue($callbacks, self::MONTHS);
        $reflection->getProperty('arrDays')->setValue($callbacks, self::DAYS);
        $reflection->getProperty('maxRepeatCount')->setValue($callbacks, $maxRepeatCount);
        $reflection->getProperty('maxExceptionsCount')->setValue($callbacks, $maxExceptionsCount);

        return $callbacks;
    }

    /**
     * @return array<int>
     */
    private function times(string ...$dates): array
    {
        return array_map(static fn (string $date): int => strtotime($date.' 09:00'), $dates);
    }
}
