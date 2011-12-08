<?php
/*
 *  $Id$
 *
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

namespace Doctrine\DBAL\Event;

use Doctrine\Common\EventArgs,
    Doctrine\DBAL\Connection,
    Doctrine\DBAL\Schema\Column;

/**
 * Event Arguments used when the portable column definition is generated inside Doctrine\DBAL\Schema\AbstractSchemaManager.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       2.2
 * @version     $Revision$
 * @author      Jan Sorgalla <jsorgalla@googlemail.com>
 */
class SchemaColumnDefinitionEventArgs extends SchemaEventArgs
{
    /**
     * @var array
     */
    private $_column = null;

    /**
     * @var string
     */
    private $_table = null;

    /**
     * @var string
     */
    private $_database = null;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $_connection = null;

    /**
     * @var \Doctrine\DBAL\Schema\Column $columnDefinition
     */
    private $_columnDefinition = null;

    /**
     * @param type $column
     * @param type $table
     * @param type $database
     * @param \Doctrine\DBAL\Connection $conn
     */
    public function __construct($column, $table, $database, Connection $connection)
    {
        $this->_column     = $column;
        $this->_table      = $table;
        $this->_database   = $database;
        $this->_connection = $connection;
    }
    
    /**
     * @return array
     */
    public function getColumn()
    {
        return $this->_column;
    }

    /**
     * @return string
     */
    public function getTable()
    {
        return $this->_table;
    }

    /**
     * @return string
     */
    public function getDatabase()
    {
        return $this->_platform;
    }

    /**
     * @return \Doctrine\DBAL\Connection
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    /**
     * @return Doctrine\DBAL\Platforms\AbstractPlatform
     */
    public function getDatabasePlatform()
    {
        return $this->_connection->getDatabasePlatform();
    }

    /**
     * @param \Doctrine\DBAL\Schema\Column $columnDefinition
     * @return SchemaColumnDefinitionEventArgs
     */
    public function setColumnDefinition(Column $columnDefinition)
    {
        $this->_columnDefinition = $columnDefinition;

        return $this;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function getColumnDefinition()
    {
        return $this->_columnDefinition;
    }
}
