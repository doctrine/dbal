<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\DriverException as DriverExceptionInterface;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Exception\InvalidPlatformVersion;
use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\MySqlSchemaManager;
use Doctrine\DBAL\VersionAwarePlatformDriver;

use function preg_match;
use function stripos;
use function version_compare;

/**
 * Abstract base implementation of the {@link Doctrine\DBAL\Driver} interface for MySQL based drivers.
 */
abstract class AbstractMySQLDriver implements ExceptionConverterDriver, VersionAwarePlatformDriver
{
    /**#@+
     * MySQL server error codes.
     *
     * @link https://dev.mysql.com/doc/refman/8.0/en/server-error-reference.html
     */
    private const ER_DBACCESS_DENIED_ERROR            = 1044;
    private const ER_ACCESS_DENIED_ERROR              = 1045;
    private const ER_NO_DB_ERROR                      = 1046;
    private const ER_BAD_NULL_ERROR                   = 1048;
    private const ER_BAD_DB_ERROR                     = 1049;
    private const ER_TABLE_EXISTS_ERROR               = 1050;
    private const ER_BAD_TABLE_ERROR                  = 1051;
    private const ER_NON_UNIQ_ERROR                   = 1052;
    private const ER_BAD_FIELD_ERROR                  = 1054;
    private const ER_DUP_FIELDNAME                    = 1060;
    private const ER_DUP_ENTRY                        = 1062;
    private const ER_PARSE_ERROR                      = 1064;
    private const ER_KILL_DENIED_ERROR                = 1095;
    private const ER_FIELD_SPECIFIED_TWICE            = 1110;
    private const ER_NULL_COLUMN_IN_INDEX             = 1121;
    private const ER_INVALID_USE_OF_NULL              = 1138;
    private const ER_TABLEACCESS_DENIED_ERROR         = 1142;
    private const ER_COLUMNACCESS_DENIED_ERROR        = 1143;
    private const ER_NO_SUCH_TABLE                    = 1146;
    private const ER_SYNTAX_ERROR                     = 1149;
    private const ER_WRONG_COLUMN_NAME                = 1166;
    private const ER_PRIMARY_CANT_HAVE_NULL           = 1171;
    private const ER_LOCK_WAIT_TIMEOUT                = 1205;
    private const ER_LOCK_DEADLOCK                    = 1213;
    private const ER_NO_REFERENCED_ROW                = 1216;
    private const ER_ROW_IS_REFERENCED                = 1217;
    private const ER_SPECIFIC_ACCESS_DENIED_ERROR     = 1227;
    private const ER_SPATIAL_CANT_HAVE_NULL           = 1252;
    private const ER_WARN_NULL_TO_NOTNULL             = 1263;
    private const ER_WARN_DEPRECATED_SYNTAX           = 1287;
    private const ER_FPARSER_BAD_HEADER               = 1341;
    private const ER_FPARSER_EOF_IN_COMMENT           = 1342;
    private const ER_FPARSER_ERROR_IN_PARAMETER       = 1343;
    private const ER_FPARSER_EOF_IN_UNKNOWN_PARAMETER = 1344;
    private const ER_NO_DEFAULT_FOR_FIELD             = 1364;
    private const ER_PROCACCESS_DENIED_ERROR          = 1370;
    private const ER_RESERVED_SYNTAX                  = 1382;
    private const ER_CONNECT_TO_FOREIGN_DATA_SOURCE   = 1429;
    private const ER_ROW_IS_REFERENCED_2              = 1451;
    private const ER_NO_REFERENCED_ROW_2              = 1452;
    private const ER_PARTITION_REQUIRES_VALUES_ERROR  = 1479;
    private const ER_EVENT_DROP_FAILED                = 1541;
    private const ER_WARN_DEPRECATED_SYNTAX_WITH_VER  = 1554;
    private const ER_FOREIGN_DUPLICATE_KEY_OLD_UNUSED = 1557;
    private const ER_NULL_IN_VALUES_LESS_THAN         = 1566;
    private const ER_DUP_ENTRY_AUTOINCREMENT_CASE     = 1569;
    private const ER_DUP_ENTRY_WITH_KEY_NAME          = 1586;
    private const ER_LOAD_DATA_INVALID_COLUMN         = 1611;
    private const ER_CONFLICT_FN_PARSE_ERROR          = 1626;
    private const ER_TRUNCATE_ILLEGAL_FK              = 1701;
    /**#@-*/

