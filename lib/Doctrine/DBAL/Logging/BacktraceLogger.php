<?php

namespace Doctrine\DBAL\Logging;

use const DEBUG_BACKTRACE_IGNORE_ARGS;
use function array_shift;
use function debug_backtrace;

class BacktraceLogger extends DebugStack
{
    /**
     * {@inheritdoc}
     */
    public function startQuery($sql, ?array $params = null, ?array $types = null)
    {
        parent::startQuery($sql, $params, $types);

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        // skip first since it's always the current method
        array_shift($backtrace);

        $this->queries[$this->currentQuery]['backtrace'] = $backtrace;
    }
}
