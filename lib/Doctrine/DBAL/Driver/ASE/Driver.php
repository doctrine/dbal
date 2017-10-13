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

use Doctrine\DBAL\Driver\AbstractASEDriver;

/**
 * Driver for ext/sybase_ct.
 *
 * @since 2.6
 * @author Maximilian Ruta <mr@xtain.net>
 */
class Driver extends AbstractASEDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        $server = $params['host'];

        if (isset($params['dbname'])) {
            $driverOptions['dbname'] = $params['dbname'];
        }

        if (isset($params['charset'])) {
            $driverOptions['charset'] = $params['charset'];
        }

        if (isset($username)) {
            $driverOptions['user'] = $username;
        }

        if (isset($username)) {
            $driverOptions['password'] = $password;
        }

        $this->platformOptions = array();
        if (isset($driverOptions['date_format'])) {
            $this->platformOptions['date_format'] = $driverOptions['date_format'];
        } else {
            $this->platformOptions['date_format'] = ASEPlatform::CS_DATES_SHORT_ALT;
        }
        if (isset($driverOptions['textsize'])) {
            $this->platformOptions['textsize'] = $driverOptions['textsize'];
        }

        $connection = new ASEConnection($this, $server, $driverOptions);

        $this->initializeConnection($connection);

        return $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'ase';
    }
}
