<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Types\Type;
use const E_USER_DEPRECATED;
use function array_merge;
use function method_exists;
use function sprintf;
use function trigger_error;

/**
 * Object representation of a database column.
 */
class Column extends AbstractAsset
{
    /** @var Type */
    protected $_type;

    /** @var int|null */
    protected $_length;

    /** @var int|null */
    protected $_precision;

    /** @var int */
    protected $_scale = 0;

    /** @var bool */
    protected $_unsigned = false;

    /** @var bool */
    protected $_fixed = false;

    /** @var bool */
    protected $_notnull = true;

    /** @var mixed */
    protected $_default;

    /** @var bool */
    protected $_autoincrement = false;

    /** @var array<string, mixed> */
    protected $_platformOptions = [];

    /** @var string|null */
    protected $_columnDefinition;

    /** @var string|null */
    protected $_comment;

    /** @var array<string, mixed> */
    protected $_customSchemaOptions = [];

    /**
     * Creates a new Column.
     *
     * @param array<string, mixed> $options
     */
    public function __construct(string $name, Type $type, array $options = [])
    {
        $this->_setName($name);
        $this->setType($type);
        $this->setOptions($options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options) : self
    {
        foreach ($options as $name => $value) {
            $method = 'set' . $name;
            if (! method_exists($this, $method)) {
                // next major: throw an exception
                @trigger_error(sprintf(
                    'The "%s" column option is not supported,' .
                    ' setting it is deprecated and will cause an error in Doctrine DBAL 3.0',
                    $name
                ), E_USER_DEPRECATED);

                continue;
            }
            $this->$method($value);
        }

        return $this;
    }

    public function setType(Type $type) : self
    {
        $this->_type = $type;

        return $this;
    }

    public function setLength(?int $length) : self
    {
        $this->_length = $length;

        return $this;
    }

    public function setPrecision(?int $precision) : self
    {
        $this->_precision = $precision;

        return $this;
    }

    public function setScale(int $scale) : self
    {
        $this->_scale = $scale;

        return $this;
    }

    public function setUnsigned(bool $unsigned) : self
    {
        $this->_unsigned = $unsigned;

        return $this;
    }

    public function setFixed(bool $fixed) : self
    {
        $this->_fixed = $fixed;

        return $this;
    }

    public function setNotnull(bool $notnull) : self
    {
        $this->_notnull = $notnull;

        return $this;
    }

    /**
     * @param mixed $default
     */
    public function setDefault($default) : self
    {
        $this->_default = $default;

        return $this;
    }

    /**
     * @param array<string, mixed> $platformOptions
     */
    public function setPlatformOptions(array $platformOptions) : self
    {
        $this->_platformOptions = $platformOptions;

        return $this;
    }

    /**
     * @param mixed $value
     */
    public function setPlatformOption(string $name, $value) : self
    {
        $this->_platformOptions[$name] = $value;

        return $this;
    }

    public function setColumnDefinition(string $value) : self
    {
        $this->_columnDefinition = $value;

        return $this;
    }

    public function getType() : Type
    {
        return $this->_type;
    }

    public function getLength() : ?int
    {
        return $this->_length;
    }

    public function getPrecision() : ?int
    {
        return $this->_precision;
    }

    public function getScale() : int
    {
        return $this->_scale;
    }

    public function getUnsigned() : bool
    {
        return $this->_unsigned;
    }

    public function getFixed() : bool
    {
        return $this->_fixed;
    }

    public function getNotnull() : bool
    {
        return $this->_notnull;
    }

    /**
     * @return mixed
     */
    public function getDefault()
    {
        return $this->_default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPlatformOptions() : array
    {
        return $this->_platformOptions;
    }

    public function hasPlatformOption(string $name) : bool
    {
        return isset($this->_platformOptions[$name]);
    }

    /**
     * @return mixed
     */
    public function getPlatformOption(string $name)
    {
        return $this->_platformOptions[$name];
    }

    public function getColumnDefinition() : ?string
    {
        return $this->_columnDefinition;
    }

    public function getAutoincrement() : bool
    {
        return $this->_autoincrement;
    }

    public function setAutoincrement(bool $flag) : self
    {
        $this->_autoincrement = $flag;

        return $this;
    }

    public function setComment(?string $comment) : self
    {
        $this->_comment = $comment;

        return $this;
    }

    public function getComment() : ?string
    {
        return $this->_comment;
    }

    /**
     * @param mixed $value
     */
    public function setCustomSchemaOption(string $name, $value) : self
    {
        $this->_customSchemaOptions[$name] = $value;

        return $this;
    }

    public function hasCustomSchemaOption(string $name) : bool
    {
        return isset($this->_customSchemaOptions[$name]);
    }

    /**
     * @return mixed
     */
    public function getCustomSchemaOption(string $name)
    {
        return $this->_customSchemaOptions[$name];
    }

    /**
     * @param array<string, mixed> $customSchemaOptions
     */
    public function setCustomSchemaOptions(array $customSchemaOptions) : self
    {
        $this->_customSchemaOptions = $customSchemaOptions;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomSchemaOptions() : array
    {
        return $this->_customSchemaOptions;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray() : array
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
