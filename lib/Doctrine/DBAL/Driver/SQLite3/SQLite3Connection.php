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

namespace Doctrine\DBAL\Driver\SQLite3;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;

/**
 * SQLite3 implementation of the Connection interface.
 *
 * @since 2.6
 * @author Ben Morel <ben@benjaminmorel.com>
 * @author Bill Schaller <bill@zeroedin.com>
 */
class SQLite3Connection extends SQLite3Abstract implements Connection, ServerInfoAwareConnection
{
    /**
     * @param string      $filename
     * @param int|null    $flags
     * @param string|null $encryption_key
     *
     * @throws SQLite3Exception
     */
    public function __construct($filename, $flags = null, $encryption_key = null)
    {
        try {
            if ($flags === null) {
                $this->sqlite3 = new \SQLite3($filename);
            } elseif ($encryption_key === null) {
                $this->sqlite3 = new \SQLite3($filename, $flags);
            } else {
                $this->sqlite3 = new \SQLite3($filename, $flags, $encryption_key);
            }
        } catch (\Exception $e) {
            throw $this->wrapDriverException($e);
        }
    }

    /**
     * Returns the underlying SQLite3 object.
     *
     * @return \SQLite3
     */
    public function getSQLite3()
    {
        return $this->sqlite3;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($prepareString)
    {
        try {
            $statement = @ $this->sqlite3->prepare($prepareString);
        } catch (\Exception $e) {
            throw $this->wrapDriverException($e);
        }

        $this->throwExceptionOnError();

        return new SQLite3Statement($this->sqlite3, $statement);
    }

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        $query = func_get_arg(0);

        $statement = $this->prepare($query);
        $statement->execute();

        return $statement;
    }

    /**
     * {@inheritdoc}
     */
    public function quote($input, $type = \PDO::PARAM_STR)
    {
        return "'" . \SQLite3::escapeString($input) . "'";
    }

    /**
     * {@inheritdoc}
     */
    public function exec($statement)
    {
        try {
            $this->sqlite3->exec($statement);
        } catch (\Exception $e) {
            throw $this->wrapDriverException($e);
        }

        $this->throwExceptionOnError();

        return $this->sqlite3->changes();
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        try {
            return $this->sqlite3->lastInsertRowID();
        } catch (\Exception $e) {
            throw $this->wrapDriverException($e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        try {
            $this->sqlite3->exec('BEGIN');
        } catch (\Exception $e) {
            throw $this->wrapDriverException($e);
        }

        $this->throwExceptionOnError();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        try {
            $this->sqlite3->exec('COMMIT');
        } catch (\Exception $e) {
            throw $this->wrapDriverException($e);
        }

        $this->throwExceptionOnError();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function rollBack()
    {
        try {
            $this->sqlite3->exec('ROLLBACK');
        } catch (\Exception $e) {
            throw $this->wrapDriverException($e);
        }

        $this->throwExceptionOnError();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        return $this->sqlite3->lastErrorCode();
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return [
            null,
            $this->sqlite3->lastErrorCode(),
            $this->sqlite3->lastErrorMsg()
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion()
    {
        return \SQLite3::version()['versionString'];
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion()
    {
        return false;
    }
}
