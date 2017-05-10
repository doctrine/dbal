<?php

namespace Doctrine\DBAL\Types;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class PolygonType extends Type
{
    const FIELD = 'polygon';

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return 'POLYGON';
    }

    public function canRequireSQLConversion()
    {
        return true;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        preg_match('/POLYGON\(\((.*)\)\)/', $value, $matches);
        if ( !isset($matches[1]) )
            throw new Exception('No Polygon Points');
        return $matches[1];
    }

    public function convertToPHPValueSQL($sqlExpr, $platform)
    {
        return sprintf('AsText(%s)', $sqlExpr);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        return sprintf('POLYGON((%s))', $value);
    }

    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform)
    {
        return sprintf('ST_PolygonFromText(%s)', $sqlExpr);
    }

    public function getName()
    {
        return self::FIELD;
    }
}
