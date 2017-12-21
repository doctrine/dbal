<?php

namespace Doctrine\DBAL\Schema;

/**
 * Configuration for a Schema.
 *
 * @link   www.doctrine-project.org
 * @since  2.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class SchemaConfig
{
    /**
     * @var boolean
     */
    protected $hasExplicitForeignKeyIndexes = false;

    /**
     * @var integer
     */
    protected $maxIdentifierLength = 63;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $defaultTableOptions = [];

    /**
     * @return boolean
     */
    public function hasExplicitForeignKeyIndexes()
    {
        return $this->hasExplicitForeignKeyIndexes;
    }

    /**
     * @param boolean $flag
     *
     * @return void
     */
    public function setExplicitForeignKeyIndexes($flag)
    {
        $this->hasExplicitForeignKeyIndexes = (bool) $flag;
    }

    /**
     * @param integer $length
     *
     * @return void
     */
    public function setMaxIdentifierLength($length)
    {
        $this->maxIdentifierLength = (int) $length;
    }

    /**
     * @return integer
     */
    public function getMaxIdentifierLength()
    {
        return $this->maxIdentifierLength;
    }

    /**
     * Gets the default namespace of schema objects.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the default namespace name of schema objects.
     *
     * @param string $name The value to set.
     *
     * @return void
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Gets the default options that are passed to Table instances created with
     * Schema#createTable().
     *
     * @return array
     */
    public function getDefaultTableOptions()
    {
        return $this->defaultTableOptions;
    }

    /**
     * @param array $defaultTableOptions
     *
     * @return void
     */
    public function setDefaultTableOptions(array $defaultTableOptions)
    {
        $this->defaultTableOptions = $defaultTableOptions;
    }
}
