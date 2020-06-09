<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Event;

use Doctrine\Common\EventArgs;

/**
 * Base class for schema related events.
 */
class SchemaEventArgs extends EventArgs
{
    /** @var bool */
    private $preventDefault = false;

    /**
     * @return $this
     */
    public function preventDefault(): self
    {
        $this->preventDefault = true;

        return $this;
    }

    public function isDefaultPrevented(): bool
    {
        return $this->preventDefault;
    }
}
