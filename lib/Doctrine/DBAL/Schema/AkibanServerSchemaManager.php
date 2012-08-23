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

namespace Doctrine\DBAL\Schema;

/**
 * Akiban Server Schema Manager
 *
 * @author Padraig O'Sullivan <osullivan.padraig@gmail.com>
 * @since  2.3
 */
class AkibanServerSchemaManager extends AbstractSchemaManager
{

    /**
     * Get all the existing schema names.
     *
     * @return array
     */
    public function getSchemaNames()
    {
        $rows = $this->_conn->fetchAll("SELECT schema_name FROM information_schema.schemata WHERE schema_name != 'information_schema'");
        return array_map(function($v) { return $v['schema_name']; }, $rows);
    }

    public function dropDatabase($database = null)
    {
        if (is_null($database)) {
            $database = $this->_conn->getDatabase();
        }

        $params = $this->_conn->getParams();
        $params["dbname"] = "information_schema";
        $tmpPlatform = $this->_platform;
        $tmpConn = $this->_conn;

        $this->_conn = \Doctrine\DBAL\DriverManager::getConnection($params);
        $this->_platform = $this->_conn->getDatabasePlatform();

        parent::dropDatabase($database);

        $this->_platform = $tmpPlatform;
        $this->_conn = $tmpConn;
    }

    public function createDatabase($database = null)
    {
        if (is_null($database)) {
            $database = $this->_conn->getDatabase();
        }

        $params = $this->_conn->getParams();
        $params["dbname"] = "information_schema";
        $tmpPlatform = $this->_platform;
        $tmpConn = $this->_conn;

        $this->_conn = \Doctrine\DBAL\DriverManager::getConnection($params);
        $this->_platform = $this->_conn->getDatabasePlatform();

        parent::createDatabase($database);

        $this->_platform = $tmpPlatform;
        $this->_conn = $tmpConn;
    }

    protected function _getPortableViewDefinition($view)
    {
        return new View($view['viewname'], $view['definition']);
    }

    protected function _getPortableUserDefinition($user)
    {
        return array(
            'user' => $user['usename'],
            'password' => $user['passwd']
        );
    }

    protected function _getPortableTableDefinition($table)
    {
        return $table['table_name'];
    }

    /**
     * @param  array $tableIndexes
     * @param  string $tableName
     * @return array
     */
    protected function _getPortableTableIndexesList($tableIndexes, $tableName=null)
    {
        // TODO
    }

    protected function _getPortableDatabaseDefinition($database)
    {
        return $database['datname'];
    }

    protected function _getPortableSequenceDefinition($sequence)
    {
        return new Sequence($sequence['sequence_name'], $sequence['increment_by'], $sequence['min_value']);
    }

    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        // TODO
    }

}
