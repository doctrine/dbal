<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Types\StringType;

/**
 * A string type that converts its values at the SQL level: to lower case when writing them,
 * to upper case when reading them.
 */
final class CaseConvertingStringType extends StringType
{
    public const NAME = 'case_converting_string';

    public function convertToDatabaseValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return 'LOWER(' . $this->castParameter($sqlExpr, $platform) . ')';
    }

    public function convertToPHPValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return 'UPPER(' . $sqlExpr . ')';
    }

    private function castParameter(string $sqlExpr, AbstractPlatform $platform): string
    {
        // Db2 cannot infer the type of a parameter marker used as a function argument
        if ($platform instanceof DB2Platform) {
            return 'CAST(' . $sqlExpr . ' AS VARCHAR(255))';
        }

        return $sqlExpr;
    }
}
