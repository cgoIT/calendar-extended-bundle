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

namespace Cgoit\CalendarExtendedBundle\Hook;

use Cgoit\CalendarExtendedBundle\Classes\Utils;
use Cgoit\CalendarExtendedBundle\Events\EventContext;
use Cgoit\CalendarExtendedBundle\Events\EventTree;
use Cgoit\CalendarExtendedBundle\Events\RecurringEventsLoader;
use Cgoit\CalendarExtendedBundle\Events\Step\CalendarConfigStep;
use Cgoit\CalendarExtendedBundle\Events\Step\ExceptionStep;
use Cgoit\CalendarExtendedBundle\Events\Step\FilterFieldsStep;
use Cgoit\CalendarExtendedBundle\Events\Step\HolidayStep;
use Cgoit\CalendarExtendedBundle\Events\Step\RepeatWeekdayStep;
use Cgoit\CalendarExtendedBundle\Events\Step\ShowOnlyNextStep;
use Cgoit\CalendarExtendedBundle\Events\Step\UrlParameterStep;
use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Module;
use Contao\ModuleModel;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Extends the events of all calendar modules: extended recurrences, fixed dates, exceptions, colors, holidays ...
 *
 * The hook only defines the order of the steps, which are implemented in Cgoit\CalendarExtendedBundle\Events.
 */
#[AsHook(hook: 'getAllEvents', priority: -100)]
class GetAllEventsHook
{
    public function __construct(
        private readonly RecurringEventsLoader $recurringEventsLoader,
        private readonly RepeatWeekdayStep $repeatWeekdayStep,
        private readonly CalendarConfigStep $calendarConfigStep,
        private readonly ExceptionStep $exceptionStep,
        private readonly HolidayStep $holidayStep,
        private readonly ShowOnlyNextStep $showOnlyNextStep,
        private readonly FilterFieldsStep $filterFieldsStep,
        private readonly UrlParameterStep $urlParameterStep,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<mixed> $arrEvents
     * @param array<mixed> $arrCalendars
     *
     * @return array<mixed>
     *
     * @throws \Exception
     */
    public function __invoke(array $arrEvents, array $arrCalendars, int $timeStart, int $timeEnd, Module|ModuleModel $objModule): array
    {
        $arrCalendars = array_map(\intval(...), $arrCalendars);

        $context = EventContext::fromModule(
            $objModule,
            $arrCalendars,
            $timeStart,
            $timeEnd,
            Utils::getCalendarConfig($arrCalendars),
            $this->getHolidayCalendarIds(),
            $this->getPage(),
        );

        // Filter events based on weekdays (only valid for standard recurrences)
        $arrEvents = $this->repeatWeekdayStep->process($arrEvents);

        // Add events from recurringExt and fixed dates
        $arrEvents = $this->recurringEventsLoader->addExtendedRecurrences($arrEvents, $context);
        $arrEvents = $this->recurringEventsLoader->addFixedDates($arrEvents, $context);

        // The order of the following steps is relevant
        $arrEvents = $this->calendarConfigStep->process($arrEvents, $context);
        $arrEvents = $this->exceptionStep->process($arrEvents, $context);
        $arrEvents = $this->holidayStep->process($arrEvents, $context);
        $arrEvents = $this->showOnlyNextStep->process($arrEvents, $context);
        $arrEvents = $this->filterFieldsStep->process($arrEvents, $context);
        $arrEvents = $this->urlParameterStep->process($arrEvents, $context);

        return EventTree::compact($arrEvents);
    }

    /**
     * The current page or, if there is none (e.g. in a custom route), the root page of the host.
     */
    private function getPage(): PageModel|null
    {
        $page = $GLOBALS['objPage'] ?? null;

        if ($page instanceof PageModel) {
            return $page;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return null;
        }

        return PageModel::findPublishedFallbackByHostname($request->getHost(), ['fallbackToEmpty' => true])?->loadDetails();
    }

    /**
     * @return array<int>
     */
    private function getHolidayCalendarIds(): array
    {
        $arrHolidayCalendars = [];
        $objHolidayCalendars = CalendarModel::findBy(['isHolidayCal=?'], ['1']);

        if (!empty($objHolidayCalendars)) {
            foreach ($objHolidayCalendars as $objHolidayCalendar) {
                $arrHolidayCalendars[] = (int) $objHolidayCalendar->id;
            }
        }

        return $arrHolidayCalendars;
    }
}
