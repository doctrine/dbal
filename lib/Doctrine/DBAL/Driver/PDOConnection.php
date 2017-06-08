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

namespace Doctrine\DBAL\Driver;

use PDO;

/**
 * PDO implementation of the Connection interface.
 * Used by all PDO-based drivers.
 *
 * @since 2.0
 */
class PDOConnection extends PDO implements Connection, ServerInfoAwareConnection
{
    /**
     * @var LastInsertId
     */
    private $lastInsertId;

    /**
     * @param string      $dsn
     * @param string|null $user
     * @param string|null $password
     * @param array|null  $options
     *
     * @throws PDOException in case of an error.
     */
    public function __construct($dsn, $user = null, $password = null, array $options = null)
    {
        try {
            parent::__construct($dsn, $user, $password, $options);
            $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('Doctrine\DBAL\Driver\PDOStatement', array($this)));
            $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }

        $this->lastInsertId = new LastInsertId();
    }

    /**
     * {@inheritdoc}
     */
    public function exec($statement)
    {
        try {
            $result = parent::exec($statement);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }

        $this->trackLastInsertId();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion()
    {
        return PDO::getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($prepareString, $driverOptions = array())
    {
        try {
            return parent::prepare($prepareString, $driverOptions);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        $args = func_get_args();
        $argsCount = count($args);

        try {
            if ($argsCount == 4) {
                $stmt = parent::query($args[0], $args[1], $args[2], $args[3]);

                $this->trackLastInsertId();

                return $stmt;
            }

            if ($argsCount == 3) {
                $stmt = parent::query($args[0], $args[1], $args[2]);

                $this->trackLastInsertId();

                return $stmt;
            }

            if ($argsCount == 2) {
                $stmt = parent::query($args[0], $args[1]);

                $this->trackLastInsertId();

                return $stmt;
            }

            $stmt = parent::query($args[0]);


            $this->trackLastInsertId();

            return $stmt;
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function quote($input, $type = \PDO::PARAM_STR)
    {
        return parent::quote($input, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        if (null === $name) {
            return $this->lastInsertId->get();
        }

        try {
            return $this->fetchLastInsertId($name);
        } catch (\PDOException $exception) {
            return '0';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion()
    {
        return false;
    }

    /**
     * Tracks the last insert ID at the current state.
     *
     * If this PDO driver is not able to fetch the last insert ID for identity columns
     * without influencing connection state or transaction state, this is a noop method.
     *
     * @return void
     */
    public function trackLastInsertId()
    {
        // We need to avoid unnecessary exception generation for drivers not supporting this feature,
        // by temporarily disabling exception mode.
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);

        $lastInsertId = $this->fetchLastInsertId();

        // Reactivate exception mode.
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        if (null === $lastInsertId) {
            // In case this driver implementation does not support this feature
            // or an error occurred while retrieving the last insert ID, there is nothing to track here.
            return;
        }

        $this->lastInsertId->set($lastInsertId);
    }

    /**
     * Fetches the last insert ID generated by this connection.
     *
     * This method queries the database connection for the last insert ID.
     *
     * @param string|null $sequenceName The name of the sequence to retrieve the last insert ID from,
     *                                  if not given the overall last insert ID is returned.
     *
     * @return string The last insert ID or '0' in case the last insert ID generated on this connection is unknown.
     *
     * @throws \PDOException in case of an error.
     */
    protected function fetchLastInsertId($sequenceName = null)
    {
        return parent::lastInsertId($sequenceName);
    }
}
