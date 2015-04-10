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
 * Schema manager for the Firebird.
 *
 * @author Andreas Prucha, Helicon Software Development <prucha@helicon.co.at>
 * @version     $Revision$
 */
class FirebirdSchemaManager extends \Doctrine\DBAL\Schema\AbstractSchemaManager
{

    const META_FIELD_TYPE_CHAR = 14;
    const META_FIELD_TYPE_VARCHAR = 37;
    const META_FIELD_TYPE_CSTRING = 40;
    const META_FIELD_TYPE_BLOB = 261;
    const META_FIELD_TYPE_DATE = 12;
    const META_FIELD_TYPE_TIME = 13;
    const META_FIELD_TYPE_TIMESTAMP = 35;
    const META_FIELD_TYPE_DOUBLE = 27;
    const META_FIELD_TYPE_FLOAT = 10;
    const META_FIELD_TYPE_INT64 = 16;
    const META_FIELD_TYPE_SHORT = 7;
    const META_FIELD_TYPE_LONG = 8;

    protected function _getPortableTableDefinition($table)
    {
        $table = \array_change_key_case($table, CASE_LOWER);

        return $this->getQuotedIdentifierName(trim($table['rdb$relation_name']));
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableViewDefinition($view)
    {
        $view = \array_change_key_case($view, CASE_LOWER);

        return new \Doctrine\DBAL\Schema\View($this->getQuotedIdentifierName(trim($view['rdb$relation_name'])), $this->getQuotedIdentifierName(trim($view['rdb$view_source'])));
    }

    protected function _getPortableUserDefinition($user)
    {
        return array(
            'user' => $user['User'],
            'password' => $user['Password'],
        );
    }

    /**
     * {@inheritdoc}
     * @todo Read current generator value
     */
    protected function _getPortableSequenceDefinition($sequence)
    {
        $sequence = \array_change_key_case($sequence, CASE_LOWER);

        return new Sequence($this->getQuotedIdentifierName(trim($sequence['rdb$generator_name'])), 1, 1);
    }

    protected function _getPortableDatabaseDefinition($database)
    {
        return $database['Database'];
    }

    /**
     * Returns the quoted identifier if necessary
     *
     * Firebird converts all nonquoted identifiers to uppercase, thus
     * all lower or mixed case identifiers get quoted here
     *
     * @param string $identifier Identifier
     *
     * @return string 
     */
    private function getQuotedIdentifierName($identifier)
    {
        if (preg_match('/[a-z]/', $identifier)) {
            return $this->_platform->quoteIdentifier($identifier);
        }

        return $identifier;
    }

    /**
     * Gets a portable column definition.
     *
     * The database type is mapped to a corresponding Doctrine mapping type.
     *
     * @param $tableColumn
     * @return array
     */
    protected function _getPortableTableColumnDefinition($tableColumn)
    {

        $options = array();

        $tableColumn = array_change_key_case($tableColumn, CASE_UPPER);

        $dbType = strtolower($tableColumn['FIELD_TYPE_NAME']);

        $type = array();
        $fixed = null;

        if (!isset($tableColumn['FIELD_NAME'])) {
            $tableColumn['FIELD_NAME'] = '';
        }
        $tableColumn['FIELD_NAME'] = strtolower($tableColumn['FIELD_NAME']);

        $scale = isset($tableColumn['FIELD_SCALE']) ? $tableColumn['FIELD_SCALE'] * -1 : null;
        $precision = $tableColumn['FIELD_PRECISION'];
        if ($tableColumn['FIELD_CHAR_LENGTH'] !== null) {
            $options['length'] = $tableColumn['FIELD_CHAR_LENGTH'];
        }

        $type = $this->_platform->getDoctrineTypeMapping($dbType);

        switch ($tableColumn['FIELD_TYPE']) {
            case self::META_FIELD_TYPE_CHAR: {
                    $fixed = true;
                    break;
                }
            case self::META_FIELD_TYPE_SHORT:
            case self::META_FIELD_TYPE_LONG:
            case self::META_FIELD_TYPE_INT64:
            case self::META_FIELD_TYPE_DOUBLE:
            case self::META_FIELD_TYPE_FLOAT: {
                    // Firebirds reflection of the datatype is quite "creative": If a numeric or decimal field is defined,
                    // the field-type reflects the internal datattype (e.g, and sub_type specifies, if decimal or numeric
                    // has been used. Thus, we need to override the datatype if necessary.
                    if ($tableColumn['FIELD_SUB_TYPE'] > 0) {
                        $type = 'decimal';
                    }
                    $options['length'] = null;
                    break;
                }
            case self::META_FIELD_TYPE_BLOB: {
                    switch ($tableColumn['FIELD_SUB_TYPE']) {
                        case 1: {
                                $type = 'text';
                                break;
                            }
                    }
                }
        }
        
        // Detect binary field by checking the characterset
        if ($tableColumn['CHARACTER_SET_NAME'] == 'OCTETS')
        {
            $type = 'binary';
        }

        // Override detected type if a type hint is specified

        $type = $this->extractDoctrineTypeFromComment($tableColumn['FIELD_DESCRIPTION'], $type);
        
        if ($tableColumn['FIELD_DESCRIPTION'] !== null) {
            $options['comment'] = $this->removeDoctrineTypeFromComment($tableColumn['FIELD_DESCRIPTION'], $type);
            if ($options['comment'] === '')
                $options['comment'] = null;
        }
        


        if (preg_match('/^.*default\s*\'(.*)\'\s*$/i', $tableColumn['FIELD_DEFAULT_SOURCE'], $matches)) {
            // default definition is a string
            $options['default'] = $matches[1];
        } else {
            if (preg_match('/^.*DEFAULT\s*(.*)\s*/i', $tableColumn['FIELD_DEFAULT_SOURCE'], $matches)) {
                // Default is numeric or a constant or a function
                $options['default'] = $matches[1];
                if (strtoupper(trim($options['default'])) == 'NULL') {
                    $options['default'] = null;
                } else {
                    // cannot handle other defaults at the moment - just ignore it for now
                }
            }
        }

        $options['notnull'] = (bool) $tableColumn['FIELD_NOT_NULL_FLAG'];

        $options = array_merge($options, array(
            'unsigned' => (bool) (strpos($dbType, 'unsigned') !== false),
            'fixed' => (bool) $fixed,
            'scale' => null,
            'precision' => null,
                )
        );

        if ($scale !== null && $precision !== null) {
            $options['scale'] = $scale;
            $options['precision'] = $precision;
        }

        return new \Doctrine\DBAL\Schema\Column($tableColumn['FIELD_NAME'], \Doctrine\DBAL\Types\Type::getType($type), $options);
    }

    protected function _getPortableTableForeignKeysList($tableForeignKeys)
    {
        $list = array();
        foreach ($tableForeignKeys as $key => $value) {
            $value = array_change_key_case($value, CASE_LOWER);
            if (!isset($list[$value['constraint_name']])) {
                if (!isset($value['on_delete']) || $value['on_delete'] == "RESTRICT") {
                    $value['on_delete'] = null;
                }
                if (!isset($value['on_update']) || $value['on_update'] == "RESTRICT") {
                    $value['on_update'] = null;
                }

                $list[$value['constraint_name']] = array(
                    'name' => $value['constraint_name'],
                    'local' => array(),
                    'foreign' => array(),
                    'foreignTable' => $value['references_table'],
                    'onDelete' => $value['on_delete'],
                    'onUpdate' => $value['on_update'],
                );
            }
            $list[$value['constraint_name']]['local'][] = $value['field_name'];
            $list[$value['constraint_name']]['foreign'][] = $value['references_field'];
        }

        $result = array();
        foreach ($list as $constraint) {
            $result[] = new \Doctrine\DBAL\Schema\ForeignKeyConstraint(
                    array_values($constraint['local']), $constraint['foreignTable'], array_values($constraint['foreign']), $constraint['name'], array(
                'onDelete' => $constraint['onDelete'],
                'onUpdate' => $constraint['onUpdate'],
                    )
            );
        }

        return $result;
    }

    protected function _getPortableTableIndexesList($tableIndexes, $tableName = null)
    {
        $mangledData = array();
        foreach ($tableIndexes as $tableIndex) {

            $tableIndex = \array_change_key_case($tableIndex, CASE_LOWER);
            
            if (!$tableIndex['foreign_key'])
            {

            $mangledItem = $tableIndex;

            $mangledItem['key_name'] = !empty($tableIndex['constraint_name']) ? $tableIndex['constraint_name'] : $tableIndex['index_name'];

            $mangledItem['non_unique'] = !(bool) $tableIndex['unique_flag'];

            $mangledItem['primary'] = ($tableIndex['constraint_type'] == 'PRIMARY KEY');

            if ($tableIndex['index_type']) {
                $mangledItem['options']['descending'] = true;
            }

            $mangledItem['column_name'] = $tableIndex['field_name'];

            $mangledData[] = $mangledItem;
            }
        }
        return parent::_getPortableTableIndexesList($mangledData, $tableName);
    }

    /**
     * {@inheritDoc} 
     * 
     * Firebird obviously has a serious problem with deadlocks if metadata updates are performed,
     * thus we try to commit as much as poosible here
     * 
     * @todo FIREBIRD: Look for better to avoid unnecessary commits
     */
    protected function _execSql($sql)
    {
        $wasInTransaction = $this->_conn->isTransactionActive();
        if ($wasInTransaction)
            $this->_conn->commit();

        foreach ((array) $sql as $query) {
            try {
                $this->_conn->beginTransaction();
                $this->_conn->executeUpdate($query);
                $this->_conn->commit();
            } catch (\Exception $exc) {
                $this->_conn->rollback();
                throw $exc;
            }
        }
        if ($wasInTransaction) {
            $this->_conn->beginTransaction();
        }
    }

}
