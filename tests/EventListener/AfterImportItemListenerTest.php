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

use Cgoit\CalendarExtendedBundle\EventListener\AfterImportItemListener;
use Cgoit\CalendarExtendedBundle\Service\RecurrenceCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Tests that an imported event gets the same pre-calculated values as an event saved in the back end.
 */
final class AfterImportItemListenerTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $configBackup;

    protected function setUp(): void
    {
        $this->configBackup = $GLOBALS['TL_CONFIG'] ?? [];

        $GLOBALS['TL_CONFIG']['datimFormat'] = 'Y-m-d H:i';
        $GLOBALS['TL_CONFIG']['dateFormat'] = 'Y-m-d';
    }

    protected function tearDown(): void
    {
        $GLOBALS['TL_CONFIG'] = $this->configBackup;
    }

    public function testCalculatesAllValuesOfTheBackend(): void
    {
        $columns = $this->listener()->calculateColumns([
            'startDate' => strtotime('2025-03-03'),
            'endDate' => strtotime('2025-03-03'),
            'addTime' => 1,
            'startTime' => strtotime('2025-03-03 09:00'),
            'endTime' => strtotime('2025-03-03 10:00'),
            'recurring' => 1,
            'repeatEach' => serialize(['unit' => 'days', 'value' => 1]),
            'recurrences' => 3,
            'useExceptions' => 1,
            'repeatExceptions' => serialize([[
                'exception' => strtotime('2025-03-04 09:00'),
                'action' => 'hide',
                'new_exception' => '',
                'new_start' => '',
                'new_end' => '',
                'cssclass' => '',
                'reason' => '',
            ]]),
        ]);

        $this->assertNotNull($columns);
        $this->assertSame(1, $columns['weekday']);
        $this->assertSame(strtotime('2025-03-06 10:00'), $columns['repeatEnd']);
        $this->assertCount(4, unserialize($columns['allRecurrences']));
        // these were dropped by the import before
        $this->assertSame([strtotime('2025-03-04 09:00')], array_keys(unserialize($columns['exceptionList'])));
    }

    public function testReturnsNothingWithoutStartDate(): void
    {
        $this->assertNull($this->listener()->calculateColumns(['startDate' => '', 'recurring' => 1]));
    }

    public function testReturnsNothingIfTheDataIsInvalid(): void
    {
        $listener = new AfterImportItemListener(new class(365, 250) extends RecurrenceCalculator {
            public function calculate(array $record): never
            {
                throw new \InvalidArgumentException('invalid');
            }
        });

        $this->assertNull($listener->calculateColumns(['startDate' => strtotime('2025-03-03')]));
    }

    private function listener(): AfterImportItemListener
    {
        return new AfterImportItemListener(new RecurrenceCalculator(365, 250));
    }
}
