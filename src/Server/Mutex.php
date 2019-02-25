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
    private $_lockReleased = true;

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
     * 获取锁
     * @return bool
     */
    public function lock()
    {
        return $this->lockwait();
    }

    /**
     * 获取锁
     * 获取锁时限制超时
     * @param float $timeout
     * @return bool
     */
    public function lockwait($timeout = 1.0)
    {
        $this->_lockCallTimes++;
        if ($this->_lockReleased && parent::lockwait($timeout) === true) {
            $this->_lockCallSuccesses++;
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    public function unlock()
    {
        if (!$this->_lockReleased && parent::unlock()) {
            $this->_lockReleased = true;
            return true;
        }
        return false;
    }
}
