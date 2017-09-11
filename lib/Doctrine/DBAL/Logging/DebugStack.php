<?php

namespace Doctrine\DBAL\Logging;

use function microtime;

/**
 * Includes executed SQLs in a Debug Stack.
 *
 * @link   www.doctrine-project.org
 * @since  2.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author Jonathan Wage <jonwage@gmail.com>
 * @author Roman Borschel <roman@code-factory.org>
 */
class DebugStack implements SQLLogger
{
    /**
     * Executed SQL queries.
     *
     * @var array
     */
    public $queries = [];

    /**
     * If Debug Stack is enabled (log queries) or not.
     *
     * @var bool
     */
    public $enabled = true;

    /**
     * @var float|null
     */
    public $start = null;

    /**
     * @var int
     */
    public $currentQuery = 0;

    /**
     * {@inheritdoc}
     */
    public function startQuery($sql, array $params = null, array $types = null)
    {
        if ($this->enabled) {
            $this->start = microtime(true);
            $this->queries[++$this->currentQuery] = ['sql' => $sql, 'params' => $params, 'types' => $types, 'executionMS' => 0];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stopQuery()
    {
        if ($this->enabled) {
            $this->queries[$this->currentQuery]['executionMS'] = microtime(true) - $this->start;
        }
    }
}
