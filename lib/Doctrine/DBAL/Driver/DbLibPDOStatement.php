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

namespace Doctrine\DBAL\Driver;

/**
 * DbLib Driver implementation.
 *
 * @since 2.6
 * @author Maximilian Ruta <mr@xtain.net>
 */
class DbLibPDOStatement extends PDOStatement
{
    /**
     * @var self
     */
    protected static $lastActive;

    /**
     * @var array
     */
    protected $resultCache;

    /**
     * @param mixed $value
     * @param int   $type
     *
     * @return mixed
     */
    protected function guessType($value)
    {
        if (is_scalar($value)) {
            if (is_int($value) || is_float($value)) {
                return \PDO::PARAM_INT;
            } else if (is_bool($value)) {
                return \PDO::PARAM_BOOL;
            }
        }

        return \PDO::PARAM_STR;
    }

    /**
     * @param mixed $value
     * @param int   $type
     *
     * @return mixed
     */
    protected function fixType($value, $type = \PDO::PARAM_STR)
    {
        switch ($type) {
            case \PDO::PARAM_NULL:
                return null;
            case \PDO::PARAM_INT:
                $float = floatval($value);
                $int = intval($float);
                if ($float && $int != $float) {
                    return $float;
                }

                return $int;
            case \PDO::PARAM_BOOL:
                return $value ? 1 : 0;
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = null)
    {
        if ($type === null) {
            $type = $this->guessType($value);
        }

        return parent::bindValue(
            $param,
            $this->fixType($value, $type),
            $type
        );
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = null, $length = null, $driverOptions = null)
    {
        if ($type === null) {
            $type = $this->guessType($variable);
        }

        $variable = $this->fixType($variable, $type);
        return parent::bindParam($column, $variable, $type, $length, $driverOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        if (isset($this->resultCache)) {
            if (count($this->resultCache) == 0) {
                return false;
            }

            $item = array_values(array_shift($this->resultCache));

            return $item[$columnIndex];
        } else {
            return parent::fetchColumn($columnIndex);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null, $cursorOrientation = null, $cursorOffset = null)
    {
        if (isset($this->resultCache)) {
            if ($fetchMode != \PDO::FETCH_ASSOC && $cursorOrientation !== null || $cursorOffset !== null) {
                throw new \RuntimeException('result caching is only implemented for PDO::FETCH_ASSOC');
            }

            if (count($this->resultCache) == 0) {
                return false;
            }

            return array_shift($this->resultCache);
        } else {
            return parent::fetch($fetchMode, $cursorOrientation, $cursorOffset);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        if (isset($this->resultCache)) {
            if ($fetchMode != \PDO::FETCH_ASSOC && $fetchArgument !== null || $ctorArgs !== null) {
                throw new \RuntimeException('result caching is only implemented for PDO::FETCH_ASSOC');
            }

            $data = $this->resultCache;
            $this->resultCache = array();
            return $data;
        } else {
            return parent::fetchAll($fetchMode, $fetchArgument, $ctorArgs);
        }
    }

    protected function cacheResults()
    {
        $this->resultCache = parent::fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        if ($params !== null) {
            foreach ($params as $key => $value) {
                if (is_int($key)) {
                    $key++;
                }

                $this->bindValue($key, $value, null);
            }
        }

        if (self::$lastActive instanceof self) {
            self::$lastActive->cacheResults();
        }

        $result = parent::execute();

        self::$lastActive = $this;

        // forward to the result set which is not empty
        while ($this->columnCount() == 0) {
            if (!$this->nextRowset()) {
                break;
            }
        }

        return $result;
    }
}