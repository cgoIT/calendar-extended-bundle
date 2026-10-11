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

namespace Cgoit\CalendarExtendedBundle\Controller\FrontendModule;

use Cgoit\CalendarExtendedBundle\Classes\Utils;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Date;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Front end module "fullcalendar". The events are loaded by the FetchEventsController.
 */
#[AsFrontendModule('fullcalendar', category: 'events', template: 'frontend_module/fullcalendar')]
class FullCalendarController extends AbstractCalendarViewController
{
    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        if (null === $this->getCalendars($model)) {
            return new Response();
        }

        $page = $this->getPageModel();

        if (!$page instanceof PageModel) {
            return new Response();
        }

        $month = $request->query->get('month');
        $week = $request->query->get('week');
        $day = $request->query->get('day');
        $format = $model->cal_fcFormat;

        // Jump to the current period
        if (!$request->query->has('month') && !$request->query->has('week') && !$request->query->has('day')) {
            switch ($format) {
                case 'cal_fc_month':
                    $month = date('Ym');
                    break;

                case 'cal_fc_week':
                    $week = date('YW');
                    break;

                case 'cal_fc_day':
                case 'cal_fc_list':
                    $day = date('Ymd');
                    break;
            }
        }

        $blnDynamicFormat = !$model->cal_ignoreDynamic && \in_array($format, ['cal_fc_list', 'cal_fc_day', 'cal_fc_week', 'cal_fc_month'], true);
        $headline = $template->get('headline');
        $headlineText = \is_array($headline) ? (string) ($headline['text'] ?? '') : '';

        // Create the date object
        try {
            if ($blnDynamicFormat && $month) {
                $date = new Date((int) $month, 'Ym');
                $format = 'cal_fc_month';
                $headlineText .= ' '.Date::parse('F Y', $date->tstamp);
            } elseif ($blnDynamicFormat && $week) {
                $selYear = (int) substr((string) $week, 0, 4);
                $selWeek = (int) substr((string) $week, -2);
                $selDay = 1 === $selWeek ? 4 : 1;
                $dt = new \DateTime();
                $dt->setISODate($selYear, $selWeek, $selDay);
                $date = new Date((int) $dt->format('Ymd'), 'Ymd');
                $format = 'cal_fc_week';
                $headlineText .= ' '.Date::parse('W/Y', $date->tstamp);
            } elseif ($blnDynamicFormat && $day) {
                $date = new Date((int) $day, 'Ymd');
                $headlineText .= ' '.Date::parse($page->dateFormat, $date->tstamp);
                if (empty($format)) {
                    $format = 'cal_fc_day';
                }
            } else {
                $date = new Date();
            }
        } catch (\OutOfBoundsException) {
            throw new PageNotFoundException('Page not found: '.$request->getUri());
        }

        if (\is_array($headline)) {
            $headline['text'] = $headlineText;
            $template->set('headline', $headline);
        }

        $businessHours = null;

        if (!empty($model->businessHours)) {
            $businessHours = [
                'daysOfWeek' => array_map(\intval(...), StringUtil::deserialize($model->businessDays, true)),
                'startTime' => date('H:i', (int) $model->businessDayStart),
                'endTime' => date('H:i', (int) $model->businessDayEnd),
            ];
        }

        // calendar-extended-bundle assets
        $assets_path = 'bundles/cgoitcalendarextended';
        $assets_fa = '/font-awesome-4.7.0';

        $GLOBALS['TL_CSS'][] = $assets_path.$assets_fa.'/css/font-awesome.min.css';
        $GLOBALS['TL_CSS'][] = $assets_path.'/css/fullcalendar.css';
        $GLOBALS['TL_JAVASCRIPT'][] = $assets_path.'/js/fullcalendar.js';

        // The module labels are not part of the default language file
        System::loadLanguageFile('tl_module');

        // The options are passed as data attribute and read by fullcalendar.js
        return $this->renderCalendar(
            $template,
            $model,
            'cal_fc_default',
            [
                'moduleId' => (int) $model->id,
                'config' => [
                    'url' => '/fullcalendar/fetchEvents/'.$page->id.'/'.$model->id,
                    'locale' => Utils::getFullCalendarLocales((string) $GLOBALS['TL_LANGUAGE']),
                    'initialDate' => date('Y-m-d\TH:i:sP', $date->tstamp),
                    'initialView' => match ($format) {
                        'cal_fc_month' => 'dayGridMonth',
                        'cal_fc_day' => 'timeGridDay',
                        'cal_fc_list' => 'listMonth',
                        default => 'timeGridWeek',
                    },
                    'firstDay' => (int) $model->cal_startDay,
                    'weekNumbers' => (bool) $model->weekNumbers,
                    'businessHours' => $businessHours,
                    'requestToken' => $this->container->get('contao.csrf.token_manager')->getDefaultTokenValue(),
                    'fetchError' => $GLOBALS['TL_LANG']['tl_module']['fetch_error'],
                ],
            ],
        );
    }
}
