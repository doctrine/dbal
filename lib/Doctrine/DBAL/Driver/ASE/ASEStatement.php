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

namespace Doctrine\DBAL\Driver\ASE;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Types\Type;
use PDO;
use IteratorAggregate;
use Doctrine\DBAL\Driver\Statement;

/**
 * ASE Statement.
 *
 * @since 2.6
 * @author Maximilian Ruta <mr@xtain.net>
 */
class ASEStatement implements IteratorAggregate, Statement
{
    /**
     * The ASE Connection object.
     *
     * @var ASEConnection
     */
    private $connection;

    /**
     * The ASE Resource.
     *
     * @var resource
     */
    private $connectionResource;

    /**
     * The SQL statement to execute.
     *
     * @var string
     */
    private $sql;

    /**
     * The ASE statement resource.
     *
     * @var resource|bool
     */
    private $stmt;

    /**
     * The ASE statement affected rows.
     *
     * @var int
     */
    private $stmtAffectedRows;

    /**
     * Parameters to bind.
     *
     * @var array
     */
    private $params = array();

    /**
     * The name of the default class to instantiate when fetch mode is \PDO::FETCH_CLASS.
     *
     * @var string
     */
    private $defaultFetchClass = '\stdClass';

    /**
     * The constructor arguments for the default class to instantiate when fetch mode is \PDO::FETCH_CLASS.
     *
     * @var string
     */
    private $defaultFetchClassCtorArgs = array();

    /**
     * The fetch style.
     *
     * @param integer
     */
    private $defaultFetchMode = PDO::FETCH_BOTH;

    /**
     * The last insert ID.
     *
     * @var \Doctrine\DBAL\Driver\SQLSrv\LastInsertId|null
     */
    private $lastInsertId;

    /**
     * @var ASEMessageHandler
     */
    private $messageHandler;

    /**
     * @var array
     */
    private $messages;

    /**
     * Append to any INSERT query to retrieve the last insert id.
     *
     * @var string
     */
    const LAST_INSERT_ID_SQL = ' SELECT @@IDENTITY AS LastInsertId';

