<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;

use function is_resource;
use function restore_error_handler;
use function serialize;
use function set_error_handler;
use function stream_get_contents;
use function unserialize;

/**
 * Type that maps a PHP object to a clob SQL type.
 */
class ObjectType extends Type
{
    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return serialize($value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = is_resource($value) ? stream_get_contents($value) : $value;

        set_error_handler(static function (int $code, string $message) use ($value): bool {
            throw ValueNotConvertible::new($value, 'object', $message);
        });

        try {
            return unserialize($value);
        } finally {
            restore_error_handler();
        }
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
