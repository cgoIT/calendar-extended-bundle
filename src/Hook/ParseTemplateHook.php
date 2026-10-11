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
use Cgoit\CalendarExtendedBundle\Events\ReaderOccurrences;
use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Routing\PageFinder;
use Contao\Date;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ExceptionInterface;

/**
 * Adjusts the template of the event reader for recurring events: the date of the requested (parameter "times") or
 * of the next occurrence, the functions to list upcoming and past dates and the data for schema.org.
 *
 * The hook runs just before the template of the core reader is parsed, so the reader does not need to be rendered twice.
 */
#[AsHook(hook: 'parseTemplate')]
class ParseTemplateHook
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly PageFinder $pageFinder,
        #[Autowire(service: 'contao.routing.content_url_generator')]
        private readonly ContentUrlGenerator $urlGenerator,
    ) {
    }

    public function __invoke(Template $template): void
    {
        // Only the template of the event reader provides these functions, the lists do not
        if (!isset($template->getUpcomingDates, $template->getPastDates, $template->getSchemaOrgData) || empty($template->id)) {
            return;
        }

        $objEvent = CalendarEventsModel::findById((int) $template->id);
        $objPage = $this->pageFinder->getCurrentPage();

        if (!$objEvent instanceof CalendarEventsModel || !$objPage instanceof PageModel || !$this->isRecurring($objEvent)) {
            return;
        }

        System::loadLanguageFile('tl_calendar_events');

        $times = $this->requestStack->getCurrentRequest()?->query->get('times');

        $this->adjustTemplate($template, $objEvent, $objPage, \is_string($times) ? $times : null);
    }

    /**
     * @param string|null $times the value of the parameter "times" (start and end of the requested occurrence)
     * @param int|null    $now   the current time, defaults to time()
     */
    public function adjustTemplate(Template $template, CalendarEventsModel $objEvent, PageModel $objPage, string|null $times, int|null $now = null): void
    {
        $now ??= time();
        $recurrences = StringUtil::deserialize($objEvent->allRecurrences, true);
        $intStartTime = (int) $objEvent->startTime;
        $intEndTime = (int) $objEvent->endTime;
        $intNextStartTime = $intStartTime;
        $intNextEndTime = $intEndTime;

        // Replace the date an time with the correct ones from the recurring event
        $requestedTimes = null;

        if (null !== $times && '' !== $times) {
            $requestedTimes = Utils::resolveRequestedTimes(
                $times,
                $intStartTime,
                $intEndTime,
                $recurrences,
                !empty($objEvent->useExceptions) ? StringUtil::deserialize($objEvent->exceptionList, true) : [],
                !empty($objEvent->addTime),
            );
        }

        if (null !== $requestedTimes) {
            [$intStartTime, $intEndTime] = $requestedTimes;
        }

        // Do not show dates in the past if the event is recurring (see #923)
        if (null !== ($nextRecurrence = ReaderOccurrences::next($recurrences, $now))) {
            $intNextStartTime = $nextRecurrence['int_start'];
            $intNextEndTime = $nextRecurrence['int_end'];

            if (null === $requestedTimes) {
                $intStartTime = $intNextStartTime;
                $intEndTime = $intNextEndTime;
            }
        }

        $span = Calendar::calculateSpan($intNextStartTime, $intNextEndTime);
        $isFixedDates = $this->isRepeatOnFixedDates($objEvent);

        [$strDate, $strTime] = $this->getDateAndTime($objEvent, $objPage, $intStartTime, $intEndTime, $span);
        [$strNextDate, $strNextTime] = $this->getDateAndTime($objEvent, $objPage, $intNextStartTime, $intNextEndTime, $span);
        [$until, $recurring] = Utils::getUntilAndRecurring($objEvent, $objPage, $intNextStartTime, $strNextDate, $strNextTime, $isFixedDates);

        // The core marks the event with the class of its own start and end
        $status = ReaderOccurrences::statusClass($intStartTime, $intEndTime, strtotime('00:00:00', $now), strtotime('23:59:59', $now));
        $template->class = ' '.trim(preg_replace('/\s+(bygone|upcoming|current)(?=\s|$)/', '', (string) $template->class).' '.$status);

        $template->date = $strDate;
        $template->time = $strTime;
        $template->datetime = $objEvent->addTime ? date('Y-m-d\TH:i:sP', $intStartTime) : date('Y-m-d', $intStartTime);
        $template->begin = $intStartTime;
        $template->end = $intEndTime;
        $template->recurring = $recurring;
        $template->until = $until;

        // Functions to retrieve upcoming and past dates (see #175)
        $template->getUpcomingDates = fn (int $limit): array => $this->toDates($objEvent, $objPage, ReaderOccurrences::upcoming($recurrences, $intStartTime, $limit), $span);
        $template->getPastDates = fn (int $limit): array => $this->toDates($objEvent, $objPage, ReaderOccurrences::past($recurrences, $intStartTime, $limit), $span);

        // schema.org information: the data of the core with the URL and the start of the shown occurrence
        // (reading the property would already call the function)
        $coreSchemaOrgData = $template->getData()['getSchemaOrgData'];

        $template->getSchemaOrgData = function () use ($coreSchemaOrgData, $objEvent, $intStartTime, $intEndTime): array {
            $jsonLd = $coreSchemaOrgData();

            try {
                $jsonLd['url'] = $this->urlGenerator->generate($objEvent, ['day' => date('Ymd', $intStartTime), 'times' => $intStartTime.','.$intEndTime]);
            } catch (ExceptionInterface) {
                // noop
            }

            $jsonLd['startDate'] = $objEvent->addTime ? date('Y-m-d\TH:i:sP', $intStartTime) : date('Y-m-d', $intStartTime);

            return $jsonLd;
        };
    }

    /**
     * @param array<int, array<string, mixed>> $recurrences
     *
     * @return array<int, array<string, mixed>>
     */
    private function toDates(CalendarEventsModel $objEvent, PageModel $objPage, array $recurrences, float|int $span): array
    {
        $dates = [];

        foreach ($recurrences as $recurrence) {
            [$strDate, $strTime] = $this->getDateAndTime($objEvent, $objPage, $recurrence['int_start'], $recurrence['int_end'], $span);

            $dates[] = [
                'date' => $strDate,
                'time' => $strTime,
                'datetime' => $objEvent->addTime ? date('Y-m-d\TH:i:sP', $recurrence['int_start']) : date('Y-m-d', $recurrence['int_start']),
                'begin' => $recurrence['int_start'],
                'end' => $recurrence['int_end'],
            ];
        }

        return $dates;
    }

    /**
     * Get the date and time based on the provided parameters.
     *
     * @return array{string, string} the date and the time
     */
    private function getDateAndTime(CalendarEventsModel $objEvent, PageModel $objPage, int $intStartTime, int $intEndTime, float|int $span): array
    {
        $strDate = Date::parse($objPage->dateFormat, $intStartTime);

        if ($span > 0) {
            $strDate = Date::parse($objPage->dateFormat, $intStartTime).$GLOBALS['TL_LANG']['MSC']['cal_timeSeparator'].Date::parse($objPage->dateFormat, $intEndTime);
        }

        $strTime = '';

        if ($objEvent->addTime) {
            if ($span > 0) {
                $strDate = Date::parse($objPage->datimFormat, $intStartTime).$GLOBALS['TL_LANG']['MSC']['cal_timeSeparator'].Date::parse($objPage->datimFormat, $intEndTime);
            } elseif ($intStartTime === $intEndTime) {
                $strTime = Date::parse($objPage->timeFormat, $intStartTime);
            } else {
                $strTime = Date::parse($objPage->timeFormat, $intStartTime).$GLOBALS['TL_LANG']['MSC']['cal_timeSeparator'].Date::parse($objPage->timeFormat, $intEndTime);
            }
        }

        return [$strDate, $strTime];
    }

    private function isRecurring(CalendarEventsModel $objEvent): bool
    {
        return !empty($objEvent->recurring) || !empty($objEvent->recurringExt) || $this->isRepeatOnFixedDates($objEvent);
    }

    private function isRepeatOnFixedDates(CalendarEventsModel $objEvent): bool
    {
        if (!empty($objEvent->repeatFixedDates)) {
            $arrFixedDates = StringUtil::deserialize($objEvent->repeatFixedDates);

            return !empty($arrFixedDates) && \is_array($arrFixedDates) && !empty(array_filter($arrFixedDates, static fn ($date) => !empty($date['new_repeat'])));
        }

        return false;
    }
}
