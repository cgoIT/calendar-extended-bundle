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
use Contao\Config;
use Contao\Date;
use Contao\StringUtil;
use Psr\Log\LoggerInterface;

/**
 * Applies the exceptions of the events (hide or move a single occurrence) and hides the events on weekends.
 */
class ExceptionStep
{
    public function __construct(private readonly LoggerInterface|null $logger = null)
    {
    }

    /**
     * @param array<mixed> $events
     *
     * @return array<mixed>
     */
    public function process(array $events, EventContext $context): array
    {
        $dateFormat = $context->page?->dateFormat ?: Config::get('dateFormat');
        $movedEvents = [];

        $events = EventTree::map(
            $events,
            function (array $event, int|string $day, int|string $startTime) use ($dateFormat, &$movedEvents): array|null {
                if (!empty($event['useExceptions']) && !empty($event['exceptionList'])) {
                    $skipInfos = StringUtil::deserialize($event['exceptionList'], true);

                    if (\array_key_exists($startTime, $skipInfos) && \is_array($skipInfos[$startTime])) {
                        if ('move' === $skipInfos[$startTime]['action']) {
                            try {
                                $moved = $this->moveEvent($event, $skipInfos[$startTime], (int) $startTime, (string) $dateFormat);
                            } catch (\InvalidArgumentException $exception) {
                                // A broken exception must not break the whole calendar, the occurrence stays where it is
                                $this->logger?->warning(\sprintf('The exception of the event ID %s cannot be applied: %s', $event['id'] ?? '?', $exception->getMessage()));

                                return $event;
                            }

                            if (empty($moved['hideOnWeekend']) || !$this->isWeekend((int) $moved['startTime'])) {
                                $movedEvents[] = $moved;
                            }
                        }

                        return null;
                    }
                }

                if (!empty($event['hideOnWeekend']) && $this->isWeekend((int) $startTime)) {
                    return null;
                }

                return $event;
            },
        );

        foreach ($movedEvents as $moved) {
            EventTree::add($events, $moved);
        }

        return $events;
    }

    /**
     * @param array<string, mixed> $event
     * @param array<string, mixed> $skipInfo
     *
     * @return array<string, mixed>
     */
    private function moveEvent(array $event, array $skipInfo, int $startTime, string $dateFormat): array
    {
        // The templates use "class" (with leading space), not the raw "cssClass" column
        $event['class'] = Utils::appendMovedClass($event['class'] ?? '', $skipInfo['cssclass'] ?? '');

        $event['oldStartTime'] = $event['startTime'];
        $event['oldEndTime'] = $event['endTime'];
        $event['moveReason'] = $skipInfo['reason'];

        // only change the start and end time if addTime is set to true for the event
        $useNewTimes = !empty($event['addTime']) && !empty($skipInfo['new_start']) && !empty($skipInfo['new_end']);
        [$newStartTime, $newEndTime] = Utils::calculateMovedTimes(
            $startTime,
            (int) $event['endTime'] - (int) $event['startTime'],
            (string) $skipInfo['new_exception'],
            $useNewTimes ? (string) $skipInfo['new_start'] : null,
            $useNewTimes ? (string) $skipInfo['new_end'] : null,
            Utils::daysBetween((int) $event['startTime'], (int) $event['endTime']),
        );

        $event['startTime'] = $newStartTime;
        $event['endTime'] = $newEndTime;

        $event['date'] = Date::parse($dateFormat, $newStartTime);
        $event['datetime'] = date('Y-m-d', $newStartTime);
        $event['day'] = $GLOBALS['TL_LANG']['DAYS'][date('w', $newStartTime)];
        $event['month'] = $GLOBALS['TL_LANG']['MONTHS'][date('n', $newStartTime) - 1];

        return $event;
    }

    private function isWeekend(int $time): bool
    {
        return \in_array((int) date('w', $time), [0, 6], true);
    }
}
