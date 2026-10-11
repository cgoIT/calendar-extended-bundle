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

use Cgoit\CalendarExtendedBundle\Events\EventContext;
use Cgoit\CalendarExtendedBundle\Events\EventTree;

/**
 * Keeps only the next occurrence of each event.
 */
class ShowOnlyNextStep
{
    /**
     * @param array<mixed> $events
     *
     * @return array<mixed>
     */
    public function process(array $events, EventContext $context, int|null $now = null): array
    {
        if (!$context->showOnlyNext) {
            return $events;
        }

        $now ??= time();
        $seen = [];

        return EventTree::map(
            $events,
            static function (array $event) use ($context, $now, &$seen): array|null {
                $timeToCompare = $context->hideRunning ? $event['begin'] : $event['end'];

                if ($timeToCompare > $now) {
                    $key = $event['pid'].'_'.$event['id'];
                    $seen[$key] = ($seen[$key] ?? 0) + 1;

                    if ($seen[$key] > 1) {
                        return null;
                    }
                }

                return $event;
            },
        );
    }
}
