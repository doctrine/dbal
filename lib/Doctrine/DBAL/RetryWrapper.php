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

namespace Doctrine\DBAL;

use Doctrine\DBAL\Exception\RetryableException;

/**
 * Wraps a database operation, represented as a callable, in retry logic.
 *
 * It is best practice to retry transactions that are aborted because of deadlocks
 * or timeouts. Such errors are caused by lock contention and you often can design
 * your application to reduce the likeliness that such an error occurs. But it's
 * impossible to guarantee that such error conditions will never occur. So when you
 * have to ensure the application does not fail in edge cases or high load, retrying
 * transactions in case of such errors is actually a must.
 *
 * The class implements the retry logic for you by re-executing your callable in
 * case of temporary database errors where retrying the failed transaction after a
 * short delay usually resolves the problem. Just wrap your database transaction
 * or single query in this class and invoke it. You can also pass arguments when
 * invoking the wrapper which will be passed through to the underlying callable.
 *
 * @author Tobias Schultze <http://tobion.de>
 * @link   www.doctrine-project.org
 * @since  2.6
 */
class RetryWrapper
{
    /**
     * The database operation to execute that can be retried on failure.
     *
     * @var callable
     */
    private $callable;

    /**
     * Maximum number of retries.
     *
     * @var integer
     */
    private $maxRetries;

    /**
     * Delay between retries in milliseconds.
     *
     * @var integer
     */
    private $retryDelay;

    /**
     * Actual number of retries.
     *
     * @var integer|null
     */
    private $retries;

    /**
     * Constructor to wrap a callable.
     *
     * @param callable $callable   The database operation to execute that can be retried on failure.
     * @param integer  $maxRetries Maximum number of retries.
     * @param integer  $retryDelay Delay between retries in milliseconds to give the blocking
     *                             transaction time to finish.
     */
    public function __construct($callable, $maxRetries = 3, $retryDelay = 100)
    {
        $this->callable = $callable;
        $this->maxRetries = $maxRetries;
        $this->retryDelay = $retryDelay;
    }

    /**
     * Returns the number of retries used.
     *
     * @return integer|null The number of retries used or null if wrapper has not been invoked yet
     */
    public function getRetries()
    {
        return $this->retries;
    }

    /**
     * Executes the callable and retries it in case of a temporary database error.
     *
     * The callable is only re-executed for temporary database errors where retrying
     * the failed transaction after a short delay usually resolves the problem. Such
     * errors are for example deadlocks and lock wait timeouts. Internally the raised
     * exception must extend RetryableException. Other exceptions like syntax errors
     * or constraint violations will not cause the callable to be re-executed.
     *
     * All arguments given will be passed through to the wrapped callable.
     *
     * @return mixed The return value of the wrapped callable
     *
     * @throws \Exception If an exception has been raised where retrying makes no sense
     *                    or a RetryableException after max retries has been reached.
     */
    public function __invoke()
    {
        $this->retries = 0;
        $args = func_get_args();

        do {
            try {
                return call_user_func_array($this->callable, $args);
            } catch (RetryableException $e) {
                if ($this->retries < $this->maxRetries) {
                    $this->retries++;
                    usleep($this->retryDelay * 1000);
                } else {
                    throw $e;
                }
            }
        } while (true);
    }
}
