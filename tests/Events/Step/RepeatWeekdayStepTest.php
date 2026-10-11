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

use Cgoit\CalendarExtendedBundle\Events\Step\RepeatWeekdayStep;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RepeatWeekdayStepTest extends TestCase
{
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

        $result = (new RepeatWeekdayStep())->process([
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
}
