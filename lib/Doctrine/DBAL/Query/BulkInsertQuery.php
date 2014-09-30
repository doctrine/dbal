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

namespace Doctrine\DBAL\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Identifier;

/**
 * Provides functionality to generate and execute bulk INSERT statements.
 *
 * Intended for row based inserts, not from SELECT statements.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
class BulkInsertQuery
{
    /**
     * @var array
     */
    private $columns;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Identifier
     */
    private $table;

    /**
     * @var array
     */
    private $parameters = array();

    /**
     * @var array
     */
    private $types = array();

    /**
     * @var array
     */
    private $values = array();

    /**
     * Constructor.
     *
     * @param Connection $connection The connection to use for query execution.
     * @param string     $table      The name of the table to insert rows into.
     * @param array      $columns    The names of the columns to insert values into.
     *                               Can be left empty to allow arbitrary table row inserts
     *                               based on the table's column order.
     */
    public function __construct(Connection $connection, $table, array $columns = array())
    {
        $this->connection = $connection;
        $this->table = new Identifier($table);
        $this->columns = $columns;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->getSQL();
    }

    /**
     * Adds a set of values to the bulk insert query to be inserted as a row into the specified table.
     *
     * @param array $values The set of values to be inserted as a row into the table.
     *                      If no columns have been specified for insertion, this can be
     *                      an arbitrary list of values to be inserted into the table.
     *                      Otherwise the values' keys have to match either one of the
     *                      specified column names or indexes.
     * @param array $types  The types for the given values to bind to the query.
     *                      If no columns have been specified for insertion, the types'
     *                      keys will be matched against the given values' keys.
     *                      Otherwise the types' keys will be matched against the
     *                      specified column names and indexes.
     *                      Non-matching keys will be discarded, missing keys will not
     *                      be bound to a specific type.
     *
     * @throws \InvalidArgumentException if columns were specified for this query
     *                                   and either no value for one of the specified
     *                                   columns is given or multiple values are given
     *                                   for a single column (named and indexed) or
     *                                   multiple types are given for a single column
     *                                   (named and indexed).
     *
     * @todo add support for expressions.
     */
    public function addValues(array $values, array $types = array())
    {
        $valueSet = array();

        if (empty($this->columns)) {
            foreach ($values as $index => $value) {
                $this->parameters[] = $value; // todo: allow expressions.
                $this->types[] = isset($types[$index]) ? $types[$index] : null;
                $valueSet[] = '?'; // todo: allow expressions.
            }

            $this->values[] = $valueSet;

            return;
        }

        foreach ($this->columns as $index => $column) {
            $namedValue = isset($values[$column]) || array_key_exists($column, $values);
            $positionalValue = isset($values[$index]) || array_key_exists($index, $values);

            if ( ! $namedValue && ! $positionalValue) {
                throw new \InvalidArgumentException(
                    sprintf('No value specified for column %s (index %d).', $column, $index)
                );
            }

            if ($namedValue && $positionalValue && $values[$column] !== $values[$index]) {
                throw new \InvalidArgumentException(
                    sprintf('Multiple values specified for column %s (index %d).', $column, $index)
                );
            }

            $this->parameters[] = $namedValue ? $values[$column] : $values[$index]; // todo: allow expressions.
            $valueSet[] = '?'; // todo: allow expressions.

            $namedType = isset($types[$column]);
            $positionalType = isset($types[$index]);

            if ($namedType && $positionalType && $types[$column] !== $types[$index]) {
                throw new \InvalidArgumentException(
                    sprintf('Multiple types specified for column %s (index %d).', $column, $index)
                );
            }

            if ($namedType) {
                $this->types[] = $types[$column];

                continue;
            }

            if ($positionalType) {
                $this->types[] = $types[$index];

                continue;
            }

            $this->types[] = null;
        }

        $this->values[] = $valueSet;
    }

    /**
     * Executes this INSERT query using the bound parameters and their types.
     *
     * @return integer The number of affected rows.
     *
     * @throws \LogicException if this query contains more rows than acceptable
     *                         for a single INSERT statement by the underlying platform.
     */
    public function execute()
    {
        $platform = $this->connection->getDatabasePlatform();
        $insertMaxRows = $platform->getInsertMaxRows();

        if ($insertMaxRows > 0 && count($this->values) > $insertMaxRows) {
            throw new \LogicException(
                sprintf(
                    'You can only insert %d rows in a single INSERT statement with platform "%s".',
                    $insertMaxRows,
                    $platform->getName()
                )
            );
        }

        return $this->connection->executeUpdate($this->getSQL(), $this->parameters, $this->types);
    }

    /**
     * Returns the parameters for this INSERT query being constructed indexed by parameter index.
     *
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Returns the parameter types for this INSERT query being constructed indexed by parameter index.
     *
     * @return array
     */
    public function getParameterTypes()
    {
        return $this->types;
    }

    /**
     * Returns the SQL formed by the current specifications of this INSERT query.
     *
     * @return string
     *
     * @throws \LogicException if no values have been specified yet.
     */
    public function getSQL()
    {
        if (empty($this->values)) {
            throw new \LogicException('You need to add at least one set of values before generating the SQL.');
        }

        $platform = $this->connection->getDatabasePlatform();
        $columnList = '';

        if (! empty($this->columns)) {
            $columnList = sprintf(
                ' (%s)',
                implode(
                    ', ',
                    array_map(
                        function ($column) use ($platform) {
                            $column = new Identifier($column);

                            return $column->getQuotedName($platform);
                        },
                        $this->columns
                    )
                )
            );
        }

        return sprintf(
            'INSERT INTO %s%s VALUES (%s)',
            $this->table->getQuotedName($platform),
            $columnList,
            implode(
                '), (',
                array_map(
                    function (array $valueSet) {
                        return implode(', ', $valueSet);
                    },
                    $this->values
                )
            )
        );
    }
}
