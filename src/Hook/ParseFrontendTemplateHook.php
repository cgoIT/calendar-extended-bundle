<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\calendar-extended-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) Kester Mielke
 * @copyright  Copyright (c) 2024, cgoIT
 * @author     Kester Mielke
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\CalendarExtendedBundle\Hook;

use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Date;
use Contao\Events;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;

#[AsHook(hook: 'parseFrontendTemplate')]
class ParseFrontendTemplateHook extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function __invoke(string $buffer, string $templateName, FrontendTemplate $template): string
    {
        $isModuleEventReader = 'eventreader' === $template->type;

        if (
            $isModuleEventReader
            && (empty($template->fromCalendarExtendedHook) || false === $template->fromCalendarExtendedHook)
        ) {
            $template->fromCalendarExtendedHook = true;

            // Get the current event
            $objEvent = CalendarEventsModel::findPublishedByParentAndIdOrAlias(Input::get('events'), $template->cal_calendar);

            $objTemplate = new FrontendTemplate($template->cal_template ?: 'event_full');
            $objTemplate->setData($objEvent->row());
            $objTemplate->class = $objEvent->cssClass ? ' '.trim($objEvent->cssClass) : '';
            $objTemplate->locationLabel = $GLOBALS['TL_LANG']['MSC']['location'];
            $objTemplate->calendar = $objEvent->getRelated('pid');
            $objTemplate->count = 0; // see #74
            $objTemplate->details = '';
            $objTemplate->hasDetails = false;
            $objTemplate->hasTeaser = false;
            $objTemplate->hasReader = true;
            $objTemplate->fromCalendarExtendedHook = true;

            $template->event = $this->processEventTemplate($objEvent, $objTemplate, $template->event, true);

            $buffer = $template->parse();
        } elseif (
            str_starts_with($templateName, 'events_')
            && (empty($template->fromCalendarExtendedHook) || false === $template->fromCalendarExtendedHook)
        ) {
            $template->fromCalendarExtendedHook = true;

            $objEvent = CalendarEventsModel::findById($template->id);

            $buffer = $this->processEventTemplate($objEvent, $template, $buffer);
        }

        return $buffer;
    }

    private function processEventTemplate(CalendarEventsModel $objEvent, FrontendTemplate $template, string $buffer, bool $blnIsEventReader = false): string
    {
        /** @var PageModel */
        global $objPage;

        System::loadLanguageFile('tl_calendar_events');

        if (($blnIsEventReader && !empty($objEvent->recurring)) || !empty($objEvent->recurringExt) || $this->isRepeatOnFixedDates($objEvent)) {
            $intStartTime = $objEvent->startTime;
            $intEndTime = $objEvent->endTime;
            $span = Calendar::calculateSpan($intStartTime, $intEndTime);

            // Replace the date an time with the correct ones from the recurring event
            if ($blnIsEventReader && Input::get('times')) {
                [$intStartTime, $intEndTime] = array_map('\intval', explode(',', Input::get('times')));

                // Mark past and upcoming events (see #187)
                if ($intEndTime < strtotime('00:00:00')) {
                    $objEvent->cssClass .= ' bygone';
                } elseif ($intStartTime > strtotime('23:59:59')) {
                    $objEvent->cssClass .= ' upcoming';
                } else {
                    $objEvent->cssClass .= ' current';
                }

                [$strDate, $strTime] = $this->getDateAndTime($objEvent, $objPage, $intStartTime, $intEndTime, $span);

                $template->date = $strDate;
                $template->time = $strTime;
                $template->datetime = $objEvent->addTime ? date('Y-m-d\TH:i:sP', $intStartTime) : date('Y-m-d', $intStartTime);
                $template->begin = $intStartTime;
                $template->end = $intEndTime;
                $template->recurring = '';
                $template->until = '';
            }

            // Add a function to retrieve upcoming dates (see #175)
            $template->getUpcomingDates = function ($recurrences) use ($objEvent, $objPage, $intStartTime, $span) {
                if (empty($objEvent->allRecurrences)) {
                    return [];
                }
                $arrAllRecurrences = StringUtil::deserialize($objEvent->allRecurrences, true);
                $upcomingRecurrences = array_filter($arrAllRecurrences, static fn ($entry) => $entry['int_start'] > $intStartTime);

                $dates = [];
                $i = 0;

                foreach ($upcomingRecurrences as $recurrence) {
                    if (++$i > $recurrences) {
                        break;
                    }

                    [$strDate, $strTime] = $this->getDateAndTime($objEvent, $objPage, $recurrence['int_start'], $recurrence['int_end'], $span);
                    $dates[] =
                        [
                            'date' => $strDate,
                            'time' => $strTime,
                            'datetime' => $objEvent->addTime ? date('Y-m-d\TH:i:sP', $recurrence['int_start']) : date('Y-m-d', $recurrence['int_end']),
                            'begin' => $recurrence['int_start'],
                            'end' => $recurrence['int_end'],
                        ];
                }

                return $dates;
            };

            // Add a function to retrieve past dates (see #175)
            $template->getPastDates = function ($recurrences) use ($objEvent, $objPage, $intStartTime, $span) {
                if (empty($objEvent->allRecurrences)) {
                    return [];
                }
                $arrAllRecurrences = StringUtil::deserialize($objEvent->allRecurrences, true);
                $upcomingRecurrences = array_filter($arrAllRecurrences, static fn ($entry) => $entry['int_end'] < $intStartTime);

                $dates = [];
                $i = 0;

                foreach ($upcomingRecurrences as $recurrence) {
                    if (++$i > $recurrences) {
                        break;
                    }

                    [$strDate, $strTime] = $this->getDateAndTime($objEvent, $objPage, $recurrence['int_start'], $recurrence['int_end'], $span);
                    $dates[] =
                        [
                            'date' => $strDate,
                            'time' => $strTime,
                            'datetime' => $objEvent->addTime ? date('Y-m-d\TH:i:sP', $recurrence['int_start']) : date('Y-m-d', $recurrence['int_end']),
                            'begin' => $recurrence['int_start'],
                            'end' => $recurrence['int_end'],
                        ];
                }

                return $dates;
            };

            if ($blnIsEventReader) {
                // Clean the RTE output
                if ($objEvent->teaser) {
                    $template->hasTeaser = true;
                    $template->teaser = StringUtil::encodeEmail($objEvent->teaser);
                }

                // Display the "read more" button for external/article links
                if ('default' !== $objEvent->source) {
                    $template->hasDetails = true;
                    $template->hasReader = false;
                }

                // Compile the event text
                else {
                    $id = $objEvent->id;

                    $template->details = function () use ($id) {
                        $strDetails = '';
                        $objElement = ContentModel::findPublishedByPidAndTable($id, 'tl_calendar_events');

                        if (null !== $objElement) {
                            while ($objElement->next()) {
                                $strDetails .= $this->getContentElement($objElement->current());
                            }
                        }

                        return $strDetails;
                    };

                    $template->hasDetails = static fn () => ContentModel::countPublishedByPidAndTable($id, 'tl_calendar_events') > 0;
                }

                $template->addImage = false;
                $template->addBefore = false;

                // Add an image
                if ($objEvent->addImage) {
                    $imgSize = $objEvent->size ?: null;

                    $figure = System::getContainer()
                        ->get('contao.image.studio')
                        ->createFigureBuilder()
                        ->from($objEvent->singleSRC)
                        ->setSize($imgSize)
                        ->setOverwriteMetadata($objEvent->getOverwriteMetadata())
                        ->enableLightbox((bool) $objEvent->fullsize)
                        ->buildIfResourceExists()
                    ;

                    if (null !== $figure) {
                        $figure->applyLegacyTemplateData($template, $objEvent->imagemargin, $objEvent->floating);
                    }
                }

                $template->enclosure = [];

                // Add enclosures
                if ($objEvent->addEnclosure) {
                    $this->addEnclosuresToTemplate($template, $objEvent->row());
                }

                // schema.org information
                $template->getSchemaOrgData = static function () use ($template, $objEvent): array {
                    $jsonLd = Events::getSchemaOrgData($objEvent);

                    if ($template->addImage && $template->figure) {
                        $jsonLd['image'] = $template->figure->getSchemaOrgData();
                    }

                    return $jsonLd;
                };
            }

            return $template->parse();
        }

        return $buffer;
    }

    /**
     * Return the date and time strings.
     *
     * @param int $intStartTime
     * @param int $intEndTime
     * @param int $span
     *
     * @return array
     */
    private function getDateAndTime(CalendarEventsModel $objEvent, PageModel $objPage, $intStartTime, $intEndTime, $span)
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

    private function isRepeatOnFixedDates(CalendarEventsModel $objEvent): bool
    {
        if (!empty($objEvent->repeatFixedDates)) {
            $arrFixedDates = StringUtil::deserialize($objEvent->repeatFixedDates);

            return !empty($arrFixedDates) && \is_array($arrFixedDates) && !empty(array_filter($arrFixedDates, static fn ($date) => !empty($date['new_repeat'])));
        }

        return false;
    }
}
