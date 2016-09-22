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

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Class for a unique constraint.
 *
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @link   www.doctrine-project.org
 * @since  3.0
 */
class UniqueConstraint extends AbstractAsset implements Constraint
{
    /**
     * Asset identifier instances of the column names the unique constraint is associated with.
     * array($columnName => Identifier)
     *
     * @var Identifier[]
     */
    protected $columns = array();

    /**
     * Platform specific options
     *
     * @var array
     */
    private $options = array();

    /**
     * @param string   $indexName
     * @param string[] $columns
     * @param array    $options
     */
    public function __construct($indexName, array $columns, array $options = array())
    {
        $this->_setName($indexName);

        $this->options = $options;

        foreach ($columns as $column) {
            $this->_addColumn($column);
        }
    }

    /**
     * @param string $column
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function _addColumn($column)
    {
        if (is_string($column)) {
            $this->_columns[$column] = new Identifier($column);
        } else {
            throw new \InvalidArgumentException("Expecting a string as Index Column");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getColumns()
    {
        return array_keys($this->_columns);
    }

    /**
     * {@inheritdoc}
     */
    public function getQuotedColumns(AbstractPlatform $platform)
    {
        $columns = array();

        foreach ($this->_columns as $column) {
            $columns[] = $column->getQuotedName($platform);
        }

        return $columns;
    }

    /**
     * @return string[]
     */
    public function getUnquotedColumns()
    {
        return array_map(array($this, 'trimQuotes'), $this->getColumns());
    }

    /**
     * @param string $name
     *
     * @return boolean
     */
    public function hasOption($name)
    {
        return isset($this->options[strtolower($name)]);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function getOption($name)
    {
        return $this->options[strtolower($name)];
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }
}