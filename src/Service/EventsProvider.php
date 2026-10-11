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

namespace Cgoit\CalendarExtendedBundle\Service;

use Cgoit\CalendarExtendedBundle\Hook\GetAllEventsHook;
use Contao\CalendarBundle\Generator\CalendarEventsGenerator;
use Contao\CalendarModel;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\Date;
use Contao\ModuleModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Loads the events for the frontend modules of this bundle.
 *
 * The core modules get their events from the "getAllEvents" hook of the core generator. The modules of this
 * bundle are not derived from the legacy Events class, so the hook of this bundle is applied directly.
 */
class EventsProvider
{
    public function __construct(
        #[Autowire(service: 'contao_calendar.generator.calendar_events')]
        private readonly CalendarEventsGenerator $generator,
        private readonly GetAllEventsHook $getAllEventsHook,
        private readonly Connection $connection,
        private readonly Security $security,
        private readonly TokenChecker $tokenChecker,
    ) {
    }

    /**
     * Return the IDs of the calendars of a module the current user is allowed to see.
     *
     * @return array<int>
     */
    public function getAccessibleCalendars(ModuleModel $module): array
    {
        $ids = array_map(\intval(...), StringUtil::deserialize($module->cal_calendar, true));

        if ([] === $ids) {
            return [];
        }

        $calendars = CalendarModel::findMultipleByIds($ids);
        $accessible = [];

        if (null !== $calendars) {
            foreach ($calendars as $calendar) {
                if ($calendar->protected && !$this->security->isGranted(ContaoCorePermissions::MEMBER_IN_GROUPS, StringUtil::deserialize($calendar->groups, true))) {
                    continue;
                }

                $accessible[] = (int) $calendar->id;
            }
        }

        return $accessible;
    }

    /**
     * Return all events of the given calendars and period, grouped by day and start time.
     *
     * @param array<int> $calendars
     *
     * @return array<mixed>
     */
    public function getAllEvents(ModuleModel $module, array $calendars, int $start, int $end, bool|null $featured = null): array
    {
        $events = $this->generator->getAllEvents(
            $calendars,
            (new \DateTime())->setTimestamp($start),
            (new \DateTime())->setTimestamp($end),
            $featured,
            (bool) $module->cal_noSpan,
        );

        return ($this->getAllEventsHook)($events, $calendars, $start, $end, $module);
    }

    /**
     * Return the first start, the last end and the last repetition of all published events of the calendars.
     *
     * @param array<int> $calendars
     *
     * @return array{int, int, int}
     */
    public function findBoundaries(array $calendars): array
    {
        $sql = 'SELECT MIN(startTime) AS dateFrom, MAX(endTime) AS dateTo, MAX(repeatEnd) AS repeatUntil FROM tl_calendar_events WHERE pid IN ('.implode(',', array_map(\intval(...), $calendars)).')';

        if (!$this->tokenChecker->isPreviewMode()) {
            $time = Date::floorToMinute();
            $sql .= " AND published='1' AND (start='' OR start<=$time) AND (stop='' OR stop>$time)";
        }

        $row = $this->connection->fetchAssociative($sql) ?: [];

        return [(int) ($row['dateFrom'] ?? 0), (int) ($row['dateTo'] ?? 0), (int) ($row['repeatUntil'] ?? 0)];
    }
}
