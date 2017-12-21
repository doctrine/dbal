<?php

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Array Type which can be used for simple values.
 *
 * Only use this type if you are sure that your values cannot contain a ",".
 *
 * @since  2.3
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class SimpleArrayType extends Type
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
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (!$value) {
            return null;
        }

        return implode(',', $value);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return [];
        }

        $value = (is_resource($value)) ? stream_get_contents($value) : $value;

        return explode(',', $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Type::SIMPLE_ARRAY;
    }

    /**
     * {@inheritdoc}
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return true;
    }
}
