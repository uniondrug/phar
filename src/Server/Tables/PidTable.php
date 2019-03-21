<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-17
 */
namespace Uniondrug\Phar\Server\Tables;

/**
 * 进程记录表
 * @package Uniondrug\Phar\Server\Tables
 */
class PidTable extends XTable
{
    const NAME = 'pid';
    const TYPE_MASTER = 1;
    const TYPE_MANAGER = 2;
    const TYPE_WORKER = 3;
    const TYPE_TASKER = 4;
    const TYPE_PROCESS = 5;
    public static $name = self::NAME;
    protected $columns = [
        'pid' => [
            parent::TYPE_INT,
            4
        ],
        'ppid' => [
            parent::TYPE_INT,
            4
        ],
        'type' => [
            parent::TYPE_INT,
            1
        ],
        'workerId' => [
            parent::TYPE_INT,
            1
        ],
        'name' => [
            parent::TYPE_STRING,
            256
        ]
    ];

    /**
     * 添加Master进程
     * @param int    $pid
     * @param string $name
     * @return bool
     */
    public function addMaster(int $pid, string $name)
    {
        return $this->_addToTable(self::TYPE_MASTER, $pid, $name);
    }

    /**
     * 添加Manager进程
     * @param int    $pid
     * @param string $name
     * @return bool
     */
    public function addManager(int $pid, string $name)
    {
        return $this->_addToTable(self::TYPE_MANAGER, $pid, $name);
    }

    /**
     * 添加Worker进程
     * @param int    $workerId
     * @param int    $pid
     * @param string $name
     * @return bool
     */
    public function addWorker(int $workerId, int $pid, string $name)
    {
        return $this->_addToTable(self::TYPE_WORKER, $pid, $name, $workerId);
    }

    /**
     * 添加Tasker进程
     * @param int    $workerId
     * @param int    $pid
     * @param string $name
     * @return bool
     */
    public function addTasker(int $workerId, int $pid, string $name)
    {
        return $this->_addToTable(self::TYPE_TASKER, $pid, $name, $workerId);
    }

    /**
     * 添加Process进程
     * @param int    $pid
     * @param string $name
     * @return bool
     */
    public function addProcess(int $pid, string $name)
    {
        return $this->_addToTable(self::TYPE_PROCESS, $pid, $name);
    }

    public function isMaster(array $proc)
    {
        return $proc['type'] === self::TYPE_MASTER;
    }

    public function isProcess(array $proc)
    {
        return $proc['type'] === self::TYPE_PROCESS;
    }

    public function isWorker(array $proc)
    {
        return $proc['type'] === self::TYPE_WORKER || $proc['type'] === self::TYPE_TASKER;
    }

    public function canReload(array $proc)
    {
        $can = false;
        switch ($proc['type']) {
            case self::TYPE_PROCESS:
            case self::TYPE_TASKER :
            case self::TYPE_WORKER :
                $can = true;
                break;
        }
        return $can;
    }

    /**
     * @param int    $type
     * @param int    $pid
     * @param string $name
     * @param int    $workerId
     * @return bool
     */
    private function _addToTable(int $type, int $pid, string $name, int $workerId = 0)
    {
        return $this->set($pid, [
            'pid' => $pid,
            'ppid' => function_exists('posix_getppid') ? (int) posix_getppid() : 0,
            'type' => $type,
            'workerId' => $workerId,
            'name' => $name
        ]);
    }
}
