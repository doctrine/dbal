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

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\DateTimeType;

/**
 * Provides the behavior, features and SQL dialect of the MySQL 5.7 database platform.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
class MySQL57Platform extends MySqlPlatform
{
    /**
     * {@inheritdoc}
     */
    protected function getPreAlterTableRenameIndexForeignKeySQL(TableDiff $diff)
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    protected function getPostAlterTableRenameIndexForeignKeySQL(TableDiff $diff)
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    protected function getRenameIndexSQL($oldIndexName, Index $index, $tableName)
    {
        return array(
            'ALTER TABLE ' . $tableName . ' RENAME INDEX ' . $oldIndexName . ' TO ' . $index->getQuotedName($this)
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getReservedKeywordsClass()
    {
        return 'Doctrine\DBAL\Platforms\Keywords\MySQL57Keywords';
    }

    /**
     * Get reserved keywords about date and time for default and update
     *
     * @return array
     */
    public static function getReservedDefaultKeywords()
    {
        return array(
            'NULL',
            'CURRENT_DATE',
            'CURRENT_TIME',
            'CURRENT_TIMESTAMP',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultValueDeclarationSQL($field)
    {
        if ($field['type'] instanceof DateTimeType &&
            in_array($field['default'], self::getReservedDefaultKeywords())
        ) {
            $default = ' DEFAULT ' . $field['default'];
        } else {
            $default = parent::getDefaultValueDeclarationSQL($field);
        }

        $default .= $this->getOnUpdateValueDeclarationSQL($field);

        return $default;
    }

    /**
     * Obtains specific MySQL code portion needed to set action for update rows.
     *
     * @param array $field The field definition array.
     *
     * @return string specific MySQL code portion needed to set action for update rows.
     *
     * @see https://dev.mysql.com/doc/refman/5.7/en/timestamp-initialization.html
     */
    public function getOnUpdateValueDeclarationSQL($field)
    {
        if ($field['type'] instanceof DateTimeType &&
            in_array($field['onUpdate'], self::getReservedDefaultKeywords())
        ) {
            return ' ON UPDATE ' . $field['onUpdate'];
        }

        return '';
    }
}
