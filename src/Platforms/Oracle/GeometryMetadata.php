<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Oracle;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Spatial\Dimension;
use Doctrine\DBAL\Spatial\Exception\InvalidGeometry;

use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strtoupper;
use function substr;

/**
 * Reads the SRIDs and the coordinate dimensions of the geometry columns registered in USER_SDO_GEOM_METADATA.
 *
 * The view requires Oracle Spatial, so it should only be queried when there are geometry columns.
 *
 * @internal
 */
final class GeometryMetadata
{
    /**
     * @param ?string $tableName The name of the table as stored in the data dictionary, or NULL for all the
     *                         tables.
     *
     * @return array<string, array<string, array{?int, ?Dimension}>> The SRID and the coordinate dimension of the
     *                                                               columns, by table name and column name as stored
     *                                                               in the data dictionary.
     *
     * @throws Exception
     */
    public static function fetch(Connection $connection, ?string $tableName): array
    {
        $sql = <<<'SQL'
            SELECT M.TABLE_NAME,
                   M.COLUMN_NAME,
                   M.SRID,
                   (SELECT LISTAGG(D.SDO_DIMNAME) WITHIN GROUP (ORDER BY ROWNUM) FROM TABLE(M.DIMINFO) D)
            FROM USER_SDO_GEOM_METADATA M
            SQL;

        $params = [];

        if ($tableName !== null) {
            $sql .= ' WHERE M.TABLE_NAME IN (:TABLE_NAME, :QUOTED_TABLE_NAME)';

            $params = [
                'TABLE_NAME' => $tableName,
                'QUOTED_TABLE_NAME' => '"' . $tableName . '"',
            ];
        }

        $metadata = [];

        foreach ($connection->iterateNumeric($sql, $params) as [$table, $column, $srid, $dimensionNames]) {
            try {
                $dimension = Dimension::fromName(strtoupper((string) $dimensionNames));
            } catch (InvalidGeometry) {
                // the dimensions have been registered with other names than X, Y, Z and M
                $dimension = null;
            }

            $metadata[self::unquote($table)][self::unquote($column)] = [
                $srid === null ? null : (int) $srid,
                $dimension,
            ];
        }

        return $metadata;
    }

    /**
     * Returns the name of a table or a column as stored in the data dictionary: USER_SDO_GEOM_METADATA stores the
     * names that are not valid unquoted identifiers between double quotes.
     */
    private static function unquote(string $name): string
    {
        if (strlen($name) > 1 && str_starts_with($name, '"') && str_ends_with($name, '"')) {
            return substr($name, 1, -1);
        }

        return $name;
    }
}
