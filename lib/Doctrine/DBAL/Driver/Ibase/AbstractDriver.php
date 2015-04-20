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

namespace Doctrine\DBAL\Driver\Ibase;

use Doctrine\DBAL\DBALException;

/**
 * A Doctrine DBAL driver for Interbase/Firebird based on the ibase-api (ibase)
 * 
 * NOTE: This driver can theoretically be used for Firebird and Interbase, but the used Platform class
 * is untested for Interbase at the moment
 *
 * @author Andreas Prucha <prucha@helicon.co.at>
 */
abstract class AbstractDriver extends \Doctrine\DBAL\Driver\AbstractFbIbDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        parent::connect($params, $username, $password, $driverOptions);
        try {
            $connectionClass = $this->getDriverConnectionClass();
            return new $connectionClass(
                $params,
                $username,
                $password,
                $driverOptions);
        } catch (IbaseException $e) {
            throw DBALException::driverException($this, $e);
        }
    }
    
    /**
     * @return string Class name of the Connection object used in this driver
     */
    abstract protected function getDriverConnectionClass();
    
}
