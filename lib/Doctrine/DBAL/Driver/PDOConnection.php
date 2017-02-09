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

use Doctrine\DBAL\ParameterType;
use PDO;
use function count;
use function func_get_args;

/**
 * PDO implementation of the Connection interface.
 * Used by all PDO-based drivers.
 *
 * @since 2.0
 */
class PDOConnection extends PDO implements Connection, ServerInfoAwareConnection
{
    /** @var string */
    private $lastInsertId = '0';

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
            $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, ['Doctrine\DBAL\Driver\PDOStatement', [$this]]);
            $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
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
    public function prepare($prepareString, $driverOptions = [])
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
    public function quote($input, $type = ParameterType::STRING)
    {
        return parent::quote($input, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        if ($name !== null) {
            return parent::lastInsertId($name);
        }

        if ($this->supportsTrackingLastInsertId()) {
            return $this->lastInsertId;
        }

        try {
            return parent::lastInsertId();
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

    public function trackLastInsertId()
    {
        if (! $this->supportsTrackingLastInsertId()) {
            return;
        }

        // We need to avoid unnecessary exception generation for drivers not supporting this feature,
        // by temporarily disabling exception mode.
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);

        $lastInsertId = parent::lastInsertId();

        // Reactivate exception mode.
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        if ($lastInsertId === null) {
            // In case this driver implementation does not support this feature
            // or an error occurred while retrieving the last insert ID, simply return the last tracked insert ID.
            return;
        }

        // The last insert ID is reset to "0" in certain situations by some implementations,
        // therefore we keep the previously set insert ID locally.
        if ('0' !== $lastInsertId) {
            $this->lastInsertId = $lastInsertId;
        }
    }

    /**
     * Returns whether this PDO driver is able to fetch the last insert ID for identity columns
     * after each executed statement(even if it might not have created an ID)
     * without influencing connection state and transaction state.
     *
     * Drivers like "pdo_pgsql" for example cannot safely try to retrieve the last insert ID without causing an error
     * that might cause active transactions to fail.
     *
     * @return bool
     */
    protected function supportsTrackingLastInsertId()
    {
        return true;
    }
}
