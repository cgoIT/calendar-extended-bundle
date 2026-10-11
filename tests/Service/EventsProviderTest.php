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

namespace Cgoit\CalendarExtendedBundle\Tests\Service;

use Cgoit\CalendarExtendedBundle\Hook\GetAllEventsHook;
use Cgoit\CalendarExtendedBundle\Service\EventsProvider;
use Contao\CalendarBundle\Generator\CalendarEventsGenerator;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class EventsProviderTest extends TestCase
{
    public function testBoundariesOnlyConsiderPublishedEventsOutsideOfThePreview(): void
    {
        $sql = '';
        $provider = $this->createProvider(false, ['dateFrom' => '100', 'dateTo' => '200', 'repeatUntil' => null], $sql);

        $this->assertSame([100, 200, 0], $provider->findBoundaries([3, 4]));
        $this->assertStringContainsString('pid IN (3,4)', $sql);
        $this->assertStringContainsString("published='1'", $sql);
    }

    public function testBoundariesIncludeUnpublishedEventsInThePreview(): void
    {
        $sql = '';
        $provider = $this->createProvider(true, false, $sql);

        $this->assertSame([0, 0, 0], $provider->findBoundaries([1]));
        $this->assertStringNotContainsString('published', $sql);
    }

    /**
     * @param array<string, mixed>|false $row
     */
    private function createProvider(bool $preview, array|false $row, string &$sql): EventsProvider
    {
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturnCallback(
                static function (string $query) use ($row, &$sql) {
                    $sql = $query;

                    return $row;
                },
            )
        ;

        $tokenChecker = $this->createStub(TokenChecker::class);
        $tokenChecker
            ->method('isPreviewMode')
            ->willReturn($preview)
        ;

        return new EventsProvider(
            $this->createStub(CalendarEventsGenerator::class),
            (new \ReflectionClass(GetAllEventsHook::class))->newInstanceWithoutConstructor(),
            $connection,
            $this->createStub(Security::class),
            $tokenChecker,
        );
    }
}
