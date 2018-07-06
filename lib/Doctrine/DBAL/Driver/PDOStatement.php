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

use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use PDO;
use const E_USER_DEPRECATED;
use function sprintf;
use function trigger_error;

/**
 * The PDO implementation of the Statement interface.
 * Used by all PDO-based drivers.
 *
 * @since 2.0
 */
class PDOStatement extends \PDOStatement implements Statement
{
    /**
     * @var int[]
     */
    private const PARAM_TYPE_MAP = [
        ParameterType::NULL         => PDO::PARAM_NULL,
        ParameterType::INTEGER      => PDO::PARAM_INT,
        ParameterType::STRING       => PDO::PARAM_STR,
        ParameterType::BINARY       => PDO::PARAM_LOB,
        ParameterType::LARGE_OBJECT => PDO::PARAM_LOB,
        ParameterType::BOOLEAN      => PDO::PARAM_BOOL,
    ];

    /**
     * @var int[]
     */
    private const FETCH_MODE_MAP = [
        FetchMode::ASSOCIATIVE     => PDO::FETCH_ASSOC,
        FetchMode::NUMERIC         => PDO::FETCH_NUM,
        FetchMode::MIXED           => PDO::FETCH_BOTH,
        FetchMode::STANDARD_OBJECT => PDO::FETCH_OBJ,
        FetchMode::COLUMN          => PDO::FETCH_COLUMN,
        FetchMode::CUSTOM_OBJECT   => PDO::FETCH_CLASS,
    ];

    /**
     * Protected constructor.
     */
    protected function __construct()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        $fetchMode = $this->convertFetchMode($fetchMode);

        // This thin wrapper is necessary to shield against the weird signature
        // of PDOStatement::setFetchMode(): even if the second and third
        // parameters are optional, PHP will not let us remove it from this
        // declaration.
        try {
            if ($arg2 === null && $arg3 === null) {
                return parent::setFetchMode($fetchMode);
            }

            if ($arg3 === null) {
                return parent::setFetchMode($fetchMode, $arg2);
            }

            return parent::setFetchMode($fetchMode, $arg2, $arg3);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        $type = $this->convertParamType($type);

        try {
            return parent::bindValue($param, $value, $type);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = ParameterType::STRING, $length = null, $driverOptions = null)
    {
        $type = $this->convertParamType($type);

        try {
            return parent::bindParam($column, $variable, $type, $length, $driverOptions);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        try {
            return parent::closeCursor();
        } catch (\PDOException $exception) {
            // Exceptions not allowed by the interface.
            // In case driver implementations do not adhere to the interface, silence exceptions here.
            return true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        try {
            return parent::execute($params);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null, $cursorOrientation = \PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        $fetchMode = $this->convertFetchMode($fetchMode);

        try {
            if ($fetchMode === null && \PDO::FETCH_ORI_NEXT === $cursorOrientation && 0 === $cursorOffset) {
                return parent::fetch();
            }

            if (\PDO::FETCH_ORI_NEXT === $cursorOrientation && 0 === $cursorOffset) {
                return parent::fetch($fetchMode);
            }

            if (0 === $cursorOffset) {
                return parent::fetch($fetchMode, $cursorOrientation);
            }

            return parent::fetch($fetchMode, $cursorOrientation, $cursorOffset);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        $fetchMode = $this->convertFetchMode($fetchMode);

        try {
            if ($fetchMode === null && null === $fetchArgument && null === $ctorArgs) {
                return parent::fetchAll();
            }

            if (null === $fetchArgument && null === $ctorArgs) {
                return parent::fetchAll($fetchMode);
            }

            if (null === $ctorArgs) {
                return parent::fetchAll($fetchMode, $fetchArgument);
            }

            return parent::fetchAll($fetchMode, $fetchArgument, $ctorArgs);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        try {
            return parent::fetchColumn($columnIndex);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * Converts DBAL parameter type to PDO parameter type
     *
     * @param int $type Parameter type
     */
    private function convertParamType(int $type) : int
    {
        if (! isset(self::PARAM_TYPE_MAP[$type])) {
            // TODO: next major: throw an exception
            @trigger_error(sprintf(
                'Using a PDO parameter type (%d given) is deprecated and will cause an error in Doctrine 3.0',
                $type
            ), E_USER_DEPRECATED);

            return $type;
        }

        return self::PARAM_TYPE_MAP[$type];
    }

    /**
     * Converts DBAL fetch mode to PDO fetch mode
     *
     * @param int|null $fetchMode Fetch mode
     */
    private function convertFetchMode(?int $fetchMode) : ?int
    {
        if ($fetchMode === null) {
            return null;
        }

        if (! isset(self::FETCH_MODE_MAP[$fetchMode])) {
            // TODO: next major: throw an exception
            @trigger_error(sprintf(
                'Using a PDO fetch mode or their combination (%d given)' .
                ' is deprecated and will cause an error in Doctrine 3.0',
                $fetchMode
            ), E_USER_DEPRECATED);

            return $fetchMode;
        }

        return self::FETCH_MODE_MAP[$fetchMode];
    }
}
