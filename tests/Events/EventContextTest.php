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

namespace Cgoit\CalendarExtendedBundle\Tests\Events;

use Cgoit\CalendarExtendedBundle\Events\EventContext;
use Contao\Module;
use PHPUnit\Framework\TestCase;

final class EventContextTest extends TestCase
{
    public function testItReadsTheSettingsOfTheModule(): void
    {
        $module = $this->createModule([
            'cal_noSpan' => '1',
            'cal_hideRunning' => '',
            'showOnlyNext' => '1',
            'hide_holiday' => '1',
            'cal_ignoreDynamic' => '',
            'filter_fields' => serialize(['title', 'location_name']),
        ]);

        $context = EventContext::fromModule($module, [1, 2], 100, 200, ['config'], [5], null);

        $this->assertSame([1, 2], $context->calendars);
        $this->assertSame(100, $context->start);
        $this->assertSame(200, $context->end);
        $this->assertTrue($context->noSpan);
        $this->assertFalse($context->hideRunning);
        $this->assertTrue($context->showOnlyNext);
        $this->assertTrue($context->hideHoliday);
        $this->assertFalse($context->ignoreDynamic);
        $this->assertSame(['title', 'location_name'], $context->filterFields);
        $this->assertSame(['config'], $context->calendarConfig);
        $this->assertSame([5], $context->holidayCalendars);
        $this->assertNull($context->page);
    }

    public function testFilterFieldsAreDisabledWithoutConfiguration(): void
    {
        $context = EventContext::fromModule($this->createModule(['filter_fields' => '']), [], 0, 0, [], [], null);

        $this->assertNull($context->filterFields);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createModule(array $values): Module
    {
        $module = $this->createMock(Module::class);
        $module
            ->method('__isset')
            ->willReturnCallback(static fn (string $key): bool => isset($values[$key]))
        ;

        $module
            ->method('__get')
            ->willReturnCallback(static fn (string $key): mixed => $values[$key] ?? null)
        ;

        return $module;
    }
}