    /**
     * @param ASEConnection                                  $connection
     * @param resource                                       $connectionResource
     * @param string                                         $sql
     * @param ASEMessageHandler                              $messageHandler
     * @param \Doctrine\DBAL\Driver\ASE\LastInsertId|null    $lastInsertId
     */
    public function __construct(ASEConnection $connection, $connectionResource, $sql, ASEMessageHandler $messageHandler, LastInsertId $lastInsertId = null)
    {
        $this->connection = $connection;
        $this->connectionResource = $connectionResource;
        $this->sql = $sql;
        $this->messageHandler = $messageHandler;

        if (stripos($sql, 'INSERT ') === 0) {
            $this->sql .= self::LAST_INSERT_ID_SQL;
            $this->lastInsertId = $lastInsertId;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = null)
    {
        return $this->bindParam($param, $value, $type, null);
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = null, $length = null)
    {
        if ($type === \PDO::PARAM_LOB && is_resource($variable)) {
            $this->params[$column] = array(stream_get_contents($variable), $type);
        } else {
            $this->params[$column] = array($variable, $type);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        if (is_resource($this->stmt)) {
            sybase_free_result($this->stmt);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        if (is_resource($this->stmt)) {
            return sybase_num_fields($this->stmt);
        }

        return 0;
    }

    /**
     * {@inheritDoc}
     */
    public function errorCode()
    {
        $message = $this->messageHandler->getLastError();

        if ($message !== null) {
            return $message->getCode();
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function errorInfo()
    {
        $message = $this->messageHandler->getLastError();

        if ($message !== null) {
            return $message->getInfo();
        }

        return false;
    }

    /**
     * @param string $sql
     * @param array $params
     * @return string
     */
    protected function interpolateQuery($sql, $params)
    {
        $quotedNamedParams = array();
        $quotedNumberedParams = array();
        $patternParts = array();
        $patternParts[] = preg_quote('?', '/');
        foreach ($params as $name => $data) {
            list($value, $type) = $data;

            $value = $this->connection->quote($value, $type);

            if (is_numeric($name)) {
                $quotedNumberedParams[$name] = $value;
            } else {
                $quotedNamedParams[$name] = $value;
            }

            $patternParts[] = preg_quote($name, '/');
        }

        $i = 0;

        $sql = preg_replace_callback(
            '/' . implode('|', $patternParts) . '/',
            function ($match) use(&$i, $quotedNumberedParams, $quotedNamedParams) {
                $match = $match[0];

                if ($match == '?') {
                    $i++;
                    if (isset($quotedNumberedParams[$i])) {
                        return $quotedNumberedParams[$i];
                    }
                } elseif (isset($quotedNamedParams[$match])) {
                    return $quotedNamedParams[$match];
                }

                return $match;
            },
            $sql
        );

        return $sql;
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        if ($params) {
            $hasZeroIndex = array_key_exists(0, $params);
            foreach ($params as $key => $val) {
                $key = ($hasZeroIndex && is_numeric($key)) ? $key + 1 : $key;
                $this->bindValue($key, $val);
            }
        }

        $prepared = $this->interpolateQuery($this->sql, $this->params);

        $this->messageHandler->clear();

        $messages = [];
        $logMessages = function($id, $severity, $state, $line, $text) use (&$messages) {
            $messages[] = $text;
        };

        $this->messageHandler->addLogger($logMessages);

        sybase_deadlock_retry_count(0);

        $this->stmt = sybase_query($prepared, $this->connectionResource);
        $this->messageHandler->removeLogger($logMessages);
        $this->messages = $messages;

        if ($this->messageHandler->hasError()) {
            throw $this->messageHandler->getLastError();
        }
        $this->stmtAffectedRows = sybase_affected_rows($this->connectionResource);
        if (!$this->stmt) {
            throw $this->messageHandler->getLastException();
        }

        if ($this->lastInsertId && is_resource($this->stmt)) {
            $row = sybase_fetch_row($this->stmt);
            if ($row === false || count($row) < 1) {
                throw $this->messageHandler->getLastException();
            }
            $this->lastInsertId->setId($row[0]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        $this->defaultFetchMode          = $fetchMode;
        $this->defaultFetchClass         = $arg2 ?: $this->defaultFetchClass;
        $this->defaultFetchClassCtorArgs = $arg3 ? (array) $arg3 : $this->defaultFetchClassCtorArgs;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        $data = $this->fetchAll();

        return new \ArrayIterator($data);
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null)
    {
        if (is_bool($this->stmt)) {
            return false;
        }

        $args      = func_get_args();
        $fetchMode = $fetchMode ?: $this->defaultFetchMode;

        switch ($fetchMode) {
            case PDO::FETCH_BOTH:
                return sybase_fetch_array($this->stmt) ?: false;
            case PDO::FETCH_NUM:
                return sybase_fetch_row($this->stmt) ?: false;
            case PDO::FETCH_ASSOC:
                return sybase_fetch_assoc($this->stmt) ?: false;
            case PDO::FETCH_OBJ:
            case PDO::FETCH_CLASS:
                $className = $this->defaultFetchClass;
                $ctorArgs  = $this->defaultFetchClassCtorArgs;

                if (count($args) >= 2) {
                    $className = $args[1];
                    $ctorArgs  = (isset($args[2])) ? $args[2] : array();
                }

                // We have to emulate this because sybase_fetch_object($this->stmt, $object) throws a segmentation fault
                $reflection = new \ReflectionClass($className);

                if (count($ctorArgs) > 0) {
                    $object = $reflection->newInstanceArgs($ctorArgs);
                } else {
                    // if a new instance is created by fetch_object function,
                    // it injects properties before calling the the constructor
                    $object = $reflection->newInstanceWithoutConstructor();
                }

                $data = sybase_fetch_assoc($this->stmt) ?: false;

                if ($data === false) {
                    return $data;
                }

                foreach ($data as $name => $value) {
                    if ($reflection->hasProperty($name)) {
                        $prop = $reflection->getProperty($name);
                        if (!$prop->isPublic()) {
                            $prop->setAccessible(true);
                            $prop->setValue($object, $value);
                            $prop->setAccessible(false);
                            continue;
                        }
                    }

                    $object->{$name} = $value;
                }

                if (count($ctorArgs) > 0 && $const = $reflection->getConstructor()) {
                    call_user_func_array(array($object, $const->getName()), $ctorArgs);
                }

                return $object;
        }

        throw new ASEException("Fetch mode is not supported!");
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null)
    {
        $rows = array();

        switch ($fetchMode) {
            case PDO::FETCH_CLASS:
                while ($row = call_user_func_array(array($this, 'fetch'), func_get_args())) {
                    $rows[] = $row;
                }
                break;
            case PDO::FETCH_COLUMN:
                while ($row = $this->fetchColumn()) {
                    $rows[] = $row;
                }
                break;
            default:
                while ($row = $this->fetch($fetchMode)) {
                    $rows[] = $row;
                }
        }

        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        $row = $this->fetch(PDO::FETCH_NUM);

        if (false === $row) {
            return false;
        }

        return isset($row[$columnIndex]) ? $row[$columnIndex] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount()
    {
        return $this->stmtAffectedRows;
    }

    public function getMessages()
    {
        return $this->messages;
    }

    public function __toString()
    {
        return $this->interpolateQuery($this->sql, $this->params);
    }
}
