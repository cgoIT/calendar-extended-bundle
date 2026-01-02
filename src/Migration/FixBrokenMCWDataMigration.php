<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\calendar-extended-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) Kester Mielke
 * @copyright  Copyright (c) 2026, cgoIT
 * @author     Kester Mielke
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\CalendarExtendedBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Class FixRepeatFixedDatesMigration.
 *
 * Fixes broken values for repeatFixedDates
 */
class FixBrokenMCWDataMigration extends AbstractMigration
{
    /**
     * @var array<string>
     */
    private static array $mcwColumns = ['repeatFixedDates', 'repeatExceptions', 'repeatExceptionsInt', 'repeatExceptionsPer'];

    private static string $strTable = 'tl_calendar_events';

    public function __construct(private readonly Connection $db)
    {
    }

    public function getName(): string
    {
        return 'Fixes broken values for MultiColumnWizard fields';
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        $t = self::$strTable;

        $schemaManager = $this->db->createSchemaManager();
        $cols = $schemaManager->listTableColumns($t);

        foreach (self::$mcwColumns as $col) {
            if (isset($cols[mb_strtolower($col)])) {
                $arrResult = $this->db->prepare("SELECT $t.$col FROM $t WHERE $t.$col IS NOT NULL AND $t.$col <> ''")
                    ->executeQuery()
                    ->fetchAllAssociative()
                ;

                foreach ($arrResult as $row) {
                    $arrData = StringUtil::deserialize($row[$col]);
                    if (empty($arrData) || !\is_array($arrData)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $t = self::$strTable;

        foreach (self::$mcwColumns as $col) {
            $arrResult = $this->db->prepare("SELECT $t.id, $t.$col FROM $t WHERE $t.$col IS NOT NULL AND $t.$col <> ''")
                ->executeQuery()
                ->fetchAllAssociative()
            ;

            foreach ($arrResult as $row) {
                $arrData = StringUtil::deserialize($row[$col]);
                if (empty($arrData) || !\is_array($arrData)) {
                    $this->db->update($t, [$col => null], ['id' => $row['id']]);
                }
            }
        }

        return $this->createResult(true);
    }
}
