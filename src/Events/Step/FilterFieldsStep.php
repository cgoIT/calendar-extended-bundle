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
 * Adds the configured fields of the event as JSON ("filter_data"), so the templates can filter on them.
 */
class FilterFieldsStep
{
    /**
     * @param array<mixed> $events
     *
     * @return array<mixed>
     */
    public function process(array $events, EventContext $context): array
    {
        $fields = $context->filterFields;

        if (null === $fields) {
            return $events;
        }

        return EventTree::map(
            $events,
            static function (array $event) use ($fields): array {
                $filter = [];

                foreach ($fields as $field) {
                    $filter[$field] = $event[$field] ?? null;
                }

                $event['filter_data'] = json_encode($filter, JSON_FORCE_OBJECT);

                return $event;
            },
        );
    }
}
