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

namespace Cgoit\CalendarExtendedBundle\Tests\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

/**
 * Runs the migrations against an in-memory SQLite database.
 */
abstract class MigrationTestCase extends TestCase
{
    protected Connection $db;

    protected function setUp(): void
    {
        $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function insert(string $table, array $row): void
    {
        $this->db->insert($table, $row);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchRow(string $table, int $id): array
    {
        return $this->db->fetchAssociative("SELECT * FROM $table WHERE id=?", [$id]) ?: [];
    }
}
