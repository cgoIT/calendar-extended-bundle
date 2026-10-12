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

use Cgoit\CalendarExtendedBundle\Events\EventTree;
use PHPUnit\Framework\TestCase;

final class EventTreeTest extends TestCase
{
    public function testMapChangesAndRemovesEvents(): void
    {
        $tree = ['20250303' => [100 => [['id' => 1], ['id' => 2]], 200 => [['id' => 3]]]];

        $result = EventTree::map(
            $tree,
            static function (array $event, int|string $day, int|string $time): array|null {
                if (2 === $event['id']) {
                    return null;
                }

                return $event + ['day' => $day, 'time' => $time];
            },
        );

        $this->assertSame([['id' => 1, 'day' => 20250303, 'time' => 100]], array_values($result['20250303'][100]));
        $this->assertSame([['id' => 3, 'day' => 20250303, 'time' => 200]], $result['20250303'][200]);
    }

    public function testCompactRemovesEmptyEntriesAndSorts(): void
    {
        $tree = [
            '20250305' => [300 => [['id' => 1]], 100 => [['id' => 2]], 200 => []],
            '20250304' => [100 => []],
            '20250301' => [50 => [['id' => 3]]],
        ];

        $result = EventTree::compact($tree);

        $this->assertSame([20250301, 20250305], array_keys($result));
        $this->assertSame([100, 300], array_keys($result['20250305']));
    }

    public function testAddGroupsByDayAndStartTime(): void
    {
        $time = strtotime('2025-03-03 09:00');
        $tree = [];

        EventTree::add($tree, ['id' => 1, 'startTime' => $time]);
        EventTree::add($tree, ['id' => 2, 'startTime' => $time]);

        $this->assertCount(2, $tree[date('Ymd', $time)][$time]);
    }
}
