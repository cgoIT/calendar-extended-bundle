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
use Cgoit\CalendarExtendedBundle\Events\Step\ExceptionStep;
use PHPUnit\Framework\TestCase;

final class ExceptionStepTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['TL_CONFIG']['dateFormat'] = 'Y-m-d';
        $GLOBALS['TL_LANG']['DAYS'] = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $GLOBALS['TL_LANG']['MONTHS'] = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    }

    public function testHideExceptionRemovesTheOccurrence(): void
    {
        $monday = strtotime('2025-03-03 09:00');
        $tuesday = strtotime('2025-03-04 09:00');
        $list = serialize([$monday => ['action' => 'hide']]);

        $result = (new ExceptionStep())->process(
            [
                '20250303' => [$monday => [$this->event($list)]],
                '20250304' => [$tuesday => [$this->event($list)]],
            ],
            new EventContext(),
        );

        $this->assertCount(0, $result['20250303'][$monday]);
        $this->assertCount(1, $result['20250304'][$tuesday]);
    }

    public function testEventsAreHiddenOnWeekends(): void
    {
        $friday = strtotime('2025-03-07 09:00');
        $saturday = strtotime('2025-03-08 09:00');

        $result = (new ExceptionStep())->process(
            [
                '20250307' => [$friday => [$this->event('', ['hideOnWeekend' => 1])]],
                '20250308' => [$saturday => [$this->event('', ['hideOnWeekend' => 1])]],
            ],
            new EventContext(),
        );

        $this->assertCount(1, $result['20250307'][$friday]);
        $this->assertCount(0, $result['20250308'][$saturday]);
    }

    public function testMoveExceptionMovesTheOccurrenceToTheNewDay(): void
    {
        $monday = strtotime('2025-03-03 09:00');
        $list = serialize([$monday => ['action' => 'move', 'new_exception' => '+2 days', 'reason' => 'Holiday', 'cssclass' => 'moved-event', 'new_start' => '', 'new_end' => '']]);

        $result = (new ExceptionStep())->process(['20250303' => [$monday => [$this->event($list)]]], new EventContext());

        $wednesday = strtotime('2025-03-05 09:00');

        $this->assertCount(0, $result['20250303'][$monday]);
        $this->assertSame('Holiday', $result['20250305'][$wednesday][0]['moveReason']);
        $this->assertSame($monday, $result['20250305'][$wednesday][0]['oldStartTime']);
        $this->assertStringContainsString('moved', (string) $result['20250305'][$wednesday][0]['class']);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function event(string $exceptionList, array $overrides = []): array
    {
        return array_merge(
            [
                'id' => 1,
                'pid' => 1,
                'class' => '',
                'addTime' => 1,
                'startTime' => strtotime('2025-03-03 09:00'),
                'endTime' => strtotime('2025-03-03 10:00'),
                'useExceptions' => '' === $exceptionList ? 0 : 1,
                'exceptionList' => $exceptionList,
            ],
            $overrides,
        );
    }
}
