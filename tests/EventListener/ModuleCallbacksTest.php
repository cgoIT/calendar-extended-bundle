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

use Cgoit\CalendarExtendedBundle\EventListener\DataContainer\ModuleCallbacks;
use PHPUnit\Framework\TestCase;

final class ModuleCallbacksTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $langBackup;

    protected function setUp(): void
    {
        $this->langBackup = $GLOBALS['TL_LANG'] ?? [];

        $GLOBALS['TL_LANG']['tl_module']['time_range_from'] = ['From', ''];
        $GLOBALS['TL_LANG']['tl_module']['time_range_to'] = ['To', ''];
    }

    protected function tearDown(): void
    {
        $GLOBALS['TL_LANG'] = $this->langBackup;
    }

    public function testTimeRangeHasAStartAndAnEnd(): void
    {
        $range = (new ModuleCallbacks([]))->getTimeRange();

        $this->assertIsArray($range);

        $this->assertSame(['time_from', 'time_to'], array_keys($range));
        $this->assertSame('time', $range['time_from']['eval']['rgxp']);
        $this->assertSame('time', $range['time_to']['eval']['rgxp']);
    }
}
