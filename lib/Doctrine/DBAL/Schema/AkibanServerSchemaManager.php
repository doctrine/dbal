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
 * @author      Padraig O'Sullivan <osullivan.padraig@gmail.com>
 * @since       2.3
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

    protected function _getPortableTableForeignKeyDefinition($tableForeignKey)
    {
        // TODO
    }

    public function dropDatabase($database)
    {
        // TODO
    }

    public function createDatabase($database)
    {
        // TODO
    }

    protected function _getPortableTriggerDefinition($trigger)
    {
        // TODO
    }

    protected function _getPortableViewDefinition($view)
    {
        // TODO
    }

    protected function _getPortableUserDefinition($user)
    {
        // TODO
    }

    protected function _getPortableTableDefinition($table)
    {
        // TODO
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
        // TODO
    }

    protected function _getPortableSequenceDefinition($sequence)
    {
        // TODO
    }

    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        // TODO
    }

}
