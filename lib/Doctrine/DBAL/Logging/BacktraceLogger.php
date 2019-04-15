<?php

namespace Doctrine\DBAL\Logging;

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
