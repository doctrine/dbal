<?php

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Type that maps an SQL CLOB to a PHP string.
 *
 * @since 2.0
 */
class TextType extends Type
{
    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return $platform->getClobTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return (is_resource($value)) ? stream_get_contents($value) : $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Type::TEXT;
    }
}
