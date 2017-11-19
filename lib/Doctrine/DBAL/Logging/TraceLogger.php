<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Logging;

/**
 * Includes backtrace and executed SQLs in a Debug Stack.
 *
 * @link   www.doctrine-project.org
 * @author Pierre du Plessis <pdples@gmail.com>
 */
class TraceLogger implements SQLLogger
{
    /**
     * Executed SQL queries.
     *
     * @var array
     */
    public $queries = array();

    /**
     * If the logger is enabled (log queries) or not.
     *
     * @var boolean
     */
    public $enabled = true;

    /**
     * @var float|null
     */
    public $start = null;

    /**
     * @var integer
     */
    public $currentQuery = 0;

    /**
     * {@inheritdoc}
     */
    public function startQuery($sql, array $params = null, array $types = null)
    {
        if ($this->enabled) {
            $backtrace = $this->getBactrace();

            $this->start = microtime(true);
            $this->queries[++$this->currentQuery] = array('sql' => $sql, 'params' => $params, 'types' => $types, 'executionMS' => 0, 'trace' => $backtrace);
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

    private function getBactrace()
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS);

        foreach ($backtrace as $key => $debug) {
            if (!$this->isInternalClass($debug['class'])) {
                $trace = array_slice($backtrace, $key - 1, 10);

                return $this->formatTrace($trace);
            }
        }

        return array();
    }

    private function formatTrace(array $trace)
    {
        $backtrace = array();

        foreach ($trace as $index => $line) {
            $backtrace[$index] = '';

            if (isset($trace[$index + 1]['class'])) {
                $backtrace[$index] .= $trace[$index + 1]['class'];
            } else {
                $backtrace[$index] .= get_class($line['object']);
            }

            $backtrace[$index] .= '::';

            if (isset($trace[$index + 1])) {
                $backtrace[$index] .= $trace[$index + 1]['function'];
            } else {
                $backtrace[$index] .= $line['function'];
            }

            if (isset($line['line'])) {
                $backtrace[$index] .= ' (L' . $line['line'] . ')';
            }
        }

        return $backtrace;
    }

    private function isInternalClass(&$class)
    {
        return substr($class, 0, strpos($class, '\\')) === 'Doctrine';
    }
}
