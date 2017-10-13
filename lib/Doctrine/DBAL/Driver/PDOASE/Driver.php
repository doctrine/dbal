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

namespace Doctrine\DBAL\Driver\PDOASE;

use Doctrine\DBAL\Driver\AbstractASEDriver;
use Doctrine\DBAL\Platforms\ASEPlatform;
use Doctrine\DBAL\Driver\AbstractDbLibDriver;

/**
 * The PDO-based ASE driver.
 *
 * @since 2.6
 */
class Driver extends AbstractASEDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        $this->platformOptions = array();
        if (isset($driverOptions['date_format'])) {
            $this->platformOptions['date_format'] = $driverOptions['date_format'];
        } else {
            $this->platformOptions['date_format'] = ASEPlatform::CS_ANSI;
        }
        if (isset($driverOptions['textsize'])) {
            $this->platformOptions['textsize'] = $driverOptions['textsize'];
        }

        $connection = new Connection(
            AbstractDbLibDriver::constructPdoDsn($params, $driverOptions),
            $username,
            $password,
            $driverOptions
        );

        $this->initializeConnection($connection);

        return $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'pdo_ase';
    }
}
