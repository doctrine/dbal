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
     * @var boolean
     */
    private $preventDefault = false;

    /**
     * @return \Doctrine\DBAL\Event\SchemaEventArgs
     */
    public function preventDefault()
    {
        $this->preventDefault = true;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isDefaultPrevented()
    {
        return $this->preventDefault;
    }
}
