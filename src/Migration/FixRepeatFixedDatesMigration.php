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
class FixRepeatFixedDatesMigration extends AbstractMigration
{
    private static string $fixedDatesColumn = 'repeatFixedDates';

    private static string $strTable = 'tl_calendar_events';

    public function __construct(private readonly Connection $db)
    {
    }

    public function getName(): string
    {
        return 'Fixes broken values for repeatFixedDates';
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        $t = self::$strTable;

        $schemaManager = $this->db->createSchemaManager();
        $cols = $schemaManager->listTableColumns($t);

        if (isset($cols[mb_strtolower(self::$fixedDatesColumn)])) {
            $arrResult = $this->db->prepare('SELECT '.self::$fixedDatesColumn." FROM $t WHERE ".self::$fixedDatesColumn.' IS NOT NULL AND '.self::$fixedDatesColumn."<> ''")
                ->executeQuery()
                ->fetchAllAssociative()
            ;

            foreach ($arrResult as $row) {
                $fixedDates = StringUtil::deserialize($row[self::$fixedDatesColumn]);
                if (empty($fixedDates) || !\is_array($fixedDates)) {
                    return true;
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

        $arrResult = $this->db->prepare('SELECT id, '.self::$fixedDatesColumn." FROM $t WHERE ".self::$fixedDatesColumn.' IS NOT NULL AND '.self::$fixedDatesColumn."<> ''")
            ->executeQuery()
            ->fetchAllAssociative()
        ;

        foreach ($arrResult as $row) {
            $fixedDates = StringUtil::deserialize($row[self::$fixedDatesColumn]);
            if (empty($fixedDates) || !\is_array($fixedDates)) {
                $arrSet = [self::$fixedDatesColumn => null];
                $this->db->update($t, $arrSet, ['id' => $row['id']]);
            }
        }

        return $this->createResult(true);
    }
}
