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
 * Represents the change of a column.
 *
 * @link   www.doctrine-project.org
 * @since  2.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class ColumnDiff
{
    /**
     * @var string
     */
    protected $oldColumnName;

    /**
     * @var \Doctrine\DBAL\Schema\Column
     */
    protected $column;

    /**
     * @var array
     */
    protected $changedProperties = array();

    /**
     * @var \Doctrine\DBAL\Schema\Column
     */
    protected $fromColumn;

    /**
     * @param string                       $oldColumnName
     * @param \Doctrine\DBAL\Schema\Column $column
     * @param array                        $changedProperties
     * @param \Doctrine\DBAL\Schema\Column $fromColumn
     */
    public function __construct($oldColumnName, Column $column, array $changedProperties = array(), Column $fromColumn = null)
    {
        $this->setOldColumnName($oldColumnName);
        $this->setColumn($column);
        $this->setChangedProperties($changedProperties);
        $this->setFromColumn($fromColumn);
    }

    /**
     * @param string $propertyName
     *
     * @return boolean
     */
    public function hasChanged($propertyName)
    {
        return in_array($propertyName, $this->getChangedProperties());
    }

    /**
     * @param array $changedProperties
     */
    public function setChangedProperties($changedProperties)
    {
        $this->changedProperties = $changedProperties;
    }

    /**
     * @return array
     */
    public function getChangedProperties()
    {
        return $this->changedProperties;
    }

    /**
     * @param \Doctrine\DBAL\Schema\Column $column
     */
    public function setColumn($column)
    {
        $this->column = $column;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function getColumn()
    {
        return $this->column;
    }

    /**
     * @param \Doctrine\DBAL\Schema\Column $fromColumn
     */
    public function setFromColumn($fromColumn)
    {
        $this->fromColumn = $fromColumn;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function getFromColumn()
    {
        return $this->fromColumn;
    }

    /**
     * @param string $oldColumnName
     */
    public function setOldColumnName($oldColumnName)
    {
        $this->oldColumnName = $oldColumnName;
    }

    /**
     * @return string
     */
    public function getOldColumnName()
    {
        return $this->oldColumnName;
    }
}
