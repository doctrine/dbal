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

namespace Doctrine\DBAL\Connections;


use Doctrine\DBAL\Connection,
    Doctrine\DBAL\Driver,
    Doctrine\DBAL\Configuration,
    Doctrine\Common\EventManager,
    Doctrine\DBAL\Event\ConnectionEventArgs,
    Doctrine\DBAL\Events;

/**
 * Master-Slave Connection
 *
 * Connection can be used with master-slave setups.
 *
 * Important for the understanding of this connection should be how and when
 * it picks the slave or master.
 *
 * 1. Slave if master was never picked before and ONLY if 'getWrappedConnection'
 *    or 'executeQuery' is used.
 * 2. Master picked when 'exec', 'executeUpdate', 'insert', 'delete', 'update', 'createSavepoint',
 *    'releaseSavepoint', 'beginTransaction', 'rollback', 'commit', 'query' or
 *    'prepare' is called.
 * 3. If master was picked once during the lifetime of the connection it will always get picked afterwards.
 * 4. One slave connection is randomly picked ONCE during a request.
 *
 * ATTENTION: You can write to the slave with this connection if you execute a write query without
 * opening up a transaction. For example:
 *
 *      $conn = DriverManager::getConnection(...);
 *      $conn->executeQuery("DELETE FROM table");
 *
 * Be aware that Connection#executeQuery is a method specifically for READ
 * operations only.
 *
 * This connection is limited to slave operations using the
 * Connection#executeQuery operation only, because it wouldn't be compatible
 * with the ORM or SchemaManager code otherwise. Both use all the other
 * operations in a context where writes could happen to a slave, which makes
 * this restricted approach necessary.
 *
 * You can manually connect to the master at any time by calling:
 *
 *      $conn->connect('master');
 *
 * Instantiation through the DriverManager looks like:
 *
 * @example
 *
 * $conn = DriverManager::getConnection(array(
 *    'wrapperClass' => 'Doctrine\DBAL\Connections\MasterSlaveConnection',
 *    'driver' => 'pdo_mysql',
 *    'master' => array('user' => '', 'password' => '', 'host' => '', 'dbname' => ''),
 *    'slaves' => array(
 *        array('user' => 'slave1', 'password', 'host' => '', 'dbname' => ''),
 *        array('user' => 'slave2', 'password', 'host' => '', 'dbname' => ''),
 *    )
 * ));
 *
 * You can also pass 'driverOptions' and any other documented option to each of this drivers to pass additional information.
 *
 * @author Lars Strojny <lstrojny@php.net>
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class MasterSlaveConnection extends Connection
{
    /**
     * Master and slave connection (one of the randomly picked slaves)
     *
     * @var Doctrine\DBAL\Driver\Connection[]
     */
    protected $connections = array('master' => null, 'slave' => null);

    /**
     * Create Master Slave Connection
     *
     * @param array $params
     * @param Driver $driver
     * @param Configuration $config
     * @param EventManager $eventManager
     */
    public function __construct(array $params, Driver $driver, Configuration $config = null, EventManager $eventManager = null)
    {
        if ( !isset($params['slaves']) || !isset($params['master']) ) {
            throw new \InvalidArgumentException('master or slaves configuration missing');
        }
        if ( count($params['slaves']) == 0 ) {
            throw new \InvalidArgumentException('You have to configure at least one slaves.');
        }

        $params['master']['driver'] = $params['driver'];
        foreach ($params['slaves'] as $slaveKey => $slave) {
            $params['slaves'][$slaveKey]['driver'] = $params['driver'];
        }

        parent::__construct($params, $driver, $config, $eventManager);
    }

    /**
     * Check if the connection is currently towards the master or not.
     *
     * @return bool
     */
    public function isConnectedToMaster()
    {
        return $this->_conn !== null && $this->_conn === $this->connections['master'];
    }

    /**
     * {@inheritDoc}
     */
    public function connect($connectionName = 'slave')
    {
        if ( $connectionName !== 'slave' && $connectionName !== 'master' ) {
            throw new \InvalidArgumentException("Invalid option to connect(), only master or slave allowed.");
        }

        $forceMasterAsSlave = false;

        if ($this->getTransactionNestingLevel() > 0) {
            $connectionName = 'master';
            $forceMasterAsSlave = true;
        }

        if ($this->connections[$connectionName]) {
            if ($forceMasterAsSlave) {
                $this->connections['slave'] = $this->_conn = $this->connections['master'];
            } else {
                $this->_conn = $this->connections[$connectionName];
            }
            return false;
        }

        if ($connectionName === 'master') {
            /** Set slave connection to master to avoid invalid reads */
            if ($this->connections['slave']) {
                unset($this->connections['slave']);
            }

            $this->connections['master'] = $this->connections['slave'] = $this->_conn = $this->connectTo($connectionName);
        } else {
            $this->connections['slave'] = $this->_conn = $this->connectTo($connectionName);
        }

        if ($this->_eventManager->hasListeners(Events::postConnect)) {
            $eventArgs = new ConnectionEventArgs($this);
            $this->_eventManager->dispatchEvent(Events::postConnect, $eventArgs);
        }

        return true;
    }

    /**
     * Connect to a specific connection
     *
     * @param  string $connectionName
     * @return Driver
     */
    protected function connectTo($connectionName)
    {
        $params = $this->getParams();

        $driverOptions = isset($params['driverOptions']) ? $params['driverOptions'] : array();

        $connectionParams = $this->chooseConnectionConfiguration($connectionName, $params);

        $user = isset($connectionParams['user']) ? $connectionParams['user'] : null;
        $password = isset($connectionParams['password']) ? $connectionParams['password'] : null;

        return $this->_driver->connect($connectionParams, $user, $password, $driverOptions);
    }

    protected function chooseConnectionConfiguration($connectionName, $params)
    {
        if ($connectionName === 'master') {
            return $params['master'];
        }

        return $params['slaves'][array_rand($params['slaves'])];
    }

    /**
     * {@inheritDoc}
     */
    public function executeUpdate($query, array $params = array(), array $types = array())
    {
        $this->connect('master');
        return parent::executeUpdate($query, $params, $types);
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction()
    {
        $this->connect('master');
        return parent::beginTransaction();
    }

    /**
     * {@inheritDoc}
     */
    public function commit()
    {
        $this->connect('master');
        return parent::commit();
    }

    /**
     * {@inheritDoc}
     */
    public function rollback()
    {
        $this->connect('master');
        return parent::rollback();
    }

    /**
     * {@inheritDoc}
     */
    public function delete($tableName, array $identifier)
    {
        $this->connect('master');
        return parent::delete($tableName, $identifier);
    }

    /**
     * {@inheritDoc}
     */
    public function update($tableName, array $data, array $identifier, array $types = array())
    {
        $this->connect('master');
        return parent::update($tableName, $data, $identifier, $types);
    }

    /**
     * {@inheritDoc}
     */
    public function insert($tableName, array $data, array $types = array())
    {
        $this->connect('master');
        return parent::insert($tableName, $data, $types);
    }

    /**
     * {@inheritDoc}
     */
    public function exec($statement)
    {
        $this->connect('master');
        return parent::exec($statement);
    }

    /**
     * {@inheritDoc}
     */
    public function createSavepoint($savepoint)
    {
        $this->connect('master');

        return parent::createSavepoint($savepoint);
    }

    /**
     * {@inheritDoc}
     */
    public function releaseSavepoint($savepoint)
    {
        $this->connect('master');

        return parent::releaseSavepoint($savepoint);
    }

    /**
     * {@inheritDoc}
     */
    public function rollbackSavepoint($savepoint)
    {
        $this->connect('master');

        return parent::rollbackSavepoint($savepoint);
    }

    public function query()
    {
        $this->connect('master');

        $args = func_get_args();

        $logger = $this->getConfiguration()->getSQLLogger();
        if ($logger) {
            $logger->startQuery($args[0]);
        }

        $statement = call_user_func_array(array($this->_conn, 'query'), $args);

        if ($logger) {
            $logger->stopQuery();
        }

        return $statement;
    }

    public function prepare($statement)
    {
        $this->connect('master');

        return parent::prepare($statement);
    }
}
