<?php

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Type that maps an SQL BLOB to a PHP resource stream.
 */
class BlobType extends Type
{
    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return $platform->getBlobTypeDeclarationSQL($column);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Types::BLOB;
    }

    /**
     * {@inheritdoc}
     */
    public function getBindingType()
    {
        return ParameterType::LARGE_OBJECT;
    }
}
