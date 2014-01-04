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

/**
 * The PDO implementation of the Statement interface.
 * Used by all PDO-based drivers.
 *
 * @since 2.0
 */
class PDOStatement extends \PDOStatement implements Statement
{
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
        // This thin wrapper is necessary to shield against the weird signature
        // of PDOStatement::setFetchMode(): even if the second and third
        // parameters are optional, PHP will not let us remove it from this
        // declaration.
        if ($arg2 === null && $arg3 === null) {
            return parent::setFetchMode($fetchMode);
        }

        if ($arg3 === null) {
            return parent::setFetchMode($fetchMode, $arg2);
        }

        return parent::setFetchMode($fetchMode, $arg2, $arg3);
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = \PDO::PARAM_STR)
    {
        return parent::bindValue($param, $value, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = \PDO::PARAM_STR, $length = null, $driverOptions = null)
    {
        return parent::bindParam($column, $variable, $type, $length, $driverOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        return parent::execute($params);
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null, $cursorOrientation = null, $cursorOffset = null)
    {
        if ($fetchMode === null && $cursorOrientation === null && $cursorOffset === null) {
            return parent::fetch();
        }

        if ($cursorOrientation === null && $cursorOffset === null) {
            return parent::fetch($fetchMode);
        }

        if ($cursorOffset === null) {
            return parent::fetch($fetchMode, $cursorOrientation);
        }

        return parent::fetch($fetchMode, $cursorOrientation, $cursorOffset);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        if ($fetchMode === null && $fetchArgument === null && $ctorArgs === null) {
            return parent::fetchAll();
        }

        if ($fetchArgument === null && $ctorArgs === null) {
            return parent::fetchAll($fetchMode);
        }

        if ($ctorArgs === null) {
            return parent::fetchAll($fetchMode, $fetchArgument);
        }

        return parent::fetchAll($fetchMode, $fetchArgument, $ctorArgs);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        return parent::fetchColumn($columnIndex);
    }
}
