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
use PHPUnit\Framework\TestCase;

final class UtilsTest extends TestCase
{
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
