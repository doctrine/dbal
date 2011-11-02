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

namespace Doctrine\DBAL\Cache;

use Doctrine\DBAL\Driver\ResultStatement;
use PDO;

class ArrayStatement implements \IteratorAggregate, ResultStatement
{
    private $data;
    private $columnCount = 0;
    private $num = 0;
    private $defaultFetchStyle = PDO::FETCH_BOTH;

    public function __construct(array $data)
    {
        $this->data = $data;
        if (count($data)) {
            $this->columnCount = count($data[0]);
        }
    }

    public function closeCursor()
    {
        unset ($this->data);
    }

    public function columnCount()
    {
        return $this->columnCount;
    }

    public function setFetchMode($fetchStyle)
    {
        $this->defaultFetchStyle = $fetchStyle;
    }

    public function getIterator()
    {
        $data = $this->fetchAll($this->defaultFetchStyle);
        return new \ArrayIterator($data);
    }

    public function fetch($fetchStyle = PDO::FETCH_BOTH)
    {
        if (isset($this->data[$this->num])) {
            $row = $this->data[$this->num++];
            if ($fetchStyle === PDO::FETCH_ASSOC) {
                return $row;
            } else if ($fetchStyle === PDO::FETCH_NUM) {
                return array_values($row);
            } else if ($fetchStyle === PDO::FETCH_BOTH) {
                return array_merge($row, array_values($row));
            }
        }
        return false;
    }

    public function fetchAll($fetchStyle = PDO::FETCH_BOTH)
    {
        $rows = array();
        while ($row = $this->fetch($fetchStyle)) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function fetchColumn($columnIndex = 0)
    {
        $row = $this->fetch(PDO::FETCH_NUM);
        if (!isset($row[$columnIndex])) {
            // TODO: verify this is correct behavior
            return false;
        }
        return $row[$columnIndex];
    }
}