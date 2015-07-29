<?php

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Type that maps interval string to a PHP DateInterval Object.
 */
class DateIntervalType extends Type
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Type::DATEINTERVAL;
    }

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        $fieldDeclaration['length'] = 20;
        $fieldDeclaration['fixed']  = true;

        return $platform->getVarcharTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        $spec = null;
        if ($value !== null) {
            /** @var \DateInterval $value */
            $spec = 'P'
                . str_pad($value->y, 4, '0', STR_PAD_LEFT) . '-'
                . $value->format('%M') . '-'
                . $value->format('%D') . 'T'
                . $value->format('%H') . ':'
                . $value->format('%I') . ':'
                . $value->format('%S')
            ;
        }

        return $spec;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value === null || $value instanceof \DateInterval) {
            return $value;
        }

        try {
            $interval = new \DateInterval($value);
        } catch (\Exception $e) {
            throw ConversionException::conversionFailedFormat($value, $this->getName(), 'PY-m-dTH:i:s');

        }

        return $interval;
    }
}
