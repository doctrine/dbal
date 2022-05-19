<?php

namespace Doctrine\DBAL\Platforms\PostgreSQL\Types;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\Types\BooleanType as PlatformBooleanType;

class BooleanType extends PlatformBooleanType
{
    /**
     * @var PostgreSQLPlatform
     */
    protected $platform;

    public function convertToPHPValue($value)
    {
        $booleanLiterals = $this->platform->getBooleanLiterals();

        if ($value !== null && in_array(strtolower($value), $booleanLiterals['false'], true)) {
            return false;
        }

        return parent::convertToPHPValue($value);
    }

    public function convertToDatabaseValue($value)
    {
        if (!$this->platform->isUseBooleanTrueFalseStrings()) {
            return parent::convertToDatabaseValue($value);
        }

        return $this->platform->doConvertBooleans(
            $value,
            /**
             * @param mixed $value
             */
            static function ($value): ?int {
                return $value === null ? null : (int) $value;
            }
        );
    }
}
