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

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver;

/**
 * The SQLite3 driver.
 *
 * @since 2.6
 * @author Ben Morel <ben@benjaminmorel.com>
 * @author Bill Schaller <bill@zeroedin.com>
 */
class Driver extends AbstractSQLiteDriver
{
    /**
     * @var array
     */
    private $userDefinedFunctions = array(
        'sqrt' => array('callback' => array('Doctrine\DBAL\Platforms\SqlitePlatform', 'udfSqrt'), 'numArgs' => 1),
        'mod'  => array('callback' => array('Doctrine\DBAL\Platforms\SqlitePlatform', 'udfMod'), 'numArgs' => 2),
        'locate'  => array('callback' => array('Doctrine\DBAL\Platforms\SqlitePlatform', 'udfLocate'), 'numArgs' => -1),
    );

    /**
     * {@inheritdoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        if (isset($driverOptions['userDefinedFunctions'])) {
            $this->userDefinedFunctions = array_merge(
                $this->userDefinedFunctions, $driverOptions['userDefinedFunctions']);
            unset($driverOptions['userDefinedFunctions']);
        }

        try {
            $connection = new SQLite3Connection(
                !empty($params['path']) ? $params['path'] : ':memory:',
                isset($params['flags']) ? $params['flags'] : null,
                isset($params['encryption_key']) ? $params['encryption_key'] : null
            );
        } catch (SQLite3Exception $e) {
            throw DBALException::driverException($this, $e);
        }

        $sqlite3 = $connection->getSQLite3();

        foreach ($this->userDefinedFunctions as $fn => $data) {
            $sqlite3->createFunction($fn, $data['callback'], $data['numArgs']);
        }

        return $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'sqlite3';
    }
}
