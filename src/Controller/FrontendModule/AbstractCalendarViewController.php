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

use Cgoit\CalendarExtendedBundle\Service\EventsProvider;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class of the calendar views of this bundle. Each view renders a "cal_*" template (selectable in the module)
 * inside the module template.
 */
abstract class AbstractCalendarViewController extends AbstractFrontendModuleController
{
    public function __construct(protected readonly EventsProvider $eventsProvider)
    {
    }

    /**
     * @return array<int>|null the accessible calendars or null if there is nothing to show
     */
    protected function getCalendars(ModuleModel $model): array|null
    {
        $calendars = $this->eventsProvider->getAccessibleCalendars($model);

        if ([] === $calendars) {
            return null;
        }

        // Tag the calendars (see #2137)
        $this->tagResponse(array_map(static fn (int $id): string => 'contao.db.tl_calendar.'.$id, $calendars));

        return $calendars;
    }

    /**
     * The URL of the current page without query parameters.
     */
    protected function getBaseUrl(Request $request): string
    {
        return strtok($request->getRequestUri(), '?') ?: '';
    }

    /**
     * @param array<string, mixed> $variables
     */
    protected function renderCalendar(FragmentTemplate $template, ModuleModel $model, string $defaultTemplate, array $variables): Response
    {
        $name = '@Contao/'.($model->cal_ctemplate ?: $defaultTemplate).'.html.twig';

        // Templates of the old template system (e.g. "*.html5") cannot be included
        if (!$this->container->get('twig')->getLoader()->exists($name)) {
            $name = '@Contao/'.$defaultTemplate.'.html.twig';
        }

        $template->set('searchable', false);
        $template->set('calendar_template', $name);
        $template->set('calendar', $variables);

        return $template->getResponse();
    }
}
