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

namespace Doctrine\DBAL\Driver\PDOPgSql;

use Doctrine\DBAL\Driver\PDOConnection;

/**
 * pdo_pgsql connection implementation.
 *
 * @author Steve Müller <deeky666@googlemail.com>
 */
class Connection extends PDOConnection implements \Doctrine\DBAL\Driver\Connection
{
    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        try {
            return $this->fetchLastInsertId($name);
        } catch (\PDOException $exception) {
            return '0';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function trackLastInsertId()
    {
        // Do not track last insert ID as it is not considered "safe" and can cause transactions to fail.
        // If there is no last insert ID yet, we get an error with SQLSTATE 55000:
        // "Object not in prerequisite state: 7 ERROR:  lastval is not yet defined in this session"
        // That error can modify the transaction/connection state.
    }
}
