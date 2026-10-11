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

namespace Cgoit\CalendarExtendedBundle\Events;

use Cgoit\CalendarExtendedBundle\Classes\Utils;
use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\ContentModel;
use Contao\Controller;
use Contao\Date;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds the event arrays for occurrences that are not created by the core (extended recurrences, fixed dates).
 */
class EventFactory
{
    /**
     * @param array<mixed> $arrEvents
     *
     * @throws \Exception
     */
    public function create(CalendarEventsModel $objEvent, EventContext $context, int $intStart, int $intEnd, array &$arrEvents, bool $isFixedDate, int|null $intRecurrences = null, string|null $reason = null): void
    {
        $objPage = $context->page;

        // Without a page the date and time formats are not available
        if (!$objPage instanceof PageModel) {
            return;
        }

        System::loadLanguageFile('tl_calendar_events');

        $intDate = $intStart;
        $strDate = Date::parse($objPage->dateFormat, $intStart);
        $strDay = $GLOBALS['TL_LANG']['DAYS'][date('w', $intStart)];
        $strMonth = $GLOBALS['TL_LANG']['MONTHS'][date('n', $intStart) - 1];
        $span = Calendar::calculateSpan($intStart, $intEnd);

        if ($span > 0) {
            $strDate = Date::parse($objPage->dateFormat, $intStart).$GLOBALS['TL_LANG']['MSC']['cal_timeSeparator'].Date::parse($objPage->dateFormat, $intEnd);
            $strDay = '';
        }

        $strTime = '';

        if ($objEvent->addTime) {
            if ($span > 0) {
                $strDate = Date::parse($objPage->datimFormat, $intStart).$GLOBALS['TL_LANG']['MSC']['cal_timeSeparator'].Date::parse($objPage->datimFormat, $intEnd);
            } elseif ($intStart === $intEnd) {
                $strTime = Date::parse($objPage->timeFormat, $intStart);
            } else {
                $strTime = Date::parse($objPage->timeFormat, $intStart).$GLOBALS['TL_LANG']['MSC']['cal_timeSeparator'].Date::parse($objPage->timeFormat, $intEnd);
            }
        }

        [$until, $recurring] = Utils::getUntilAndRecurring($objEvent, $objPage, $intStart, $strDate, $strTime, $isFixedDate);

        $arrEvent = $objEvent->row();

        $arrEvent['startDate'] = $objEvent->startDate;
        $arrEvent['startTime'] = $objEvent->startTime;
        $arrEvent['endDate'] = $objEvent->endDate;
        $arrEvent['endTime'] = $objEvent->endTime;
        $arrEvent['date'] = $strDate;
        $arrEvent['time'] = $strTime;
        $arrEvent['datetime'] = $objEvent->addTime ? date('Y-m-d\TH:i:sP', $intStart) : date('Y-m-d', $intStart);
        $arrEvent['day'] = $strDay;
        $arrEvent['month'] = $strMonth;
        $arrEvent['parent'] = $objEvent->pid;
        $arrEvent['calendar'] = $objEvent->getRelated('pid');
        $arrEvent['link'] = $objEvent->title;
        $arrEvent['target'] = '';
        $arrEvent['title'] = StringUtil::specialchars($objEvent->title, true);
        $arrEvent['href'] = System::getContainer()
            ->get('contao.routing.content_url_generator')
            ->generate($objEvent, [], UrlGeneratorInterface::ABSOLUTE_PATH)
        ;
        $arrEvent['class'] = $objEvent->cssClass ? ' '.$objEvent->cssClass : '';
        $arrEvent['recurring'] = $recurring;
        $arrEvent['until'] = $until;
        $arrEvent['begin'] = $intStart;
        $arrEvent['end'] = $intEnd;
        $arrEvent['effectiveEndTime'] = $arrEvent['end'];
        $arrEvent['reason'] = $reason;
        $arrEvent['details'] = '';
        $arrEvent['hasTeaser'] = false;

        if (null !== $intRecurrences) {
            $arrEvent['recurrences'] = $intRecurrences;
        }

        // Set open-end events to 23:59:59, so they run until the end of the day (see #4476)
        if ($intStart === $intEnd && $objEvent->addTime) {
            $arrEvent['effectiveEndTime'] = strtotime(date('Y-m-d', $arrEvent['endTime']).' 23:59:59');
        }

        // Override the link target
        if ('external' === $objEvent->source && $objEvent->target) {
            $arrEvent['target'] = ' target="_blank" rel="noreferrer noopener"';
        }

        // Clean the RTE output
        if ($arrEvent['teaser']) {
            $arrEvent['hasTeaser'] = true;
            $arrEvent['teaser'] = StringUtil::encodeEmail($arrEvent['teaser']);
        }

        // Display the "read more" button for external/article links
        if ('default' !== $objEvent->source) {
            $arrEvent['hasDetails'] = true;
        }

        // Compile the event text
        else {
            $id = $objEvent->id;

            $arrEvent['details'] = static function () use ($id) {
                $strDetails = '';
                $objElement = ContentModel::findPublishedByPidAndTable($id, 'tl_calendar_events');

                if (null !== $objElement) {
                    while ($objElement->next()) {
                        $strDetails .= Controller::getContentElement($objElement->current());
                    }
                }

                return $strDetails;
            };

            $arrEvent['hasDetails'] = static fn () => ContentModel::countPublishedByPidAndTable($id, 'tl_calendar_events') > 0;
        }

        // Get today's start and end timestamp
        $todayBegin = strtotime('00:00:00');
        $todayEnd = strtotime('23:59:59');

        // Mark past and upcoming events (see #3692)
        if ($intEnd < $todayBegin) {
            $arrEvent['class'] .= ' bygone';
        } elseif ($intStart > $todayEnd) {
            $arrEvent['class'] .= ' upcoming';
        } else {
            $arrEvent['class'] .= ' current';
        }

        if (1 === (int) $arrEvent['featured']) {
            $arrEvent['class'] .= ' featured';
        }

        EventTree::add($arrEvents, $arrEvent, $intDate);

        // Multi-day event
        if (!$context->noSpan) {
            for ($i = 1; $i <= $span; ++$i) {
                $intDate = strtotime('+1 day', $intDate);

                if ($intDate > $intEnd) {
                    break;
                }

                EventTree::add($arrEvents, $arrEvent, $intDate);
            }
        }
    }
}
