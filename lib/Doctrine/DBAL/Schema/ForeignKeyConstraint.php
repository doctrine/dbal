<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use function array_keys;
use function array_map;
use function in_array;
use function strrpos;
use function strtolower;
use function strtoupper;
use function substr;

/**
 * An abstraction class for a foreign key constraint.
 */
class ForeignKeyConstraint extends AbstractAsset implements Constraint
{
    /**
     * Instance of the referencing table the foreign key constraint is associated with.
     *
     * @var Table
     */
    protected $_localTable;

    /**
     * Asset identifier instances of the referencing table column names the foreign key constraint is associated with.
     *
     * @var array<string, Identifier>
     */
    protected $_localColumnNames;

    /**
     * Table or asset identifier instance of the referenced table name the foreign key constraint is associated with.
     *
     * @var Table|Identifier
     */
    protected $_foreignTableName;

    /**
     * Asset identifier instances of the referenced table column names the foreign key constraint is associated with.
     *
     * @var array<string, Identifier>
     */
    protected $_foreignColumnNames;

    /**
     * Options associated with the foreign key constraint.
     *
     * @var array<string, mixed>
     */
    protected $_options;

    /**
     * Initializes the foreign key constraint.
     *
     * @param array<int, string>   $localColumnNames   Names of the referencing table columns.
     * @param Table|string         $foreignTableName   Referenced table.
     * @param array<int, string>   $foreignColumnNames Names of the referenced table columns.
     * @param string               $name               Name of the foreign key constraint.
     * @param array<string, mixed> $options            Options associated with the foreign key constraint.
     */
    public function __construct(array $localColumnNames, $foreignTableName, array $foreignColumnNames, string $name = '', array $options = [])
    {
        $this->_setName($name);

        $this->_localColumnNames = $this->createIdentifierMap($localColumnNames);

        if ($foreignTableName instanceof Table) {
            $this->_foreignTableName = $foreignTableName;
        } else {
            $this->_foreignTableName = new Identifier($foreignTableName);
        }

        $this->_foreignColumnNames = $this->createIdentifierMap($foreignColumnNames);
        $this->_options            = $options;
    }

    /**
     * @param array<int, string> $names
     *
     * @return array<string, Identifier>
     */
    private function createIdentifierMap(array $names) : array
    {
        $identifiers = [];

        foreach ($names as $name) {
            $identifiers[$name] = new Identifier($name ?? '');
        }

        return $identifiers;
    }

    /**
     * Returns the name of the referencing table
     * the foreign key constraint is associated with.
     */
    public function getLocalTableName() : string
    {
        return $this->_localTable->getName();
    }

    /**
     * Sets the Table instance of the referencing table
     * the foreign key constraint is associated with.
     */
    public function setLocalTable(Table $table) : void
    {
        $this->_localTable = $table;
    }

    public function getLocalTable() : Table
    {
        return $this->_localTable;
    }

    /**
     * Returns the names of the referencing table columns
     * the foreign key constraint is associated with.
     *
     * @return array<int, string>
     */
    public function getLocalColumns() : array
    {
        return array_keys($this->_localColumnNames);
    }

    /**
     * Returns the quoted representation of the referencing table column names
     * the foreign key constraint is associated with.
     *
     * But only if they were defined with one or the referencing table column name
     * is a keyword reserved by the platform.
     * Otherwise the plain unquoted value as inserted is returned.
     *
     * @param AbstractPlatform $platform The platform to use for quotation.
     *
     * @return array<int, string>
     */
    public function getQuotedLocalColumns(AbstractPlatform $platform) : array
    {
        $columns = [];

        foreach ($this->_localColumnNames as $column) {
            $columns[] = $column->getQuotedName($platform);
        }

        return $columns;
    }

    /**
     * Returns unquoted representation of local table column names for comparison with other FK
     *
     * @return array<int, string>
     */
    public function getUnquotedLocalColumns() : array
    {
        return array_map([$this, 'trimQuotes'], $this->getLocalColumns());
    }

    /**
     * Returns unquoted representation of foreign table column names for comparison with other FK
     *
     * @return array<int, string>
     */
    public function getUnquotedForeignColumns() : array
    {
        return array_map([$this, 'trimQuotes'], $this->getForeignColumns());
    }

    /**
     * {@inheritdoc}
     *
     * @see getLocalColumns
     */
    public function getColumns() : array
    {
        return $this->getLocalColumns();
    }

