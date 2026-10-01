<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\Deprecations\Deprecation;

use function is_string;
use function preg_match;

/**
 * Represents a GUID/UUID datatype (both are actually synonyms) in the database.
 */
class GuidType extends StringType
{
    /**
     * 32 hexadecimal digits, with optional surrounding braces and an optional hyphen after any group of four digits.
     *
     * This accepts everything PostgreSQL accepts for its native UUID type, so that no value the database could have
     * stored is reported as malformed. The `(?(1)\})` conditional requires the closing brace only when an opening
     * brace was matched.
     */
    private const FORMAT = '/^(\{)?[0-9a-f]{4}(?:-?[0-9a-f]{4}){7}(?(1)\})$/i';

    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        $this->deprecateMalformedValue($value, __METHOD__);

        return $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        $this->deprecateMalformedValue($value, __METHOD__);

        return $value;
    }

    private function deprecateMalformedValue(mixed $value, string $method): void
    {
        if ($value === null) {
            return;
        }

        if (is_string($value) && preg_match(self::FORMAT, $value) === 1) {
            return;
        }

        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7504',
            <<<'DEPRECATION'
            Handling a value that is not shaped like a GUID in %s is deprecated.
            In 5.0, such a value will be rejected with a conversion exception.
            DEPRECATION,
            $method,
        );
    }
}
