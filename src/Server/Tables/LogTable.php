<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-01-02
 */
namespace Uniondrug\Phar\Server\Tables;

use Swoole\Lock;
use Uniondrug\Phar\Server\XHttp;

/**
 * 日志内存表
 * 系统运行过程中的日志先加入到内存中, 待积累一定
 * 量时，触发Publish日志
 * @package Uniondrug\Phar\Server\Tables
 */
class LogTable extends XTable
{
    const MESSAGE_LENGTH = 4096;
    const TABLE_NAME = 'logTable';
    /**
     * 数量
     * @var int
     */
    private $maxCount = 0;
    /**
     * 列信息
     * @var array
     */
    protected $columns = [
        'id' => [
            parent::TYPE_STRING,
            20
        ],
        'time' => [
            parent::TYPE_STRING,
            20
        ],
        'level' => [
            parent::TYPE_STRING,
            12
        ],
        'message' => [
            parent::TYPE_STRING,
            self::MESSAGE_LENGTH
        ]
    ];
    protected $name = self::TABLE_NAME;

    /**
     * LogTable constructor.
     * @param XHttp $server
     * @param int   $size
     */
    public function __construct($server, $size)
    {
        parent::__construct($server, $size + 200);
        $this->maxCount = $size;
    }

    /**
     * 添加记录
     * @param string $level
     * @param string $msg
     * @return bool
     */
    public function add(string $level, string $msg)
    {
        // 1. 计算唯一Key
        //    非绝对重复, 可能性比较小
        $key = sprintf("l%13d%03d%03d", microtime(true) * 1000, mt_rand(1, 999), mt_rand(1, 999));
        // 2. 消息长度
        if (strlen($msg) > self::MESSAGE_LENGTH) {
            $msg = substr($msg, 0, self::MESSAGE_LENGTH);
        }
        // 3. 加锁
        $mutex = new Lock(SWOOLE_MUTEX);
        $mutex->lock();
        $this->set($key, [
            'time' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $msg
        ]);
        // 4. 统计
        $count = $this->getServer()->getStatsTable()->incrLogs();
        $mutex->unlock();
        return $count >= $this->maxCount;
    }

    /**
     * 提交日志
     * @return array
     */
    public function flush()
    {
        $mutex = new Lock(SWOOLE_MUTEX);
        $mutex->lock();
        $data = [];
        foreach ($this as $key => $item) {
            $this->del($key);
            $data[] = $item;
        }
        $this->getServer()->getStatsTable()->resetLogs();
        $mutex->unlock();
        return $data;
    }
}
