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

namespace Doctrine\DBAL\Driver\PDOInformix;

use Doctrine\DBAL\Driver\PDOConnection;

/**
 * {@inheritdoc}
 */
class Connection extends PDOConnection
{

    /**
     * {@inheritdoc}
     */
    function quote($input, $type=\PDO::PARAM_STR) {

      if ($type == \PDO::PARAM_INT ) {
          return $input;
      }

      return "'" . str_replace("'", "''", $input) . "'";

    }

    /**
     * {@inheritdoc}
     *
     * @see lastSequenceValue()
     */
    public function lastInsertId($name = null)
    {
        return is_string($name)
            ? $this->lastSequenceValue($name)
            : parent::lastInsertId($name);
    }

    /**
     * Returns the last value of a sequence.
     *
     * The PDO_INFORMIX driver doesn't returns the last retrieved value of the
     * sequence when a sequence name is specified in the PDO::lastInsertId()
     * method, this method provides this functionality executing a SQL 
     * statement with the CURRVAL operator.
     *
     * @link http://php.net/manual/en/pdo.lastinsertid.php
     * @link http://pic.dhe.ibm.com/infocenter/idshelp/v115/topic/com.ibm.sqls.doc/ids_sqs_1461.htm
     */
    protected function lastSequenceValue($name)
    {
        $sql = 'SELECT ' . $name . '.CURRVAL FROM systables WHERE tabid = 1';

        return $this->query($sql)->fetchColumn(0);
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * The version string is retrieved with the dbinfo() function
     * since the PDO_INFORMIX extension doesn't support the
     * PDO::ATTR_SERVER_VERSION attribute.
     */
    public function getServerVersion()
    {
        return $this->query(
            'SELECT DBINFO(\'version\', \'full\') FROM systables WHERE tabid = 1'
        )->fetchColumn(0);
    }
}
