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

namespace Doctrine\DBAL\Driver\PDOPgSql;

use Doctrine\DBAL\Platforms;
use PDO;

/**
 * Overrides buggy behaviour for the PDO PgSql driver
 *
 * @package Doctrine\DBAL\Driver\PDOPgSql
 * @author Pablo Santiago Sanchez <phackwer@hotmail.com>
 * @since 2.4
 */
class Connection extends \Doctrine\DBAL\Driver\PDOConnection implements \Doctrine\DBAL\Driver\Connection
{
    /**
     * Overrides default PDO::lastInsertId behaviour since it has a buggy call to curr_val
     *
     * Object not in prerequisite state: 7 ERROR:  currval of sequence "sequence_name" is not yet defined in this session
     */
    public function lastInsertId($name = null)
    {
        /**
         * If no sequence name has been given, try the standard behaviour
         */
        if (!$name) {
            return parent::lastInsertId();
        }

        /**
         * The LASTVAL() function has not been implemented until 8.1, so, if postgres is lower than that,
         * we should try the old buggy call to curr_val
         */
        $version = $this->getAttribute(PDO::ATTR_SERVER_VERSION);

        if ($version < '8.1.0') {
            return parent::lastInsertId($name);
        }

        /**
         * Now, some magic
         */
        $sql    = 'SELECT LASTVAL()';
        $stmt   = $this->query($sql);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result === false || !isset($result['lastval'])) {
            throw new Exception("lastInsertId failed: Query was executed but no result was returned.");
        }

        return (int) $result['lastval'];
    }
}
