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

namespace Doctrine\DBAL\Schema;

/**
 * Schema manager for the Drizzle RDBMS.
 *
 * @author Kim Hemsø Rasmussen <kimhemsoe@gmail.com>
 */
class DrizzleSchemaManager extends AbstractSchemaManager
{
    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $tableName = $tableColumn['COLUMN_NAME'];
        $dbType = strtolower($tableColumn['DATA_TYPE']);

        $type = $this->_platform->getDoctrineTypeMapping($dbType);
        $type = $this->extractDoctrineTypeFromComment($tableColumn['COLUMN_COMMENT'], $type);
        $tableColumn['COLUMN_COMMENT'] = $this->removeDoctrineTypeFromComment($tableColumn['COLUMN_COMMENT'], $type);

        $options = array(
            'notnull' => !(bool)$tableColumn['IS_NULLABLE'],
            'length' => (int)$tableColumn['CHARACTER_MAXIMUM_LENGTH'],
            'default' => empty($tableColumn['COLUMN_DEFAULT']) ? null : $tableColumn['COLUMN_DEFAULT'],
            'autoincrement' => (bool)$tableColumn['IS_AUTO_INCREMENT'],
            'scale' => (int)$tableColumn['NUMERIC_SCALE'],
            'precision' => (int)$tableColumn['NUMERIC_PRECISION'],
            'comment' => (isset($tableColumn['COLUMN_COMMENT']) ? $tableColumn['COLUMN_COMMENT'] : null),
        );

        return new Column($tableName, \Doctrine\DBAL\Types\Type::getType($type), $options);
    }

    protected function _getPortableDatabaseDefinition($database)
    {
        return $database['SCHEMA_NAME'];
    }

    protected function _getPortableTableDefinition($table)
    {
        return $table['TABLE_NAME'];
    }

    public function _getPortableTableForeignKeyDefinition($tableForeignKey)
    {
        $columns = array();
        foreach (explode(',', $tableForeignKey['CONSTRAINT_COLUMNS']) as $value) {
            $columns[] = trim($value, ' `');
        }

        $ref_columns = array();
        foreach (explode(',', $tableForeignKey['REFERENCED_TABLE_COLUMNS']) as $value) {
            $ref_columns[] = trim($value, ' `');
        }

        return new ForeignKeyConstraint(
            $columns,
            $tableForeignKey['REFERENCED_TABLE_NAME'],
            $ref_columns,
            $tableForeignKey['CONSTRAINT_NAME'],
            array(
                'onUpdate' => $tableForeignKey['UPDATE_RULE'],
                'onDelete' => $tableForeignKey['DELETE_RULE'],
            )
        );
    }

    protected function _getPortableTableIndexesList($tableIndexes, $tableName = null)
    {
        $indexes = array();
        foreach ($tableIndexes as $k) {
            $k['primary'] = (boolean)$k['primary'];
            $indexes[] = $k;
        }

        return parent::_getPortableTableIndexesList($indexes, $tableName);
    }
}

