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
use Cgoit\CalendarExtendedBundle\Tests\Support\Models;
use PHPUnit\Framework\TestCase;

/**
 * Tests the texts about the repetition of an event (Utils::getUntilAndRecurring()).
 */
final class UtilsRecurringTextTest extends TestCase
{
    /**
     * @var array<mixed>
     */
    private array $languageBackup;

    protected function setUp(): void
    {
        $this->languageBackup = $GLOBALS['TL_LANG'] ?? [];

        $GLOBALS['TL_LANG']['MSC'] = [
            'cal_single_weeks' => 'weekly',
            'cal_multiple_weeks' => 'every %s weeks',
            'cal_until' => 'until %s',
            'cal_repeat' => 'repeat %s%s|%s|%s',
            'cal_repeat_ended' => 'ended %s%s',
            'and_label' => 'and',
            'count_other_dates_singular' => '(%s more date)',
            'count_other_dates_plural' => '(%s more dates)',
        ];
        $GLOBALS['TL_LANG']['tl_calendar_events'] = [
            'first' => 'first',
            'monday' => 'Monday',
            'cal_repeat_fixed_dates' => 'on %s|%s|%s',
        ];
    }

    protected function tearDown(): void
    {
        $GLOBALS['TL_LANG'] = $this->languageBackup;
    }

    public function testAnEventWithoutRecurrenceHasNoText(): void
    {
        $this->assertSame(['', ''], $this->texts([]));
    }

    public function testAnIncompleteRepetitionHasNoText(): void
    {
        $this->assertSame(['', ''], $this->texts(['recurring' => '1', 'repeatEach' => serialize(['value' => 2])]));
        $this->assertSame(['', ''], $this->texts(['recurringExt' => '1', 'repeatEachExt' => '']));
    }

    public function testACoreRepetitionWithoutEndHasNoUntil(): void
    {
        [$until, $recurring] = $this->texts(['recurring' => '1', 'repeatEach' => serialize(['value' => '2', 'unit' => 'weeks']), 'recurrences' => '0']);

        $this->assertSame('', $until);
        $this->assertSame('repeat every 2 weeks|2030-01-07|2030-01-07', $recurring);
    }

    public function testACoreRepetitionWithEndAndTime(): void
    {
        [$until, $recurring] = $this->texts(
            ['recurring' => '1', 'repeatEach' => serialize(['value' => 1, 'unit' => 'weeks']), 'recurrences' => '3', 'repeatEnd' => (string) strtotime('2030-02-01'), 'addTime' => '1'],
            '09:00',
        );

        $this->assertSame(' until 2030-02-01', $until);
        $this->assertSame('repeat weekly until 2030-02-01|2030-01-07T09:00:00+00:00|2030-01-07 09:00', $recurring);
    }

    public function testAnEndedRepetitionSaysSo(): void
    {
        [$until, $recurring] = $this->texts(['recurring' => '1', 'repeatEach' => serialize(['value' => 1, 'unit' => 'weeks']), 'recurrences' => '3', 'repeatEnd' => (string) strtotime('2020-02-01')]);

        $this->assertSame(' until 2020-02-01', $until);
        $this->assertSame('ended weekly until 2020-02-01', $recurring);
    }

    public function testAnExtendedRepetition(): void
    {
        [$until, $recurring] = $this->texts(['recurringExt' => '1', 'repeatEachExt' => serialize(['value' => 'first', 'unit' => 'monday']), 'recurrences' => '0']);

        $this->assertSame('', $until);
        $this->assertSame('repeat first Monday|2030-01-07|2030-01-07', $recurring);
    }

    public function testAnEndedExtendedRepetition(): void
    {
        [, $recurring] = $this->texts(['recurringExt' => '1', 'repeatEachExt' => serialize(['value' => 'first', 'unit' => 'monday']), 'recurrences' => '2', 'repeatEnd' => (string) strtotime('2020-02-01')]);

        $this->assertSame('ended first Monday until 2020-02-01', $recurring);
    }

    public function testFixedDatesAreListed(): void
    {
        $dates = static fn (string ...$days): string => serialize(array_map(static fn (string $day): array => ['new_repeat' => strtotime($day)], $days));
        $fixed = ['repeatEnd' => (string) strtotime('2030-03-01')];

        [$until, $recurring] = $this->texts($fixed + ['repeatFixedDates' => $dates('2030-01-07')], fixed: true);
        $this->assertSame(' until 2030-03-01', $until);
        $this->assertSame('on 2030-01-07|2030-01-07T00:00:00+00:00|2030-01-07', $recurring);

        [, $recurring] = $this->texts($fixed + ['repeatFixedDates' => $dates('2030-01-07', '2030-02-04', '2030-03-01')], fixed: true);
        $this->assertStringStartsWith('on 2030-01-07, 2030-02-04 and 2030-03-01|', $recurring);

        [, $recurring] = $this->texts($fixed + ['repeatFixedDates' => $dates('2030-01-01', '2030-01-02', '2030-01-03', '2030-01-04', '2030-01-05')], fixed: true);
        $this->assertStringStartsWith('on 2030-01-01, 2030-01-02, 2030-01-03, 2030-01-04 (1 more date)|', $recurring);

        [, $recurring] = $this->texts($fixed + ['repeatFixedDates' => $dates('2030-01-01', '2030-01-02', '2030-01-03', '2030-01-04', '2030-01-05', '2030-01-06')], fixed: true);
        $this->assertStringStartsWith('on 2030-01-01, 2030-01-02, 2030-01-03, 2030-01-04 (2 more dates)|', $recurring);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string>
     */
    private function texts(array $row, string $time = '', bool $fixed = false): array
    {
        $event = Models::event($row + ['addTime' => '', 'recurrences' => '0', 'repeatEnd' => '0']);
        $page = Models::page(['dateFormat' => 'Y-m-d']);

        return Utils::getUntilAndRecurring($event, $page, Utils::parseTime('2030-01-07 '.($time ?: '00:00')), '2030-01-07', $time, $fixed);
    }
}
