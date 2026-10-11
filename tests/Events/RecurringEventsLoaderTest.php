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
use Cgoit\CalendarExtendedBundle\Events\EventFactory;
use Cgoit\CalendarExtendedBundle\Events\RecurringEventsLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RecurringEventsLoaderTest extends TestCase
{
    #[DataProvider('loaders')]
    public function testTheQueryIsSkippedWithoutCalendars(string $method): void
    {
        $events = ['20250303' => [1741000000 => [['title' => 'Concert']]]];

        $result = (new RecurringEventsLoader(new EventFactory()))->{$method}($events, new EventContext(start: 0, end: 1));

        $this->assertSame($events, $result);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function loaders(): iterable
    {
        yield 'extended recurrences' => ['addExtendedRecurrences'];
        yield 'fixed dates' => ['addFixedDates'];
    }
}
