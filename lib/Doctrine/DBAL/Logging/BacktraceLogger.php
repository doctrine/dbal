<?php

namespace Doctrine\DBAL\Logging;

use const DEBUG_BACKTRACE_IGNORE_ARGS;
use function debug_backtrace;

class BacktraceLogger extends DebugStack
{
    /**
     * {@inheritdoc}
     */
    public function startQuery($sql, ?array $params = null, ?array $types = null)
    {
        parent::startQuery($sql, $params, $types);

        $this->queries[$this->currentQuery]['backtrace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    }
}
