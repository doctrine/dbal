<?php

namespace Doctrine\DBAL\Logging;

use function microtime;

/**
 * Includes executed SQLs in a Debug Stack.
 */
class DebugStack implements SQLLogger
{
    const SOURCE_DISABLED = 0;
    const SOURCE_ENABLED = 1;

    /**
     * Executed SQL queries.
     *
     * @var mixed[][]
     */
    public $queries = [];

    /**
     * If Debug Stack is enabled (log queries) or not.
     *
     * @var bool
     */
    public $enabled = true;

    /** @var float|null */
    public $start = null;

    /** @var int */
    public $currentQuery = 0;

    private $includeSource;

    public function __construct($includeSource = self::SOURCE_DISABLED)
    {
        $this->includeSource = $includeSource;
    }

    /**
     * {@inheritdoc}
     */
    public function startQuery($sql, ?array $params = null, ?array $types = null)
    {
        if (! $this->enabled) {
            return;
        }
        
        $this->start = microtime(true);
        $log = [
            'sql' => $sql,
            'params' => $params,
            'types' => $types,
            'executionMS' => 0,
        ];

        if ($this->includeSource !== self::SOURCE_DISABLED) {
            $log['querySource'] = $this->findQuerySource();
        }

        $this->queries[++$this->currentQuery] = $log;
    }

    /**
     * {@inheritdoc}
     */
    public function stopQuery()
    {
        if (! $this->enabled) {
            return;
        }

        $this->queries[$this->currentQuery]['executionMS'] = microtime(true) - $this->start;
    }

    private function findQuerySource()
    {
        foreach (debug_backtrace() as $row) {
            if (stripos($row['file'], 'vendor') === false) {
                return $row;
            }
        }
        return ['file' => '*unknown*', 'line' => 0];
    }
}
