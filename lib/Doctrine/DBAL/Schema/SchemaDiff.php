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

use \Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Schema Diff.
 *
 * @link      www.doctrine-project.org
 * @copyright Copyright (C) 2005-2009 eZ Systems AS. All rights reserved.
 * @license   http://ez.no/licenses/new_bsd New BSD License
 * @since     2.0
 * @author    Benjamin Eberlei <kontakt@beberlei.de>
 */
class SchemaDiff
{
    /**
     * @var \Doctrine\DBAL\Schema\Schema
     */
    protected $fromSchema;

    /**
     * All added tables.
     *
     * @var \Doctrine\DBAL\Schema\Table[]
     */
    protected $newTables = array();

    /**
     * All changed tables.
     *
     * @var \Doctrine\DBAL\Schema\TableDiff[]
     */
    protected $changedTables = array();

    /**
     * All removed tables.
     *
     * @var \Doctrine\DBAL\Schema\Table[]
     */
    protected $removedTables = array();

    /**
     * @var \Doctrine\DBAL\Schema\Sequence[]
     */
    protected $newSequences = array();

    /**
     * @var \Doctrine\DBAL\Schema\Sequence[]
     */
    protected $changedSequences = array();

    /**
     * @var \Doctrine\DBAL\Schema\Sequence[]
     */
    protected $removedSequences = array();

    /**
     * @var \Doctrine\DBAL\Schema\ForeignKeyConstraint[]
     */
    protected $orphanedForeignKeys = array();

    /**
     * Constructs an SchemaDiff object.
     *
     * @param \Doctrine\DBAL\Schema\Table[]     $newTables
     * @param \Doctrine\DBAL\Schema\TableDiff[] $changedTables
     * @param \Doctrine\DBAL\Schema\Table[]     $removedTables
     * @param \Doctrine\DBAL\Schema\Schema|null $fromSchema
     */
    public function __construct($newTables = array(), $changedTables = array(), $removedTables = array(), Schema $fromSchema = null)
    {
        $this->setNewTables($newTables);
        $this->setChangedTables($changedTables);
        $this->setRemovedTables($removedTables);
        $this->setFromSchema($fromSchema);
    }

    /**
     * The to save sql mode ensures that the following things don't happen:
     *
     * 1. Tables are deleted
     * 2. Sequences are deleted
     * 3. Foreign Keys which reference tables that would otherwise be deleted.
     *
     * This way it is ensured that assets are deleted which might not be relevant to the metadata schema at all.
     *
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     *
     * @return array
     */
    public function toSaveSql(AbstractPlatform $platform)
    {
        return $this->_toSql($platform, true);
    }

    /**
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     *
     * @return array
     */
    public function toSql(AbstractPlatform $platform)
    {
        return $this->_toSql($platform, false);
    }

    /**
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     * @param boolean                                   $saveMode
     *
     * @return array
     */
    protected function _toSql(AbstractPlatform $platform, $saveMode = false)
    {
        $sql = array();

        if ($platform->supportsForeignKeyConstraints() && $saveMode == false) {
            foreach ($this->getOrphanedForeignKeys() as $orphanedForeignKey) {
                $sql[] = $platform->getDropForeignKeySQL($orphanedForeignKey, $orphanedForeignKey->getLocalTableName());
            }
        }

        if ($platform->supportsSequences() == true) {
            foreach ($this->getChangedSequences() as $sequence) {
                $sql[] = $platform->getAlterSequenceSQL($sequence);
            }

            if ($saveMode === false) {
                foreach ($this->getRemovedSequences() as $sequence) {
                    $sql[] = $platform->getDropSequenceSQL($sequence);
                }
            }

            foreach ($this->getNewSequences() as $sequence) {
                $sql[] = $platform->getCreateSequenceSQL($sequence);
            }
        }

        $foreignKeySql = array();
        foreach ($this->getNewTables() as $table) {
            $sql = array_merge(
                $sql,
                $platform->getCreateTableSQL($table, AbstractPlatform::CREATE_INDEXES)
            );

            if ($platform->supportsForeignKeyConstraints()) {
                foreach ($table->getForeignKeys() as $foreignKey) {
                    $foreignKeySql[] = $platform->getCreateForeignKeySQL($foreignKey, $table);
                }
            }
        }
        $sql = array_merge($sql, $foreignKeySql);

        if ($saveMode === false) {
            foreach ($this->getRemovedTables() as $table) {
                $sql[] = $platform->getDropTableSQL($table);
            }
        }

        foreach ($this->getChangedTables() as $tableDiff) {
            $sql = array_merge($sql, $platform->getAlterTableSQL($tableDiff));
        }

        return $sql;
    }

    /**
     * @param \Doctrine\DBAL\Schema\Sequence[] $changedSequences
     */
    public function setChangedSequences($changedSequences)
    {
        $this->changedSequences = $changedSequences;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Sequence[]
     */
    public function getChangedSequences()
    {
        return $this->changedSequences;
    }

    /**
     * @param \Doctrine\DBAL\Schema\TableDiff[] $changedTables
     */
    public function setChangedTables($changedTables)
    {
        $this->changedTables = $changedTables;
    }

    /**
     * @return \Doctrine\DBAL\Schema\TableDiff[]
     */
    public function getChangedTables()
    {
        return $this->changedTables;
    }

    /**
     * @param \Doctrine\DBAL\Schema\Schema $fromSchema
     */
    public function setFromSchema($fromSchema)
    {
        $this->fromSchema = $fromSchema;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Schema
     */
    public function getFromSchema()
    {
        return $this->fromSchema;
    }

    /**
     * @param \Doctrine\DBAL\Schema\Sequence[] $newSequences
     */
    public function setNewSequences($newSequences)
    {
        $this->newSequences = $newSequences;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Sequence[]
     */
    public function getNewSequences()
    {
        return $this->newSequences;
    }

    /**
     * @param \Doctrine\DBAL\Schema\Table[] $newTables
     */
    public function setNewTables($newTables)
    {
        $this->newTables = $newTables;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Table[]
     */
    public function getNewTables()
    {
        return $this->newTables;
    }

    /**
     * @param \Doctrine\DBAL\Schema\ForeignKeyConstraint[] $orphanedForeignKeys
     */
    public function setOrphanedForeignKeys($orphanedForeignKeys)
    {
        $this->orphanedForeignKeys = $orphanedForeignKeys;
    }

    /**
     * @return \Doctrine\DBAL\Schema\ForeignKeyConstraint[]
     */
    public function getOrphanedForeignKeys()
    {
        return $this->orphanedForeignKeys;
    }

    /**
     * @param \Doctrine\DBAL\Schema\Sequence[] $removedSequences
     */
    public function setRemovedSequences($removedSequences)
    {
        $this->removedSequences = $removedSequences;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Sequence[]
     */
    public function getRemovedSequences()
    {
        return $this->removedSequences;
    }

    /**
     * @param \Doctrine\DBAL\Schema\Table[] $removedTables
     */
    public function setRemovedTables($removedTables)
    {
        $this->removedTables = $removedTables;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Table[]
     */
    public function getRemovedTables()
    {
        return $this->removedTables;
    }
}
