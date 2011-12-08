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
    Doctrine\DBAL\Platforms\AbstractPlatform,
    Doctrine\DBAL\Schema\Table,
    Doctrine\DBAL\Schema\Column;

/**
 * Event Arguments used when SQL queries for creating table columns are generated inside Doctrine\DBAL\Platform\AbstractPlatform.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       2.2
 * @version     $Revision$
 * @author      Jan Sorgalla <jsorgalla@googlemail.com>
 */
class SchemaCreateTableColumnEventArgs extends SchemaCreateTableEventArgs
{
    /**
     * @var Column
     */
    private $_column = null;

    /**
     * @param Column $column
     * @param Table $table
     * @param AbstractPlatform $platform 
     */
    public function __construct(Column $column, Table $table, AbstractPlatform $platform)
    {
        parent::__construct($table, $platform);
        $this->_column = $column;
    }

    /**
     * @return Doctrine\DBAL\Schema\Column
     */
    public function getColumn()
    {
        return $this->_column;
    }
}
