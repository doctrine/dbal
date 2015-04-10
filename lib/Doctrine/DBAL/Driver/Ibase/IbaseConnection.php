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
 */
class IbaseConnection implements Connection, ServerInfoAwareConnection
{

    /**
     * @var ressource ibase api connection ressource
     */
    protected $dbh;

    /**
     * Count the number of nested transactions
     * This is used to simulate autocommit;
     * @var integer 
     */
    protected $transactionDepth = 0;

    /**
     * @param array  $params
     * @param string $username
     * @param string $password
     * @param array  $driverOptions
     *
     * @throws \Doctrine\DBAL\Driver\Ibase\IbaseException
     */
    public function __construct(array $params, $username, $password, array $driverOptions = array())
    {
        $dbs = $this->makeDbString($params);

        $this->dbh = ibase_connect($dbs, $username, $password);
    }

    public function __destruct()
    {
        if (is_resource($this->dbh)) {
            @ibase_commit($this->dbh);
            @ibase_close($this->dbh);
            $this->dbh = null;
        }
    }

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
        return ibase_server_info($this->dbh, IBASE_SVC_SERVER_VERSION);
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($prepareString)
    {
        return new IbaseStatement($this->dbh, $prepareString, $this);
    }

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
        
        $inExpliciateTransaction = $this->transactionDepth > 0;
        
        try {
            $stmt = $this->prepare($statement);
            $stmt->execute();
            $result = $stmt->rowCount();
            
            if (!$inExpliciateTransaction) {
                ibase_commit_ret($this->dbh);
            }
        } catch (\Exception $ex) {
            if (!$inExpliciateTransaction) {
                @ibase_rollback_ret($this->dbh);
            }
            throw $ex;
        }


        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        if ($name === null) {
            return false;
        }

        Interbase / FirebirdPlatform::assertValidIdentifier($name);

        $sql = 'SELECT ' . $name . '.CURRVAL FROM DUAL';
        $stmt = $this->query($sql);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result === false || !isset($result['CURRVAL'])) {
            throw new IbaseException("lastInsertId failed: Query was executed but no result was returned.");
        }

        return (int) $result['CURRVAL'];
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
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        if (!ibase_trans(IBASE_READ | IBASE_WRITE | IBASE_REC_VERSION | IBASE_NOWAIT, $this->dbh)) {
            throw IbaseException::fromErrorInfo($this->errorInfo());
        }

        $this->transactionDepth++;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        if (!ibase_commit($this->dbh)) {
            throw IbaseException::fromErrorInfo($this->errorInfo());
        }

        $this->transactionDepth--;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function rollBack()
    {
        if (!ibase_rollback($this->dbh)) {
            throw IbaseException::fromErrorInfo($this->errorInfo());
        }

        $this->transactionDepth--;

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
        return array(
            'code' => $this->errorCode(),
            'message' => ibase_errmsg());
    }

}
