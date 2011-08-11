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

namespace Doctrine\DBAL\Driver\OCI8;

/**
 * OCI8 implementation of the Connection interface.
 *
 * @since 2.0
 */
class OCI8Connection implements \Doctrine\DBAL\Driver\Connection
{
    private $_dbh;

    private $_executeMode = OCI_COMMIT_ON_SUCCESS;

    /**
     * Create a Connection to an Oracle Database using oci8 extension.
     * 
     * @param string $username
     * @param string $password
     * @param string $db
     */
    public function __construct($username, $password, $db, $charset = null, $sessionMode = OCI_DEFAULT)
    {
        if (!defined('OCI_NO_AUTO_COMMIT')) {
            define('OCI_NO_AUTO_COMMIT', 0);
        }

        $this->_dbh = @oci_connect($username, $password, $db, $charset, $sessionMode);
        if (!$this->_dbh) {
            throw OCI8Exception::fromErrorInfo(oci_error());
        }
    }

    /**
     * Create a non-executed prepared statement.
     * 
     * @param  string $prepareString
     * @return OCI8Statement
     */
    public function prepare($prepareString)
    {
        return new OCI8Statement($this->_dbh, $prepareString, $this->_executeMode);
    }

    /**
     * @param string $sql
     * @return OCI8Statement
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
     * Quote input value.
     *
     * @param mixed $input
     * @param int $type PDO::PARAM* 
     * @return mixed
     */
    public function quote($input, $type=\PDO::PARAM_STR)
    {
        return is_numeric($input) ? $input : "'$input'";
    }

    /**
     *
     * @param  string $statement
     * @return int
     */
    public function exec($statement)
    {
        $stmt = $this->prepare($statement);
        $stmt->execute();
        return $stmt->rowCount();
    }
    
    public function lastInsertId($name = null)
    {
        //TODO: throw exception or support sequences?
    }

    /**
     * Start a transactiom
     *
     * Oracle has to explicitly set the autocommit mode off. That means
     * after connection, a commit or rollback there is always automatically
     * opened a new transaction.
     *
     * @return bool
     */
    public function beginTransaction()
    {
        $this->_executeMode = OCI_NO_AUTO_COMMIT;
        return true;
    }

    /**
     * @throws OCI8Exception
     * @return bool
     */
    public function commit()
    {
        if (!oci_commit($this->_dbh)) {
            throw OCI8Exception::fromErrorInfo($this->errorInfo());
        }
        $this->_executeMode = OCI_COMMIT_ON_SUCCESS;
        return true;
    }

    /**
     * @throws OCI8Exception
     * @return bool
     */
    public function rollBack()
    {
        if (!oci_rollback($this->_dbh)) {
            throw OCI8Exception::fromErrorInfo($this->errorInfo());
        }
        $this->_executeMode = OCI_COMMIT_ON_SUCCESS;
        return true;
    }
    
    public function errorCode()
    {
        $error = oci_error($this->_dbh);
        if ($error !== false) {
            $error = $error['code'];
        }
        return $error;
    }
    
    public function errorInfo()
    {
        return oci_error($this->_dbh);
    }
}
