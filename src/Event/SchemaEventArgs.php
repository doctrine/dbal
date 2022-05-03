<?php

namespace Doctrine\DBAL\Event;

use Doctrine\Common\EventArgs;

/**
 * Base class for schema related events.
 */
class SchemaEventArgs extends EventArgs
{
    /** @var bool */
    private $preventDefault = false;

    public function preventDefault(): SchemaEventArgs
    {
        $this->preventDefault = true;

        return $this;
    }

    public function isDefaultPrevented(): bool
    {
        return $this->preventDefault;
    }
}