    /**
     * Returns the quoted representation of the referencing table column names
     * the foreign key constraint is associated with.
     *
     * But only if they were defined with one or the referencing table column name
     * is a keyword reserved by the platform.
     * Otherwise the plain unquoted value as inserted is returned.
     *
     * @see getQuotedLocalColumns
     *
     * @param AbstractPlatform $platform The platform to use for quotation.
     *
     * @return array<int, string>
     */
    public function getQuotedColumns(AbstractPlatform $platform) : array
    {
        return $this->getQuotedLocalColumns($platform);
    }

    /**
     * Returns the name of the referenced table
     * the foreign key constraint is associated with.
     */
    public function getForeignTableName() : string
    {
        return $this->_foreignTableName->getName();
    }

    /**
     * Returns the non-schema qualified foreign table name.
     */
    public function getUnqualifiedForeignTableName() : string
    {
        $name     = $this->_foreignTableName->getName();
        $position = strrpos($name, '.');

        if ($position !== false) {
            $name = substr($name, $position);
        }

        return strtolower($name);
    }

    /**
     * Returns the quoted representation of the referenced table name
     * the foreign key constraint is associated with.
     *
     * But only if it was defined with one or the referenced table name
     * is a keyword reserved by the platform.
     * Otherwise the plain unquoted value as inserted is returned.
     *
     * @param AbstractPlatform $platform The platform to use for quotation.
     */
    public function getQuotedForeignTableName(AbstractPlatform $platform) : string
    {
        return $this->_foreignTableName->getQuotedName($platform);
    }

    /**
     * Returns the names of the referenced table columns
     * the foreign key constraint is associated with.
     *
     * @return array<int, string>
     */
    public function getForeignColumns() : array
    {
        return array_keys($this->_foreignColumnNames);
    }

    /**
     * Returns the quoted representation of the referenced table column names
     * the foreign key constraint is associated with.
     *
     * But only if they were defined with one or the referenced table column name
     * is a keyword reserved by the platform.
     * Otherwise the plain unquoted value as inserted is returned.
     *
     * @param AbstractPlatform $platform The platform to use for quotation.
     *
     * @return array<int, string>
     */
    public function getQuotedForeignColumns(AbstractPlatform $platform) : array
    {
        $columns = [];

        foreach ($this->_foreignColumnNames as $column) {
            $columns[] = $column->getQuotedName($platform);
        }

        return $columns;
    }

    /**
     * Returns whether or not a given option
     * is associated with the foreign key constraint.
     */
    public function hasOption(string $name) : bool
    {
        return isset($this->_options[$name]);
    }

    /**
     * Returns an option associated with the foreign key constraint.
     *
     * @return mixed
     */
    public function getOption(string $name)
    {
        return $this->_options[$name];
    }

    /**
     * Returns the options associated with the foreign key constraint.
     *
     * @return array<string, mixed>
     */
    public function getOptions() : array
    {
        return $this->_options;
    }

    /**
     * Returns the referential action for UPDATE operations
     * on the referenced table the foreign key constraint is associated with.
     */
    public function onUpdate() : ?string
    {
        return $this->onEvent('onUpdate');
    }

    /**
     * Returns the referential action for DELETE operations
     * on the referenced table the foreign key constraint is associated with.
     */
    public function onDelete() : ?string
    {
        return $this->onEvent('onDelete');
    }

    /**
     * Returns the referential action for a given database operation
     * on the referenced table the foreign key constraint is associated with.
     *
     * @param string $event Name of the database operation/event to return the referential action for.
     */
    private function onEvent(string $event) : ?string
    {
        if (isset($this->_options[$event])) {
            $onEvent = strtoupper($this->_options[$event]);

            if (! in_array($onEvent, ['NO ACTION', 'RESTRICT'])) {
                return $onEvent;
            }
        }

        return null;
    }

    /**
     * Checks whether this foreign key constraint intersects the given index columns.
     *
     * Returns `true` if at least one of this foreign key's local columns
     * matches one of the given index's columns, `false` otherwise.
     *
     * @param Index $index The index to be checked against.
     */
    public function intersectsIndexColumns(Index $index) : bool
    {
        foreach ($index->getColumns() as $indexColumn) {
            foreach ($this->_localColumnNames as $localColumn) {
                if (strtolower($indexColumn) === strtolower($localColumn->getName())) {
                    return true;
                }
            }
        }

        return false;
    }
}
