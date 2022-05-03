<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\UnknownColumnOption;
use Doctrine\DBAL\Types\Type;

use function array_merge;
use function is_numeric;
use function method_exists;

/**
 * Object representation of a database column.
 */
class Column extends AbstractAsset
{
    /** @var Type */
    protected $_type;

    /** @var int|null */
    protected $_length;

    /** @var int */
    protected $_precision = 10;

    /** @var int */
    protected $_scale = 0;

    /** @var bool */
    protected $_unsigned = false;

    /** @var bool */
    protected $_fixed = false;

    /** @var bool */
    protected $_notnull = true;

    /** @var string|null */
    protected $_default;

    /** @var bool */
    protected $_autoincrement = false;

    /** @var mixed[] */
    protected $_platformOptions = [];

    /** @var string|null */
    protected $_columnDefinition;

    /** @var string|null */
    protected $_comment;

    /** @var mixed[] */
    protected $_customSchemaOptions = [];

    /**
     * Creates a new Column.
     *
     * @param string  $name
     * @param mixed[] $options
     *
     * @throws SchemaException
     */
    public function __construct($name, Type $type, array $options = [])
    {
        $this->_setName($name);
        $this->setType($type);
        $this->setOptions($options);
    }

    /**
     * @param mixed[] $options
     *
     * @throws SchemaException
     */
    public function setOptions(array $options): Column
    {
        foreach ($options as $name => $value) {
            $method = 'set' . $name;

            if (! method_exists($this, $method)) {
                throw UnknownColumnOption::new($name);
            }

            $this->$method($value);
        }

        return $this;
    }

    public function setType(Type $type): Column
    {
        $this->_type = $type;

        return $this;
    }

    /**
     * @param int|null $length
     */
    public function setLength($length): Column
    {
        if ($length !== null) {
            $this->_length = (int) $length;
        } else {
            $this->_length = null;
        }

        return $this;
    }

    /**
     * @param int $precision
     */
    public function setPrecision($precision): Column
    {
        if (! is_numeric($precision)) {
            $precision = 10; // defaults to 10 when no valid precision is given.
        }

        $this->_precision = (int) $precision;

        return $this;
    }

    /**
     * @param int $scale
     */
    public function setScale($scale): Column
    {
        if (! is_numeric($scale)) {
            $scale = 0;
        }

        $this->_scale = (int) $scale;

        return $this;
    }

    /**
     * @param bool $unsigned
     */
    public function setUnsigned($unsigned): Column
    {
        $this->_unsigned = (bool) $unsigned;

        return $this;
    }

    /**
     * @param bool $fixed
     */
    public function setFixed($fixed): Column
    {
        $this->_fixed = (bool) $fixed;

        return $this;
    }

    /**
     * @param bool $notnull
     */
    public function setNotnull($notnull): Column
    {
        $this->_notnull = (bool) $notnull;

        return $this;
    }

    /**
     * @param mixed $default
     */
    public function setDefault($default): Column
    {
        $this->_default = $default;

        return $this;
    }

    /**
     * @param mixed[] $platformOptions
     */
    public function setPlatformOptions(array $platformOptions): Column
    {
        $this->_platformOptions = $platformOptions;

        return $this;
    }

    /**
     * @param string $name
     * @param mixed  $value
     */
    public function setPlatformOption($name, $value): Column
    {
        $this->_platformOptions[$name] = $value;

        return $this;
    }

    /**
     * @param string $value
     */
    public function setColumnDefinition($value): Column
    {
        $this->_columnDefinition = $value;

        return $this;
    }

    public function getType(): Type
    {
        return $this->_type;
    }

    public function getLength(): ?int
    {
        return $this->_length;
    }

    public function getPrecision(): int
    {
        return $this->_precision;
    }

    public function getScale(): int
    {
        return $this->_scale;
    }

    public function getUnsigned(): bool
    {
        return $this->_unsigned;
    }

    public function getFixed(): bool
    {
        return $this->_fixed;
    }

    public function getNotnull(): bool
    {
        return $this->_notnull;
    }

    public function getDefault(): ?string
    {
        return $this->_default;
    }

    /**
     * @return mixed[]
     */
    public function getPlatformOptions(): array
    {
        return $this->_platformOptions;
    }

    /**
     * @param string $name
     */
    public function hasPlatformOption($name): bool
    {
        return isset($this->_platformOptions[$name]);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function getPlatformOption($name)
    {
        return $this->_platformOptions[$name];
    }

    public function getColumnDefinition(): ?string
    {
        return $this->_columnDefinition;
    }

    public function getAutoincrement(): bool
    {
        return $this->_autoincrement;
    }

    /**
     * @param bool $flag
     */
    public function setAutoincrement($flag): Column
    {
        $this->_autoincrement = $flag;

        return $this;
    }

    /**
     * @param string|null $comment
     */
    public function setComment($comment): Column
    {
        $this->_comment = $comment;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->_comment;
    }

    /**
     * @param string $name
     * @param mixed  $value
     */
    public function setCustomSchemaOption($name, $value): Column
    {
        $this->_customSchemaOptions[$name] = $value;

        return $this;
    }

    /**
     * @param string $name
     */
    public function hasCustomSchemaOption($name): bool
    {
        return isset($this->_customSchemaOptions[$name]);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function getCustomSchemaOption($name)
    {
        return $this->_customSchemaOptions[$name];
    }

    /**
     * @param mixed[] $customSchemaOptions
     */
    public function setCustomSchemaOptions(array $customSchemaOptions): Column
    {
        $this->_customSchemaOptions = $customSchemaOptions;

        return $this;
    }

    /**
     * @return mixed[]
     */
    public function getCustomSchemaOptions(): array
    {
        return $this->_customSchemaOptions;
    }

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        return array_merge([
            'name'          => $this->_name,
            'type'          => $this->_type,
            'default'       => $this->_default,
            'notnull'       => $this->_notnull,
            'length'        => $this->_length,
            'precision'     => $this->_precision,
            'scale'         => $this->_scale,
            'fixed'         => $this->_fixed,
            'unsigned'      => $this->_unsigned,
            'autoincrement' => $this->_autoincrement,
            'columnDefinition' => $this->_columnDefinition,
            'comment' => $this->_comment,
        ], $this->_platformOptions, $this->_customSchemaOptions);
    }
}
