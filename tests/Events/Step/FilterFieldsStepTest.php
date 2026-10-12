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
use Cgoit\CalendarExtendedBundle\Events\Step\FilterFieldsStep;
use PHPUnit\Framework\TestCase;

final class FilterFieldsStepTest extends TestCase
{
    public function testFilterDataContainsConfiguredFields(): void
    {
        $events = $this->process(
            new FilterFieldsStep(),
            [['title' => 'Concert', 'location_name' => 'Hall']],
            new EventContext(filterFields: ['title', 'location_name']),
        );

        $this->assertSame('{"title":"Concert","location_name":"Hall"}', $events[0]['filter_data']);
    }

    public function testFilterDataToleratesMissingEventFields(): void
    {
        $events = $this->process(new FilterFieldsStep(), [['title' => 'Concert']], new EventContext(filterFields: ['title', 'location_plz']));

        $this->assertSame('{"title":"Concert","location_plz":null}', $events[0]['filter_data']);
    }

    public function testFilterDataIsNotAddedWithoutFilterFields(): void
    {
        $events = $this->process(new FilterFieldsStep(), [['title' => 'Concert']], new EventContext());

        $this->assertArrayNotHasKey('filter_data', $events[0]);
    }

    /**
     * @param array<int, array<string, mixed>> $eventList
     *
     * @return array<int, array<string, mixed>>
     */
    private function process(FilterFieldsStep $step, array $eventList, EventContext $context): array
    {
        // structure: day => time => events
        return $step->process(['20250303' => [1741000000 => $eventList]], $context)['20250303'][1741000000];
    }
}
