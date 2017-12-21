<?php

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Array Type which can be used to generate json arrays.
 *
 * @since  2.3
 * @deprecated Use JsonType instead
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class JsonArrayType extends JsonType
{
    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value === null || $value === '') {
            return [];
        }

        $value = (is_resource($value)) ? stream_get_contents($value) : $value;

        return json_decode($value, true);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Type::JSON_ARRAY;
    }

    /**
     * {@inheritdoc}
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return true;
    }
}
