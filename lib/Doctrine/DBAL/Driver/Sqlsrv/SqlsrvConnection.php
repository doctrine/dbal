<?php
/*
 *  $Id$
 *
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
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Driver\Sqlsrv;

/**
 * Sqlsrv implementation of the Connection interface.
 *
 * @since 2.0
 * @author Juozas Kaziukenas <juozas@juokaz.com>
 */
class SqlsrvConnection implements \Doctrine\DBAL\Driver\Connection
{
    private $_dbh;
    
    public function __construct($serverName, array $connectionInfo)
    {
        $this->_dbh = @sqlsrv_connect($serverName, $connectionInfo);
        if (!is_resource($this->_dbh)) {
            throw SqlsrvException::fromErrorInfo($this->errorInfo());
        }
    }
    
    public function prepare($prepareString)
    {
        return new SqlsrvStatement($this->_dbh, $prepareString);
    }
    
    public function query()
    {
        $args = func_get_args();
        $sql = $args[0];
        //$fetchMode = $args[1];
        $stmt = $this->prepare($sql);
        $stmt->execute();
        return $stmt;
    }
    
    public function quote($input, $type=\PDO::PARAM_STR)
    {
        return is_numeric($input) ? $input : "'" . str_replace("'", "''", $input) . "'";
    }
    
    public function exec($statement)
    {
        $stmt = $this->prepare($statement);
        $stmt->execute();
        return $stmt->rowCount();
    }
    
    public function lastInsertId($name = null)
    {
        $id = $this->query('SELECT @@IDENTITY')->fetchColumn();

        if (!$id) {
            return 1;
        }

        return $id;
    }
    
    public function beginTransaction()
    {
        if (!sqlsrv_begin_transaction($this->_dbh)) {
            throw SqlsrvException::fromErrorInfo($this->errorInfo());
        }
        return true;
    }
    
    public function commit()
    {
        if (!sqlsrv_commit($this->_dbh)) {
            throw SqlsrvException::fromErrorInfo($this->errorInfo());
        }
        return true;
    }
    
    public function rollBack()
    {
        if (!sqlsrv_rollback($this->_dbh)) {
            throw SqlsrvException::fromErrorInfo($this->errorInfo());
        }
        return true;
    }
    
    public function errorCode()
    {
        $errors = sqlsrv_errors();

		if (false === isset($errors[0]['code']))
        {
			return null;
        }

        $error = $errors[0];

        return $error['code'];
    }
    
    public function errorInfo()
    {
        $errors = sqlsrv_errors();

		if (false === isset($errors[0]['message']))
        {
			return null;
        }

        return $errors[0];
    }
    
}