<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\SerializationFailed;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;

use function is_resource;
use function json_decode;
use function json_encode;
use function json_last_error;
use function json_last_error_msg;
use function stream_get_contents;

use const JSON_ERROR_NONE;

/**
 * Type generating json objects values
 */
class JsonType extends Type
{
    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return null;
        }

        $encoded = json_encode($value);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw SerializationFailed::new($value, 'json', json_last_error_msg());
        }

        return $encoded;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        $val = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ValueNotConvertible::new($value, $this->getName());
        }

        return $val;
    }

    public function getName(): string
    {
        return Types::JSON;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return ! $platform->hasNativeJsonType();
    }
}
