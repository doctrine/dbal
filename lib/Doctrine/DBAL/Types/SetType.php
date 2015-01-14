<?php

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

class SetType extends Type
{

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::SET;
    }

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        if (!isset($fieldDeclaration['values'])) {
            throw new \Exception("Option 'values' in 'options' parameter missing for set type");
        }

        return 'SET(' . $fieldDeclaration['values'] . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return (array)explode(',', $value);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        return join(',', (array)$value);
    }
}