    /**#@+
     * MySQL client error codes.
     *
     * @link https://dev.mysql.com/doc/refman/8.0/en/client-error-reference.html
     */
    private const CR_CONNECTION_ERROR = 2002;
    private const CR_UNKNOWN_HOST     = 2005;
    /**#@-*/

    public function convertException(string $message, DriverExceptionInterface $exception): DriverException
    {
        switch ($exception->getCode()) {
            case self::ER_LOCK_DEADLOCK:
                return new Exception\DeadlockException($message, $exception);

            case self::ER_LOCK_WAIT_TIMEOUT:
                return new Exception\LockWaitTimeoutException($message, $exception);

            case self::ER_TABLE_EXISTS_ERROR:
                return new Exception\TableExistsException($message, $exception);

            case self::ER_BAD_TABLE_ERROR:
            case self::ER_NO_SUCH_TABLE:
                return new Exception\TableNotFoundException($message, $exception);

            case self::ER_NO_REFERENCED_ROW:
            case self::ER_ROW_IS_REFERENCED:
            case self::ER_ROW_IS_REFERENCED_2:
            case self::ER_NO_REFERENCED_ROW_2:
            case self::ER_TRUNCATE_ILLEGAL_FK:
                return new Exception\ForeignKeyConstraintViolationException($message, $exception);

            case self::ER_DUP_ENTRY:
            case self::ER_FOREIGN_DUPLICATE_KEY_OLD_UNUSED:
            case self::ER_DUP_ENTRY_AUTOINCREMENT_CASE:
            case self::ER_DUP_ENTRY_WITH_KEY_NAME:
                return new Exception\UniqueConstraintViolationException($message, $exception);

            case self::ER_BAD_FIELD_ERROR:
            case self::ER_WRONG_COLUMN_NAME:
            case self::ER_LOAD_DATA_INVALID_COLUMN:
                return new Exception\InvalidFieldNameException($message, $exception);

            case self::ER_NON_UNIQ_ERROR:
            case self::ER_DUP_FIELDNAME:
            case self::ER_FIELD_SPECIFIED_TWICE:
                return new Exception\NonUniqueFieldNameException($message, $exception);

            case self::ER_PARSE_ERROR:
            case self::ER_SYNTAX_ERROR:
            case self::ER_WARN_DEPRECATED_SYNTAX:
            case self::ER_FPARSER_BAD_HEADER:
            case self::ER_FPARSER_EOF_IN_COMMENT:
            case self::ER_FPARSER_ERROR_IN_PARAMETER:
            case self::ER_FPARSER_EOF_IN_UNKNOWN_PARAMETER:
            case self::ER_RESERVED_SYNTAX:
            case self::ER_PARTITION_REQUIRES_VALUES_ERROR:
            case self::ER_EVENT_DROP_FAILED:
            case self::ER_WARN_DEPRECATED_SYNTAX_WITH_VER:
            case self::ER_CONFLICT_FN_PARSE_ERROR:
                return new Exception\SyntaxErrorException($message, $exception);

            case self::ER_DBACCESS_DENIED_ERROR:
            case self::ER_ACCESS_DENIED_ERROR:
            case self::ER_NO_DB_ERROR:
            case self::ER_BAD_DB_ERROR:
            case self::ER_KILL_DENIED_ERROR:
            case self::ER_TABLEACCESS_DENIED_ERROR:
            case self::ER_COLUMNACCESS_DENIED_ERROR:
            case self::ER_SPECIFIC_ACCESS_DENIED_ERROR:
            case self::ER_PROCACCESS_DENIED_ERROR:
            case self::ER_CONNECT_TO_FOREIGN_DATA_SOURCE:
            case self::CR_CONNECTION_ERROR:
            case self::CR_UNKNOWN_HOST:
                return new Exception\ConnectionException($message, $exception);

            case self::ER_BAD_NULL_ERROR:
            case self::ER_NULL_COLUMN_IN_INDEX:
            case self::ER_INVALID_USE_OF_NULL:
            case self::ER_PRIMARY_CANT_HAVE_NULL:
            case self::ER_SPATIAL_CANT_HAVE_NULL:
            case self::ER_WARN_NULL_TO_NOTNULL:
            case self::ER_NO_DEFAULT_FOR_FIELD:
            case self::ER_NULL_IN_VALUES_LESS_THAN:
                return new Exception\NotNullConstraintViolationException($message, $exception);
        }

        return new DriverException($message, $exception);
    }

