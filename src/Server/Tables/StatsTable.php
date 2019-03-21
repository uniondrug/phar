<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-17
 */
namespace Uniondrug\Phar\Server\Tables;

/**
 * 数据统计表
 * @package Uniondrug\Phar\Server\Tables
 */
class StatsTable extends XTable
{
    const NAME = 'stats';
    const FIELD = 'count';
    const TASK_ON = 'onTask';
    const TASK_ON_FAILURE = 'onTaskFailure';
    const TASK_RUN = 'runTask';
    public static $name = self::NAME;
    protected $columns = [
        self::FIELD => [
            parent::TYPE_INT,
            4
        ]
    ];

    /**
     * @return bool
     */
    public function incrTaskOn()
    {
        return $this->incr(self::TASK_ON, self::FIELD, 1);
    }

    /**
     * @return bool
     */
    public function incrTaskFailure()
    {
        return $this->incr(self::TASK_ON_FAILURE, self::FIELD, 1);
    }

    /**
     * @return bool
     */
    public function incrTaskRun()
    {
        return $this->incr(self::TASK_RUN, self::FIELD, 1);
    }
}
