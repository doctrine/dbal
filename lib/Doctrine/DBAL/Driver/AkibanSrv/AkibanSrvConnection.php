<?php
/*
 *  $Id$
 *
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

namespace Doctrine\DBAL\Driver\AkibanSrv;

use Doctrine\DBAL\Platforms\AkibanServerPlatform;

/**
 * Akiban Server implementation of the Connection interface.
 *
 * @author Padraig O'Sullivan <osullivan.padraig@gmail.com>
 * @since 2.3
 */
class AkibanSrvConnection implements \Doctrine\DBAL\Driver\Connection
{
    /**
     * @var resource
     */
    protected $_dbh;

    /**
     * Create a Connection to an Akiban Server Database using 
     * the native PostgreSQL PHP driver.
     *
     * @param string $username
     * @param string $password
     * @param string $schema
     */
    public function __construct($connectionString)
    {
        $this->_dbh = pg_connect($connectionString);
        if ( ! $this->_dbh ) {
            throw AkibanSrvException::fromErrorString("Failed to connect to Akiban Server.");
        }
    }

    /**
     * Create a non-executed prepared statement.
     *
     * @param  string $prepareString
     * @return AkibanSrvStatement that has not been executed
     */
    public function prepare($prepareString)
    {
        return new AkibanSrvStatement($this->_dbh, $prepareString, $this);
    }

    /**
     * Create an executed prepared statement.
     *
     * @param  string $sql
     * @return AkibanSrvStatement that has been executed
     */
    public function query()
    {
        $args = func_get_args();
        $sql = $args[0];
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
    public function quote($value, $type=\PDO::PARAM_STR)
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        $value = str_replace("'", "''", $value);
        return "'" . addcslashes($value, "\000\n\r\\\032") . "'";
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

    /**
     * {@inheritDoc}
     */
    public function lastInsertId($name = null)
    {
        if ($name === null) {
            return false;
        }

        // TODO
        return 0;
    }

    /**
     * Start a transactiom
     *
     * @return bool
     */
    public function beginTransaction()
    {
        $trxStatus = pg_transaction_status($this->_dbh);
        if (! $trxStatus == PGSQL_TRANSACTION_INTRANS) {
            if (! pg_query($this->_dbh, "BEGIN")) {
                throw AkibanSrvException::fromErrorString($this->errorInfo());
            }
        }
        return true;
    }

    /**
     * @return bool
     */
    public function commit()
    {
        if (! pg_query($this->_dbh, "COMMIT")) {
            throw AkibanSrvException::fromErrorString($this->errorInfo());
        }
        return true;
    }

    /**
     * @return bool
     */
    public function rollBack()
    {
        $trxStatus = pg_transaction_status($this->_dbh);
        if ($trxStatus == PGSQL_TRANSACTION_INTRANS) {
            if (! pg_query($this->_dbh, "ROLLBACK")) {
                throw AkibanSrvException::fromErrorString($this->errorInfo());
            }
        }
        return true;
    }

    public function errorCode()
    {
        // TODO - this returns error message, not error code
        return pg_last_error($this->_dbh);
    }

    public function errorInfo()
    {
        return pg_last_error($this->_dbh);
    }
}
