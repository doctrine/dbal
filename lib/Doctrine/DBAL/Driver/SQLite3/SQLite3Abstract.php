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
 *
 * @since 2.6
 * @author Ben Morel <benjamin.morel@gmail.com>
 * @author Bill Schaller <bill@zeroedin.com>
 */
abstract class SQLite3Abstract
{
    /**
     * @var \SQLite3
     */
    protected $sqlite3;

    /**
     * @return void
     *
     * @throws SQLite3Exception
     */
    protected function throwExceptionOnError()
    {
        $errorCode = $this->sqlite3->lastErrorCode();

        if ($errorCode === 0) {
            return;
        }

        $errorMessage = $this->sqlite3->lastErrorMsg();

        throw new SQLite3Exception($errorMessage, null, $errorCode);
    }

    /**
     * Wraps an exception thrown by SQLite3 in a SQLite3Exception.
     *
     * @param \Exception $driverException
     * @return SQLite3Exception
     */
    protected function wrapDriverException(\Exception $driverException)
    {
        $errorMessage = $driverException->getMessage();
        $errorCode = $driverException->getCode();

        if ($this->sqlite3 instanceof \SQLite3) {
            $errorMessage = $this->sqlite3->lastErrorMsg();
            $errorCode = $this->sqlite3->lastErrorCode();
        }

        return new SQLite3Exception($errorMessage, null, $errorCode, $driverException);
    }
}
