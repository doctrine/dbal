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

namespace Doctrine\DBAL\Portability;

use Doctrine\DBAL\Driver\StatementIterator;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use function array_change_key_case;
use function is_null;
use function is_string;
use function rtrim;

/**
 * Portability wrapper for a Statement.
 *
 * @link   www.doctrine-project.org
 * @since  2.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class Statement implements \IteratorAggregate, \Doctrine\DBAL\Driver\Statement
{
    /**
     * @var int
     */
    private $portability;

    /**
     * @var \Doctrine\DBAL\Driver\Statement
     */
    private $stmt;

    /**
     * @var int
     */
    private $case;

    /**
     * @var int
     */
    private $defaultFetchMode = FetchMode::MIXED;

    /**
     * Wraps <tt>Statement</tt> and applies portability measures.
     *
     * @param \Doctrine\DBAL\Driver\Statement       $stmt
     * @param \Doctrine\DBAL\Portability\Connection $conn
     */
    public function __construct($stmt, Connection $conn)
    {
        $this->stmt = $stmt;
        $this->portability = $conn->getPortability();
        $this->case = $conn->getFetchCase();
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = ParameterType::STRING, $length = null)
    {
        return $this->stmt->bindParam($column, $variable, $type, $length);
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        return $this->stmt->bindValue($param, $value, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        return $this->stmt->closeCursor();
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        return $this->stmt->columnCount();
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        return $this->stmt->errorCode();
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return $this->stmt->errorInfo();
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        return $this->stmt->execute($params);
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($fetchMode, $arg1 = null, $arg2 = null)
    {
        $this->defaultFetchMode = $fetchMode;

        return $this->stmt->setFetchMode($fetchMode, $arg1, $arg2);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new StatementIterator($this);
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null, $cursorOrientation = \PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        $fetchMode = $fetchMode ?: $this->defaultFetchMode;

        $row = $this->stmt->fetch($fetchMode);

        $iterateRow = $this->portability & (Connection::PORTABILITY_EMPTY_TO_NULL|Connection::PORTABILITY_RTRIM);
        $fixCase    = $this->case !== null
            && ($fetchMode === FetchMode::ASSOCIATIVE || $fetchMode === FetchMode::MIXED)
            && ($this->portability & Connection::PORTABILITY_FIX_CASE);

        $row = $this->fixRow($row, $iterateRow, $fixCase);

        return $row;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        $fetchMode = $fetchMode ?: $this->defaultFetchMode;

        if ($fetchArgument) {
            $rows = $this->stmt->fetchAll($fetchMode, $fetchArgument);
        } else {
            $rows = $this->stmt->fetchAll($fetchMode);
        }

        $iterateRow = $this->portability & (Connection::PORTABILITY_EMPTY_TO_NULL|Connection::PORTABILITY_RTRIM);
        $fixCase    = $this->case !== null
            && ($fetchMode === FetchMode::ASSOCIATIVE || $fetchMode === FetchMode::MIXED)
            && ($this->portability & Connection::PORTABILITY_FIX_CASE);

        if ( ! $iterateRow && !$fixCase) {
            return $rows;
        }

        if ($fetchMode === FetchMode::COLUMN) {
            foreach ($rows as $num => $row) {
                $rows[$num] = [$row];
            }
        }

        foreach ($rows as $num => $row) {
            $rows[$num] = $this->fixRow($row, $iterateRow, $fixCase);
        }

        if ($fetchMode === FetchMode::COLUMN) {
            foreach ($rows as $num => $row) {
                $rows[$num] = $row[0];
            }
        }

        return $rows;
    }

    /**
     * @param mixed $row
     * @param int   $iterateRow
     * @param bool  $fixCase
     *
     * @return array
     */
    protected function fixRow($row, $iterateRow, $fixCase)
    {
        if ( ! $row) {
            return $row;
        }

        if ($fixCase) {
            $row = array_change_key_case($row, $this->case);
        }

        if ($iterateRow) {
            foreach ($row as $k => $v) {
                if (($this->portability & Connection::PORTABILITY_EMPTY_TO_NULL) && $v === '') {
                    $row[$k] = null;
                } elseif (($this->portability & Connection::PORTABILITY_RTRIM) && is_string($v)) {
                    $row[$k] = rtrim($v);
                }
            }
        }

        return $row;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        $value = $this->stmt->fetchColumn($columnIndex);

        if ($this->portability & (Connection::PORTABILITY_EMPTY_TO_NULL|Connection::PORTABILITY_RTRIM)) {
            if (($this->portability & Connection::PORTABILITY_EMPTY_TO_NULL) && $value === '') {
                $value = null;
            } elseif (($this->portability & Connection::PORTABILITY_RTRIM) && is_string($value)) {
                $value = rtrim($value);
            }
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount()
    {
        return $this->stmt->rowCount();
    }
}
