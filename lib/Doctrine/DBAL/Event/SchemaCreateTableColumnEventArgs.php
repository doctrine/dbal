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

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform,
    Doctrine\DBAL\Schema\Column,
    Doctrine\DBAL\Schema\Table;

/**
 * Event Arguments used when SQL queries for creating table columns are generated inside Doctrine\DBAL\Platform\AbstractPlatform.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       2.2
 * @author      Jan Sorgalla <jsorgalla@googlemail.com>
 */
class SchemaCreateTableColumnEventArgs extends SchemaEventArgs
{
    /**
     * @var \Doctrine\DBAL\Schema\Column
     */
    private $_column = null;

    /**
     * @var \Doctrine\DBAL\Schema\Table
     */
    private $_table = null;

    /**
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    private $_platform = null;

    /**
     * @var array
     */
    private $_sql = array();

    /**
     * @param \Doctrine\DBAL\Schema\Column $column
     * @param \Doctrine\DBAL\Schema\Table $table
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     */
    public function __construct(Column $column, Table $table, AbstractPlatform $platform)
    {
        $this->_column   = $column;
        $this->_table    = $table;
        $this->_platform = $platform;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function getColumn()
    {
        return $this->_column;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Table
     */
    public function getTable()
    {
        return $this->_table;
    }

    /**
     * @return \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    public function getPlatform()
    {
        return $this->_platform;
    }

    /**
     * @param string|array $sql
     * @return \Doctrine\DBAL\Event\SchemaCreateTableColumnEventArgs
     */
    public function addSql($sql)
    {
        if (is_array($sql)) {
            $this->_sql = array_merge($this->_sql, $sql);
        } else {
            $this->_sql[] = $sql;
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getSql()
    {
        return $this->_sql;
    }
}
