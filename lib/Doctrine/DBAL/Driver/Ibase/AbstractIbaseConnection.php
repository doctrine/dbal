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

namespace Doctrine\DBAL\Driver\Ibase;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Platforms\FirebirdPlatform;

/**
 * ibase-api implementation of the Connection interface.
 * 
 * <b>This Driver/Platform is in Beta state</b>
 * 
 * @author Andreas Prucha, Helicon Software Development <prucha@helicon.co.at>
 */
abstract class AbstractIbaseConnection implements Connection, ServerInfoAwareConnection
{

    /**
     * Attribute to set the default transaction isolation level.
     * 
     * @see \Doctrine\DBAL\Connection::TRANSACTION_READ_COMMITTED
     * @see \Doctrine\DBAL\Connection::TRANSACTION_READ_UNCOMMITTED
     * @see \Doctrine\DBAL\Connection::TRANSACTION_REPEATABLE_READ
     * @see \Doctrine\DBAL\Connection::TRANSACTION_SERIALIZABLE
     * 
     */
    const ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL = 'doctrineTransactionIsolationLevel';

    /**
     * Transaction wait timeout in case of an locking conflict
     */
    const ATTR_DOCTRINE_DEFAULT_TRANS_WAIT = 'doctrineTransactionWait';

    /**
     * @var string Full database identifier passed to ibase_connect
     */
    protected $dbs;

    /**
     * @var string Connection Username
     */
    protected $username;

    /**
     * @var string Connection Password
     */
    protected $password;

    /**
     * @var ressource ibase api connection ressource
     */
    protected $ibaseConnectionRc;

    /**
     * @var int Transaction Depth. Should never be > 1
     */
    protected $transactionDepth = 0;

    /**
     * @var ressource Ressource of the active transaction. 
     */
    protected $ibaseActiveTransactionRc = null;

    /**
     * Isolation level used when a transaction is started
     * @var integer 
     */
    protected $attrDcTransIsolationLevel = \Doctrine\DBAL\Connection::TRANSACTION_READ_COMMITTED;

    /**
     * Wait timeout used in transactions
     * 
     * @var integer  Number of seconds to wait.
     */
    protected $attrDcTransWait = 5;

    /**
     * True if auto-commit is enabled
     * @var boolean 
     */
    protected $attrAutoCommit = true;

    /**
     * {@inheritDoc}
     * @param array  $params
     * @param string $username
     * @param string $password
     * @param array  $driverOptions
     * 
     * <b>driverOptions</b>
     * 'TRANSACTION_FLAGS'. 
     *
     * @throws \Doctrine\DBAL\Driver\Ibase\IbaseException
     */
    public function __construct(array $params, $username, $password, array $driverOptions = array())
    {
        $this->ibaseConnectionRc = null;
        $this->dbs = $this->makeDbString($params);
        $this->username = $username;
        $this->password = $password;
        foreach ($driverOptions as $k => $v) {
            $this->setAttribute($k, $v);
        }
        $this->getActiveTransactionIbaseRes();
    }

    public function __destruct()
    {
        if ($this->transactionDepth > 0) {
            // Auto-Rollback explicite transactions
            $this->rollback();
        }
        $this->autoCommit();
        @ibase_close($this->ibaseConnectionRc);
    }

    /**
     * {@inheritDoc}
     * 
     * Additionally to the standard driver attributes, the attribute 
     * {@link self::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL} can be used to control 
     * the isolation level used for transactions
     * 
     * @param type $attribute
     * @param type $value
     * @return type
     */
    public function setAttribute($attribute, $value)
    {
        switch ($attribute) {
            case self::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL: {
                    $this->attrDcTransIsolationLevel = $value;
                    break;
                }
            case self::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT: {
                    $this->attrDcTransWait = $value;
                    break;
                }
            case \PDO::ATTR_AUTOCOMMIT: {
                    $this->attrAutoCommit = $value;
                }
        }
    }

    /**
     * {@inheritDoc}
     * 
     * @param type $attribute
     * @return type
     */
    public function getAttribute($attribute)
    {
        switch ($attribute) {
            case self::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL: {
                    return $this->attrDcTransIsolationLevel;
                }
            case self::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT: {
                    return $this->attrDcTransWait;
                }
            case \PDO::ATTR_AUTOCOMMIT: {
                    return $this->attrAutoCommit;
                }
        }
    }

    /**
     * Checks ibase_error and raises an exception if an error occured
     * 
     * @throws IbaseException
     */
    protected function checkLastApiCall()
    {
        $lastError = $this->errorInfo();
        if (isset($lastError['code']) && $lastError['code']) {
            throw IbaseException::fromErrorInfo($lastError);
        }
    }

    /**
     * Returns the current transaction context resource
     * 
     * Inside an active transaction, the current transaction resource ({@link $activeTransactionIbaseRes}) is returned,
     * Otherwise the function returns the connection resource ({@link $connectionIbaseRes}).
     * 
     * If the connection is not open, it gets opened.
     * 
     * @return resource|null
     */
    public function getActiveTransactionIbaseRes()
    {
        if (!$this->ibaseConnectionRc || !is_resource($this->ibaseConnectionRc)) {
            $this->ibaseConnectionRc = @ibase_connect($this->dbs, $this->username, $this->password);
            if (!is_resource($this->ibaseConnectionRc)) {
                $this->checkLastApiCall();
            }

            if (!is_resource($this->ibaseConnectionRc)) {
                throw IbaseException::fromErrorInfo($this->errorInfo());
            }

            $this->ibaseActiveTransactionRc = $this->internalBeginTransaction(true);
        }
        if ($this->ibaseActiveTransactionRc && is_resource($this->ibaseActiveTransactionRc)) {
            return $this->ibaseActiveTransactionRc;
        }
    }

