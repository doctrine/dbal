<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Class for database column "geometry".
 *
 * @author Rauni Lillemets
 */
class GeometryType extends Type
{

    const GEOMETRY = 'geometry';
    const SRID = 3301;


    public function getSqlDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return 'geometry';
    }


    //Should create WKT object from WKT string. (or leave as WKT string)
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return $value;
    }


    //Should create WKT string from WKT object. (or leave as WKT string)
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        return $value;
    }


    public function getName()
    {
        return self::GEOMETRY;
    }


    public function canRequireSQLConversion()
    {
        return true;
    }


    //Should give WKT
    public function convertToPHPValueSQL($sqlExpr, $platform)
    {
        return 'ST_AsText(\'' . $sqlExpr . '\') ';
    }


    //Should create WKB
    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform)
    {
        return 'ST_GeomFromText(\'' . $sqlExpr . '\', ' . self::SRID . ')';
    }
}
