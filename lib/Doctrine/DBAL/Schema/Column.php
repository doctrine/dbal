<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Types\Type;

/**
 * Object representation of a database column.
 *
 * @link   www.doctrine-project.org
 * @since  2.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class Column extends AbstractAsset
{
    /**
     * @var Type
     */
    protected $type;

    /**
     * @var integer|null
     */
    protected $length = null;

    /**
     * @var integer
     */
    protected $precision = 10;

    /**
     * @var integer
     */
    protected $scale = 0;

    /**
     * @var boolean
     */
    protected $unsigned = false;

    /**
     * @var boolean
     */
    protected $fixed = false;

    /**
     * @var boolean
     */
    protected $notnull = true;

    /**
     * @var string|null
     */
    protected $default = null;

    /**
     * @var boolean
     */
    protected $autoincrement = false;

    /**
     * @var array
     */
    protected $platformOptions = [];

    /**
     * @var string|null
     */
    protected $columnDefinition = null;

    /**
     * @var string|null
     */
    protected $comment = null;

    /**
     * @var array
     */
    protected $customSchemaOptions = [];

    /**
     * Creates a new Column.
     *
     * @param string $columnName
     * @param Type   $type
     * @param array  $options
     */
    public function __construct($columnName, Type $type, array $options=[])
    {
        $this->setName($columnName);
        $this->setType($type);
        $this->setOptions($options);
    }

    /**
     * @param array $options
     *
     * @return Column
     */
    public function setOptions(array $options)
    {
        foreach ($options as $name => $value) {
            $method = "set".$name;
            if ( ! method_exists($this, $method)) {
                // next major: throw an exception
                @trigger_error(sprintf(
                    'The "%s" column option is not supported,'.
                    ' setting it is deprecated and will cause an error in Doctrine 3.0',
                    $name
                ), E_USER_DEPRECATED);

                return $this;
            }
            $this->$method($value);
        }

        return $this;
    }

    /**
     * @param Type $type
     *
     * @return Column
     */
    public function setType(Type $type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @param integer|null $length
     *
     * @return Column
     */
    public function setLength($length)
    {
        if ($length !== null) {
            $this->length = (int) $length;
        } else {
            $this->length = null;
        }

        return $this;
    }

    /**
     * @param integer $precision
     *
     * @return Column
     */
    public function setPrecision($precision)
    {
        if (!is_numeric($precision)) {
            $precision = 10; // defaults to 10 when no valid precision is given.
        }

        $this->precision = (int) $precision;

        return $this;
    }

    /**
     * @param integer $scale
     *
     * @return Column
     */
    public function setScale($scale)
    {
        if (!is_numeric($scale)) {
            $scale = 0;
        }

        $this->scale = (int) $scale;

        return $this;
    }

    /**
     * @param boolean $unsigned
     *
     * @return Column
     */
    public function setUnsigned($unsigned)
    {
        $this->unsigned = (bool) $unsigned;

        return $this;
    }

    /**
     * @param boolean $fixed
     *
     * @return Column
     */
    public function setFixed($fixed)
    {
        $this->fixed = (bool) $fixed;

        return $this;
    }

    /**
     * @param boolean $notnull
     *
     * @return Column
     */
    public function setNotnull($notnull)
    {
        $this->notnull = (bool) $notnull;

        return $this;
    }

    /**
     * @param mixed $default
     *
     * @return Column
     */
    public function setDefault($default)
    {
        $this->default = $default;

        return $this;
    }

    /**
     * @param array $platformOptions
     *
     * @return Column
     */
    public function setPlatformOptions(array $platformOptions)
    {
        $this->platformOptions = $platformOptions;

        return $this;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return Column
     */
    public function setPlatformOption($name, $value)
    {
        $this->platformOptions[$name] = $value;

        return $this;
    }

    /**
     * @param string $value
     *
     * @return Column
     */
    public function setColumnDefinition($value)
    {
        $this->columnDefinition = $value;

        return $this;
    }

    /**
     * @return Type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return integer|null
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * @return integer
     */
    public function getPrecision()
    {
        return $this->precision;
    }

    /**
     * @return integer
     */
    public function getScale()
    {
        return $this->scale;
    }

    /**
     * @return boolean
     */
    public function getUnsigned()
    {
        return $this->unsigned;
    }

    /**
     * @return boolean
     */
    public function getFixed()
    {
        return $this->fixed;
    }

    /**
     * @return boolean
     */
    public function getNotnull()
    {
        return $this->notnull;
    }

    /**
     * @return string|null
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * @return array
     */
    public function getPlatformOptions()
    {
        return $this->platformOptions;
    }

    /**
     * @param string $name
     *
     * @return boolean
     */
    public function hasPlatformOption($name)
    {
        return isset($this->platformOptions[$name]);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function getPlatformOption($name)
    {
        return $this->platformOptions[$name];
    }

    /**
     * @return string|null
     */
    public function getColumnDefinition()
    {
        return $this->columnDefinition;
    }

    /**
     * @return boolean
     */
    public function getAutoincrement()
    {
        return $this->autoincrement;
    }

    /**
     * @param boolean $flag
     *
     * @return Column
     */
    public function setAutoincrement($flag)
    {
        $this->autoincrement = $flag;

        return $this;
    }

    /**
     * @param string $comment
     *
     * @return Column
     */
    public function setComment($comment)
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return Column
     */
    public function setCustomSchemaOption($name, $value)
    {
        $this->customSchemaOptions[$name] = $value;

        return $this;
    }

    /**
     * @param string $name
     *
     * @return boolean
     */
    public function hasCustomSchemaOption($name)
    {
        return isset($this->customSchemaOptions[$name]);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function getCustomSchemaOption($name)
    {
        return $this->customSchemaOptions[$name];
    }

    /**
     * @param array $customSchemaOptions
     *
     * @return Column
     */
    public function setCustomSchemaOptions(array $customSchemaOptions)
    {
        $this->customSchemaOptions = $customSchemaOptions;

        return $this;
    }

    /**
     * @return array
     */
    public function getCustomSchemaOptions()
    {
        return $this->customSchemaOptions;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return array_merge([
            'name'          => $this->name,
            'type'          => $this->type,
            'default'       => $this->default,
            'notnull'       => $this->notnull,
            'length'        => $this->length,
            'precision'     => $this->precision,
            'scale'         => $this->scale,
            'fixed'         => $this->fixed,
            'unsigned'      => $this->unsigned,
            'autoincrement' => $this->autoincrement,
            'columnDefinition' => $this->columnDefinition,
            'comment' => $this->comment,
        ], $this->platformOptions, $this->customSchemaOptions);
    }
}
