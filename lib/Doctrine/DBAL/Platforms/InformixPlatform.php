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

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Constraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;

/**
 * The InformixPlatform provides the behavior, features and SQL dialect of the
 * IBM Informix database platform.
 *
 * @author Jose M. Alonso M.  <josemalonsom@yahoo.es>
 * @link   www.doctrine-project.org
 */
class InformixPlatform extends AbstractPlatform
{

    /**
     * {@inheritDoc}
     */
    public function getBinaryTypeDeclarationSQL(array $field)
    {
        return 'BYTE';
    }

    /**
     * {@inheritDoc}
     */
    public function getBlobTypeDeclarationSQL(array $field)
    {
       /*
        The use of BLOB type in Informix is tricky and doesn't work properly
        with the pdo_informix extension so the BYTE type is used instead.
       */
        return 'BYTE';
    }

    /**
     * {@inheritDoc}
     */
    public function initializeDoctrineTypeMappings()
    {
        $this->doctrineTypeMapping = array(
            'bigint'            => 'bigint',
            'bigserial'         => 'bigint',
            'blob'              => 'blob',
            'boolean'           => 'boolean',
            'byte'              => 'blob',
            'char'              => 'string',
            'clob'              => 'text',
            'date'              => 'date',
            'datetime'          => 'datetime',
            'decimal'           => 'decimal',
            'float'             => 'float',
            'int8'              => 'bigint',
            'integer'           => 'integer',
            'lvarchar'          => 'text',
            'money'             => 'decimal',
            'nchar'             => 'string',
            'nvarchar'          => 'string',
            'serial8'           => 'bigint',
            'serial'            => 'integer',
            'smallfloat'        => 'float',
            'smallint'          => 'smallint',
            'text'              => 'text',
            'time'              => 'time',
            'varchar'           => 'string',
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        return $fixed
            ? 'CHAR(' . ($length ? : $this->getVarcharDefaultLength()) . ')'
            : 'VARCHAR(' . ($length ? : $this->getVarcharDefaultLength()) . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getClobTypeDeclarationSQL(array $field)
    {
        /*
         The use of CLOB type in Informix is tricky and doesn't work properly
         with the pdo_informix extension so the TEXT type is used instead.
        */
        return 'TEXT';
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'informix';
    }

    /**
     * {@inheritDoc}
     *
     * By default Informix doesn't support quoted identifiers, for this to
     * work you must enable the DELIMIDENT option in your Informix environment.
     *
     * @return string
     * @link http://www-01.ibm.com/support/knowledgecenter/SSGU8G_12.1.0/com.ibm.sqls.doc/ids_sqs_1667.htm
     */
    public function getIdentifierQuoteCharacter()
    {
        return '"';
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxIdentifierLength()
    {
        return 128;
    }

    /**
     * {@inheritDoc}
     */
    public function getEmptyIdentityInsertSQL($tableName, $identifierColumnName)
    {
        return 'INSERT INTO ' . $tableName . ' (' . $identifierColumnName . ') VALUES (0)';
    }

    /**
     * {@inheritDoc}
     */
    public function getVarcharMaxLength()
    {
        return 255;
    }

    /**
     * {@inheritDoc}
     */
    public function getMd5Expression($column)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    public function getNowExpression()
    {
        return 'TODAY';
    }

    /**
     * {@inheritDoc}
     */
    public function getNotExpression($expression)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    public function getPiExpression()
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    public function getDateDiffExpression($date1, $date2)
    {
        return $date1 . '::DATE - ' . $date2 . '::DATE';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateAddHourExpression($date, $hours)
    {
        return $date . ' + INTERVAL(' . $hours . ') HOUR(9) TO HOUR';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateSubHourExpression($date, $hours)
    {
        return $date . ' - INTERVAL(' . $hours . ') HOUR(9) TO HOUR';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateAddDaysExpression($date, $days)
    {
        return $date . ' + INTERVAL(' . $days . ') DAY(9) TO DAY';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateSubDaysExpression($date, $days)
    {
        return $date . ' - INTERVAL(' . $days . ') DAY(9) TO DAY';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateAddMonthExpression($date, $months)
    {
        return 'ADD_MONTHS(' . $date . ',' . $months . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateSubMonthExpression($date, $months)
    {
        return $this->getDateAddMonthExpression($date, -abs($months));
    }

    /**
     * {@inheritDoc}
     */
    public function getBitAndComparisonExpression($value1, $value2)
    {
        return 'BITAND(' . $value1 . ', ' . $value2 . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getBitOrComparisonExpression($value1, $value2)
    {
        return 'BITOR(' . $value1 . ', ' . $value2 . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getDropForeignKeySQL($foreignKey, $table)
    {
        if ( $foreignKey instanceof ForeignKeyConstraint ) {
            $foreignKey = $foreignKey->getQuotedName($this);
        }

        if ( $table instanceof Table ) {
            $table = $table->getQuotedName($this);
        }

        return 'ALTER TABLE ' . $table . ' DROP CONSTRAINT ' . $foreignKey;
    }

    /**
     * {@inheritDoc}
     * Informix don't support comments on columns
     */
    public function getCommentOnColumnSQL($tableName, $columnName, $comment)
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function getBooleanTypeDeclarationSQL(array $columnDef)
    {
        return 'BOOLEAN';
    }

    /**
     * {@inheritDoc}
     */
    public function getIntegerTypeDeclarationSQL(array $columnDef)
    {
        return empty($columnDef['autoincrement'])
            ? 'INTEGER'
            : 'SERIAL';
    }

    /**
     * {@inheritDoc}
     */
    public function getBigIntTypeDeclarationSQL(array $columnDef)
    {
        return empty($columnDef['autoincrement'])
            ? 'BIGINT'
            : 'BIGSERIAL';
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $columnDef)
    {
        return 'SMALLINT';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef)
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'DATETIME YEAR TO SECOND';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'DATE';
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'DATETIME HOUR TO SECOND';
    }

    /**
     * {@inheritDoc}
     */
    public function getListDatabasesSQL()
    {
        return 'SELECT sysmaster:sysdatabases.name FROM sysmaster:sysdatabases';
    }

    /**
     * {@inheritDoc}
     */
    public function getListSequencesSQL($database)
    {
        return 'SELECT st.tabname as sequence, ss.start_val, ss.inc_val '
            . 'FROM syssequences ss, systables st '
            . 'WHERE ss.tabid = st.tabid';
    }

    /**
     * {@inheritdoc}
     */
    public function getSequenceNextValSQL($sequenceName)
    {
        return 'SELECT ' . $sequenceName . '.NEXTVAL '
               . 'FROM systables WHERE tabid = 1';
    }

    /**
     * {@inheritDoc}
     */
    public function getListTableConstraintsSQL($table)
    {
        return 'SELECT '
            . 'sc.constrid, sc.constrname, sc.owner, sc.tabid, '
            . 'sc.constrtype, sc.idxname, sc.collation '
            . 'FROM systables st, sysconstraints sc WHERE '
            . 'st.tabname = \'' . $table . '\' '
            . 'AND st.tabid = sc.tabid';
    }

    /**
     * {@inheritDoc}
     *
     * @link http://pic.dhe.ibm.com/infocenter/idshelp/v115/topic/com.ibm.sqlr.doc/ids_sqr_025.htm
     * @link http://pic.dhe.ibm.com/infocenter/idshelp/v115/topic/com.ibm.sqlr.doc/ids_sqr_094.htm
     */
    public function getListTableColumnsSQL($table, $database = null)
    {

        return 'SELECT st.tabname, sc.colname, sc.colno, sc.coltype, '
            . 'sc.collength, sd.type typedefault, sd.default, '
            . 'CASE '
            . '    WHEN sc.coltype IN (0,256)  THEN \'char\' '
            . '    WHEN sc.coltype IN (1,257)  THEN \'smallint\' '
            . '    WHEN sc.coltype IN (2,258)  THEN \'integer\' '
            . '    WHEN sc.coltype IN (3,259)  THEN \'float\' '
            . '    WHEN sc.coltype IN (4,260)  THEN \'smallfloat\' '
            . '    WHEN sc.coltype IN (5,261)  THEN \'decimal\' '
            . '    WHEN sc.coltype IN (6,262)  THEN \'serial\' '
            . '    WHEN sc.coltype IN (7,263)  THEN \'date\' '
            . '    WHEN sc.coltype IN (8,264)  THEN \'money\' '
            . '    WHEN sc.coltype IN (9,265)  THEN \'null\' '
            . '    WHEN sc.coltype IN (10,266) THEN \'datetime\' '
            . '    WHEN sc.coltype IN (11,267) THEN \'byte\' '
            . '    WHEN sc.coltype IN (12,268) THEN \'text\' '
            . '    WHEN sc.coltype IN (13,269) THEN \'varchar\' '
            . '    WHEN sc.coltype IN (14,270) THEN \'interval\' '
            . '    WHEN sc.coltype IN (15,271) THEN \'nchar\' '
            . '    WHEN sc.coltype IN (16,272) THEN \'nvarchar\' '
            . '    WHEN sc.coltype IN (17,273) THEN \'int8\' '
            . '    WHEN sc.coltype IN (18,274) THEN \'serial8\' '
            . '    WHEN sc.coltype IN (19,275) THEN \'set\' '
            . '    WHEN sc.coltype IN (20,276) THEN \'multiset\' '
            . '    WHEN sc.coltype IN (21,277) THEN \'list\' '
            . '    WHEN sc.coltype IN (22,278) THEN \'row\' '
            . '    WHEN sc.coltype IN (23,279) THEN \'collection\' '
            . '    WHEN sc.coltype IN (43,299) THEN \'lvarchar\' '
            . '    WHEN sc.coltype IN (45,301) THEN \'boolean\' '
            . '    WHEN sc.coltype IN (52,308) THEN \'bigint\' '
            . '    WHEN sc.coltype IN (53,309) THEN \'bigserial\' '
            . '    ELSE '
            . '        CASE '
            . '            WHEN (sc.extended_id > 0) THEN '
            . '                (SELECT LOWER(name) FROM sysxtdtypes WHERE '
            . '                    extended_id = sc.extended_id) '
            . '            ELSE \'unknown\''
            . '        END '
            . 'END typename, '
            . 'CASE '
            . '    WHEN (sc.coltype IN (13,269,16,272)) THEN /* varchar and nvarchar */ '
            . '        CASE '
            . '            WHEN (sc.collength > 0) THEN MOD(sc.collength,256)::INT '
            . '            ELSE MOD(sc.collength+65536,256)::INT '
            . '        END '
            . '    ELSE '
            . '        NULL '
            . 'END maxlength, '
            . 'CASE '
            . '    WHEN (sc.coltype IN (13,269,16,272)) THEN /* varchar and nvarchar */ '
            . '        CASE '
            . '            WHEN (sc.collength > 0) THEN (sc.collength/256)::INT '
            . '            ELSE ((65536+sc.collength)/256)::INT '
            . '        END '
            . '    ELSE '
            . '        NULL '
            . 'END minlength, '
            . 'CASE '
            . '    WHEN (sc.coltype IN (5,261,8,264) AND (sc.collength / 256) >= 1) '
            . '        THEN (sc.collength / 256)::INT /* decimal and money */ '
            . '    ELSE '
            . '        NULL '
            . 'END precision, '
            . 'CASE '
            . '    WHEN (sc.coltype IN (5,261,8,264) AND (MOD(sc.collength, 256) <> 255)) '
            . '        THEN MOD(sc.collength, 256)::INT /* decimal and money */ '
            . '    ELSE '
            . '        NULL '
            . 'END scale, '
            . 'CASE  '
            . '    WHEN (sc.coltype < 256) THEN \'Y\' '
            . '    WHEN (sc.coltype BETWEEN 256 AND 309) THEN \'N\' '
            . '    ELSE '
            . '        NULL '
            . 'END nulls '
            . 'FROM systables st '
            . 'LEFT OUTER JOIN syscolumns sc ON st.tabid = sc.tabid '
            . 'LEFT OUTER JOIN sysdefaults sd ON (sc.tabid = sd.tabid AND sc.colno = sd.colno) '
            . 'WHERE UPPER(st.tabname) = UPPER(\'' . $table . '\')';
    }

    /**
     * {@inheritDoc}
     */
    public function getListTablesSQL()
    {
        return 'SELECT systables.tabname FROM systables WHERE tabtype = \'T\'';
    }

    /**
     * {@inheritDoc}
     */
    public function getListUsersSQL()
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    public function getListViewsSQL($database)
    {
        return 'SELECT '
            . 'systables.tabname viewname, sysviews.seqno, sysviews.viewtext '
            . 'FROM systables, sysviews '
            . 'WHERE systables.tabtype = \'V\' '
            . 'AND systables.tabid = sysviews.tabid '
            . 'ORDER BY systables.tabname ASC, sysviews.seqno ASC';

    }

    /**
     * {@inheritDoc}
     *
     * @link http://pic.dhe.ibm.com/infocenter/idshelp/v115/topic/com.ibm.sqlr.doc/ids_sqr_041.htm
     * @link http://pic.dhe.ibm.com/infocenter/idshelp/v115/topic/com.ibm.sqlr.doc/ids_sqr_029.htm
     */
    public function getListTableIndexesSQL($table, $currentDatabase = null)
    {
        /*
         * We list first the indexes created by the user excluding the
         * internal indexes created automaticly by Informix, then we
         * list the constraints that have indexes.
         */
        return 'SELECT st.tabname, si.idxname, si.idxtype, '
            . 'NULL::VARCHAR(128) constrname, NULL::CHAR(1) constrtype, '
            . 'sc1.colname  col1,  sc2.colname  col2,  sc3.colname  col3, '
            . 'sc4.colname  col4,  sc5.colname  col5,  sc6.colname  col6, '
            . 'sc7.colname  col7,  sc8.colname  col8,  sc9.colname  col9, '
            . 'sc10.colname col10, sc11.colname col11, sc12.colname col12, '
            . 'sc13.colname col13, sc14.colname col14, sc15.colname col15, '
            . 'sc16.colname col16 '
            . 'FROM  systables st '
            . 'INNER JOIN sysindexes si '
            . '    ON si.tabid = st.tabid '
            . 'LEFT OUTER JOIN syscolumns sc1 '
            . '    ON (ABS(si.part1)= sc1.colno AND si.tabid = sc1.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc2 '
            . '    ON (ABS(si.part2)= sc2.colno AND si.tabid = sc2.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc3 '
            . '    ON (ABS(si.part3)= sc3.colno AND si.tabid = sc3.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc4 '
            . '    ON (ABS(si.part4)= sc4.colno AND si.tabid = sc4.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc5 '
            . '    ON (ABS(si.part5)= sc5.colno AND si.tabid = sc5.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc6 '
            . '    ON (ABS(si.part6)= sc6.colno AND si.tabid = sc6.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc7 '
            . '    ON (ABS(si.part7)= sc7.colno AND si.tabid = sc7.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc8 '
            . '    ON (ABS(si.part8)= sc8.colno AND si.tabid = sc8.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc9 '
            . '    ON (ABS(si.part9)= sc9.colno AND si.tabid = sc9.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc10 '
            . '    ON (ABS(si.part10)= sc10.colno AND si.tabid = sc10.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc11 '
            . '    ON (ABS(si.part11)= sc11.colno AND si.tabid = sc11.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc12 '
            . '    ON (ABS(si.part12)= sc12.colno AND si.tabid = sc12.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc13 '
            . '    ON (ABS(si.part13)= sc13.colno AND si.tabid = sc13.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc14 '
            . '    ON (ABS(si.part14)= sc14.colno AND si.tabid = sc14.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc15 '
            . '    ON (ABS(si.part15)= sc15.colno AND si.tabid = sc15.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc16 '
            . '    ON (ABS(si.part16)= sc16.colno AND si.tabid = sc16.tabid) '
            . 'WHERE UPPER(st.tabname) = UPPER(\'' . $table . '\') '
            . 'AND si.idxname NOT LIKE \' \' || si.tabid || \'_%\' '
            . 'UNION '
            . 'SELECT st.tabname, si.idxname, si.idxtype, '
            . 'ctr.constrname, ctr.constrtype, '
            . 'sc1.colname  col1,  sc2.colname  col2,  sc3.colname  col3, '
            . 'sc4.colname  col4,  sc5.colname  col5,  sc6.colname  col6, '
            . 'sc7.colname  col7,  sc8.colname  col8,  sc9.colname  col9, '
            . 'sc10.colname col10, sc11.colname col11, sc12.colname col12, '
            . 'sc13.colname col13, sc14.colname col14, sc15.colname col15, '
            . 'sc16.colname col16 '
            . 'FROM  systables st '
            . 'INNER JOIN sysconstraints ctr '
            . '    ON (ctr.tabid = st.tabid) '
            . 'LEFT OUTER JOIN sysindexes si '
            . '    ON (si.tabid = ctr.tabid AND si.idxname = ctr.idxname) '
            . 'LEFT OUTER JOIN syscolumns sc1 '
            . '    ON (ABS(si.part1)= sc1.colno AND si.tabid = sc1.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc2 '
            . '    ON (ABS(si.part2)= sc2.colno AND si.tabid = sc2.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc3 '
            . '    ON (ABS(si.part3)= sc3.colno AND si.tabid = sc3.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc4 '
            . '    ON (ABS(si.part4)= sc4.colno AND si.tabid = sc4.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc5 '
            . '    ON (ABS(si.part5)= sc5.colno AND si.tabid = sc5.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc6 '
            . '    ON (ABS(si.part6)= sc6.colno AND si.tabid = sc6.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc7 '
            . '    ON (ABS(si.part7)= sc7.colno AND si.tabid = sc7.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc8 '
            . '    ON (ABS(si.part8)= sc8.colno AND si.tabid = sc8.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc9 '
            . '    ON (ABS(si.part9)= sc9.colno AND si.tabid = sc9.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc10 '
            . '    ON (ABS(si.part10)= sc10.colno AND si.tabid = sc10.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc11 '
            . '    ON (ABS(si.part11)= sc11.colno AND si.tabid = sc11.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc12 '
            . '    ON (ABS(si.part12)= sc12.colno AND si.tabid = sc12.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc13 '
            . '    ON (ABS(si.part13)= sc13.colno AND si.tabid = sc13.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc14 '
            . '    ON (ABS(si.part14)= sc14.colno AND si.tabid = sc14.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc15 '
            . '    ON (ABS(si.part15)= sc15.colno AND si.tabid = sc15.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc16 '
            . '    ON (ABS(si.part16)= sc16.colno AND si.tabid = sc16.tabid) '
            . 'WHERE UPPER(st.tabname) = UPPER(\'' . $table . '\') '
            . 'AND ctr.idxname IS NOT NULL ';
    }

    /**
     * {@inheritDoc}
     *
     * The SQL sentence used is based on the next thread:
     * {@link http://www.databaseteam.org/6-informix/8791d9fcbeab8020.htm}.
     */
    public function getListTableForeignKeysSQL($table)
    {

        return 'SELECT st.tabname, sc.constrname, sr.updrule, sr.delrule, '
            . 'refst.tabname reftabname, refsc.idxname refconstrname, '
            . 'sc1.colname  col1,  sc2.colname  col2,  sc3.colname  col3,  '
            . 'sc4.colname  col4,  sc5.colname  col5,  sc6.colname  col6,  '
            . 'sc7.colname  col7,  sc8.colname  col8,  sc9.colname  col9,  '
            . 'sc10.colname col10, sc11.colname col11, sc12.colname col12, '
            . 'sc13.colname col13, sc14.colname col14, sc15.colname col15, '
            . 'sc16.colname col16, '
            . 'pksc1.colname  pkcol1,  pksc2.colname  pkcol2,  pksc3.colname  pkcol3,  '
            . 'pksc4.colname  pkcol4,  pksc5.colname  pkcol5,  pksc6.colname  pkcol6,  '
            . 'pksc7.colname  pkcol7,  pksc8.colname  pkcol8,  pksc9.colname  pkcol9,  '
            . 'pksc10.colname pkcol10, pksc11.colname pkcol11, pksc12.colname pkcol12, '
            . 'pksc13.colname pkcol13, pksc14.colname pkcol14, pksc15.colname pkcol15, '
            . 'pksc16.colname pkcol16 '
            . 'FROM systables st '
            . 'INNER JOIN sysconstraints sc '
            . '    ON st.tabid = sc.tabid '
            . 'INNER JOIN sysreferences sr '
            . '    ON sc.constrid = sr.constrid '
            . 'INNER JOIN systables refst '
            . '    ON sr.ptabid = refst.tabid '
            . 'INNER JOIN sysindexes si '
            . '    ON sc.idxname = si.idxname '
            . 'INNER JOIN sysconstraints refsc '
            . '    ON sr.primary = refsc.constrid '
            . 'INNER JOIN sysindexes refsi '
            . '    ON refsc.idxname = refsi.idxname '
            . 'LEFT OUTER JOIN syscolumns sc1 '
            . '    ON (ABS(si.part1)= sc1.colno AND si.tabid = sc1.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc2 '
            . '    ON (ABS(si.part2)= sc2.colno AND si.tabid = sc2.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc3 '
            . '    ON (ABS(si.part3)= sc3.colno AND si.tabid = sc3.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc4 '
            . '    ON (ABS(si.part4)= sc4.colno AND si.tabid = sc4.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc5 '
            . '    ON (ABS(si.part5)= sc5.colno AND si.tabid = sc5.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc6 '
            . '    ON (ABS(si.part6)= sc6.colno AND si.tabid = sc6.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc7 '
            . '    ON (ABS(si.part7)= sc7.colno AND si.tabid = sc7.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc8 '
            . '    ON (ABS(si.part8)= sc8.colno AND si.tabid = sc8.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc9 '
            . '    ON (ABS(si.part9)= sc9.colno AND si.tabid = sc9.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc10 '
            . '    ON (ABS(si.part10)= sc10.colno AND si.tabid = sc10.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc11 '
            . '    ON (ABS(si.part11)= sc11.colno AND si.tabid = sc11.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc12 '
            . '    ON (ABS(si.part12)= sc12.colno AND si.tabid = sc12.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc13 '
            . '    ON (ABS(si.part13)= sc13.colno AND si.tabid = sc13.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc14 '
            . '    ON (ABS(si.part14)= sc14.colno AND si.tabid = sc14.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc15 '
            . '    ON (ABS(si.part15)= sc15.colno AND si.tabid = sc15.tabid) '
            . 'LEFT OUTER JOIN syscolumns sc16 '
            . '    ON (ABS(si.part16)= sc16.colno AND si.tabid = sc16.tabid) '
            . 'LEFT OUTER JOIN syscolumns pksc1 '
            . '    ON (ABS(refsi.part1)= pksc1.colno AND refsi.tabid = pksc1.tabid)'
            . 'LEFT OUTER JOIN syscolumns pksc2 '
            . '    ON (ABS(refsi.part2)= pksc2.colno AND refsi.tabid = pksc2.tabid)'
            . 'LEFT OUTER JOIN syscolumns pksc3 '
            . '    ON (ABS(refsi.part3)= pksc3.colno AND refsi.tabid = pksc3.tabid)'
            . 'LEFT OUTER JOIN syscolumns pksc4 '
            . '    ON (ABS(refsi.part4)= pksc4.colno AND refsi.tabid = pksc4.tabid)'
            . 'LEFT OUTER JOIN syscolumns pksc5 '
            . '    ON (ABS(refsi.part5)= pksc5.colno AND refsi.tabid = pksc5.tabid)'
            . 'LEFT OUTER JOIN syscolumns pksc6 '
            . '    ON (ABS(refsi.part6)= pksc6.colno AND refsi.tabid = pksc6.tabid)'
            . 'LEFT OUTER JOIN syscolumns pksc7 '
            . '    ON (ABS(refsi.part7)= pksc7.colno AND refsi.tabid = pksc7.tabid)'
            . 'LEFT OUTER JOIN syscolumns pksc8 '
            . '    ON (ABS(refsi.part8)= pksc8.colno AND refsi.tabid = pksc8.tabid)'
            . 'LEFT OUTER JOIN syscolumns pksc9 '
            . '    ON (ABS(refsi.part9)= pksc9.colno AND refsi.tabid = pksc9.tabid)'
            . 'LEFT OUTER JOIN syscolumns pksc10 '
            . '    ON (ABS(refsi.part10)= pksc10.colno AND refsi.tabid = pksc10.tabid)'
            . 'LEFT OUTER JOIN syscolumns pksc11 '
            . '    ON (ABS(refsi.part11)= pksc11.colno AND refsi.tabid = pksc11.tabid)'
            . 'LEFT OUTER JOIN syscolumns pksc12 '
            . '    ON (ABS(refsi.part12)= pksc12.colno AND refsi.tabid = pksc12.tabid)'
            . 'LEFT OUTER JOIN syscolumns pksc13 '
            . '    ON (ABS(refsi.part13)= pksc13.colno AND refsi.tabid = pksc13.tabid)'
            . 'LEFT OUTER JOIN syscolumns pksc14 '
            . '    ON (ABS(refsi.part14)= pksc14.colno AND refsi.tabid = pksc14.tabid)'
            . 'LEFT OUTER JOIN syscolumns pksc15 '
            . '    ON (ABS(refsi.part15)= pksc15.colno AND refsi.tabid = pksc15.tabid)'
            . 'LEFT OUTER JOIN syscolumns pksc16 '
            . '    ON (ABS(refsi.part16)= pksc16.colno AND refsi.tabid = pksc16.tabid)'
            . 'WHERE '
            . 'UPPER(st.tabname) = UPPER(\'' . $table . '\') '
            . 'AND sc.constrtype = \'R\' ';

    }

    /**
     * {@inheritDoc}
     */
    public function getCreateViewSQL($name, $sql)
    {
        return "CREATE VIEW ".$name." AS ".$sql;
    }

    /**
     * {@inheritDoc}
     */
    public function getDropViewSQL($name)
    {
        return "DROP VIEW ".$name;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateDatabaseSQL($database)
    {
        return "CREATE DATABASE ".$database." WITH LOG";
    }

    /**
     * {@inheritDoc}
     */
    public function supportsCreateDropDatabase()
    {
        return false;
    }

    /**
     * Converts the boolean values to the string representation used in Informix.
     *
     * - false => 'f'
     * - true  => 't'
     *
     * @param mixed $item
     *
     * @return mixed
     */
    public function convertBooleans($item)
    {
        if (is_array($item)) {
            foreach ($item as $k => $value) {
                if (is_bool($value)) {
                    $item[$k] = $value === true ? 't' : 'f';
                }
            }
        } elseif (is_bool($item)) {
            $item = $item === true ? 't' : 'f';
        }

        return $item;
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentDateSQL()
    {
        return 'TODAY';
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentTimeSQL()
    {
        return 'CURRENT HOUR TO SECOND';
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentTimestampSQL()
    {
        return 'CURRENT YEAR TO SECOND';
    }

    /**
     * {@inheritDoc}
     */
    public function getIndexDeclarationSQL($name, Index $index)
    {
        // Index declaration in statements like CREATE TABLE is not supported.
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL($tableName, array $columns, array $options = array())
    {
        /*
         * Informix creates an automatic index in ascending order for the
         * unique, primary-key and referencial constraints. If you try to 
         * create an specific index in the same column or columns that fits
         * with the automatic index the database server returns an error. When
         * the index exists before the creation of the constraint, if is
         * possible, it's shared and no error is return, so it's important
         * that the indexes are created before the foreign key constraints.
         */

        $indexes = isset($options['indexes']) ? $options['indexes']
            : array();

        $options['indexes'] = array();

        $foreignKeys = isset($options['foreignKeys']) ? $options['foreignKeys']
            : array();

        $options['foreignKeys'] = array();

        $sqls = parent::_getCreateTableSQL($tableName, $columns, $options);

        foreach ( $indexes as $definition ) {
            $sqls[] = $this->getCreateIndexSQL($definition, $tableName);
        }

        foreach ( $foreignKeys as $definition ) {
            $sqls[] = $this->getCreateForeignKeySQL($definition, $tableName);
        }

        return $sqls;
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterTableSQL(TableDiff $diff)
    {
        $sql = array();
        $columnSql = array();

        $queryParts = array();

        foreach ( $diff->addedColumns as $column ) {

            if ( $this->onSchemaAlterTableAddColumn($column, $diff, $columnSql) ) {
                continue;
            }

            $queryParts[] = 'ADD ' . $this->getColumnDeclarationSQL($column->getQuotedName($this), $column->toArray());
        }

        foreach ( $diff->removedColumns as $column ) {

            if ( $this->onSchemaAlterTableRemoveColumn($column, $diff, $columnSql) ) {
                continue;
            }

            $queryParts[] =  'DROP ' . $column->getQuotedName($this);
        }

        foreach ( $diff->changedColumns as $columnDiff ) {

            if ( $this->onSchemaAlterTableChangeColumn($columnDiff, $diff, $columnSql) ) {
                continue;
            }

            /* @var $columnDiff \Doctrine\DBAL\Schema\ColumnDiff */
            $column = $columnDiff->column;

            if ( $columnDiff->oldColumnName != $column->getName() ) {

              $sql[] = $this->getRenameColumnSQL(
                  $diff->name, $columnDiff->oldColumnName, $column
              );

            }

            $queryParts[] =  'MODIFY '
                . $this->getColumnDeclarationSQL($column->getQuotedName($this), $column->toArray());
        }

        foreach ( $diff->renamedColumns as $oldColumnName => $column ) {

            if ( $this->onSchemaAlterTableRenameColumn($oldColumnName, $column, $diff, $columnSql) ) {
                continue;
            }

            $sql[] = $this->getRenameColumnSQL($diff->name, $oldColumnName, $column);
        }

        $tableSql = array();

        if ( ! $this->onSchemaAlterTable($diff, $tableSql) ) {

            if ( count($queryParts) > 0 ) {
                $sql[] = 'ALTER TABLE ' . $diff->name . ' ' . implode(", ", $queryParts);
            }

            $sql = array_merge($sql, $this->_getAlterTableIndexForeignKeySQL($diff));

            if ( $diff->newName !== false ) {
                $sql[] =  'RENAME TABLE ' . $diff->name . ' TO ' . $diff->newName;
            }
        }

        return array_merge($sql, $tableSql, $columnSql);
    }

    /**
     * {@inheritDoc}
     */
    protected function getPostAlterTableIndexForeignKeySQL(TableDiff $diff)
    {
      $tableName = (false !== $diff->newName)
          ? $diff->getNewName()->getQuotedName($this)
          : $diff->getName()->getQuotedName($this);

      $sql = array();

      foreach ( $diff->addedIndexes as $index ) {
          $sql[] = $this->getCreateIndexSQL($index, $tableName);
      }

      $diff->addedIndexes = array();

      foreach ( $diff->changedIndexes as $index ) {
          $sql[] = $this->getCreateIndexSQL($index, $tableName);
      }

      $diff->changedIndexes = array();

      $sql = array_merge($sql, parent::getPostAlterTableIndexForeignKeySQL($diff));

      return $sql;

    }

    /**
     * {@inheritDoc}
     */
    public function getCreateTemporaryTableSnippetSQL()
    {
        return 'CREATE ' . $this->getTemporaryTableSQL() . ' TABLE';
    }

    /**
     * {@inheritDoc}
     */
    public function getTemporaryTableSQL()
    {
        return 'TEMP';
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateSequenceSQL(Sequence $sequence)
    {
        return 'CREATE SEQUENCE ' . $sequence->getQuotedName($this)
            . ' START WITH ' . $sequence->getInitialValue()
            . ' INCREMENT BY ' . $sequence->getAllocationSize()
            . ' MINVALUE ' . $sequence->getInitialValue();
    }

    /**
     * {@inheritdoc}
     */
    public function getAlterSequenceSQL(Sequence $sequence)
    {
        return 'ALTER SEQUENCE ' . $sequence->getQuotedName($this)
            . ' INCREMENT BY ' . $sequence->getAllocationSize();
    }

    /**
     * {@inheritdoc}
     */
    public function getDropSequenceSQL($sequence)
    {
        if ( $sequence instanceof Sequence ) {
            $sequence = $sequence->getQuotedName($this);
        }

        return 'DROP SEQUENCE ' . $sequence;
    }

    /**
     * {@inheritDoc}
     */
    protected function doModifyLimitQuery($query, $limit, $offset = null)
    {
        if ( $limit === null && $offset === null ) {
            return $query;
        }

        $snippet  = $offset ? "SKIP $offset " : "";
        $snippet .= $limit  ? "LIMIT $limit " : "";

        $sql = preg_replace('/SELECT\s+/i', 'SELECT ' . $snippet, $query, 1);

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsSequences()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsIdentityColumns()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function prefersIdentityColumns()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     *
     * Informix returns all column names in SQL result sets in uppercase.
     */
    public function getSQLResultCasing($column)
    {
        return strtoupper($column);
    }

    /**
     * {@inheritDoc}
     */
    public function getDummySelectSQL()
    {
        return 'SELECT 1 FROM SYSTABLES WHERE TABID = 1';
    }

    /**
     * {@inheritDoc}
     */
    protected function getReservedKeywordsClass()
    {
        return 'Doctrine\DBAL\Platforms\Keywords\InformixKeywords';
    }

    /**
     * {@inheritDoc}
     */
    public function getUniqueConstraintDeclarationSQL($name, Index $index)
    {
        $columns = $index->getQuotedColumns($this);

        if ( count($columns) === 0 ) {
            throw new \InvalidArgumentException("Incomplete definition. 'columns' required.");
        }

        return ' UNIQUE (' . $this->getIndexFieldDeclarationListSQL($columns)
            . ') CONSTRAINT ' . $name;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateConstraintSQL(Constraint $constraint, $table)
    {

        $constraintSql = parent::getCreateConstraintSQL($constraint, $table);

        return $this->repositionContraintNameSQL($constraint, $constraintSql);

    }

    /**
     * {@inheritDoc}
     */
    public function getCreatePrimaryKeySQL(Index $index, $table)
    {
        $sql = 'ALTER TABLE ' . $table . ' ADD CONSTRAINT PRIMARY KEY ('
            . $this->getIndexFieldDeclarationListSQL($index->getQuotedColumns($this)) . ')';

        if ($index->getName()) {
            $sql .= ' CONSTRAINT ' . $index->getQuotedName($this);
        }

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function getForeignKeyBaseDeclarationSQL(ForeignKeyConstraint $foreignKey)
    {

        $foreignKeySql = parent::getForeignKeyBaseDeclarationSQL($foreignKey);

        return $this->repositionContraintNameSQL($foreignKey, $foreignKeySql);

    }

    /**
     * {@inheritDoc}
     */
    public function getCreateForeignKeySQL(ForeignKeyConstraint $foreignKey, $table)
    {
        if ( $table instanceof Table ) {
            $table = $table->getQuotedName($this);
        }

        $query = 'ALTER TABLE ' . $table . ' ADD CONSTRAINT '
               . $this->getForeignKeyDeclarationSQL($foreignKey);

        return $query;
    }

    /**
     * {@inheritDoc}
     */
    public function getForeignKeyDeclarationSQL(ForeignKeyConstraint $foreignKey)
    {
        $foreignKeySql = parent::getForeignKeyDeclarationSQL($foreignKey);

        return $this->repositionContraintNameSQL($foreignKey, $foreignKeySql);

    }

    /**
    * Repositions the declaration of the name of the constraint.
    *
    * In Informix the name of the constraint is placed at the end of the
    * declaration.
    *
    * @param \Doctrine\DBAL\Schema\Constraint $constraint
    * @param string $sql
    *
    * @return string
    */
    protected function repositionContraintNameSQL(Constraint $constraint, $sql) {

        if ( $constraintName = $constraint->getName() ) {

            if ( preg_match("/\bADD\s+CONSTRAINT\b/i", $sql) ) {

                $sql = preg_replace("/\s*\bADD\s+CONSTRAINT\s+$constraintName\b\s*/i",
                                    ' ADD CONSTRAINT ', $sql);
            }
            else {

                $sql = preg_replace("/\s*\bCONSTRAINT\s+$constraintName\b\s*/i", '', $sql);
            }

            $sql .= ' CONSTRAINT ' . $constraintName;

        }

        return $sql;

    }

    /**
     * Gets the SQL to rename a column.
     *
     * @param string table that contains the column
     * @param string old column name
     * @param Column new column
     */
    protected function getRenameColumnSQL($tableName, $oldName, Column $column)
    {
        return 'RENAME COLUMN ' . $tableName . '.' . $oldName
            . ' TO ' . $column->getQuotedName($this);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDateArithmeticIntervalExpression($date, $operator, $interval, $unit)
    {
        $timeUnit = '';

        switch($unit) {
            case self::DATE_INTERVAL_UNIT_SECOND:
                $timeUnit = 'SECOND';
                break;

            case self::DATE_INTERVAL_UNIT_MINUTE:
                $timeUnit = 'MINUTE';
                break;

            case self::DATE_INTERVAL_UNIT_HOUR:
                $timeUnit = 'HOUR';
                break;

            case self::DATE_INTERVAL_UNIT_DAY:
                $timeUnit = 'DAY';
                break;

            case self::DATE_INTERVAL_UNIT_WEEK:
                $timeUnit = 'DAY';
                $interval *= 7;
                break;

            case self::DATE_INTERVAL_UNIT_MONTH:
                $timeUnit = 'MONTH';
                break;

            case self::DATE_INTERVAL_UNIT_QUARTER:
                $timeUnit = 'MONTH';
                $interval *= 3;
                break;

            case self::DATE_INTERVAL_UNIT_YEAR:
                $timeUnit = 'YEAR';
                break;
        }

        return "$date $operator INTERVAL ($interval)  $timeUnit(9) TO $timeUnit";
    }
}
