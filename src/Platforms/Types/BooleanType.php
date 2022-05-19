<?php

namespace Doctrine\DBAL\Platforms\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

class BooleanType implements Type
{
    /**
     * @var AbstractPlatform
     */
    protected $platform;

    public function __construct(AbstractPlatform $platform)
    {
        $this->platform = $platform;
    }

    public function getSQLDeclaration(array $column)
    {
        return 'BOOLEAN';
    }

    public function convertToDatabaseValue($value)
    {
        return $this->platform->convertBooleans($value);
    }

    public function convertToPHPValue($value)
    {
        return $value === null ? null : (bool) $value;
    }

    public function requiresSQLCommentHint()
    {
        return false;
    }
}
