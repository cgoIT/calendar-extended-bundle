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

use Contao\CalendarModel;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\Date;
use Contao\ModuleModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Loads the events for the frontend modules of this bundle.
 *
 * The modules of this bundle are not derived from the legacy Events class, so a stand-in module is used. The
 * "getAllEvents" hooks, including the one of this bundle, are applied by the core.
 */
class EventsProvider
{
    public function __construct(
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
        return (new EventsModule($module))->findEvents($calendars, $start, $end, $featured);
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
        if ([] === $calendars) {
            return [0, 0, 0];
        }

        $params = array_map(\intval(...), array_values($calendars));
        $sql = 'SELECT MIN(startTime) AS dateFrom, MAX(endTime) AS dateTo, MAX(repeatEnd) AS repeatUntil FROM tl_calendar_events WHERE pid IN ('.implode(', ', array_fill(0, \count($params), '?')).')';

        if (!$this->tokenChecker->isPreviewMode()) {
            $time = Date::floorToMinute();
            $sql .= " AND published='1' AND (start='' OR start<=?) AND (stop='' OR stop>?)";
            $params[] = $time;
            $params[] = $time;
        }

        $row = $this->connection->fetchAssociative($sql, $params) ?: [];

        return [(int) ($row['dateFrom'] ?? 0), (int) ($row['dateTo'] ?? 0), (int) ($row['repeatUntil'] ?? 0)];
    }
}
