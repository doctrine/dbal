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

class Index extends AbstractAsset implements Constraint
{
    /**
     * Asset identifier instances of the column names the index is associated with.
     * array($columnName => Identifier)
     *
     * @var Identifier[]
     */
    protected $_columns;

    /**
     * @var boolean
     */
    protected $_isUnique = false;

    /**
     * @var boolean
     */
    protected $_isPrimary = false;

    /**
     * Platform specific flags for indexes.
     *
     * @var array
     */
    protected $_flags = array();

    /**
     * @param string  $indexName
     * @param array   $columns
     * @param boolean $isUnique
     * @param boolean $isPrimary
     * @param array   $flags
     */
    public function __construct($indexName, array $columns, $isUnique = false, $isPrimary = false, array $flags = array())
    {
        $isUnique = ($isPrimary)?true:$isUnique;

        $this->_setName($indexName);
        $this->_isUnique = $isUnique;
        $this->_isPrimary = $isPrimary;

        foreach ($columns as $column) {
            $this->_addColumn($column);
        }
        foreach ($flags as $flag) {
            $this->addFlag($flag);
        }
    }

    /**
     * @param string $column
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function _addColumn($column)
    {
        if(is_string($column)) {
            $this->_columns[$column] = new Identifier($column);
        } else {
            throw new \InvalidArgumentException("Expecting a string as Index Column");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getColumns()
    {
        return array_keys($this->_columns);
    }

    /**
     * {@inheritdoc}
     */
    public function getQuotedColumns(AbstractPlatform $platform)
    {
        $columns = array();

        foreach ($this->_columns as $column) {
            $columns[] = $column->getQuotedName($platform);
        }

        return $columns;
    }

    /**
     * @return array
     */
    public function getUnquotedColumns()
    {
        return array_map(array($this, 'trimQuotes'), $this->getColumns());
    }

    /**
     * Is the index neither unique nor primary key?
     *
     * @return boolean
     */
    public function isSimpleIndex()
    {
        return !$this->_isPrimary && !$this->_isUnique;
    }

    /**
     * @return boolean
     */
    public function isUnique()
    {
        return $this->_isUnique;
    }

    /**
     * @return boolean
     */
    public function isPrimary()
    {
        return $this->_isPrimary;
    }

    /**
     * @param string  $columnName
     * @param integer $pos
     *
     * @return boolean
     */
    public function hasColumnAtPosition($columnName, $pos = 0)
    {
        $columnName   = $this->trimQuotes(strtolower($columnName));
        $indexColumns = array_map('strtolower', $this->getUnquotedColumns());

        return array_search($columnName, $indexColumns) === $pos;
    }

    /**
     * Checks if this index exactly spans the given column names in the correct order.
     *
     * @param array $columnNames
     *
     * @return boolean
     */
    public function spansColumns(array $columnNames)
    {
        $columns         = $this->getColumns();
        $numberOfColumns = count($columns);
        $sameColumns     = true;

        for ($i = 0; $i < $numberOfColumns; $i++) {
            if ( ! isset($columnNames[$i]) || $this->trimQuotes(strtolower($columns[$i])) !== $this->trimQuotes(strtolower($columnNames[$i]))) {
                $sameColumns = false;
            }
        }

        return $sameColumns;
    }

    /**
     * Checks if the other index already fulfills all the indexing and constraint needs of the current one.
     *
     * @param \Doctrine\DBAL\Schema\Index $other
     *
     * @return boolean
     */
    public function isFullfilledBy(Index $other)
    {
        // allow the other index to be equally large only. It being larger is an option
        // but it creates a problem with scenarios of the kind PRIMARY KEY(foo,bar) UNIQUE(foo)
        if (count($other->getColumns()) != count($this->getColumns())) {
            return false;
        }

        // Check if columns are the same, and even in the same order
        $sameColumns = $this->spansColumns($other->getColumns());

        if ($sameColumns) {
            if ( ! $this->isUnique() && !$this->isPrimary()) {
                // this is a special case: If the current key is neither primary or unique, any uniqe or
                // primary key will always have the same effect for the index and there cannot be any constraint
                // overlaps. This means a primary or unique index can always fulfill the requirements of just an
                // index that has no constraints.
                return true;
            } else if ($other->isPrimary() != $this->isPrimary()) {
                return false;
            } else if ($other->isUnique() != $this->isUnique()) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Detects if the other index is a non-unique, non primary index that can be overwritten by this one.
     *
     * @param \Doctrine\DBAL\Schema\Index $other
     *
     * @return boolean
     */
    public function overrules(Index $other)
    {
        if ($other->isPrimary()) {
            return false;
        } else if ($this->isSimpleIndex() && $other->isUnique()) {
            return false;
        }

        if ($this->spansColumns($other->getColumns()) && ($this->isPrimary() || $this->isUnique())) {
            return true;
        }

        return false;
    }

    /**
     * Returns platform specific flags for indexes.
     *
     * @return array
     */
    public function getFlags()
    {
        return array_keys($this->_flags);
    }

    /**
     * Adds Flag for an index that translates to platform specific handling.
     *
     * @example $index->addFlag('CLUSTERED')
     *
     * @param string $flag
     *
     * @return \Doctrine\DBAL\Schema\Index
     */
    public function addFlag($flag)
    {
        $this->flags[strtolower($flag)] = true;

        return $this;
    }

    /**
     * Does this index have a specific flag?
     *
     * @param string $flag
     *
     * @return boolean
     */
    public function hasFlag($flag)
    {
        return isset($this->flags[strtolower($flag)]);
    }

    /**
     * Removes a flag.
     *
     * @param string $flag
     *
     * @return void
     */
    public function removeFlag($flag)
    {
        unset($this->flags[strtolower($flag)]);
    }
}
