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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AkibanServerPlatform;
use Doctrine\DBAL\Schema\AkibanServerSchemaManager;

/**
 * Driver that connects to Akiban Server through pgsql.
 *
 * @author Padraig O'Sullivan <osullivan.padraig@gmail.com>
 * @since  2.4
 */
class Driver implements \Doctrine\DBAL\Driver
{
    /**
     * {@inheritDoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        return new AkibanSrvConnection(
            $this->constructConnectionString($params, $username, $password)
        );
    }

    /**
     * Constructs the Akiban Server connection string.
     *
     * @return string The connection string.
     */
    private function constructConnectionString(array $params, $username, $password)
    {
        $connString = '';
        if (! empty($params['host'])) {
            $connString .= 'host=' . $params['host'] . ' ';
        }
        if (! empty($params['port'])) {
            $connString .= 'port=' . $params['port'] . ' ';
        }
        if (! empty($params['dbname'])) {
            $connString .= 'dbname=' . $params['dbname'] . ' ';
        }
        if (! empty($username)) {
            $connString .= 'user=' . $username . ' ';
        }
        if (! empty($password)) {
            $connString .= 'user=' . $username . ' ';
        }

        return $connString;
    }

    /**
     * {@inheritDoc}
     */
    public function getDatabasePlatform()
    {
        return new AkibanServerPlatform();
    }

    /**
     * {@inheritDoc}
     */
    public function getSchemaManager(Connection $conn)
    {
        return new AkibanServerSchemaManager($conn);
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'akibansrv';
    }

    /**
     * {@inheritDoc}
     */
    public function getDatabase(Connection $conn)
    {
        $params = $conn->getParams();
        return $params['dbname'];
    }
}

