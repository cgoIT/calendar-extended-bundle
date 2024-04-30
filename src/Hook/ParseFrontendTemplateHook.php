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

use Cgoit\CalendarExtendedBundle\Classes\Utils;
use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Date;
use Contao\FrontendTemplate;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;

#[AsHook(hook: 'parseFrontendTemplate')]
class ParseFrontendTemplateHook
{
    /**
     * @var array<mixed>
     */
    private readonly array $arrMonths;

    public function __construct()
    {
        System::loadLanguageFile('default', 'en', true);
        $this->arrMonths = $GLOBALS['TL_LANG']['MONTHS'];
        System::loadLanguageFile('default');
    }

    public function __invoke(string $buffer, string $templateName, FrontendTemplate $template): string
    {
        if (
            str_starts_with($templateName, 'event_')
            && (empty($template->fromCalendarExtendedHook) || false === $template->fromCalendarExtendedHook)
        ) {
            $template->fromCalendarExtendedHook = true;

            $objEvent = CalendarEventsModel::findById($template->id);

            /** @var PageModel */
            global $objPage;

            System::loadLanguageFile('tl_calendar_events');

            if (!empty($objEvent->recurringExt) || $this->isRepeatOnFixedDates($objEvent)) {
                $intStartTime = $objEvent->startTime;
                $intEndTime = $objEvent->endTime;
                $month = (int) date('n', $intStartTime);
                $year = (int) date('Y', $intStartTime);
                $span = Calendar::calculateSpan($intStartTime, $intEndTime);

                $isFixedDate = false;

                if (!empty($objEvent->recurringExt)) {
                    $arrRange = StringUtil::deserialize($objEvent->repeatEachExt, true);

                    if (empty($arrRange) || empty($arrRange['value']) || empty($arrRange['unit'])) {
                        return $buffer;
                    }

                    $arg = $arrRange['value'];
                    $unit = $arrRange['unit'];

                    while (($template->cal_hideRunning ? $intStartTime : $intEndTime) < time() && $intEndTime < $objEvent->repeatEnd) {
                        ++$month;

                        if (0 === $month % 13) {
                            $month = 1;
                            ++$year;
                        }
                        $timetoadd = $arg.' '.$unit.' of '.$this->arrMonths[$month - 1].' '.$year;

                        $intStartTime = strtotime($timetoadd, $intStartTime);
                        $intEndTime = $intStartTime + $objEvent->endTime - $objEvent->startTime;
                    }
                } elseif ($this->isRepeatOnFixedDates($objEvent)) {
                    $isFixedDate = true;

                    $arrFixedDates = StringUtil::deserialize($objEvent->repeatFixedDates);
                    if (!empty($arrFixedDates) && \is_array($arrFixedDates)) {
                        foreach ($arrFixedDates as $fixedDate) {
                            if (($template->cal_hideRunning ? $intStartTime : $intEndTime) < time() && $intEndTime < $objEvent->repeatEnd) {
                                break;
                            }

                            $intStartTime = (int) $fixedDate['new_repeat'];
                            $intEndTime = $intStartTime + $objEvent->startTime - $objEvent->endTime;
                        }
                    }
                }

                // Mark past and upcoming events (see #187)
                if ($intEndTime < strtotime('00:00:00')) {
                    $objEvent->cssClass .= ' bygone';
                } elseif ($intStartTime > strtotime('23:59:59')) {
                    $objEvent->cssClass .= ' upcoming';
                } else {
                    $objEvent->cssClass .= ' current';
                }

                [$strDate, $strTime] = $this->getDateAndTime($objEvent, $objPage, $intStartTime, $intEndTime, $span);
                [$until, $recurring] = Utils::getUntilAndRecurring($objEvent, $objPage, $intStartTime, $strDate, $strTime, $isFixedDate);

                $template->date = $strDate;
                $template->time = $strTime;
                $template->datetime = $objEvent->addTime ? date('Y-m-d\TH:i:sP', $intStartTime) : date('Y-m-d', $intStartTime);
                $template->begin = $intStartTime;
                $template->end = $intEndTime;
                $template->recurring = $recurring;
                $template->until = $until;

                $buffer = $template->parse();
            }
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

            return !empty($arrFixedDates) && !empty(array_filter($arrFixedDates, static fn ($date) => !empty($date['new_repeat'])));
        }

        return false;
    }
}
