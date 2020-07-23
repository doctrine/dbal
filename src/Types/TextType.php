<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

use function is_resource;
use function stream_get_contents;

/**
 * Type that maps an SQL CLOB to a PHP string.
 */
class TextType extends Type
{
    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return is_resource($value) ? stream_get_contents($value) : $value;
    }

    public function getName(): string
    {
        return Types::TEXT;
    }
}
