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

namespace Cgoit\CalendarExtendedBundle\Controller\Route;

use Cgoit\CalendarExtendedBundle\Classes\FullCalendarEventMapper;
use Cgoit\CalendarExtendedBundle\Classes\Utils;
use Cgoit\CalendarExtendedBundle\Service\EventsProvider;
use Contao\Controller;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\Date;
use Contao\Input;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/fullcalendar/fetchEvents/{pageId}/{moduleId}', name: FetchEventsController::class, defaults: ['_scope' => 'frontend', '_token_check' => true], methods: 'POST')]
#[AsController]
class FetchEventsController
{
    /**
     * Maximum requested time range in seconds (about 400 days).
     */
    private const MAX_RANGE = 400 * 86400;

    public function __construct(private readonly EventsProvider $eventsProvider)
    {
    }

    public function __invoke(Request $request, int $pageId, int $moduleId): Response
    {
        System::getContainer()->get('contao.framework')->initialize();

        $page = PageModel::findPublishedById($pageId);
        $objModule = ModuleModel::findById($moduleId);

        if (!$page instanceof PageModel || !$objModule instanceof ModuleModel || 'fullcalendar' !== $objModule->type) {
            throw new NotFoundHttpException();
        }

        // Load the details, otherwise the protection inherited from the parent pages is missing
        $page->loadDetails();

        if (!Controller::isVisibleElement($objModule) || ($page->protected && !$this->isPageAccessible($page)) || $this->isHiddenForMembers($objModule)) {
            throw new NotFoundHttpException();
        }

        $range = Utils::parseTimeRange((string) Input::post('start'), (string) Input::post('end'), self::MAX_RANGE);

        if (null === $range) {
            throw new BadRequestHttpException('Invalid time range.');
        }

        [$intStart, $intEnd] = $range;

        // The hooks and the date formatting of the core expect the current page in the global variable
        $previousPage = $GLOBALS['objPage'] ?? null;
        $GLOBALS['objPage'] = $page;
        $request->attributes->set('pageModel', $page);

        try {
            $events = $this->fetchEvents($objModule, $intStart, $intEnd);
        } finally {
            $request->attributes->set('pageModel', null);
            $GLOBALS['objPage'] = $previousPage;
        }

        return new JsonResponse($events);
    }

    /**
     * The module can be restricted to guests (not logged in members).
     */
    private function isHiddenForMembers(ModuleModel $objModule): bool
    {
        return (bool) $objModule->guests && System::getContainer()->get('security.helper')->isGranted('ROLE_MEMBER');
    }

    private function isPageAccessible(PageModel $page): bool
    {
        $security = System::getContainer()->get('security.helper');

        return $security->isGranted(ContaoCorePermissions::MEMBER_IN_GROUPS, StringUtil::deserialize($page->groups, true));
    }

    /**
     * Fetch all events for the given time range.
     *
     * @return array<mixed>
     */
    private function fetchEvents(ModuleModel $objModule, int $intStart, int $intEnd): array
    {
        // Get all events of the calendars the user may access
        $arrCalendars = $this->eventsProvider->getAccessibleCalendars($objModule);

        if ([] === $arrCalendars) {
            return [];
        }

        $arrAllEvents = $this->eventsProvider->getAllEvents($objModule, $arrCalendars, $intStart, $intEnd);

        // Sort the days
        $sort = 'descending' === $objModule->cal_order ? 'krsort' : 'ksort';
        $sort($arrAllEvents);

        // Sort the events
        foreach (array_keys($arrAllEvents) as $key) {
            $sort($arrAllEvents[$key]);
        }

        // Step 1: get the current time
        $currTime = Date::floorToMinute();

        // Array of events for JSON output
        $json_events = [];
        $multiday_event = [];

        // Create the JSON of all events
        foreach ($arrAllEvents as $days) {
            foreach ($days as $events) {
                foreach ($events as $event) {
                    // Use repeatEnd if > 0 (see #8447)
                    if (($event['repeatEnd'] ?: $event['end']) < $intStart || $event['begin'] > $intEnd) {
                        continue;
                    }

                    // Hide Events that are already started
                    if ($objModule->hide_started && $event['begin'] < $currTime) {
                        continue;
                    }

                    $mapped = FullCalendarEventMapper::map($event);

                    // Ignore if this is not the first multi day entry
                    if (false === array_search($event['id'], $multiday_event, true)) {
                        $json_events[] = $mapped['event'];
                    }

                    // Remember if multi day event
                    if ($mapped['multiday'] && false === array_search($event['id'], $multiday_event, true)) {
                        $multiday_event[] = $event['id'];
                    }
                }
            }
        }

        $json_events = $this->makeUnique($json_events);

        // Free resources
        unset($event, $events, $arrAllEvents, $multiday_event);

        return $json_events;
    }

    /**
     * @param array<mixed> $arrEvents
     *
     * @return array<mixed>
     */
    private function makeUnique(array $arrEvents): array
    {
        $uniqueEvents = [];

        foreach ($arrEvents as $event) {
            $included = array_filter(
                $uniqueEvents,
                static fn ($e) => $event['id'] === $e['id']
                    && $event['start'] === $e['start']
                    && $event['end'] === $e['end'],
            );
            if (empty($included)) {
                $uniqueEvents[] = $event;
            }
        }

        return $uniqueEvents;
    }
}
