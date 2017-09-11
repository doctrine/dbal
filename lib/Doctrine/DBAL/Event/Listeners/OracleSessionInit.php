<?php

namespace Doctrine\DBAL\Event\Listeners;

use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\Common\EventSubscriber;
use function array_change_key_case;
use function array_merge;
use function count;
use function implode;

/**
 * Should be used when Oracle Server default environment does not match the Doctrine requirements.
 *
 * The following environment variables are required for the Doctrine default date format:
 *
 * NLS_TIME_FORMAT="HH24:MI:SS"
 * NLS_DATE_FORMAT="YYYY-MM-DD HH24:MI:SS"
 * NLS_TIMESTAMP_FORMAT="YYYY-MM-DD HH24:MI:SS"
 * NLS_TIMESTAMP_TZ_FORMAT="YYYY-MM-DD HH24:MI:SS TZH:TZM"
 *
 * @link   www.doctrine-project.org
 * @since  2.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class OracleSessionInit implements EventSubscriber
{
    /**
     * @var array
     */
    protected $_defaultSessionVars = [
        'NLS_TIME_FORMAT' => "HH24:MI:SS",
        'NLS_DATE_FORMAT' => "YYYY-MM-DD HH24:MI:SS",
        'NLS_TIMESTAMP_FORMAT' => "YYYY-MM-DD HH24:MI:SS",
        'NLS_TIMESTAMP_TZ_FORMAT' => "YYYY-MM-DD HH24:MI:SS TZH:TZM",
        'NLS_NUMERIC_CHARACTERS' => ".,",
    ];

    /**
     * @param array $oracleSessionVars
     */
    public function __construct(array $oracleSessionVars = [])
    {
        $this->_defaultSessionVars = array_merge($this->_defaultSessionVars, $oracleSessionVars);
    }

    /**
     * @param \Doctrine\DBAL\Event\ConnectionEventArgs $args
     *
     * @return void
     */
    public function postConnect(ConnectionEventArgs $args)
    {
        if (count($this->_defaultSessionVars)) {
            array_change_key_case($this->_defaultSessionVars, \CASE_UPPER);
            $vars = [];
            foreach ($this->_defaultSessionVars as $option => $value) {
                if ($option === 'CURRENT_SCHEMA') {
                    $vars[] = $option . " = " . $value;
                } else {
                    $vars[] = $option . " = '" . $value . "'";
                }
            }
            $sql = "ALTER SESSION SET ".implode(" ", $vars);
            $args->getConnection()->executeUpdate($sql);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents()
    {
        return [Events::postConnect];
    }
}
