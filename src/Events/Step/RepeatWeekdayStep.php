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

namespace Cgoit\CalendarExtendedBundle\Events\Step;

use Cgoit\CalendarExtendedBundle\Events\EventTree;
use Contao\StringUtil;

/**
 * Filters the events of standard recurrences based on the selected weekdays.
 */
class RepeatWeekdayStep
{
    /**
     * @param array<mixed> $events
     *
     * @return array<mixed>
     */
    public function process(array $events): array
    {
        return EventTree::map(
            $events,
            static function (array $event, int|string $day, int|string $startTime): array|null {
                if (!empty($event['recurring']) && !empty($event['repeatWeekday'])) {
                    // The stored weekdays might be strings or integers, depending on the origin of the event
                    $weekdays = array_map(\intval(...), StringUtil::deserialize($event['repeatWeekday'], true));

                    if (!\in_array((int) date('w', (int) $startTime), $weekdays, true)) {
                        return null;
                    }
                }

                return $event;
            },
        );
    }
}
