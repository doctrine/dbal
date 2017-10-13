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
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Platforms\ASEPlatform;

/**
 * ASE Connection implementation.
 *
 * @since 2.6
 * @author Maximilian Ruta <mr@xtain.net>
 */
class ASEConnection implements Connection, ServerInfoAwareConnection
{
    /**
     * @const string
     */
    const MASTER_DB = 'master';

    /**
     * @var resource
     */
    protected $connectionResource;

    /**
     * @var ASEMessageHandler
     */
    protected $messageHandler;

    /**
     * @var string
     */
    protected $appname;

    /**
     * @var \Doctrine\DBAL\Driver\ASE\LastInsertId
     */
    protected $lastInsertId;

    /**
     * @var string
     */
    public $database;

    /**
     * @var Driver
     */
    protected $driver;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $charset;

    /**
     * @var string
     */
    protected $server;

    /**
     * @param Driver $driver
     * @param string $server
     * @param array  $driverOptions
     *
     * @throws \Doctrine\DBAL\Driver\ASE\ASEException
     */
    public function __construct(Driver $driver, $server, $driverOptions)
    {
        $this->driver = $driver;
        $this->appname = md5(uniqid());

        $this->server = $server;

        $username = null;
        $password = null;
        $charset = null;
        $database = null;
        if (isset($driverOptions['user'])) {
            $username = $driverOptions['user'];
        }
        $this->username = $username;

        if (isset($driverOptions['password'])) {
            $password = $driverOptions['password'];
        }
        $this->password = $password;

        if (isset($driverOptions['charset'])) {
            $charset = $driverOptions['charset'];
        }
        $this->charset = $charset;

        if (!isset($driverOptions['dbname'])) {
            $driverOptions['dbname'] = self::MASTER_DB;
        }

        $this->database = $database = $driverOptions['dbname'];

        $this->lastInsertId = new LastInsertId();

        ASEMessageHandler::registerLogger();
        ASEMessageHandler::clearGlobal();

        $this->reconnect();
    }

    public function reconnect()
    {
        $this->close();

        $error = null;
        $oldHandler = set_error_handler(function($code, $message, $file, $row) use (&$error) {
            $error = $message;
            return true;
        });
        try {
            $this->connectionResource = sybase_connect($this->server, $this->username, $this->password, $this->charset, $this->appname, true);
        } catch (\Throwable $e) {
            set_error_handler($oldHandler);
            throw DBALException::driverException($this->driver, ASEMessageHandler::fromThrowable($e));
        } catch (\Exception $e) {
            set_error_handler($oldHandler);
            throw DBALException::driverException($this->driver, ASEMessageHandler::fromThrowable($e));
        }
        set_error_handler($oldHandler);

        if (!is_resource($this->connectionResource) || $error !== null) {
            if ($error === null) {
                $error = "Unable to connect";
            }

            throw DBALException::driverException($this->driver, new ASEDriverException($error));
        }

        if ($this->messageHandler instanceof  ASEMessageHandler) {
            $this->messageHandler->setResource($this->connectionResource);
        } else {
            $this->messageHandler = new ASEMessageHandler($this->connectionResource);
        }

        if (!$this->connectionResource) {
            throw DBALException::driverException($this->driver, $this->messageHandler->getLastException());
        }

        if (isset($this->database)) {
            $this->messageHandler->clear();
            sybase_select_db($this->database, $this->connectionResource);

            if ($this->messageHandler->hasError()) {
                throw DBALException::driverException($this->driver, $this->messageHandler->getLastException());
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion()
    {
        $result = $this->prepare('SELECT @@version');
        $result->execute();
        return $result->fetchColumn(0);
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function prepare($sql)
    {
        return new ASEStatement($this, $this->connectionResource, $sql, $this->messageHandler, $this->lastInsertId);
    }

    /**
     * {@inheritDoc}
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
     * @license New BSD, code from Zend Framework
     */
    public function quote($value, $type=null)
    {
        return ASEPlatform::quote($value, $type);
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
        // there is currently no possibility to get the last insert id for specific tables
        return $this->lastInsertId->getId();
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction()
    {
        $this->messageHandler->clear();
        $this->exec('BEGIN TRANSACTION');
        if ($this->messageHandler->hasError()) {
            throw DBALException::driverException($this->driver, $this->messageHandler->getLastError());
        }
    }

    /**
     * {@inheritDoc}
     */
    public function commit()
    {
        $this->messageHandler->clear();
        $this->exec('COMMIT TRANSACTION');
        if ($this->messageHandler->hasError()) {
            throw DBALException::driverException($this->driver, $this->messageHandler->getLastError());
        }
    }

    /**
     * {@inheritDoc}
     */
    public function rollBack()
    {
        $this->messageHandler->clear();
        $this->exec('ROLLBACK TRANSACTION');
        if ($this->messageHandler->hasError()) {
            throw DBALException::driverException($this->driver, $this->messageHandler->getLastError());
        }
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

    public function close()
    {
        if ($this->connectionResource) {
            // sometimes sybase_close not closes the connection directly.
            // To not have the current database in use anymore, we free it by
            // switching to the master database
            sybase_select_db(self::MASTER_DB, $this->connectionResource);

            // because the connection is maybe still alive (because of a bug in sybase_ct)
            // we pick the wooden mallet
            @sybase_query('SELECT syb_quit()', $this->connectionResource);
            @sybase_close($this->connectionResource);
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}