<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-01-02
 */
namespace Uniondrug\Phar\Server\Tables;

use Uniondrug\Phar\Server\XHttp;
use Uniondrug\Phar\Server\XOld;

/**
 * 数据统计表
 * @package Uniondrug\Phar\Server\Tables
 */
class StatsTable extends XTable
{
    const FIELD = 'count';
    const NAME = 'statsTable';
    const SIZE = 256;
    /**
     * 统计类型
     */
    const KEY_TASK_ON = 'onTask';
    const KEY_TASK_ON_FAIL = 'onTaskFail';
    const KEY_TASK_RUN = 'runTask';
    const KEY_TASK_RUN_FAIL = 'runTaskFail';
    const KEY_LOGS_TIMES = 'logPublish';
    const KEY_LOGS_COUNT = 'logPublishCount';
    const KEY_LOGS_STORED = 'logStored';
    /**
     * 列信息
     * @var array
     */
    protected $columns = [
        self::FIELD => [
            parent::TYPE_INT,
            4
        ]
    ];
    /**
     * 表名称
     * @var string
     */
    protected $name = self::NAME;

    /**
     * constructor.
     * @param XHttp|XOld $server
     * @param int        $size
     */
    public function __construct($server, $size)
    {
        parent::__construct($server, $size);
    }

    /**
     * 读取统计值
     * @param string $key
     * @return int
     */
    public function getCount(string $key)
    {
        if ($this->exist($key)) {
            $data = $this->get($key);
            if (is_array($data) && isset($data[self::FIELD])) {
                return (int) $data[self::FIELD];
            }
        }
        return 0;
    }

    /**
     * 统计递加
     * @param string $key
     * @param int    $count
     * @return bool
     */
    public function incrCount(string $key, int $count = 1)
    {
        if ($this->exist($key)) {
            return $this->incr($key, self::FIELD, $count);
        }
        return $this->resetCount($key);
    }

    /**
     * 上报次数
     * @return bool
     */
    public function incrLogs()
    {
        return $this->incrCount(self::KEY_LOGS_TIMES, 1);
    }

    /**
     * 上报数量
     * 共上报多少数据到Kafka/File中
     * @param int $count
     * @return bool
     */
    public function incrLogsCount(int $count)
    {
        return $this->incrCount(self::KEY_LOGS_COUNT, $count);
    }

    /**
     * 积余统计
     * 内存表积余Log数量
     * @param int $count
     * @return bool
     */
    public function incrLogsStored(int $count)
    {
        return $this->incrCount(self::KEY_LOGS_STORED, $count);
    }

    /**
     * 触发onTask()次数
     * @return bool
     */
    public function incrTaskOn()
    {
        return $this->incrCount(self::KEY_TASK_ON);
    }

    /**
     * 解发onTask()失败次数
     * @return bool
     */
    public function incrTaskOnFail()
    {
        return $this->incrCount(self::KEY_TASK_ON_FAIL);
    }

    /**
     * 调用runTask()次数
     * @return bool
     */
    public function incrTaskRun()
    {
        return $this->incrCount(self::KEY_TASK_RUN);
    }

    /**
     * 调用runTask()失败次数
     * @return bool
     */
    public function incrTaskRunFail()
    {
        return $this->incrCount(self::KEY_TASK_RUN_FAIL);
    }

    /**
     * 重置统计值
     * @param string $key
     * @param int    $count
     * @return bool
     */
    public function resetCount(string $key, int $count = 0)
    {
        return $this->set($key, [self::FIELD => $count]);
    }
}