    /**
     * Constructs an connection string for {@link PHP_MANUAL#ibase_connect}
     * 
     * @param type $params
     * @return type
     */
    protected function makeDbString($params)
    {
        $result = '';
        $params = array_merge(
                array('host' => null, 'port' => null), $params
        );
        if (!empty($params['host'])) {
            $result .= $params['host'];
        }
        if (!empty($params['port'])) {
            if (!empty($result)) {
                $result .= '/';
            }
            $result .= $params['port'];
        }
        if (!empty($params['dbname'])) {
            if (!empty($result)) {
                $result .= ':';
            }
            $result .= $params['dbname'];
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \UnexpectedValueException if the version string returned by the database server
     *                                   does not contain a parsable version number.
     */
    public function getServerVersion()
    {
        return ibase_server_info($this->ibaseConnectionRc, IBASE_SVC_SERVER_VERSION);
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     * 
     * Creates a new instance of AbstractIbaseStatement
     * 
     * @param string $prepareString SQL Statement
     * @return \Doctrine\DBAL\Driver\Ibase\AbstractIbaseStatement
     */
    abstract public function prepare($prepareString);

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        $args = func_get_args();
        $sql = $args[0];
//$fetchMode = $args[1];
        $stmt = $this->prepare($sql);
        $stmt->execute();

        return $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function quote($value, $type = \PDO::PARAM_STR)
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        $value = str_replace("'", "''", $value);

        return "'" . addcslashes($value, "\000\n\r\\\032") . "'";
    }

    /**
     * {@inheritdoc}
     */
    public function exec($statement)
    {
        $stmt = $this->prepare($statement);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        if ($name === null) {
            return false;
        }

        $sql = 'SELECT GEN_ID(' . $name . ', 0) LAST_VAL FROM RDB$DATABASE';
        $stmt = $this->query($sql);
        $result = $stmt->fetchColumn(0);

        return $result;
    }

    /**
     * Returns the current execution mode.
     *
     * @return integer
     */
    public function getExecuteMode()
    {
        return $this->executeMode;
    }

    /**
     * Generates an SET TRANSACTION statement used to start an transaction
     * 
     * @param type $isolationLevel
     * @param type $timeout
     * @return string
     */
    public function getStartTransactionSql($isolationLevel, $timeout = 5)
    {
        switch ($isolationLevel) {
            case \Doctrine\DBAL\Connection::TRANSACTION_READ_UNCOMMITTED: {
                    $result .= 'SET TRANSACTION READ WRITE ISOLATION LEVEL READ COMMITTED RECORD_VERSION';
                    break;
                }
            case \Doctrine\DBAL\Connection::TRANSACTION_READ_COMMITTED: {
                    $result .= 'SET TRANSACTION READ WRITE ISOLATION LEVEL READ COMMITTED RECORD_VERSION';
                    break;
                }
            case \Doctrine\DBAL\Connection::TRANSACTION_REPEATABLE_READ: {
                    $result .= 'SET TRANSACTION READ WRITE ISOLATION LEVEL SNAPSHOT ';
                    break;
                }
            case \Doctrine\DBAL\Connection::TRANSACTION_SERIALIZABLE: {
                    $result .= 'SET TRANSACTION READ WRITE ISOLATION LEVEL SNAPSHOT TABLE STABILITY';
                    break;
                }
        }
        $result .= ($this->attrDcTransWait > 0) ? ' WAIT LOCK TIMEOUT ' . $this->attrDcTransWait : ' NO WAIT';
        return $result;
    }

    /**
     * Starts a new transaction and returns the transaction handle
     * 
     * @param resource $commitDefaultTransaction
     */
    protected function internalBeginTransaction($commitDefaultTransaction = true)
    {
        if ($commitDefaultTransaction) {
            @ibase_commit($this->ibaseConnectionRc);
        }
        $result = @ibase_query($this->ibaseConnectionRc, $this->getStartTransactionSql($this->attrDcTransIsolationLevel));
        if (is_resource($result)) {
            $this->checkLastApiCall();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     * 
     */
    public function beginTransaction()
    {
        if ($this->transactionDepth < 1) {
            $this->ibaseActiveTransactionRc = $this->internalBeginTransaction(true);
            $this->transactionDepth++;
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        if ($this->transactionDepth > 0) {
            $res = @ibase_commit($this->ibaseActiveTransactionRc);
            if (!$res) {
                $this->checkLastApiCall();
            }
            $this->transactionDepth--;
        }
        $this->ibaseActiveTransactionRc = $this->internalBeginTransaction(true);
        return true;
    }

    /**
     * Commits the transaction if autocommit is enabled no explicte transaction has been started
     */
    public function autoCommit()
    {
        if ($this->attrAutoCommit && $this->transactionDepth < 1) {
            $result = @ibase_commit_ret($this->getActiveTransactionIbaseRes());
            if (!$result) {
                $this->checkLastApiCall();
            }
            return $result;
        } else {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if ($this->transactionDepth > 0) {
            $res = @ibase_rollback($this->ibaseActiveTransactionRc);
            if (!$res) {
                $this->checkLastApiCall();
            }
            $this->transactionDepth--;
        }
        $this->ibaseActiveTransactionRc = $this->internalBeginTransaction(true);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        return ibase_errcode();
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        $errorCode = $this->errorCode();
        if ($errorCode) {
            return array(
                'code' => $this->errorCode(),
                'message' => ibase_errmsg());
        } else {
            return array('code' => 0, 'message' => null);
        }
    }

}
