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

use Doctrine\DBAL\Driver\AbstractDriverException;

/**
 * SQLite3 driver exception.
 *
 * @since 2.6
 * @author Ben Morel <benjamin.morel@gmail.com
 * @author Bill Schaller <bill@zeroedin.com>>
 */
class SQLite3Exception extends AbstractDriverException
{
    /**
     * Wraps an exception thrown by SQLite3 in a SQLite3Exception.
     *
     * @param \Exception $nativeException
     * @param \SQLite3|null $connection
     * @return SQLite3Exception
     */
    public static function fromNativeException(\Exception $nativeException, \SQLite3 $connection = null)
    {
        $errorMessage = $nativeException->getMessage();
        $errorCode = $nativeException->getCode();

        if ($connection instanceof \SQLite3) {
            $errorMessage = $connection->lastErrorMsg();
            $errorCode = $connection->lastErrorCode();
        }

        return new self($errorMessage, null, $errorCode, $nativeException);
    }
}
