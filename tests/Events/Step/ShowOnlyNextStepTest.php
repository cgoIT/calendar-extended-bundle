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
use Cgoit\CalendarExtendedBundle\Events\Step\ShowOnlyNextStep;
use PHPUnit\Framework\TestCase;

final class ShowOnlyNextStepTest extends TestCase
{
    public function testOnlyTheNextOccurrenceIsKept(): void
    {
        $now = strtotime('2025-03-05 12:00');
        $events = (new ShowOnlyNextStep())->process(
            [
                '20250303' => [strtotime('2025-03-03 09:00') => [['pid' => 1, 'id' => 1, 'begin' => strtotime('2025-03-03 09:00'), 'end' => strtotime('2025-03-03 10:00')]]],
                '20250307' => [strtotime('2025-03-07 09:00') => [['pid' => 1, 'id' => 1, 'begin' => strtotime('2025-03-07 09:00'), 'end' => strtotime('2025-03-07 10:00')]]],
                '20250310' => [strtotime('2025-03-10 09:00') => [['pid' => 1, 'id' => 1, 'begin' => strtotime('2025-03-10 09:00'), 'end' => strtotime('2025-03-10 10:00')]]],
            ],
            new EventContext(showOnlyNext: true),
            $now,
        );

        // the past occurrence stays, of the upcoming ones only the first
        $this->assertCount(1, $events['20250303'][strtotime('2025-03-03 09:00')]);
        $this->assertCount(1, $events['20250307'][strtotime('2025-03-07 09:00')]);
        $this->assertCount(0, $events['20250310'][strtotime('2025-03-10 09:00')]);
    }
}
