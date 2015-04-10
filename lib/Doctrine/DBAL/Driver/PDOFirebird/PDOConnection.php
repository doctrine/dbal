<?php

/*
 */

namespace Doctrine\DBAL\Driver\PDOFirebird;

/**
 * @author Kim Hemsø Rasmussen <kimhemsoe@gmail.com>
 */
class PDOConnection extends \Doctrine\DBAL\Driver\PDOConnection
{

  const INTERNAL_TRANSACTION_CONFLICT_NOACTION = 0;
  const INTERNAL_TRANSACTION_CONFLICT_CONTINUE = 1;
  const INTERNAL_TRANSACTION_CONFLICT_COMMIT = 2;

  /**
   * Defines how to handle running internal transactions
   * 
   * startTransaction() handles conflicts with running internal
   * transactions depending on this setting
   * 
   * <ul>
   *  <li>
   *    self::INTERNAL_TRANSACTION_CONFLICT_NOACTION: 
   *      Let the PDO driver decide. This might cause exceptions, if
   *      the PDO driver has started an internal transactions before
   *      the explicite call of startTransaction()
   *  </li>
   *  <li>
   *    self::INTERNAL_TRANSACTION_CONFLICT_CONTINUE:
   *      If a internal transaction is active, startTransaction()
   *      does not start a new transaction, but continues using
   *      the current transaction.
   *  </li>
   *  <li>
   *    self::INTERNAL_TRANSACTION_CONFLICT_COMMIT:
   *      If a internal transaction is active, startTransaction()
   *      commits the current transaction before starting a new one.
   *  </li>
   * </ul>
   * PDO driver) is already running, startTransaction handles this
   * 
   * @var int 
   */
  public $handleInternalTransactions = self::INTERNAL_TRANSACTION_CONFLICT_COMMIT;
  protected $_expliciteTransactionDepth = 0;

  public function __construct($dsn, $user = null, $password = null,
          array $options = null)
  {
    parent::__construct($dsn, $user, $password, $options);
  }

  /**
   * Starts a transaction
   * 
   * This function overrides the standard PDO::startTransaction
   * and implements additional error handling. It checks if an
   * "internal" transaction (started by the PDO driver itself) is already
   * running.
   * 
   * The Firebird PDO driver seems to start an intenrnal transaction when
   * a select-query is executed, but sometimes does commit this internal
   * transaction, despite AutoCommit. 
   * If an explcite transaction is started afterwards, the PDO driver
   * throws an exception, tellig that an transaction is already started.
   * 
   * In order to avoid an exception, this function does not start a new
   * transaction, if a 
   * 
   * @throws \Doctrine\DBAL\ConnectionException
   */
  public function beginTransaction()
  {
    if ($this->_expliciteTransactionDepth > 0)
    {
      throw new \Doctrine\DBAL\ConnectionException(__METHOD__ . ': There is already an active transaction');
    } else
    {
      $this->_expliciteTransactionDepth++;
      switch ($this->handleInternalTransactions)
      {
        case self::INTERNAL_TRANSACTION_CONFLICT_COMMIT: {
            if ($this->inTransaction()) $this->commit();
            return parent::beginTransaction();
          }
        case self::INTERNAL_TRANSACTION_CONFLICT_CONTINUE: {
            if ($this->inTransaction())
                return TRUE; // Trnsaction is already running. Do nothing and ==> RETURN TRUE
            else return parent::beginTransaction();
          }
        default: {
            return parent::beginTransaction();
          }
      }
    }
  }

  /**
   * Commit the current transaction
   */
  public function commit()
  {
    $this->_expliciteTransactionDepth--;
    return parent::commit();
  }

  /**
   * Rollback the current transaction
   */
  public function rollback()
  {
    $this->_expliciteTransactionDepth--;
    return parent::rollback();
  }
  
  /**
   * Prepares a statement
   * 
   * @param type $statement
   * @param array $driver_options
   * @return type
   */
  public function prepare($statement, array $driver_options = array())
  {
    return parent::prepare($statement, $driver_options);
  }

}
