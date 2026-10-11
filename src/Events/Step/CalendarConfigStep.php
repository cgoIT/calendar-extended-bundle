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
 * Adds the title and the colors of the calendar to each event.
 */
class CalendarConfigStep
{
    /**
     * @param array<mixed> $events
     *
     * @return array<mixed>
     */
    public function process(array $events, EventContext $context): array
    {
        return EventTree::map(
            $events,
            static function (array $event) use ($context): array {
                $event['bgstyle'] = '';
                $event['fgstyle'] = '';
                $event['bgcolor'] = '';
                $event['fgcolor'] = '';

                if (isset($context->calendarConfig[$event['pid']])) {
                    $conf = $context->calendarConfig[$event['pid']];
                    $event['calendar_title'] = $conf['calendar'];

                    if (!empty($conf['background'])) {
                        $event['bgstyle'] = $conf['background'];
                    }

                    if (!empty($conf['foreground'])) {
                        $event['fgstyle'] = $conf['foreground'];
                    }

                    $event['bgcolor'] = $conf['backgroundColor'] ?? '';
                    $event['fgcolor'] = $conf['foregroundColor'] ?? '';
                }

                return $event;
            },
        );
    }
}
