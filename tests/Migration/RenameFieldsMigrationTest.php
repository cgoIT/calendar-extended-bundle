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

use Cgoit\CalendarExtendedBundle\Migration\RenameFieldsMigration;

final class RenameFieldsMigrationTest extends MigrationTestCase
{
    public function testDoesNotRunIfTheOldColumnsDoNotExist(): void
    {
        $this->db->executeStatement('CREATE TABLE tl_module (id INTEGER PRIMARY KEY, hide_holiday TEXT, cellheight TEXT)');
        $this->insert('tl_module', ['id' => 1, 'hide_holiday' => '1', 'cellheight' => '20']);

        $this->assertFalse((new RenameFieldsMigration($this->db))->shouldRun());
    }

    public function testDoesNotRunIfTheValuesAreAlreadyEqual(): void
    {
        $this->createTable();
        $this->insert('tl_module', ['id' => 1, 'show_holiday' => '1', 'hide_holiday' => '1', 'cellhight' => '20', 'cellheight' => '20']);

        $this->assertFalse((new RenameFieldsMigration($this->db))->shouldRun());
    }

    public function testRunsIfAnOldValueDiffers(): void
    {
        $this->createTable();
        $this->insert('tl_module', ['id' => 1, 'show_holiday' => '1', 'hide_holiday' => '', 'cellhight' => '20', 'cellheight' => '20']);

        $this->assertTrue((new RenameFieldsMigration($this->db))->shouldRun());
    }

    public function testCopiesTheValuesOfTheOldColumns(): void
    {
        $this->createTable();
        $this->insert('tl_module', ['id' => 1, 'show_holiday' => '1', 'hide_holiday' => '', 'cellhight' => '40', 'cellheight' => '']);
        $this->insert('tl_module', ['id' => 2, 'show_holiday' => '', 'hide_holiday' => '', 'cellhight' => '', 'cellheight' => '']);

        $migration = new RenameFieldsMigration($this->db);
        $result = $migration->run();

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('1', $this->fetchRow('tl_module', 1)['hide_holiday']);
        $this->assertSame('40', $this->fetchRow('tl_module', 1)['cellheight']);
        $this->assertSame('', $this->fetchRow('tl_module', 2)['hide_holiday']);
        $this->assertFalse($migration->shouldRun());
    }

    public function testHasAName(): void
    {
        $this->assertSame('Rename fields show_holiday, cellhight', (new RenameFieldsMigration($this->db))->getName());
    }

    private function createTable(): void
    {
        $this->db->executeStatement('CREATE TABLE tl_module (id INTEGER PRIMARY KEY, show_holiday TEXT, hide_holiday TEXT, cellhight TEXT, cellheight TEXT)');
    }
}
