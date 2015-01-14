<?php

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

class EnumType extends Type
{

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::ENUM;
    }

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        if (!isset($fieldDeclaration['values'])) {
            throw new \Exception("Option 'values' in 'options' parameter missing for enum type");
        }

        return 'ENUM(' . $fieldDeclaration['values'] . ')';
    }
}