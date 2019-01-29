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
    /**
     * 单条Log最大字符数
     */
    const MESSAGE_LENGTH = 8192;
    /**
     * 内存表名称
     */
    const TABLE_NAME = 'logTable';
    /**
     * 列信息
     * @var array
     */
    protected $columns = [
        'key' => [
            parent::TYPE_STRING,
            23
        ],
        'time' => [
            parent::TYPE_STRING,
            28
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
    /**
     * 互拆锁
     * @var Lock
     */
    private $mutex;
    /**
     * 防内存溢出阀值
     * @var int
     */
    private $limit = 0;
    /**
     * 内存表名称
     * @var string
     */
    protected $name = self::TABLE_NAME;

    /**
     * constructor.
     * @param XHttp $server
     * @param int   $size
     */
    public function __construct($server, $size)
    {
        $size < 128 && $size = 128;
        parent::__construct($server, $size);
        $this->limit = $size - 32;
        $this->mutex = new Lock(SWOOLE_MUTEX);
    }

    /**
     * 添加记录
     * @param StatsTable $table
     * @param string     $level
     * @param string     $msg
     * @return array|null
     */
    public function add(StatsTable $table, string $level, string $msg)
    {
        // 1. 生成键名/Key
        $key = $this->makeKey();
        // 2. 日志长度/单条日志最大字符数限制
        if (strlen($msg) > self::MESSAGE_LENGTH) {
            $msg = substr($msg, 0, self::MESSAGE_LENGTH);
        }
        // 3. 内存表加锁
        $this->mutex->lock();
        // 4. 向内存表写入数据
        $this->set($key, [
            'key' => $key,
            'time' => (new \DateTime())->format('Y-m-d H:i:s.u'),
            'level' => $level,
            'message' => $msg
        ]);
        // 5. 统计内存表数量
        $count = $table->incrLogs();
        // 6. 内存表解锁
        $this->mutex->unlock();
        // 7. 返回数据
        if ($count >= $this->limit) {
            return $this->flush($table);
        }
        return null;
    }

    /**
     * 清空日志
     * @param StatsTable $table
     * @return array|null
     */
    public function flush(StatsTable $table)
    {
        // 1. 加锁
        $this->mutex->lock();
        // 2. 读取数据
        $num = 0;
        $data = $this->toArray();
        foreach ($this as $key => $item) {
            $num++;
            $this->del($key);
        }
        // 4. 统计重设置
        $table->resetLogs();
        // 3. 解锁
        $this->mutex->unlock();
        // 4. 返回结果
        return $num > 0 ? $data : null;
    }

    /**
     * 生成键名
     * 按时间生成长度为23个字符的Key名称
     * @return string
     */
    public function makeKey()
    {
        return sprintf("l%16d%03d%03d", microtime(true) * 1000000, mt_rand(1, 999), mt_rand(1, 999));
    }
}
