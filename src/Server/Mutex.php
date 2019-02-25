<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server;

use Swoole\Lock;

/**
 * 全局锁
 * @package Uniondrug\Phar\Bootstrap
 */
class Mutex extends Lock
{
    private $_lockCallTimes = 0;
    private $_lockCallSuccesses = 0;

    /**
     * Mutex constructor.
     */
    public function __construct()
    {
        parent::__construct(SWOOLE_MUTEX);
    }

    /**
     * @return int
     */
    public function getCallTimes()
    {
        return $this->_lockCallTimes;
    }

    /**
     * @return int
     */
    public function getCallSuccesses()
    {
        return $this->_lockCallSuccesses;
    }

    /**
     * @return bool
     */
    public function lock()
    {
        $this->_lockCallTimes++;
        $done = parent::lock();
        if ($done) {
            $this->_lockCallSuccesses++;
        }
        return $done;
    }

    /**
     * @param float $timeout
     */
    public function lockwait($timeout = 0.1)
    {
        $this->_lockCallTimes++;
        $done = parent::lockwait($timeout);
        if ($done) {
            $this->_lockCallSuccesses++;
        }
        return $done;
    }
}
