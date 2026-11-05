<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use DateTime;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\Deprecations\Deprecation;
use Exception;

/**
 * Mutable variant of {@see VarDateTimeImmutableType}.
 *
 * @deprecated Use {@see VarDateTimeImmutableType} instead.
 */
class VarDateTimeType extends DateTimeType
{
    /**
     * @param T $value
     *
     * @return (T is null ? null : DateTime)
     *
     * @template T
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateTime
    {
        $this->triggerDeprecation();

        if ($value === null || $value instanceof DateTime) {
            return $value;
        }

        try {
            $dateTime = new DateTime($value);
        } catch (Exception $e) {
            throw ValueNotConvertible::new($value, DateTime::class, $e->getMessage(), $e);
        }

        return $dateTime;
    }

    protected function triggerDeprecation(): void
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7575',
            'The "%s" type is deprecated. Use the "%s" type instead.',
            self::class,
            VarDateTimeImmutableType::class,
        );
    }
}
