<?php

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Type that maps an SQL INT to a PHP integer.
 *
 * @author Roman Borschel <roman@code-factory.org>
 * @since 2.0
 */
class IntegerType extends Type implements PhpIntegerMappingType
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Type::INTEGER;
    }

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return $platform->getIntegerTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return (null === $value) ? null : (int) $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getBindingType()
    {
        return ParameterType::INTEGER;
    }
}
