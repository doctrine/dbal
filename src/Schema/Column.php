<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\UnknownColumnOption;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;

use function array_merge;
use function is_numeric;
use function strtolower;

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
        $this->_setOptions($options);
    }

    /**
     * @deprecated Use the setter methods directly.
     *
     * @param mixed[] $options
     *
     * @return Column
     *
     * @throws SchemaException
     */
    public function setOptions(array $options)
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/',
            '%s is deprecated, use the setter methods directly.',
            __METHOD__
        );

        $this->_setOptions($options);

        return $this;
    }

    /**
     * @param mixed[] $options
     *
     * @throws UnknownColumnOption
     */
    private function _setOptions(array $options): void
    {
        foreach ($options as $name => $value) {
            switch (strtolower($name)) {
                case 'type':
                    $this->setType($value);
                    break;
                case 'length':
                    $this->setLength($value);
                    break;
                case 'precision':
                    $this->setPrecision($value);
                    break;
                case 'scale':
                    $this->setScale($value);
                    break;
                case 'unsigned':
                    $this->setUnsigned($value);
                    break;
                case 'fixed':
                    $this->setFixed($value);
                    break;
                case 'notnull':
                    $this->setNotnull($value);
                    break;
                case 'default':
                    $this->setDefault($value);
                    break;
                case 'columndefinition':
                    $this->setColumnDefinition($value);
                    break;
                case 'autoincrement':
                    $this->setAutoincrement($value);
                    break;
                case 'comment':
                    $this->setComment($value);
                    break;
                case 'platformoptions':
                    $this->setPlatformOptions($value);
                    break;
                case 'customschemaoptions':
                    $this->setCustomSchemaOptions($value);
                    break;
                default:
                    throw UnknownColumnOption::new($name);
            }
        }
    }

    /**
     * @return Type
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * @return int|null
     */
    public function getLength()
    {
        return $this->_length;
    }

    /**
     * @return int
     */
    public function getPrecision()
    {
        return $this->_precision;
    }

    /**
     * @return int
     */
    public function getScale()
    {
        return $this->_scale;
    }

    /**
     * @return bool
     */
    public function getUnsigned()
    {
        return $this->_unsigned;
    }

    /**
     * @return bool
     */
    public function getFixed()
    {
        return $this->_fixed;
    }

    /**
     * @return bool
     */
    public function getNotnull()
    {
        return $this->_notnull;
    }

    /**
     * @return string|null
     */
    public function getDefault()
    {
        return $this->_default;
    }

    /**
     * @return string|null
     */
    public function getColumnDefinition()
    {
        return $this->_columnDefinition;
    }

    /**
     * @return bool
     */
    public function getAutoincrement()
    {
        return $this->_autoincrement;
    }

    /**
     * @return string|null
     */
    public function getComment()
    {
        return $this->_comment;
    }

    /**
     * @return Column
     */
    public function setType(Type $type)
    {
        $this->_type = $type;

        return $this;
    }

    /**
     * @param int|null $length
     *
     * @return Column
     */
    public function setLength($length)
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
     *
     * @return Column
     */
    public function setPrecision($precision)
    {
        if (! is_numeric($precision)) {
            $precision = 10; // defaults to 10 when no valid precision is given.
        }

        $this->_precision = (int) $precision;

        return $this;
    }

    /**
     * @param int $scale
     *
     * @return Column
     */
    public function setScale($scale)
    {
        if (! is_numeric($scale)) {
            $scale = 0;
        }

        $this->_scale = (int) $scale;

        return $this;
    }

    /**
     * @param bool $unsigned
     *
     * @return Column
     */
    public function setUnsigned($unsigned)
    {
        $this->_unsigned = (bool) $unsigned;

        return $this;
    }

    /**
     * @param bool $fixed
     *
     * @return Column
     */
    public function setFixed($fixed)
    {
        $this->_fixed = (bool) $fixed;

        return $this;
    }

    /**
     * @param bool $notnull
     *
     * @return Column
     */
    public function setNotnull($notnull)
    {
        $this->_notnull = (bool) $notnull;

        return $this;
    }

    /**
     * @param mixed $default
     *
     * @return Column
     */
    public function setDefault($default)
    {
        $this->_default = $default;

        return $this;
    }

    /**
     * @param string $value
     *
     * @return Column
     */
    public function setColumnDefinition($value)
    {
        $this->_columnDefinition = $value;

        return $this;
    }

    /**
     * @param bool $flag
     *
     * @return Column
     */
    public function setAutoincrement($flag)
    {
        $this->_autoincrement = $flag;

        return $this;
    }

    /**
     * @param string|null $comment
     *
     * @return Column
     */
    public function setComment($comment)
    {
        $this->_comment = $comment;

        return $this;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasPlatformOption($name)
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

    /**
     * @return mixed[]
     */
    public function getPlatformOptions()
    {
        return $this->_platformOptions;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return Column
     */
    public function setPlatformOption($name, $value)
    {
        $this->_platformOptions[$name] = $value;

        return $this;
    }

    /**
     * @param mixed[] $platformOptions
     *
     * @return Column
     */
    public function setPlatformOptions(array $platformOptions)
    {
        $this->_platformOptions = $platformOptions;

        return $this;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasCustomSchemaOption($name)
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
     * @return mixed[]
     */
    public function getCustomSchemaOptions()
    {
        return $this->_customSchemaOptions;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return Column
     */
    public function setCustomSchemaOption($name, $value)
    {
        $this->_customSchemaOptions[$name] = $value;

        return $this;
    }

    /**
     * @param mixed[] $customSchemaOptions
     *
     * @return Column
     */
    public function setCustomSchemaOptions(array $customSchemaOptions)
    {
        $this->_customSchemaOptions = $customSchemaOptions;

        return $this;
    }

    /**
     * @return mixed[]
     */
    public function toArray()
    {
        return array_merge([
            'name'             => $this->_name,
            'type'             => $this->_type,
            'default'          => $this->_default,
            'notnull'          => $this->_notnull,
            'length'           => $this->_length,
            'precision'        => $this->_precision,
            'scale'            => $this->_scale,
            'fixed'            => $this->_fixed,
            'unsigned'         => $this->_unsigned,
            'autoincrement'    => $this->_autoincrement,
            'columnDefinition' => $this->_columnDefinition,
            'comment'          => $this->_comment,
        ], $this->_platformOptions, $this->_customSchemaOptions);
    }

    public function createCopy(string $name): Column
    {
        $column = clone $this;
        $column->_setName($name);

        return $column;
    }
}
