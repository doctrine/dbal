<?php

namespace Doctrine\DBAL\Platforms\Keywords;

use Doctrine\Deprecations\Deprecation;

/**
 * DB2 Keywords.
 */
class DB2Keywords extends KeywordList
{
    /**
     * {@inheritDoc}
     *
     * @deprecated
     */
    public function getName()
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5433',
            'DB2Keywords::getName() is deprecated.',
        );

        return 'DB2';
    }

    /**
     * {@inheritDoc}
     */
    protected function getKeywords()
    {
        return [
            'ACTIVATE',
            'ADD',
            'AFTER',
            'ALIAS',
            'ALL',
            'ALLOCATE',
            'ALLOW',
            'ALTER',
            'AND',
            'ANY',
            'AS',
            'ASENSITIVE',
            'ASSOCIATE',
            'ASUTIME',
            'AT',
            'ATTRIBUTES',
            'AUDIT',
            'AUTHORIZATION',
            'AUX',
            'AUXILIARY',
            'BEFORE',
            'BEGIN',
            'BETWEEN',
            'BINARY',
            'BUFFERPOOL',
            'BY',
            'CACHE',
            'CALL',
            'CALLED',
            'CAPTURE',
            'CARDINALITY',
            'CASCADED',
            'CASE',
            'CAST',
            'CCSID',
            'CHAR',
            'CHARACTER',
            'CHECK',
            'CLONE',
            'CLOSE',
            'CLUSTER',
            'COLLECTION',
            'COLLID',
            'COLUMN',
            'COMMENT',
            'COMMIT',
            'CONCAT',
            'CONDITION',
            'CONNECT',
            'CONNECTION',
            'CONSTRAINT',
            'CONTAINS',
            'CONTINUE',
            'COUNT',
            'COUNT_BIG',
            'CREATE',
            'CROSS',
            'CURRENT',
            'CURRENT_DATE',
            'CURRENT_LC_CTYPE',
            'CURRENT_PATH',
            'CURRENT_SCHEMA',
            'CURRENT_SERVER',
            'CURRENT_TIME',
            'CURRENT_TIMESTAMP',
            'CURRENT_TIMEZONE',
            'CURRENT_USER',
            'CURSOR',
            'CYCLE',
            'DATA',
            'DATABASE',
            'DATAPARTITIONNAME',
            'DATAPARTITIONNUM',
            'DATE',
            'DAY',
            'DAYS',
            'DB2GENERAL',
            'DB2GENRL',
            'DB2SQL',
            'DBINFO',
            'DBPARTITIONNAME',
            'DBPARTITIONNUM',
            'DEALLOCATE',
            'DECLARE',
            'DEFAULT',
            'DEFAULTS',
            'DEFINITION',
            'DELETE',
            'DENSE_RANK',
            'DENSERANK',
            'DESCRIBE',
            'DESCRIPTOR',
            'DETERMINISTIC',
            'DIAGNOSTICS',
            'DISABLE',
            'DISALLOW',
            'DISCONNECT',
            'DISTINCT',
            'DO',
            'DOCUMENT',
            'DOUBLE',
            'DROP',
            'DSSIZE',
            'DYNAMIC',
            'EACH',
            'EDITPROC',
            'ELSE',
            'ELSEIF',
            'ENABLE',
            'ENCODING',
            'ENCRYPTION',
            'END',
            'END-EXEC',
            'ENDING',
            'ERASE',
            'ESCAPE',
            'EVERY',
            'EXCEPT',
            'EXCEPTION',
            'EXCLUDING',
            'EXCLUSIVE',
            'EXECUTE',
            'EXISTS',
            'EXIT',
            'EXPLAIN',
            'EXTERNAL',
            'EXTRACT',
            'FENCED',
            'FETCH',
            'FIELDPROC',
            'FILE',
            'FINAL',
            'FOR',
            'FOREIGN',
            'FREE',
            'FROM',
            'FULL',
            'FUNCTION',
            'GENERAL',
            'GENERATED',
            'GET',
            'GLOBAL',
            'GO',
            'GOTO',
            'GRANT',
            'GRAPHIC',
            'GROUP',
            'HANDLER',
            'HASH',
            'HASHED_VALUE',
            'HAVING',
            'HINT',
            'HOLD',
            'HOUR',
            'HOURS',
            'IDENTITY',
            'IF',
            'IMMEDIATE',
            'IN',
            'INCLUDING',
            'INCLUSIVE',
            'INCREMENT',
            'INDEX',
            'INDICATOR',
            'INF',
            'INFINITY',
            'INHERIT',
            'INNER',
            'INOUT',
            'INSENSITIVE',
            'INSERT',
            'INTEGRITY',
            'INTERSECT',
            'INTO',
            'IS',
            'ISOBID',
            'ISOLATION',
            'ITERATE',
            'JAR',
            'JAVA',
            'JOIN',
            'KEEP',
            'KEY',
            'LABEL',
            'LANGUAGE',
            'LATERAL',
            'LC_CTYPE',
            'LEAVE',
            'LEFT',
            'LIKE',
            'LINKTYPE',
            'LOCAL',
            'LOCALDATE',
            'LOCALE',
            'LOCALTIME',
            'LOCALTIMESTAMP RIGHT',
            'LOCATOR',
            'LOCATORS',
            'LOCK',
            'LOCKMAX',
            'LOCKSIZE',
            'LONG',
            'LOOP',
            'MAINTAINED',
            'MATERIALIZED',
            'MAXVALUE',
            'MICROSECOND',
            'MICROSECONDS',
            'MINUTE',
            'MINUTES',
            'MINVALUE',
            'MODE',
            'MODIFIES',
            'MONTH',
            'MONTHS',
            'NAN',
            'NEW',
            'NEW_TABLE',
            'NEXTVAL',
            'NO',
            'NOCACHE',
            'NOCYCLE',
            'NODENAME',
            'NODENUMBER',
            'NOMAXVALUE',
            'NOMINVALUE',
            'NONE',
            'NOORDER',
            'NORMALIZED',
            'NOT',
            'NULL',
            'NULLS',
            'NUMPARTS',
            'OBID',
            'OF',
            'OLD',
            'OLD_TABLE',
            'ON',
            'OPEN',
            'OPTIMIZATION',
            'OPTIMIZE',
            'OPTION',
            'OR',
            'ORDER',
            'OUT',
            'OUTER',
            'OVER',
            'OVERRIDING',
            'PACKAGE',
            'PADDED',
            'PAGESIZE',
            'PARAMETER',
            'PART',
            'PARTITION',
            'PARTITIONED',
            'PARTITIONING',
            'PARTITIONS',
            'PASSWORD',
            'PATH',
            'PIECESIZE',
            'PLAN',
            'POSITION',
            'PRECISION',
            'PREPARE',
            'PREVVAL',
            'PRIMARY',
            'PRIQTY',
            'PRIVILEGES',
            'PROCEDURE',
            'PROGRAM',
            'PSID',
            'PUBLIC',
            'QUERY',
            'QUERYNO',
            'RANGE',
            'RANK',
            'READ',
            'READS',
            'RECOVERY',
            'REFERENCES',
            'REFERENCING',
            'REFRESH',
            'RELEASE',
            'RENAME',
            'REPEAT',
            'RESET',
            'RESIGNAL',
            'RESTART',
            'RESTRICT',
            'RESULT',
            'RESULT_SET_LOCATOR WLM',
            'RETURN',
            'RETURNS',
            'REVOKE',
            'ROLE',
            'ROLLBACK',
            'ROUND_CEILING',
            'ROUND_DOWN',
            'ROUND_FLOOR',
            'ROUND_HALF_DOWN',
            'ROUND_HALF_EVEN',
            'ROUND_HALF_UP',
            'ROUND_UP',
            'ROUTINE',
            'ROW',
            'ROW_NUMBER',
            'ROWNUMBER',
            'ROWS',
            'ROWSET',
            'RRN',
            'RUN',
            'SAVEPOINT',
            'SCHEMA',
            'SCRATCHPAD',
            'SCROLL',
            'SEARCH',
            'SECOND',
            'SECONDS',
            'SECQTY',
            'SECURITY',
            'SELECT',
            'SENSITIVE',
            'SEQUENCE',
            'SESSION',
            'SESSION_USER',
            'SET',
            'SIGNAL',
            'SIMPLE',
            'SNAN',
            'SOME',
            'SOURCE',
            'SPECIFIC',
            'SQL',
            'SQLID',
            'STACKED',
            'STANDARD',
            'START',
            'STARTING',
            'STATEMENT',
            'STATIC',
            'STATMENT',
            'STAY',
            'STOGROUP',
            'STORES',
            'STYLE',
            'SUBSTRING',
            'SUMMARY',
            'SYNONYM',
            'SYSFUN',
            'SYSIBM',
            'SYSPROC',
            'SYSTEM',
            'SYSTEM_USER',
            'TABLE',
            'TABLESPACE',
            'THEN',
            'TIME',
            'TIMESTAMP',
            'TO',
            'TRANSACTION',
            'TRIGGER',
            'TRIM',
            'TRUNCATE',
            'TYPE',
            'UNDO',
            'UNION',
            'UNIQUE',
            'UNTIL',
            'UPDATE',
            'USAGE',
            'USER',
            'USING',
            'VALIDPROC',
            'VALUE',
            'VALUES',
            'VARGRAPHIC',
            'VARIABLE',
            'VARIANT',
            'VCAT',
            'VERSION',
            'VIEW',
            'VOLATILE',
            'VOLUMES',
            'WHEN',
            'WHENEVER',
            'WHERE',
            'WHILE',
            'WITH',
            'WITHOUT',
            'WRITE',
            'XMLELEMENT',
            'XMLEXISTS',
            'XMLNAMESPACES',
            'YEAR',
            'YEARS',
        ];
    }
}
