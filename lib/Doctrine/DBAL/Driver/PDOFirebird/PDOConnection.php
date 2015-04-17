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

namespace Doctrine\DBAL\Driver\PDOFirebird;

/**
 * @author Andreas Prucha, Helicon Software Development <prucha@helicon.co.at>
 * @experimental
 */
class PDOConnection extends \Doctrine\DBAL\Driver\PDOConnection
{

    protected $transactionDepth = 0;
    
    protected $autoCommitWasEnabled = true;

    /**
     * {@inheritDoc}
     * @param type $dsn
     * @param type $user
     * @param type $password
     * @param array $options
     */
    public function __construct($dsn, $user = null, $password = null, array $options = null)
    {
        if (!isset($options[\PDO::ATTR_AUTOCOMMIT]))
            $options[\PDO::ATTR_AUTOCOMMIT] = true;
        parent::__construct($dsn, $user, $password, array_filter($options, function ($value) {
                    return (strncmp($value, 'doctrine', 8));
                }));
        $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, array('\Doctrine\DBAL\Driver\PDOFirebird\PDOStatement', array($this)));
    }
    
    /**
     * Executes a COMMIT RETAIN if no transaction has been started.
     * 
     * This function is used internally by PDOStatement.
     */
    public function commitRetainIfOutsideTransaction()
    {
        if ($this->transactionDepth < 1 && $this->getAttribute(\PDO::ATTR_AUTOCOMMIT))
        {
            parent::exec('COMMIT RETAIN');
        }
    }
    
    /**
     * Executes a COMMIT RETAIN if no transaction has been started.
     * 
     * This function is used internally by PDOStatement.
     */
    public function rollbackRetainIfOutsideTransaction()
    {
        if ($this->transactionDepth < 1 && $this->getAttribute(\PDO::ATTR_AUTOCOMMIT))
        {
            parent::exec('ROLLBACK RETAIN');
        }
    }

    public function beginTransaction()
    {
        $result = false;
        if ($this->transactionDepth < 1) {
            // The firebird PDO driver seems to start some strange transactions, so we check
            // if a transaction is already running despite we do not know about it here. If
            // such a ghost-transaction has been started, we just commit it.
            $this->autoCommitWasEnabled = $this->getAttribute(\PDO::ATTR_AUTOCOMMIT);
            $this->setAttribute(\PDO::ATTR_AUTOCOMMIT, false);
            if (parent::inTransaction() && $this->autoCommitWasEnabled)
            {
                parent::commit(); // Commit. But *parent*::commit, because we do not want to manipulate the counter
            }
            // Start the transaction
            $result = parent::beginTransaction();
            if ($result)
                $this->transactionDepth++;
        } else {
            throw new \Doctrine\DBAL\Driver\PDOException('Transaction already started');
        }
        return $result;
    }

    /**
     * {@inheritDoc}
     * @return boolean
     */
    public function commit()
    {
        if ($this->transactionDepth > 0) {
            $this->transactionDepth--;
            $result = parent::commit();
            if ($result)
                $this->setAttribute(\PDO::ATTR_AUTOCOMMIT, $this->autoCommitWasEnabled);
        }
        return $result;
    }

    /**
     * {@inheritDoc}
     * @return boolean
     */
    public function rollBack()
    {
        if ($this->transactionDepth > 0) {
            $this->transactionDepth--;
            $result = parent::rollback();
            if ($result)
                $this->setAttribute(\PDO::ATTR_AUTOCOMMIT, $this->autoCommitWasEnabled);
        }
        return $result;
    }

    public function inTransaction()
    {
        return ($this->transactionDepth > 0) || parent::inTransaction();
    }

}
