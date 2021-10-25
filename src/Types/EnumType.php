<?php

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use UnitEnum;

use function restore_error_handler;
use function serialize;
use function set_error_handler;
use function unserialize;

final class EnumType extends Type
{
    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return $platform->getVarcharTypeDeclarationSQL($column);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UnitEnum) {
            return serialize($value);
        }

        throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', 'UnitEnum']);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return null;
        }

        set_error_handler(function (int $code, string $message): bool {
            throw ConversionException::conversionFailedUnserialization($this->getName(), $message);
        });

        try {
            return unserialize($value);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Types::ENUM;
    }

    /**
     * {@inheritdoc}
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return true;
    }
}