    /**
     * {@inheritdoc}
     *
     * @throws DBALException
     */
    public function createDatabasePlatformForVersion(string $version): AbstractPlatform
    {
        $mariadb = stripos($version, 'mariadb') !== false;
        if ($mariadb && version_compare($this->getMariaDbMysqlVersionNumber($version), '10.2.7', '>=')) {
            return new MariaDb1027Platform();
        }

        if (! $mariadb) {
            $oracleMysqlVersion = $this->getOracleMysqlVersionNumber($version);
            if (version_compare($oracleMysqlVersion, '8', '>=')) {
                return new MySQL80Platform();
            }

            if (version_compare($oracleMysqlVersion, '5.7.9', '>=')) {
                return new MySQL57Platform();
            }
        }

        return $this->getDatabasePlatform();
    }

    /**
     * Get a normalized 'version number' from the server string
     * returned by Oracle MySQL servers.
     *
     * @param string $versionString Version string returned by the driver, i.e. '5.7.10'
     *
     * @throws DBALException
     */
    private function getOracleMysqlVersionNumber(string $versionString): string
    {
        if (
            preg_match(
                '/^(?P<major>\d+)(?:\.(?P<minor>\d+)(?:\.(?P<patch>\d+))?)?/',
                $versionString,
                $versionParts
            ) === 0
        ) {
            throw InvalidPlatformVersion::new(
                $versionString,
                '<major_version>.<minor_version>.<patch_version>'
            );
        }

        $majorVersion = $versionParts['major'];
        $minorVersion = $versionParts['minor'] ?? 0;
        $patchVersion = $versionParts['patch'] ?? null;

        if ($majorVersion === '5' && $minorVersion === '7' && $patchVersion === null) {
            $patchVersion = '9';
        }

        return $majorVersion . '.' . $minorVersion . '.' . $patchVersion;
    }

    /**
     * Detect MariaDB server version, including hack for some mariadb distributions
     * that starts with the prefix '5.5.5-'
     *
     * @param string $versionString Version string as returned by mariadb server, i.e. '5.5.5-Mariadb-10.0.8-xenial'
     *
     * @throws DBALException
     */
    private function getMariaDbMysqlVersionNumber(string $versionString): string
    {
        if (
            preg_match(
                '/^(?:5\.5\.5-)?(mariadb-)?(?P<major>\d+)\.(?P<minor>\d+)\.(?P<patch>\d+)/i',
                $versionString,
                $versionParts
            ) === 0
        ) {
            throw InvalidPlatformVersion::new(
                $versionString,
                '^(?:5\.5\.5-)?(mariadb-)?<major_version>.<minor_version>.<patch_version>'
            );
        }

        return $versionParts['major'] . '.' . $versionParts['minor'] . '.' . $versionParts['patch'];
    }

    /**
     * {@inheritdoc}
     *
     * @return MySqlPlatform
     */
    public function getDatabasePlatform(): AbstractPlatform
    {
        return new MySqlPlatform();
    }

    /**
     * {@inheritdoc}
     *
     * @return MySqlSchemaManager
     */
    public function getSchemaManager(Connection $conn): AbstractSchemaManager
    {
        return new MySqlSchemaManager($conn);
    }
}
