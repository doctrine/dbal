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

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver\PDOException;

/**
 * DbLib Driver implementation.
 *
 * @since 2.6
 * @author Maximilian Ruta <mr@xtain.net>
 */
class AbstractDbLibDriver
{
    /**
     * @param array $params
     * @param array $driverOptions
     * @return string
     */
    public static function constructPdoDsn(array $params, array $driverOptions)
    {
        if (isset($driverOptions['protocol_version']) && !isset($params['protocol_version'])) {
            $params['protocol_version'] = $driverOptions['protocol_version'];
        }

        $dsn = 'dblib:host=';

        if (isset($params['host'])) {
            $dsn .= $params['host'];
        }

        if (isset($params['port']) && !empty($params['port'])) {
            $dsn .= ':' . $params['port'];
        }

        if (isset($params['dbname'])) {
            $dsn .= ';dbname=' .  $params['dbname'];
        }

        if (isset($params['charset'])) {
            $dsn .= ';chartset=' . $params['charset'];
        }

        if (isset($params['protocol_version'])) {
            $dsn .= ';version=' . $params['protocol_version'];
        }

        return $dsn;
    }

    /**
     * @param \PDO $PDO
     * @param array $args
     *
     * @return \PDOStatement
     * @throws \Exception
     */
    public static function emulateQuery(\PDO $PDO, array $args)
    {
        $argsCount = count($args);

        try {
            $stmt = $PDO->prepare($args[0]);

            if ($stmt === false) {
                throw new \Exception('prepare failed');
            }

            if ($argsCount == 4) {
                $stmt->setFetchMode($args[1], $args[2], $args[3]);
            }

            if ($argsCount == 3) {
                $stmt->setFetchMode($args[1], $args[2]);
            }

            if ($argsCount == 2) {
                $stmt->setFetchMode($args[1]);
            }

            $stmt->execute();
            return $stmt;
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

}