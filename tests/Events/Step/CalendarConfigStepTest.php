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
use Cgoit\CalendarExtendedBundle\Events\Step\CalendarConfigStep;
use PHPUnit\Framework\TestCase;

final class CalendarConfigStepTest extends TestCase
{
    public function testCalendarConfigAddsStylesAndColors(): void
    {
        $context = new EventContext(calendarConfig: [
            5 => [
                'calendar' => 'Holidays',
                'background' => 'background-color:#ff0000;',
                'foreground' => 'color:#ffffff;',
                'backgroundColor' => '#ff0000',
                'foregroundColor' => '#ffffff',
            ],
        ]);

        $result = $this->process(new CalendarConfigStep(), [['pid' => 5], ['pid' => 7]], $context);

        $this->assertSame('Holidays', $result[0]['calendar_title']);
        $this->assertSame('background-color:#ff0000;', $result[0]['bgstyle']);
        $this->assertSame('#ff0000', $result[0]['bgcolor']);
        $this->assertSame('#ffffff', $result[0]['fgcolor']);
        $this->assertSame('', $result[1]['bgcolor']);
        $this->assertSame('', $result[1]['bgstyle']);
    }

    /**
     * @param array<int, array<string, mixed>> $eventList
     *
     * @return array<int, array<string, mixed>>
     */
    private function process(CalendarConfigStep $step, array $eventList, EventContext $context): array
    {
        // structure: day => time => events
        return $step->process(['20250303' => [1741000000 => $eventList]], $context)['20250303'][1741000000];
    }
}
