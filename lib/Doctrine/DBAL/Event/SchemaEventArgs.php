<?php

namespace Doctrine\DBAL\Event;

use Doctrine\Common\EventArgs;

/**
 * Base class for schema related events.
 *
 * @link   www.doctrine-project.org
 * @since  2.2
 * @author Jan Sorgalla <jsorgalla@googlemail.com>
 */
class SchemaEventArgs extends EventArgs
{
    /**
     * @var bool
     */
    private $_preventDefault = false;

    /**
     * @return \Doctrine\DBAL\Event\SchemaEventArgs
     */
    public function preventDefault()
    {
        $this->_preventDefault = true;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDefaultPrevented()
    {
        return $this->_preventDefault;
    }
}
