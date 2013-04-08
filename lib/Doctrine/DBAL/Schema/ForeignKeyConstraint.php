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

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * An abstraction class for a foreign key constraint.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @link   www.doctrine-project.org
 * @since  2.0
 */
class ForeignKeyConstraint extends AbstractAsset implements Constraint
{
    /**
     * @var Table Instance of the referencing table the foreign key constraint is associated with.
     */
    protected $localTable;

    /**
     * @var array Names of the referencing table columns the foreign key constraint is associated with.
     */
    protected $localColumnNames;

    /**
     * @var string Name of the referenced table the foreign key constraint is associated with.
     */
    protected $foreignTableName;

    /**
     * @var array Names of the referenced table columns the foreign key constraint is associated with.
     */
    protected $foreignColumnNames;

    /**
     * @var array Options associated with the foreign key constraint.
     */
    protected $options;

    /**
     * Initializes the foreign key constraint.
     *
     * @param array       $localColumnNames   Names of the referencing table columns.
     * @param string      $foreignTableName   Name of the referenced table.
     * @param array       $foreignColumnNames Names of the referenced table columns.
     * @param string|null $name               Name of the foreign key constraint.
     * @param array       $options            Options associated with the foreign key constraint.
     */
    public function __construct(array $localColumnNames, $foreignTableName, array $foreignColumnNames, $name = null, array $options = array())
    {
        $this->_setName($name);
        $this->localColumnNames = $localColumnNames;
        $this->foreignTableName = $foreignTableName;
        $this->foreignColumnNames = $foreignColumnNames;
        $this->options = $options;
    }

    /**
     * Returns the name of the referencing table
     * the foreign key constraint is associated with.
     *
     * @return string
     */
    public function getLocalTableName()
    {
        return $this->localTable->getName();
    }

    /**
     * Sets the Table instance of the referencing table
     * the foreign key constraint is associated with.
     *
     * @param Table $table Instance of the referencing table.
     */
    public function setLocalTable(Table $table)
    {
        $this->localTable = $table;
    }

    /**
     * @return Table
     */
    public function getLocalTable()
    {
        return $this->_localTable;
    }

    /**
     * Returns the names of the referencing table columns
     * the foreign key constraint is associated with.
     *
     * @return array
     */
    public function getLocalColumns()
    {
        return $this->localColumnNames;
    }

    /**
     * Returns the names of the referencing table columns
     * the foreign key constraint is associated with.
     *
     * @return array
     */
    public function getColumns()
    {
        return $this->localColumnNames;
    }

    /**
     * Returns the name of the referenced table
     * the foreign key constraint is associated with.
     *
     * @return string
     */
    public function getForeignTableName()
    {
        return $this->foreignTableName;
    }

    /**
     * Return the non-schema qualified foreign table name.
     *
     * @return string
     */
    public function getUnqualifiedForeignTableName()
    {
        $parts = explode(".", $this->_foreignTableName);
        return strtolower(end($parts));
    }

    /**
     * Get the quoted representation of this asset but only if it was defined with one. Otherwise
     * return the plain unquoted value as inserted.
     *
     * @param AbstractPlatform $platform The platform to use for quoting.
     *
     * @return string
     */
    public function getQuotedForeignTableName(AbstractPlatform $platform)
    {
        $keywords = $platform->getReservedKeywordsList();
        $parts = explode(".", $this->getForeignTableName());

        foreach ($parts as $k => $v) {
            $parts[$k] = ($this->_quoted || $keywords->isKeyword($v)) ? $platform->quoteIdentifier($v) : $v;
        }

        return implode(".", $parts);
    }

    /**
     * Returns the names of the referenced table columns
     * the foreign key constraint is associated with.
     *
     * @return array
     */
    public function getForeignColumns()
    {
        return $this->foreignColumnNames;
    }

    /**
     * Returns whether or not a given option
     * is associated with the foreign key constraint.
     *
     * @param string $name Name of the option to check.
     *
     * @return boolean
     */
    public function hasOption($name)
    {
        return isset($this->options[$name]);
    }

    /**
     * Returns an option associated with the foreign key constraint.
     *
     * @param string $name Name of the option the foreign key constraint is associated with.
     *
     * @return mixed
     */
    public function getOption($name)
    {
        return $this->options[$name];
    }

    /**
     * Returns the options associated with the foreign key constraint.
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Returns the referential action for UPDATE operations
     * on the referenced table the foreign key constraint is associated with.
     *
     * @return string|null
     */
    public function onUpdate()
    {
        return $this->onEvent('onUpdate');
    }

    /**
     * Returns the referential action for DELETE operations
     * on the referenced table the foreign key constraint is associated with.
     *
     * @return string|null
     */
    public function onDelete()
    {
        return $this->onEvent('onDelete');
    }

    /**
     * Returns the referential action for a given database operation
     * on the referenced table the foreign key constraint is associated with.
     *
     * @param string $event Name of the database operation/event to return the referential action for.
     *
     * @return string|null
     */
    private function onEvent($event)
    {
        if (isset($this->options[$event])) {
            $onEvent = strtoupper($this->options[$event]);

            if ( ! in_array($onEvent, array('NO ACTION', 'RESTRICT'))) {
                return $onEvent;
            }
        }

        return false;
    }
}
