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
 * Table Diff.
 *
 * @link   www.doctrine-project.org
 * @since  2.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class TableDiff
{
    /**
     * @var string
     */
    protected $name = null;

    /**
     * @var string|boolean
     */
    protected $newName = false;

    /**
     * All added fields.
     *
     * @var \Doctrine\DBAL\Schema\Column[]
     */
    protected $addedColumns;

    /**
     * All changed fields.
     *
     * @var \Doctrine\DBAL\Schema\Column[]
     */
    protected $changedColumns = array();

    /**
     * All removed fields.
     *
     * @var \Doctrine\DBAL\Schema\Column[]
     */
    protected $removedColumns = array();

    /**
     * Columns that are only renamed from key to column instance name.
     *
     * @var \Doctrine\DBAL\Schema\Column[]
     */
    protected $renamedColumns = array();

    /**
     * All added indexes.
     *
     * @var \Doctrine\DBAL\Schema\Index[]
     */
    protected $addedIndexes = array();

    /**
     * All changed indexes.
     *
     * @var \Doctrine\DBAL\Schema\Index[]
     */
    protected $changedIndexes = array();

    /**
     * All removed indexes
     *
     * @var \Doctrine\DBAL\Schema\Index[]
     */
    protected $removedIndexes = array();

    /**
     * All added foreign key definitions
     *
     * @var \Doctrine\DBAL\Schema\ForeignKeyConstraint[]
     */
    protected $addedForeignKeys = array();

    /**
     * All changed foreign keys
     *
     * @var \Doctrine\DBAL\Schema\ForeignKeyConstraint[]
     */
    protected $changedForeignKeys = array();

    /**
     * All removed foreign keys
     *
     * @var \Doctrine\DBAL\Schema\ForeignKeyConstraint[]
     */
    protected $removedForeignKeys = array();

    /**
     * @var \Doctrine\DBAL\Schema\Table
     */
    protected $fromTable;

    /**
     * Constructs an TableDiff object.
     *
     * @param string                           $tableName
     * @param \Doctrine\DBAL\Schema\Column[]   $addedColumns
     * @param \Doctrine\DBAL\Schema\Column[]   $changedColumns
     * @param \Doctrine\DBAL\Schema\Column[]   $removedColumns
     * @param \Doctrine\DBAL\Schema\Index[]    $addedIndexes
     * @param \Doctrine\DBAL\Schema\Index[]    $changedIndexes
     * @param \Doctrine\DBAL\Schema\Index[]    $removedIndexes
     * @param \Doctrine\DBAL\Schema\Table|null $fromTable
     */
    public function __construct($tableName, $addedColumns = array(),
        $changedColumns = array(), $removedColumns = array(), $addedIndexes = array(),
        $changedIndexes = array(), $removedIndexes = array(), Table $fromTable = null)
    {
        $this->setName($tableName);
        $this->setAddedColumns($addedColumns);
        $this->setChangedColumns($changedColumns);
        $this->setRemovedColumns($removedColumns);
        $this->setAddedIndexes($addedIndexes);
        $this->setChangedIndexes($changedIndexes);
        $this->setRemovedIndexes($removedIndexes);
        $this->setFromTable($fromTable);
    }

    /**
     * @param \Doctrine\DBAL\Schema\Column[] $addedColumns
     */
    public function setAddedColumns($addedColumns)
    {
        $this->addedColumns = $addedColumns;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Column[]
     */
    public function getAddedColumns()
    {
        return $this->addedColumns;
    }

    /**
     * @param \Doctrine\DBAL\Schema\ForeignKeyConstraint[] $addedForeignKeys
     */
    public function setAddedForeignKeys($addedForeignKeys)
    {
        $this->addedForeignKeys = $addedForeignKeys;
    }

    /**
     * @return \Doctrine\DBAL\Schema\ForeignKeyConstraint[]
     */
    public function getAddedForeignKeys()
    {
        return $this->addedForeignKeys;
    }

    /**
     * @param \Doctrine\DBAL\Schema\Index[] $addedIndexes
     */
    public function setAddedIndexes($addedIndexes)
    {
        $this->addedIndexes = $addedIndexes;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Index[]
     */
    public function getAddedIndexes()
    {
        return $this->addedIndexes;
    }

    /**
     * @param \Doctrine\DBAL\Schema\ColumnDiff[] $changedColumns
     */
    public function setChangedColumns($changedColumns)
    {
        $this->changedColumns = $changedColumns;
    }

    /**
     * @return \Doctrine\DBAL\Schema\ColumnDiff[]
     */
    public function getChangedColumns()
    {
        return $this->changedColumns;
    }

    /**
     * @param \Doctrine\DBAL\Schema\ForeignKeyConstraint[] $changedForeignKeys
     */
    public function setChangedForeignKeys($changedForeignKeys)
    {
        $this->changedForeignKeys = $changedForeignKeys;
    }

    /**
     * @return \Doctrine\DBAL\Schema\ForeignKeyConstraint[]
     */
    public function getChangedForeignKeys()
    {
        return $this->changedForeignKeys;
    }

    /**
     * @param \Doctrine\DBAL\Schema\Index[] $changedIndexes
     */
    public function setChangedIndexes($changedIndexes)
    {
        $this->changedIndexes = $changedIndexes;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Index[]
     */
    public function getChangedIndexes()
    {
        return $this->changedIndexes;
    }

    /**
     * @param \Doctrine\DBAL\Schema\Table $fromTable
     */
    public function setFromTable($fromTable)
    {
        $this->fromTable = $fromTable;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Table
     */
    public function getFromTable()
    {
        return $this->fromTable;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param bool|string $newName
     */
    public function setNewName($newName)
    {
        $this->newName = $newName;
    }

    /**
     * @return bool|string
     */
    public function getNewName()
    {
        return $this->newName;
    }

    /**
     * @param \Doctrine\DBAL\Schema\Column[] $removedColumns
     */
    public function setRemovedColumns($removedColumns)
    {
        $this->removedColumns = $removedColumns;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Column[]
     */
    public function getRemovedColumns()
    {
        return $this->removedColumns;
    }

    /**
     * @param \Doctrine\DBAL\Schema\ForeignKeyConstraint[] $removedForeignKeys
     */
    public function setRemovedForeignKeys($removedForeignKeys)
    {
        $this->removedForeignKeys = $removedForeignKeys;
    }

    /**
     * @return \Doctrine\DBAL\Schema\ForeignKeyConstraint[]
     */
    public function getRemovedForeignKeys()
    {
        return $this->removedForeignKeys;
    }

    /**
     * @param \Doctrine\DBAL\Schema\Index[] $removedIndexes
     */
    public function setRemovedIndexes($removedIndexes)
    {
        $this->removedIndexes = $removedIndexes;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Index[]
     */
    public function getRemovedIndexes()
    {
        return $this->removedIndexes;
    }

    /**
     * @param \Doctrine\DBAL\Schema\Column[] $renamedColumns
     */
    public function setRenamedColumns($renamedColumns)
    {
        $this->renamedColumns = $renamedColumns;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Column[]
     */
    public function getRenamedColumns()
    {
        return $this->renamedColumns;
    }
}
