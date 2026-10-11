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

use Cgoit\CalendarExtendedBundle\Classes\Utils;
use Cgoit\CalendarExtendedBundle\Events\EventContext;
use Cgoit\CalendarExtendedBundle\Events\EventTree;

/**
 * Appends the day and the times of a recurrence to the link, so the reader shows the right date.
 */
class UrlParameterStep
{
    /**
     * @param array<mixed> $events
     *
     * @return array<mixed>
     */
    public function process(array $events, EventContext $context): array
    {
        if ($context->ignoreDynamic) {
            return $events;
        }

        return EventTree::map(
            $events,
            static function (array $event): array {
                if ($event['startTime'] !== $event['begin'] && \is_string($event['href'] ?? null) && '' !== $event['href']) {
                    $event['href'] = Utils::appendDayAndTimesToUrl($event['href'], (int) $event['begin'], (int) $event['end']);
                }

                return $event;
            },
        );
    }
}
