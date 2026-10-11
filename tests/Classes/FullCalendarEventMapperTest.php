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

use Cgoit\CalendarExtendedBundle\Classes\FullCalendarEventMapper;
use PHPUnit\Framework\TestCase;

final class FullCalendarEventMapperTest extends TestCase
{
    public function testMapsColorsAndPlainTexts(): void
    {
        $result = FullCalendarEventMapper::map($this->event([
            'title' => 'Fish &amp; Chips',
            'link' => 'Fish & Chips',
            'teaser' => '<p>Tasty</p>',
            'bgcolor' => '#ff0000',
            'fgcolor' => '#ffffff',
        ]));

        $this->assertSame('Fish & Chips', $result['event']['title']);
        $this->assertSame('Tasty', $result['event']['description']);
        $this->assertSame('#ff0000', $result['event']['backgroundColor']);
        $this->assertSame('#ffffff', $result['event']['textColor']);
    }

    public function testColorsDefaultToEmptyStrings(): void
    {
        $result = FullCalendarEventMapper::map($this->event([]));

        $this->assertSame('', $result['event']['backgroundColor']);
        $this->assertSame('', $result['event']['textColor']);
    }

    public function testAllDayEventEndsOnTheFollowingDay(): void
    {
        $result = FullCalendarEventMapper::map($this->event([
            'addTime' => 0,
            'startDate' => strtotime('2025-03-03 00:00'),
            'endDate' => strtotime('2025-03-05 00:00'),
            'endTime' => strtotime('2025-03-05 23:59'),
        ]));

        $this->assertTrue($result['event']['allDay']);
        $this->assertSame('2025-03-03', $result['event']['start']);
        $this->assertSame('2025-03-06', $result['event']['end']);
        $this->assertTrue($result['multiday']);
    }

    public function testTimedEventUsesBeginAndEnd(): void
    {
        $result = FullCalendarEventMapper::map($this->event([
            'addTime' => 1,
            'begin' => strtotime('2025-03-03 10:00'),
            'end' => strtotime('2025-03-03 12:30'),
        ]));

        $this->assertFalse($result['event']['allDay']);
        $this->assertSame('2025-03-03T10:00:00', $result['event']['start']);
        $this->assertSame('2025-03-03T12:30:00', $result['event']['end']);
        $this->assertFalse($result['multiday']);
        $this->assertSame('fa-calendar-o', $result['event']['icon']);
    }

    public function testFixedDatesMarkTheEventAsRecurring(): void
    {
        $fixedDates = serialize([['new_repeat' => strtotime('2025-03-10 00:00')]]);

        $result = FullCalendarEventMapper::map($this->event([
            'repeatFixedDates' => $fixedDates,
            'endTime' => strtotime('2025-03-05 10:00'),
        ]));

        $this->assertSame('fa-repeat', $result['event']['icon']);
        $this->assertFalse($result['multiday']);
    }

    public function testRecurringEventIsNeverMultiDay(): void
    {
        $result = FullCalendarEventMapper::map($this->event(['recurring' => 1, 'endTime' => strtotime('2025-03-05 10:00')]));

        $this->assertSame('fa-repeat', $result['event']['icon']);
        $this->assertFalse($result['multiday']);
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function event(array $override): array
    {
        return array_merge(
            [
                'id' => 1,
                'pid' => 2,
                'title' => 'Event',
                'teaser' => '',
                'href' => '/event',
                'addTime' => 0,
                'startDate' => strtotime('2025-03-03 00:00'),
                'endDate' => '',
                'startTime' => strtotime('2025-03-03 10:00'),
                'endTime' => strtotime('2025-03-03 12:00'),
                'begin' => strtotime('2025-03-03 10:00'),
                'end' => strtotime('2025-03-03 12:00'),
            ],
            $override,
        );
    }
}
