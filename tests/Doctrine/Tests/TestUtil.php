<?php

namespace Doctrine\Tests;

/**
 * TestUtil is a class with static utility methods used during tests.
 *
 * @author robo
 */
class TestUtil
{
    /**
     * Gets a <b>real</b> database connection using the following parameters
     * of the $GLOBALS array:
     *
     * 'db_type' : The name of the Doctrine DBAL database driver to use.
     * 'db_username' : The username to use for connecting.
     * 'db_password' : The password to use for connecting.
     * 'db_host' : The hostname of the database to connect to.
     * 'db_name' : The name of the database to connect to.
     * 'db_port' : The port of the database to connect to.
     *
     * Usually these variables of the $GLOBALS array are filled by PHPUnit based
     * on an XML configuration file. If no such parameters exist, an SQLite
     * in-memory database is used.
     *
     * IMPORTANT:
     * 1) Each invocation of this method returns a NEW database connection.
     * 2) The database is dropped and recreated to ensure it's clean.
     *
     * @return \Doctrine\DBAL\Connection The database connection instance.
     */
    public static function getConnection()
    {
        if (isset($GLOBALS['db_type'], $GLOBALS['db_username'], $GLOBALS['db_password'],
                $GLOBALS['db_host'], $GLOBALS['db_name'], $GLOBALS['db_port']) &&
           isset($GLOBALS['tmpdb_type'], $GLOBALS['tmpdb_username'], $GLOBALS['tmpdb_password'],
                $GLOBALS['tmpdb_host'], $GLOBALS['tmpdb_name'], $GLOBALS['tmpdb_port'])) {
            $realDbParams = array();
            $tmpDbParams = array();
            $optionsPrefix = 'options_';
            foreach ($GLOBALS as $key => $value) {
                if (strpos($key, 'db_') === 0) {
                    $prefix = 'db_';
                    $params = &$realDbParams;
                } elseif (strpos($key, 'tmpdb_') === 0) {
                    $prefix = 'tmpdb_';
                    $params = &$tmpDbParams;
                } else {
                    continue;
                }
                $key = substr($key, strlen($prefix));

                switch ($key) {
                    case 'type':
                        $params['driver'] = $value;
                        break;
                    case 'username':
                        $params['user'] = $value;
                        break;
                    case 'name':
                        $params['dbname'] = $value;
                        break;
                    case (strpos($key, $optionsPrefix) === 0):
                        $optionKey = substr($key, strlen($optionsPrefix));
                        if (defined($optionKey)) {
                            $optionKey = constant($optionKey);
                        }
                        if (defined($value)) {
                            $value = constant($value);
                        }
                        $params['driverOptions'][$optionKey] = $value;
                        break;
                    case 'host':
                    case 'port':
                    case 'password':
                        $params[$key] = $value;
                        break;
                }
            }

            $realConn = \Doctrine\DBAL\DriverManager::getConnection($realDbParams);

            $platform  = $realConn->getDatabasePlatform();

            if ($platform->supportsCreateDropDatabase()) {
                $dbname = $realConn->getDatabase();
                // Connect to tmpdb in order to drop and create the real test db.
                $tmpConn = \Doctrine\DBAL\DriverManager::getConnection($tmpDbParams);
                $realConn->close();

                $tmpConn->getSchemaManager()->dropAndCreateDatabase($dbname);

                $tmpConn->close();
            } else {
                $sm = $realConn->getSchemaManager();

                /* @var $schema Schema */
                $schema = $sm->createSchema();
                $stmts = $schema->toDropSql($realConn->getDatabasePlatform());

                foreach ($stmts AS $stmt) {
                    $realConn->exec($stmt);
                }
            }

            $conn = \Doctrine\DBAL\DriverManager::getConnection($realDbParams, null, null);
        } else {
            $params = array(
                'driver' => 'pdo_sqlite',
                'memory' => true
            );
            if (isset($GLOBALS['db_path'])) {
                $params['path'] = $GLOBALS['db_path'];
                unlink($GLOBALS['db_path']);
            }
            $conn = \Doctrine\DBAL\DriverManager::getConnection($params);
        }

        if (isset($GLOBALS['db_event_subscribers'])) {
            $evm = $conn->getEventManager();
            foreach (explode(",", $GLOBALS['db_event_subscribers']) AS $subscriberClass) {
                $subscriberInstance = new $subscriberClass();
                $evm->addEventSubscriber($subscriberInstance);
            }
        }

        return $conn;
    }

    /**
     * @return \Doctrine\DBAL\Connection
     */
    public static function getTempConnection()
    {
        $tmpDbParams = array(
            'driver' => $GLOBALS['tmpdb_type'],
            'user' => $GLOBALS['tmpdb_username'],
            'password' => $GLOBALS['tmpdb_password'],
            'host' => $GLOBALS['tmpdb_host'],
            'dbname' => $GLOBALS['tmpdb_name'],
            'port' => $GLOBALS['tmpdb_port']
        );

        // Connect to tmpdb in order to drop and create the real test db.
        return \Doctrine\DBAL\DriverManager::getConnection($tmpDbParams);
    }
}
