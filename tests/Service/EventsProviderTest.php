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

use Cgoit\CalendarExtendedBundle\Service\EventsProvider;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class EventsProviderTest extends TestCase
{
    public function testBoundariesOnlyConsiderPublishedEventsOutsideOfThePreview(): void
    {
        $sql = '';
        $params = [];
        $provider = $this->createProvider(false, ['dateFrom' => '100', 'dateTo' => '200', 'repeatUntil' => null], $sql, $params);

        $this->assertSame([100, 200, 0], $provider->findBoundaries([3, 4]));
        $this->assertStringContainsString('pid IN (?, ?)', $sql);
        $this->assertStringContainsString("published='1'", $sql);
        $this->assertCount(4, $params);
        $this->assertSame([3, 4], \array_slice($params, 0, 2));
    }

    public function testBoundariesIncludeUnpublishedEventsInThePreview(): void
    {
        $sql = '';
        $params = [];
        $provider = $this->createProvider(true, false, $sql, $params);

        $this->assertSame([0, 0, 0], $provider->findBoundaries([1]));
        $this->assertStringNotContainsString('published', $sql);
        $this->assertSame([1], $params);
    }

    public function testBoundariesWithoutCalendarsDoNotQuery(): void
    {
        $sql = '';
        $params = [];
        $provider = $this->createProvider(false, false, $sql, $params);

        $this->assertSame([0, 0, 0], $provider->findBoundaries([]));
        $this->assertSame('', $sql);
    }

    /**
     * @param array<string, mixed>|false $row
     * @param array<int, mixed>          $params
     */
    private function createProvider(bool $preview, array|false $row, string &$sql, array &$params): EventsProvider
    {
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturnCallback(
                static function (string $query, array $queryParams) use ($row, &$sql, &$params) {
                    $sql = $query;
                    $params = $queryParams;

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
            $connection,
            $this->createStub(Security::class),
            $tokenChecker,
        );
    }
}
