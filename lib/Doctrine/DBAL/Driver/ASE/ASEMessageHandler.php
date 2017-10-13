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

namespace Doctrine\DBAL\Driver\ASE;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;

/**
 * ASE message handling implementation.
 *
 * @since 2.6
 * @author Maximilian Ruta <mr@xtain.net>
 */
class ASEMessageHandler
{
    /**
     * @var bool
     */
    protected static $registred = false;

    /**
     * @var ASEDriverException[]
     */
    protected static $globalMessages = [];

    /**
     * @var ASEDriverException[]
     */
    protected $messages = [];

    /**
     * @var \Closure[]
     */
    protected $logger = [];

    public static function registerLogger()
    {
        if (self::$registred) {
            return;
        }

        self::$registred = true;
        sybase_set_message_handler(function($id, $severity, $state, $line, $text) {
            self::$globalMessages[] = new ASEDriverException($text, $severity, null, $state, $line, $id);
        });
    }

    /**
     * @param \Throwable $e
     * @return ASEDriverException
     */
    public static function fromThrowable($e)
    {
        $message = $e->getMessage();

        $matches = [];
        if (preg_match('/((Warning|Fatal Error|Error|Notice)\: )?(.*\)\:[\s])?(.*)/', $message, $matches)) {
            $message = $matches[4];
        }

        return new ASEDriverException($message);
    }

    /**
     * ASEMessageHandler constructor.
     * @param resource $resource
     */
    public function __construct($resource = null)
    {
        if ($resource !== null) {
            $this->setResource($resource);
        }
    }

    /**
     * @param resource $resource
     */
    public function setResource($resource)
    {
        $self = $this;

        sybase_set_message_handler(function($id, $severity, $state, $line, $text) use ($self) {
            $self->messages[] = new ASEDriverException($text, $severity, null, $state, $line, $id);
            foreach ($this->logger as $logger) {
                $logger($id, $severity, $state, $line, $text);
            }
        }, $resource);
    }

    /**
     * @param \Closure $logger
     */
    public function addLogger(\Closure $logger)
    {
        $this->logger[] = $logger;
    }

    /**
     * @param \Closure $logger
     */
    public function removeLogger(\Closure $logger)
    {
        if(($key = array_search($logger, $this->logger, true)) !== FALSE) {
            unset($this->logger[$key]);
        }
    }

    /**
     * @return ASEDriverException|null
     */
    public function getLastMessage($level = 10)
    {
        /** @var ASEDriverException $message */
        foreach (array_reverse($this->messages) as $message) {
            if ($message->getCode() >= $level) {
                return $message;
            }
        }

        /** @var ASEDriverException $message */
        foreach (array_reverse(self::$globalMessages) as $message) {
            if ($message->getCode() >= $level) {
                return $message;
            }
        }

        return null;
    }

    public static function clearGlobal()
    {
        self::$globalMessages = [];
    }

    public function clear()
    {
        self::$globalMessages = [];
        $this->messages = [];
    }

    /**
     * @return ASEDriverException|null
     */
    public function getLastException()
    {
        $message = $this->getLastMessage();

        if ($message === null) {
            $message = new ASEException("ASE error occurred but no error message was retrieved from driver.");
        }

        return $message;
    }

    /**
     * @return ASEDriverException|null
     */
    public function getLastError()
    {
        return $this->getLastMessage(11);
    }

    /**
     * @return bool
     */
    public function hasError()
    {
        return $this->getLastMessage(11) !== null;
    }
}