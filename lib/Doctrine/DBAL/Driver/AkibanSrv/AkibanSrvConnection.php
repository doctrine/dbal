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

namespace Doctrine\DBAL\Driver\AkibanSrv;

use Doctrine\DBAL\Platforms\AkibanServerPlatform;

/**
 * Akiban Server implementation of the Connection interface.
 *
 * @author Padraig O'Sullivan <osullivan.padraig@gmail.com>
 * @since  2.4
 */
class AkibanSrvConnection implements \Doctrine\DBAL\Driver\Connection
{
    /**
     * Internal handle for this connection.
     * @var resource
     */
    protected $connectionHandle;

    /**
     * Create a Connection to an Akiban Server Database using 
     * the native PostgreSQL PHP driver.
     *
     * @param string $connectionString
     */
    public function __construct($connectionString)
    {
        $this->connectionHandle= pg_connect($connectionString);
        if (! $this->connectionHandle) {
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
        return new AkibanSrvStatement($this->connectionHandle, 
                                      $prepareString);
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
     * {@inheritDoc}
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
     * {@inheritDoc}
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

        $sql = "SELECT CURRENT VALUE FOR " . $name;
        $stmt = $this->query($sql);
        $result = $stmt->fetchColumn(0);

        if ($result === false) {
            throw new AkibanSrvException("lastInsertId failed due to current value not being returned for a sequence.");
        }
        return (int) $result[0];
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction()
    {
        $trxStatus = pg_transaction_status($this->connectionHandle);
        if (! $trxStatus == PGSQL_TRANSACTION_INTRANS && 
            ! pg_query($this->connectionHandle, "BEGIN")) {
            throw AkibanSrvException::fromErrorString($this->errorInfo());
        }
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function commit()
    {
        if (! pg_query($this->connectionHandle, "COMMIT")) {
            throw AkibanSrvException::fromErrorString($this->errorInfo());
        }
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function rollBack()
    {
        $trxStatus = pg_transaction_status($this->connectionHandle);
        if ($trxStatus == PGSQL_TRANSACTION_INTRANS && 
            ! pg_query($this->connectionHandle, "ROLLBACK")) {
            throw AkibanSrvException::fromErrorString($this->errorInfo());
        }
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function errorCode()
    {
        // TODO - this returns error message, not error code
        return pg_last_error($this->connectionHandle);
    }

    /**
     * {@inheritDoc}
     */
    public function errorInfo()
    {
        return pg_last_error($this->connectionHandle);
    }
}

