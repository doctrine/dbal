<?php


namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Type that maps interval string to a PHP DateInterval Object.
 */
class DateIntervalType extends Type
{
    const DATEINTERVAL_PATTERN = '#(?P<date>\d{4}-\d{2}-\d{2}).(?P<time>\d{2}:\d{2}:\d{2})#';

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
            $spec = str_pad($value->y, 4, '0', STR_PAD_LEFT) . '-'
                . $value->format('%M') . '-'
                . $value->format('%D') . ' '
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

        if (preg_match(self::DATEINTERVAL_PATTERN, $value, $parts) !== 1) {
            throw ConversionException::conversionFailedFormat($value, $this->getName(), 'Y-m-d H:i:s');
        }
        try {
            $interval = new \DateInterval('P' . $parts['date'] . 'T' . $parts['time']);
        } catch (\Exception $e) {
            throw ConversionException::conversionFailedFormat($value, $this->getName(), 'PY-m-dTH:i:s');

        }

        return $interval;
    }
}
