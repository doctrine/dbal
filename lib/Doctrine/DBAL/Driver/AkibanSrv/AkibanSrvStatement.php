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

namespace Doctrine\DBAL\Driver\AkibanSrv;

use PDO;
use IteratorAggregate;
use Doctrine\DBAL\Driver\Statement;

/**
 * Akiban Server Statement
 *
 * @since 2.3
 * @author Padraig O'Sullivan <osullivan.padraig@gmail.com>
 */
class AkibanSrvStatement implements IteratorAggregate, Statement
{
    /**
     * Akiban Server handle.
     *
     * @var resource
     */
    private $_dbh;

    /**
     * SQL statement to execute
     *
     * @var string
     */
    private $_statement;

    /**
     * Akiban Server connection object.
     *
     * @var resource
     */
    private $_conn;

    public function __construct($dbh, $statement, AkibanSrvConnection $conn)
    {
        $this->_statement = $statement;
        $this->_dbh = $dbh;
        $this->_conn = $conn;
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = null)
    {
        return $this->bindParam($param, $value, $type, null);
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = null, $length = null)
    {
        // TODO
    }

    public function closeCursor()
    {
        // TODO
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        // TODO
    }

    /**
     * {@inheritDoc}
     */
    public function errorCode()
    {
        return pg_last_error($this->dbh);
    }

    /**
     * {@inheritDoc}
     */
    public function errorInfo()
    {
        return pg_last_error($this->dbh);
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        // TODO
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        // TODO
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        // TODO
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null)
    {
        // TODO
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null)
    {
        // TODO
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        // TODO
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount()
    {
        // TODO
    }
}

