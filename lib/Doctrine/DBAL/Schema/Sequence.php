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
 * Sequence structure.
 *
 * @link   www.doctrine-project.org
 * @since  2.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class Sequence extends AbstractAsset
{
    /**
     * @var integer
     */
    protected $allocationSize = 1;

    /**
     * @var integer
     */
    protected $initialValue = 1;

    /**
     * The number of preallocated sequence values that are kept in memory.
     *
     * @var integer|string|null
     */
    private $cacheSize;

    /**
     * Constructor.
     *
     * @param string              $name           The name of the sequence.
     * @param integer             $allocationSize The amount to increment by when allocating sequence numbers from the
     *                                            sequence.
     * @param integer             $initialValue   The first sequence number to be generated.
     * @param integer|string|null $cacheSize      The number of preallocated sequence values that are kept in memory.
     *                                            This has to be a positive integer or numeric character string value.
     *                                            The value "0" indicates to disable caching.
     *                                            A null argument indicates to use platform's default cache size.
     */
    public function __construct($name, $allocationSize = 1, $initialValue = 1, $cacheSize = null)
    {
        $this->_setName($name);
        $this->allocationSize = is_numeric($allocationSize) ? $allocationSize : 1;
        $this->initialValue   = is_numeric($initialValue) ? $initialValue : 1;
        $this->setCacheSize($cacheSize);
    }

    /**
     * Compares this sequence against the given sequence for equality.
     *
     * This equality comparison does not take the sequences' names into account.
     *
     * @param Sequence $sequence The sequence to compare against this sequence for equality.
     *
     * @return boolean True if the given sequence equals this sequence, false otherwise.
     */
    public function equals(Sequence $sequence)
    {
        $cacheSize = $sequence->getCacheSize();

        // Additional check to avoid errors with 0 == null and null == 0.
        if ((null === $this->cacheSize && 0 === $cacheSize) ||
            (null === $cacheSize && 0 === $this->cacheSize)
        ) {
            return false;
        }

        return $this->allocationSize == $sequence->getAllocationSize() &&
            $this->initialValue == $sequence->getInitialValue() &&
            $this->cacheSize == $sequence->getCacheSize();
    }

    /**
     * @return integer
     */
    public function getAllocationSize()
    {
        return $this->allocationSize;
    }

    /**
     * @return integer
     */
    public function getInitialValue()
    {
        return $this->initialValue;
    }

    /**
     * Returns the number of preallocated sequence values that are kept in memory.
     *
     * The value "0" indicates that caching is disabled for this sequence.
     * A null return value indicates that the default cache size is used.
     *
     * @return integer|string|null
     */
    public function getCacheSize()
    {
        return $this->cacheSize;
    }

    /**
     * @param integer $allocationSize
     *
     * @return \Doctrine\DBAL\Schema\Sequence
     */
    public function setAllocationSize($allocationSize)
    {
        $this->allocationSize = is_numeric($allocationSize) ? $allocationSize : 1;

        return $this;
    }

    /**
     * @param integer $initialValue
     *
     * @return \Doctrine\DBAL\Schema\Sequence
     */
    public function setInitialValue($initialValue)
    {
        $this->initialValue = is_numeric($initialValue) ? $initialValue : 1;

        return $this;
    }

    /**
     * Sets the number of preallocated sequence values that are kept in memory.
     *
     * @param integer|string|null $cacheSize The number of preallocated sequence values that are kept in memory.
     *                                       This has to be a positive integer or numeric character string value.
     *                                       The value "0" indicates to disable caching.
     *                                       A null argument indicates to use platform's default cache size.
     *
     * @return \Doctrine\DBAL\Schema\Sequence This sequence instance.
     *
     * @throws \InvalidArgumentException if the given cache size is invalid.
     */
    public function setCacheSize($cacheSize)
    {
        if (null === $cacheSize) {
            $this->cacheSize = null;

            return $this;
        }

        if ( ! is_int($cacheSize) && ! ctype_digit($cacheSize)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid cache size "%s" specified for sequence "%s". ' .
                    'Expected cache size to be of type integer or numeric character string.',
                    is_array($cacheSize) || is_object($cacheSize) ? gettype($cacheSize) : $cacheSize,
                    $this->_name
                )
            );
        }

        if ($cacheSize < 0) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid cache size "%s" specified for sequence "%s". ' .
                    'The cache size must be greater or equal to 0.',
                    $cacheSize,
                    $this->_name
                )
            );
        }

        $this->cacheSize = $cacheSize;

        return $this;
    }

    /**
     * Checks if this sequence is an autoincrement sequence for a given table.
     *
     * This is used inside the comparator to not report sequences as missing,
     * when the "from" schema implicitly creates the sequences.
     *
     * @param \Doctrine\DBAL\Schema\Table $table
     *
     * @return boolean
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
     * @param \Doctrine\DBAL\Schema\Visitor\Visitor $visitor
     *
     * @return void
     */
    public function visit(Visitor $visitor)
    {
        $visitor->acceptSequence($this);
    }
}
