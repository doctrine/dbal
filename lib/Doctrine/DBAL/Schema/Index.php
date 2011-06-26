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

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Visitor\Visitor;

class Index extends AbstractAsset implements Constraint
{
    /**
     * @var array
     */
    protected $_columns;

    /**
     * @var bool
     */
    protected $_isUnique = false;

    /**
     * @var bool
     */
    protected $_isPrimary = false;

    /**
     * @param string $indexName
     * @param array $column
     * @param bool $isUnique
     * @param bool $isPrimary
     */
    public function __construct($indexName, array $columns, $isUnique=false, $isPrimary=false)
    {
        $isUnique = ($isPrimary)?true:$isUnique;

        $this->_setName($indexName);
        $this->_isUnique = $isUnique;
        $this->_isPrimary = $isPrimary;

        foreach($columns AS $column) {
            $this->_addColumn($column);
        }
    }

    /**
     * @param string $column
     */
    protected function _addColumn($column)
    {
        if(is_string($column)) {
            $this->_columns[] = $column;
        } else {
            throw new \InvalidArgumentException("Expecting a string as Index Column");
        }
    }

    /**
     * @return array
     */
    public function getColumns()
    {
        return $this->_columns;
    }
    
    /**
     * Is the index neither unique nor primary key?
     * 
     * @return bool
     */
    public function isSimpleIndex()
    {
        return !$this->_isPrimary && !$this->_isUnique;
    }

    /**
     * @return bool
     */
    public function isUnique()
    {
        return $this->_isUnique;
    }

    /**
     * @return bool
     */
    public function isPrimary()
    {
        return $this->_isPrimary;
    }

    /**
     * @param  string $columnName
     * @param  int $pos
     * @return bool
     */
    public function hasColumnAtPosition($columnName, $pos=0)
    {
        $columnName = strtolower($columnName);
        $indexColumns = \array_map('strtolower', $this->getColumns());
        return \array_search($columnName, $indexColumns) === $pos;
    }

    /**
     * Check if this index exactly spans the given column names in the correct order.
     *
     * @param array $columnNames
     * @return boolean
     */
    public function spansColumns(array $columnNames)
    {
        $sameColumns = true;
        for ($i = 0; $i < count($this->_columns); $i++) {
            if (!isset($columnNames[$i]) || strtolower($this->_columns[$i]) != strtolower($columnNames[$i])) {
                $sameColumns = false;
            }
        }
        return $sameColumns;
    }

    /**
     * Check if the other index already fullfills all the indexing and constraint needs of the current one.
     *
     * @param Index $other
     * @return bool
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
            if (!$this->isUnique() && !$this->isPrimary()) {
                // this is a special case: If the current key is neither primary or unique, any uniqe or
                // primary key will always have the same effect for the index and there cannot be any constraint
                // overlaps. This means a primary or unique index can always fullfill the requirements of just an
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
     * Detect if the other index is a non-unique, non primary index that can be overwritten by this one.
     *
     * @param Index $other
     * @return bool
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
}