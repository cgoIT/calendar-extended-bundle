<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\calendar-extended-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) Kester Mielke
 * @copyright  Copyright (c) 2024, cgoIT
 * @author     Kester Mielke
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\BfvWidgetBundle\Tests;

use Cgoit\CalendarExtendedBundle\CgoitCalendarExtendedBundle;
use PHPUnit\Framework\TestCase;

class CalendarExtendedBundleTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $bundle = new CgoitCalendarExtendedBundle();

        $this->assertInstanceOf(CgoitCalendarExtendedBundle::class, $bundle);
    }
}
