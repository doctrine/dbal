<?php

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\Types\UnsupportedTypeException;

/**
 * Type that maps an SQL boolean to a PHP boolean.
 */
class BooleanType extends Type
{
    private function getPlatformType(AbstractPlatform $platform) : \Doctrine\DBAL\Platforms\Types\Type
    {
        $type = $platform->getType(self::class);

        if ($type === null) {
            throw new UnsupportedTypeException('Basic types need to be implemented by platform');
        }

        return $type;
    }

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return $this->getPlatformType($platform)->getSQLDeclaration($column);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        return $this->getPlatformType($platform)->convertToDatabaseValue($value);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return $this->getPlatformType($platform)->convertToPHPValue($value);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Types::BOOLEAN;
    }

    /**
     * {@inheritdoc}
     */
    public function getBindingType()
    {
        return ParameterType::BOOLEAN;
    }

    /**
     * @return bool
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return $this->getPlatformType($platform)->requiresSQLCommentHint();
    }
}
