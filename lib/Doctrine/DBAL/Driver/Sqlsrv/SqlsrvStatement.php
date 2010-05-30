<?php
/*
 *  $Id: Interface.php 3882 2008-02-22 18:11:35Z jwage $
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

use \PDO;

/**
 * The Sql server implementation of the Statement interface.
 *
 * @since 2.0
 * @author Juozas Kaziukenas <juozas@juokaz.com>
 */
class SqlsrvStatement implements \Doctrine\DBAL\Driver\Statement
{
    /** Statement handle. */
    private $_sth;
	private $_dbh = null;
	private $_query = null;
    private static $fetchStyleMap = array(
        PDO::FETCH_BOTH => SQLSRV_FETCH_BOTH,
        PDO::FETCH_ASSOC => SQLSRV_FETCH_ASSOC,
        PDO::FETCH_NUM => SQLSRV_FETCH_NUMERIC
    );
    private $_paramMap = array();
	private $_bindParams = array();

    /**
     * Creates a new SqlsrvStatement that uses the given connection handle and SQL statement.
     *
     * @param resource $dbh The connection handle.
     * @param string $statement The SQL statement.
     */
    public function __construct($dbh, $statement)
    {
		$this->_dbh = $dbh;
		$this->_query = $this->_convertPositionalToNamedPlaceholders($statement);
    }
	
	/**
     * Sqlsrv doesnt support bamed params and these should be replaced
     * to question marks
     *
     * @param string $statement The SQL statement to convert.
     */
    private function _convertPositionalToNamedPlaceholders($statement)
    {
		// reset bind params
        $this->bindParams = array();
        
        // get params count
        $param_count = substr_count($statement,'?');

        // prepare bind params
        if($param_count > 0) {
            
            for ($i = 0; $i < $param_count; $i++)
            {
                // preapre bind param for later usage
                $this->_bindParams[$i] = null;
            }
        }
        else
        {
            // parse statement and get named bind params
            if(preg_match_all('/[= ]:([a-z]+)\b/', $statement, $matches))
            {
                for($i=0, $c = count($matches[1]); $i < $c; $i++)
                {
                    // preapre bind param for later usage
                    $this->_bindParams[$i] = null;
					
					// save name for later usage
					$this->_paramMap[$matches[1][$i]] = $i + 1;

                    // replace to question mark (:name => ?)
                    $statement = str_replace(':' . $matches[1][$i], '?',$statement);
                }
            }
        }
		
        return $statement;
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = null)
    {
        return $this->bindParam($param, $value, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = null)
    {
		$column = isset($this->_paramMap[$column]) ? $this->_paramMap[$column] : $column;
	
		if($column > 0 && $column <= count($this->_bindParams)) {
            $this->_bindParams[$column-1] = &$variable;
        } else {
            throw SqlsrvException::fromErrorInfo(array('message' => "Parameter out of bounds"));
        }
    }

    /**
     * Closes the cursor, enabling the statement to be executed again.
     *
     * @return boolean              Returns TRUE on success or FALSE on failure.
     */
    public function closeCursor()
    {
        return sqlsrv_free_stmt($this->_sth);
    }

    /** 
     * {@inheritdoc}
     */
    public function columnCount()
    {
        return sqlsrv_num_fields($this->_sth);
    }

    /**
     * {@inheritdoc}
     */
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
    
    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        $errors = sqlsrv_errors();

		if (false === isset($errors[0]['message']))
        {
			return null;
        }

        return $errors[0];
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        if ($params) {
            $hasZeroIndex = isset($params[0]);
            foreach ($params as $key => $val) {
                if ($hasZeroIndex && is_numeric($key)) {
                    $this->bindValue($key + 1, $val);
                } else {
                    $this->bindValue($key, $val);
                }
            }
        }
		
		// prepare statement
        $this->_sth = sqlsrv_prepare($this->_dbh, $this->_query, $this->_bindParams);

        if ( ! $this->_sth) {
            throw SqlsrvException::fromErrorInfo($this->errorInfo());
        }

        $ret = @sqlsrv_execute($this->_sth);
        if ( ! $ret) {
            throw SqlsrvException::fromErrorInfo($this->errorInfo());
        }
        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchStyle = PDO::FETCH_BOTH)
    {
        if ( ! isset(self::$fetchStyleMap[$fetchStyle])) {
            throw new \InvalidArgumentException("Invalid fetch style: " . $fetchStyle);
        }
        
        return sqlsrv_fetch_array($this->_sth, self::$fetchStyleMap[$fetchStyle]);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchStyle = PDO::FETCH_BOTH)
    {
        if ( ! isset(self::$fetchStyleMap[$fetchStyle])) {
            throw new \InvalidArgumentException("Invalid fetch style: " . $fetchStyle);
        }
		
		$result = array();
		while ($row = $this->fetch($fetchStyle)) {
			$result[] = $row;
		}
        
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        return sqlsrv_get_field($this->_sth, $columnIndex);
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount()
    {
        return sqlsrv_num_rows($this->_sth);
    }    
}