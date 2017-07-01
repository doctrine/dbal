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
        $fieldDeclaration['length'] = 21;
        $fieldDeclaration['fixed']  = true;

        return $platform->getVarcharTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof \DateInterval) {
            $sign = (0 === $value->invert) ? '+' : '-';

            return $sign . 'P'
                . str_pad($value->y, 4, '0', STR_PAD_LEFT) . '-'
                . $value->format('%M-%DT%H:%I:%S');
        }

        throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', 'DateInterval']);
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
            $firstCharacter = substr($value, 0, 1);

            if ($firstCharacter !== '+' && $firstCharacter !== '-') {
                return new \DateInterval($value);
            }

            $interval = new \DateInterval(substr($value, 1));

            if ($firstCharacter === '-') {
                $interval->invert = 1;
            }

            return $interval;
        } catch (\Exception $exception) {
            throw ConversionException::conversionFailedFormat($value, $this->getName(), 'RPY-m-dTH:i:s', $exception);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return true;
    }
}
