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

use Doctrine\DBAL\Schema\Visitor\Visitor;

/**
 * Sequence Structure
 *
 * 
 * @link    www.doctrine-project.org
 * @since   2.0
 * @version $Revision$
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 */
class Sequence extends AbstractAsset
{
    /**
     * @var int
     */
    protected $_allocationSize = 1;

    /**
     * @var int
     */
    protected $_initialValue = 1;

    /**
     *
     * @param string $name
     * @param int $allocationSize
     * @param int $initialValue
     */
    public function __construct($name, $allocationSize=1, $initialValue=1)
    {
        $this->_setName($name);
        $this->_allocationSize = (is_numeric($allocationSize))?$allocationSize:1;
        $this->_initialValue = (is_numeric($initialValue))?$initialValue:1;
    }

    public function getAllocationSize()
    {
        return $this->_allocationSize;
    }

    public function getInitialValue()
    {
        return $this->_initialValue;
    }

    public function setAllocationSize($allocationSize)
    {
        $this->_allocationSize = (is_numeric($allocationSize))?$allocationSize:1;
    }

    public function setInitialValue($initialValue)
    {
        $this->_initialValue = (is_numeric($initialValue))?$initialValue:1;
    }

    /**
     * Check if this sequence is an autoincrement sequence for a given table.
     *
     * This is used inside the comparator to not report sequences as missing,
     * when the "from" schema implicitly creates the sequences.
     *
     * @param Table $table
     *
     * @return bool
     */
    public function isAutoIncrementsFor(Table $table)
    {
        if ( ! $table->hasPrimaryKey()) {
            return false;
        }

        $pkColumns = $table->getPrimaryKey()->getColumns();

        if (count($pkColumns) != 1) {
            return false;
        }

        $column = $table->getColumn($pkColumns[0]);

        if ( ! $column->getAutoincrement()) {
            return false;
        }

        $sequenceName      = $this->getShortestName($table->getNamespaceName());
        $tableName         = $table->getShortestName($table->getNamespaceName());
        $tableSequenceName = sprintf('%s_%s_seq', $tableName, $pkColumns[0]);

        return $tableSequenceName === $sequenceName;
    }

    /**
     * @param Visitor $visitor
     */
    public function visit(Visitor $visitor)
    {
        $visitor->acceptSequence($this);
    }
}
