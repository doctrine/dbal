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

namespace Doctrine\DBAL\Driver\SQLite3;

/**
 * Base functionality for SQLite3Connection and SQLite3Statement.
 */
abstract class SQLite3Abstract
{
    /**
     * @var \SQLite3
     */
    protected $sqlite3;

    /**
     * Runs code interacting with native SQLite3 objects, and catches errors to throw a proper exception.
     *
     * @param \Closure $function A function calling native SQLite3 objects.
     *
     * @return mixed The return value of the given function.
     *
     * @throws SQLite3Exception If an error occurs.
     */
    protected function call(\Closure $function)
    {
        // Temporary set the error reporting level to 0 to avoid any warning
        $errorReportingLevel = error_reporting(0);

        // Call the function containing SQLite3 code to execute
        $result = $function();

        // Restore the original error reporting level
        error_reporting($errorReportingLevel);

        $errorCode = $this->sqlite3->lastErrorCode();

        if ($errorCode === 0) {
            return $result;
        }

        $errorMessage = $this->sqlite3->lastErrorMsg();

        throw new SQLite3Exception($errorMessage, $errorCode);
    }
}
