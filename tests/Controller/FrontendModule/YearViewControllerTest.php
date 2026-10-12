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

use Cgoit\CalendarExtendedBundle\Controller\FrontendModule\YearViewController;
use Contao\CoreBundle\Exception\PageNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;

final class YearViewControllerTest extends CalendarViewTestCase
{
    private const array MODULE = ['id' => '5', 'use_navigation' => '1', 'linkCurrent' => '1'];

    public function testWithoutCalendarsNothingIsRendered(): void
    {
        [$response, $data] = $this->render(new YearViewController($this->provider([])), $this->page(), self::MODULE);

        $this->assertSame('', $response->getContent());
        $this->assertArrayNotHasKey('calendar', $data);
    }

    public function testWithoutPageNothingIsRendered(): void
    {
        [$response, $data] = $this->render(new YearViewController($this->provider([1])), null, self::MODULE);

        $this->assertSame('', $response->getContent());
        $this->assertArrayNotHasKey('calendar', $data);
    }

    public function testTheGivenYearIsShownWithNavigation(): void
    {
        $year = (int) date('Y');
        $provider = $this->provider([1], [$this->time(($year - 3).'-01-01'), $this->time(($year + 3).'-01-01'), 0]);

        [$response, $data] = $this->render(new YearViewController($provider), $this->page(), self::MODULE, ['year' => (string) ($year - 1)]);

        $this->assertSame('rendered', $response->getContent());
        $this->assertSame('@Contao/cal_yearview.html.twig', $data['calendar_template']);

        $calendar = $data['calendar'];
        $this->assertSame($year - 1, $calendar['intYear']);
        $this->assertSame('/calendar?year='.($year - 2), $calendar['prevHref']);
        $this->assertSame('/calendar?year='.$year, $calendar['nextHref']);
        $this->assertSame('/calendar?year='.$year, $calendar['currHref']);
        $this->assertCount(12, $calendar['months']);
        $this->assertCount(12, $calendar['yeardays']);
    }

    public function testThereIsNoLinkBeyondTheBoundaries(): void
    {
        $year = (int) date('Y');
        $provider = $this->provider([1], [$this->time($year.'-01-01'), $this->time($year.'-01-01'), 0]);

        [, $data] = $this->render(new YearViewController($provider), $this->page(), self::MODULE);

        $this->assertSame($year, $data['calendar']['intYear']);
        $this->assertArrayNotHasKey('prevHref', $data['calendar']);
        $this->assertArrayNotHasKey('nextHref', $data['calendar']);
    }

    public function testTheHorizontalViewHasAColumnPerMonth(): void
    {
        [, $data] = $this->render(new YearViewController($this->provider([1])), $this->page(), self::MODULE + ['use_horizontal' => '1']);

        $this->assertCount(31, $data['calendar']['yeardays']);
    }

    public function testACustomTemplateIsUsedIfItExists(): void
    {
        [, $data] = $this->render(new YearViewController($this->provider([1])), $this->page(), self::MODULE + ['cal_ctemplate' => 'cal_custom']);

        $this->assertSame('@Contao/cal_custom.html.twig', $data['calendar_template']);
    }

    public function testTheDefaultTemplateIsUsedIfTheCustomOneDoesNotExist(): void
    {
        [, $data] = $this->render(new YearViewController($this->provider([1])), $this->page(), self::MODULE + ['cal_ctemplate' => 'cal_custom'], hasTemplate: false);

        $this->assertSame('@Contao/cal_yearview.html.twig', $data['calendar_template']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownYears(): iterable
    {
        yield 'not numeric' => ['abc'];
        yield 'two digits' => ['26'];
        yield 'before the first event' => ['1990'];
        yield 'after the last event' => ['2999'];
    }

    #[DataProvider('unknownYears')]
    public function testAnUnknownYearIsNotFound(string $year): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->render(new YearViewController($this->provider([1], [$this->time('2020-01-01'), $this->time('2030-01-01'), 0])), $this->page(), self::MODULE, ['year' => $year]);
    }
}
