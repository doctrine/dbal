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

namespace Doctrine\DBAL\Portability;

use PDO;

/**
 * Portability Wrapper for a Statement
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       2.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 */
class Statement implements \Doctrine\DBAL\Driver\Statement
{

    /**
     * @var int
     */
    private $portability;
    
    /**
     * @var Doctrine\DBAL\Driver\Statement
     */
    private $stmt;
    
    /**
     * @var int
     */
    private $case;

    /**
     * Wraps <tt>Statement</tt> and applies portability measures
     *
     * @param Doctrine\DBAL\Driver\Statement $stmt
     * @param Doctrine\DBAL\Connection $conn
     */
    public function __construct($stmt, Connection $conn)
    {
        $this->stmt = $stmt;
        $this->portability = $conn->getPortability();
        $this->case = $conn->getFetchCase();
    }

    public function bindParam($column, &$variable, $type = null)
    {
        return $this->stmt->bindParam($column, $variable, $type);
    }

    public function bindValue($param, $value, $type = null)
    {
        return $this->stmt->bindValue($param, $value, $type);
    }

    public function closeCursor()
    {
        return $this->stmt->closeCursor();
    }

    public function columnCount()
    {
        return $this->stmt->columnCount();
    }

    public function errorCode()
    {
        return $this->stmt->errorCode();
    }

    public function errorInfo()
    {
        return $this->stmt->errorInfo();
    }

    public function execute($params = null)
    {
        return $this->stmt->execute($params);
    }

    public function fetch($fetchStyle = PDO::FETCH_BOTH)
    {
        $row = $this->stmt->fetch($fetchStyle);
        
        $row = $this->fixRow($row,
            $this->portability & (Connection::PORTABILITY_EMPTY_TO_NULL|Connection::PORTABILITY_RTRIM),
            ($fetchStyle == PDO::FETCH_ASSOC || $fetchStyle == PDO::FETCH_BOTH) && ($this->portability & Connection::PORTABILITY_FIX_CASE)
        );
        
        return $row;
    }

    public function fetchAll($fetchStyle = PDO::FETCH_BOTH, $columnIndex = 0)
    {
        if ($columnIndex != 0) {
            $rows = $this->stmt->fetchAll($fetchStyle, $columnIndex);
        } else {
            $rows = $this->stmt->fetchAll($fetchStyle);
        }
        
        $iterateRow = $this->portability & (Connection::PORTABILITY_EMPTY_TO_NULL|Connection::PORTABILITY_RTRIM);
        $fixCase = ($fetchStyle == PDO::FETCH_ASSOC || $fetchStyle == PDO::FETCH_BOTH) && ($this->portability & Connection::PORTABILITY_FIX_CASE);
        if (!$iterateRow && !$fixCase) {
            return $rows;
        }

        foreach ($rows AS $num => $row) {
            $rows[$num] = $this->fixRow($row, $iterateRow, $fixCase);
        }
        
        return $rows;
    }
    
    protected function fixRow($row, $iterateRow, $fixCase)
    {
        if (!$row) {
            return $row;
        }
        
        if ($fixCase) {
            $row = array_change_key_case($row, $this->case);
        }

        if ($iterateRow) {
            foreach ($row AS $k => $v) {
                if (($this->portability & Connection::PORTABILITY_EMPTY_TO_NULL) && $v === '') {
                    $row[$k] = null;
                } else if (($this->portability & Connection::PORTABILITY_RTRIM) && is_string($v)) {
                    $row[$k] = rtrim($v);
                }
            }
        }
        return $row;
    }

    public function fetchColumn($columnIndex = 0)
    {
        $value = $this->stmt->fetchColumn($columnIndex);
        
        if ($this->portability & (Connection::PORTABILITY_EMPTY_TO_NULL|Connection::PORTABILITY_RTRIM)) {
            if (($this->portability & Connection::PORTABILITY_EMPTY_TO_NULL) && $value === '') {
                $value = null;
            } else if (($this->portability & Connection::PORTABILITY_RTRIM) && is_string($value)) {
                $value = rtrim($value);
            }
        }
        
        return $value;
    }

    public function rowCount()
    {
        return $this->stmt->rowCount();
    }

}