<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use function assert;
use function fopen;
use function fseek;
use function fwrite;
use function is_resource;
use function is_string;

/**
 * Type that maps an SQL BLOB to a PHP resource stream.
 */
class BlobType extends Type
{
    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform) : string
    {
        return $platform->getBlobTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $fp = fopen('php://temp', 'rb+');
            assert(is_resource($fp));
            fwrite($fp, $value);
            fseek($fp, 0);
            $value = $fp;
        }

        if (! is_resource($value)) {
            throw ValueNotConvertible::new($value, self::BLOB);
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getName() : string
    {
        return Types::BLOB;
    }

    /**
     * {@inheritdoc}
     */
    public function getBindingType() : int
    {
        return ParameterType::LARGE_OBJECT;
    }
}
