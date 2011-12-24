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
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\DBALException,
    Doctrine\DBAL\Schema\TableDiff,
    Doctrine\DBAL\Schema\Index,
    Doctrine\DBAL\Schema\Table;

/**
 * Drizzle platform
 * 
 * @author Kim Hemsø Rasmussen <kimhemsoe@gmail.com> 
 */
class DrizzlePlatform extends AbstractPlatform
{
    /**
     * Get the platform name for this instance.
     *
     * @return string
     */
    public function getName()
    {
        return 'drizzle';
    }

    /**
     * Gets the character used for identifier quoting.
     *
     * @return string
     * @override
     */
    public function getIdentifierQuoteCharacter()
    {
        return '`';
    }

    /**
     * @override
     */
    public function getBooleanTypeDeclarationSQL(array $field)
    {
        return 'BOOLEAN';
    }


    public function getIntegerTypeDeclarationSQL(array $field)
    {
        return 'INT' . $this->_getCommonIntegerTypeDeclarationSQL($field);
    }


    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef)
    {
        $autoinc = '';
        if ( ! empty($columnDef['autoincrement'])) {
            $autoinc = ' AUTO_INCREMENT';
        }
        return $autoinc;
    }

    public function getBigIntTypeDeclarationSQL(array $field)
    {
        return 'BIGINT' . $this->_getCommonIntegerTypeDeclarationSQL($field);
    }


    public function getSmallIntTypeDeclarationSQL(array $field)
    {
        return 'INT' . $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        return $length ? 'VARCHAR(' . $length . ')' : 'VARCHAR(255)';
    }

    protected function initializeDoctrineTypeMappings()
    {
        $this->doctrineTypeMapping = array(
            'boolean'       => 'boolean',
            'varchar'       => 'string',
            'integer'       => 'integer',
        );
    }

    public function getClobTypeDeclarationSQL(array $field)
    {
        return 'TEXT';
    }

    /**
     * Gets the SQL Snippet used to declare a BLOB column type.
     */
    public function getBlobTypeDeclarationSQL(array $field)
    {
        return 'BLOB';
    }

    public function getCreateDatabaseSQL($name)
    {
        return 'CREATE DATABASE ' . $name;
    }

    public function getDropDatabaseSQL($name)
    {
        return 'DROP DATABASE ' . $name;
    }

    public function getListDatabasesSQL()
    {
        return "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE CATALOG_NAME='LOCAL'";
    }

    protected function getReservedKeywordsClass()
    {
        return 'Doctrine\DBAL\Platforms\Keywords\DrizzleKeywords';
    }

    public function getListTablesSQL()
    {
        return "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE='BASE' AND TABLE_SCHEMA=DATABASE()";
    }

    public function getListTableColumnsSQL($table, $database = null)
    {
        if ($database) {
            $database = "'" . $database . "'";
        } else {
            $database = 'DATABASE()';
        }

        return "SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT, IS_NULLABLE, IS_AUTO_INCREMENT" .
               " FROM DATA_DICTIONARY.COLUMNS" .
               " WHERE TABLE_SCHEMA=" . $database . " AND TABLE_NAME = '" . $table . "'";
    }

    public function getListTableForeignKeysSQL($table, $database = null)
    {
        return "SELECT * FROM DATA_DICTIONARY.FOREIGN_KEYS";
    }

    public function getListTableIndexesSQL($table, $database = null)
    {
        return "SELECT * FROM DATA_DICTIONARY.INDEXES WHERE FALSE";
    }

    public function prefersIdentityColumns()
    {
        return true;
    }

    public function supportsIdentityColumns()
    {
        return true;
    }
}
