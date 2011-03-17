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


namespace Doctrine\DBAL\Portability;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;

class Connection extends \Doctrine\DBAL\Connection
{
    const PORTABILITY_ALL               = 255;
    const PORTABILITY_NONE              = 0;
    const PORTABILITY_RTRIM             = 1;
    const PORTABILITY_EMPTY_TO_NULL     = 4;
    const PORTABILITY_FIX_CASE          = 8;
    
    const PORTABILITY_ORACLE            = 9;
    const PORTABILITY_POSTGRESQL        = 13;
    const PORTABILITY_SQLITE            = 13;
    const PORTABILITY_OTHERVENDORS      = 12;
    
    /**
     * @var int
     */
    private $portability = self::PORTABILITY_NONE;
    
    /**
     * @var int
     */
    private $case = \PDO::CASE_NATURAL;
    
    public function connect()
    {
        $ret = parent::connect();
        if ($ret) {       
            $params = $this->getParams();
            if (isset($params['portability'])) {
                if ($this->_platform->getName() === "oracle") {
                    $params['portability'] = $params['portability'] & self::PORTABILITY_ORACLE;
                } else if ($this->_platform->getName() === "postgresql") {
                    $params['portability'] = $params['portability'] & self::PORTABILITY_POSTGRESQL;
                } else if ($this->_platform->getName() === "sqlite") {
                    $params['portability'] = $params['portability'] & self::PORTABILITY_SQLITE;
                } else {
                    $params['portability'] = $params['portability'] & self::PORTABILITY_OTHERVENDORS;
                }
                $this->portability = $params['portability'];
            }
            if (isset($params['fetch_case']) && $this->portability & self::PORTABILITY_FIX_CASE) {
                if ($this->_conn instanceof \Doctrine\DBAL\Driver\PDOConnection) {
                    // make use of c-level support for case handling
                    $this->_conn->setAttribute(\PDO::ATTR_CASE, $params['fetch_case']);
                } else {
                    $this->case = ($params['fetch_case'] == \PDO::CASE_LOWER) ? CASE_LOWER : CASE_UPPER;
                }
            }    
        }
        return $ret;
    }
    
    public function getPortability()
    {
        return $this->portability;
    }
    
    public function getFetchCase()
    {
        return $this->case;
    }
    
    public function executeQuery($query, array $params = array(), $types = array())
    {
        return new Statement(parent::executeQuery($query, $params, $types), $this);
    }
    
    /**
     * Prepares an SQL statement.
     *
     * @param string $statement The SQL statement to prepare.
     * @return Doctrine\DBAL\Driver\Statement The prepared statement.
     */
    public function prepare($statement)
    {
        return new Statement(parent::prepare($statement), $this);
    }
    
    public function query()
    {
        $this->connect();

        $stmt = call_user_func_array(array($this->_conn, 'query'), func_get_args());
        return new Statement($stmt, $this);
    }
}
