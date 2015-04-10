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

namespace Doctrine\DBAL\Platforms\Keywords;

/**
 * Firebird Keywordlist
 *
 * @license  BSD http://www.opensource.org/licenses/bsd-license.php
 * @author      Andreas Prucha <prucha@helicon.co.at>
 */
class FirebirdKeywords extends \Doctrine\DBAL\Platforms\Keywords\KeywordList
{

    public function getName()
    {
        return 'Firebird';
    }

    protected function getKeywords()
    {
        return array(
            'ADD',
            'ADMIN',
            'ALL',
            'ALTER',
            'AND',
            'ANY',
            'AS',
            'AT',
            'AVG',
            'BEGIN',
            'BETWEEN',
            'BIGINT',
            'BIT_LENGTH',
            'BLOB',
            'BOTH',
            'BY',
            'CASE',
            'CAST',
            'CHAR',
            'CHAR_LENGTH',
            'CHARACTER',
            'CHARACTER_LENGTH',
            'CHECK',
            'CLOSE',
            'COLLATE',
            'COLUMN',
            'COMMIT',
            'CONNECT',
            'CONSTRAINT',
            'COUNT',
            'CREATE',
            'CROSS',
            'CURRENT',
            'CURRENT_CONNECTION',
            'CURRENT_DATE',
            'CURRENT_ROLE',
            'CURRENT_TIME',
            'CURRENT_TIMESTAMP',
            'CURRENT_TRANSACTION',
            'CURRENT_USER',
            'CURSOR',
            'DATE',
            'DAY',
            'DEC',
            'DECIMAL',
            'DECLARE',
            'DEFAULT',
            'DELETE',
            'DISCONNECT',
            'DISTINCT',
            'DOUBLE',
            'DROP',
            'ELSE',
            'END',
            'ESCAPE',
            'EXECUTE',
            'EXISTS',
            'EXTERNAL',
            'EXTRACT',
            'FETCH',
            'FILTER',
            'FLOAT',
            'FOR',
            'FOREIGN',
            'FROM',
            'FULL',
            'FUNCTION',
            'GDSCODE',
            'GLOBAL',
            'GRANT',
            'GROUP',
            'HAVING',
            'HOUR',
            'IN',
            'INDEX',
            'INNER',
            'INSENSITIVE',
            'INSERT',
            'INT',
            'INTEGER',
            'INTO',
            'IS',
            'JOIN',
            'LEADING',
            'LEFT',
            'LIKE',
            'LONG',
            'LOWER',
            'MAX',
            'MAXIMUM_SEGMENT',
            'MERGE',
            'MIN',
            'MINUTE',
            'MONTH',
            'NATIONAL',
            'NATURAL',
            'NCHAR',
            'NO',
            'NOT',
            'NULL',
            'NUMERIC',
            'OCTET_LENGTH',
            'OF',
            'ON',
            'ONLY',
            'OPEN',
            'OR',
            'ORDER',
            'OUTER',
            'PARAMETER',
            'PLAN',
            'POSITION',
            'POST_EVENT',
            'PRECISION',
            'PRIMARY',
            'PROCEDURE',
            'RDB$DB_KEY',
            'REAL',
            'RECORD_VERSION',
            'RECREATE',
            'RECURSIVE',
            'REFERENCES',
            'RELEASE',
            'RETURNING_VALUES',
            'RETURNS',
            'REVOKE',
            'RIGHT',
            'ROLLBACK',
            'ROW_COUNT',
            'ROWS',
            'SAVEPOINT',
            'SECOND',
            'SELECT',
            'SENSITIVE',
            'SET',
            'SIMILAR',
            'SMALLINT',
            'SOME',
            'SQLCODE',
            'SQLSTATE',
            'START',
            'SUM',
            'TABLE',
            'THEN',
            'TIME',
            'TIMESTAMP',
            'TO',
            'TRAILING',
            'TRIGGER',
            'TRIM',
            'UNION',
            'UNIQUE',
            'UPDATE',
            'UPPER',
            'USER',
            'USING',
            'VALUE',
            'VALUES',
            'VARCHAR',
            'VARIABLE',
            'VARYING',
            'VIEW',
            'WHEN',
            'WHERE',
            'WHILE',
            'WITH',
            'YEAR');
    }

}
