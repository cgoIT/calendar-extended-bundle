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

namespace Cgoit\CalendarExtendedBundle\Tests\Controller\FrontendModule;

use Cgoit\CalendarExtendedBundle\Classes\Utils;
use Cgoit\CalendarExtendedBundle\Controller\FrontendModule\AbstractCalendarViewController;
use Cgoit\CalendarExtendedBundle\Service\EventsProvider;
use Cgoit\CalendarExtendedBundle\Tests\Support\Models;
use Contao\CoreBundle\Cache\CacheTagManager;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Routing\PageFinder;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\PageModel;
use Contao\System;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\LoaderInterface;

/**
 * Calls getResponse() of a calendar view with a minimal container and returns the variables of the template.
 */
abstract class CalendarViewTestCase extends TestCase
{
    /**
     * @var array<mixed>
     */
    private array $languageBackup;

    /**
     * @var array<mixed>
     */
    private array $configBackup;

    /**
     * @var array<mixed>
     */
    private array $loadedLanguageFiles;

    protected function setUp(): void
    {
        $this->languageBackup = $GLOBALS['TL_LANG'] ?? [];
        $this->configBackup = $GLOBALS['TL_CONFIG'] ?? [];

        // new Date() reads the date format
        $GLOBALS['TL_CONFIG']['dateFormat'] = 'Y-m-d';
        $GLOBALS['TL_CONFIG']['timeFormat'] = 'H:i';
        $GLOBALS['TL_CONFIG']['datimFormat'] = 'Y-m-d H:i';

        $GLOBALS['TL_LANG']['MSC'] = [
            'calendar_week' => 'Week',
            'calendar_year' => 'Year',
            'curr_week' => 'Current week',
            'curr_year' => 'Current year',
            'cal_previous' => 'Previous',
            'cal_next' => 'Next',
            'cal_time' => 'Time',
            'dayShortLength' => 2,
            'monthShortLength' => 3,
        ];
        $GLOBALS['TL_LANG']['tl_module']['fetch_error'] = 'Error';
        $GLOBALS['TL_LANGUAGE'] = 'en';

        // The language files would need the kernel, mark them as loaded
        $loaded = new \ReflectionProperty(System::class, 'arrLanguageFiles');
        $this->loadedLanguageFiles = $loaded->getValue();
        $loaded->setValue(null, ['default' => ['en' => 'en'], 'tl_module' => ['en' => 'en']]);

        $GLOBALS['TL_LANG']['DAYS'] = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $GLOBALS['TL_LANG']['MONTHS'] = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    }

    protected function tearDown(): void
    {
        $GLOBALS['TL_LANG'] = $this->languageBackup;
        $GLOBALS['TL_CONFIG'] = $this->configBackup;

        (new \ReflectionProperty(System::class, 'arrLanguageFiles'))->setValue(null, $this->loadedLanguageFiles);
    }

    /**
     * @param array<string, mixed>  $moduleRow
     * @param array<string, string> $query
     *
     * @return array{Response, array<string, mixed>} the response and the data set on the template
     */
    protected function render(AbstractCalendarViewController $controller, PageModel|null $page, array $moduleRow, array $query = [], bool $hasTemplate = true): array
    {
        $pageFinder = $this->createStub(PageFinder::class);
        $pageFinder
            ->method('getCurrentPage')
            ->willReturn($page)
        ;

        $loader = $this->createStub(LoaderInterface::class);
        $loader
            ->method('exists')
            ->willReturn($hasTemplate)
        ;
        $twig = $this->createStub(Environment::class);
        $twig
            ->method('getLoader')
            ->willReturn($loader)
        ;

        $container = new Container();
        $container->set('contao.routing.page_finder', $pageFinder);
        $container->set('contao.cache.tag_manager', $this->createStub(CacheTagManager::class));
        $container->set('twig', $twig);

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router
            ->method('generate')
            ->willReturnCallback(static fn (string $name, array $parameters): string => '/fetch/'.$parameters['pageId'].'/'.$parameters['moduleId'].'?signature='.$parameters['signature'])
        ;
        $container->set('router', $router);

        $tokenManager = $this->createStub(ContaoCsrfTokenManager::class);
        $tokenManager
            ->method('getDefaultTokenValue')
            ->willReturn('token')
        ;
        $container->set('contao.csrf.token_manager', $tokenManager);
        $controller->setContainer($container);

        $template = new FragmentTemplate('frontend_module/test', static fn (): Response => new Response('rendered'));
        $template->set('headline', ['text' => 'Calendar', 'unit' => 'h2']);

        $method = new \ReflectionMethod($controller, 'getResponse');
        $response = $method->invoke($controller, $template, Models::module($moduleRow), Request::create('/calendar?'.http_build_query($query)));

        return [$response, $template->getData()];
    }

    protected function time(string $date): int
    {
        return Utils::parseTime($date);
    }

    protected function page(): PageModel
    {
        return Models::page(['id' => '1', 'dateFormat' => 'Y-m-d']);
    }

    /**
     * @param array<int>           $calendars
     * @param array{int, int, int} $boundaries
     * @param array<mixed>         $events
     */
    protected function provider(array $calendars, array $boundaries = [0, 0, 0], array $events = []): EventsProvider&Stub
    {
        $provider = $this->createStub(EventsProvider::class);
        $provider
            ->method('getAccessibleCalendars')
            ->willReturn($calendars)
        ;

        $provider
            ->method('findBoundaries')
            ->willReturn($boundaries)
        ;

        $provider
            ->method('getAllEvents')
            ->willReturn($events)
        ;

        return $provider;
    }
}
