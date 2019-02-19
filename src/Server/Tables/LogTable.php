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
    const MUTEX_TIMEOUT = 0.2;
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
     * 防内存溢出阀值
     * @var int
     */
    private $limit = 0;
    /**
     * 内存表名称
     * @var string
     */
    protected $name = self::TABLE_NAME;
    private $mutex;

    /**
     * constructor.
     * @param XHttp $server
     * @param int   $size
     */
    public function __construct($server, $size)
    {
        $size < 1024 && $size = 1024;
        parent::__construct($server, $size);
        $this->limit = $size / 2;
        $this->mutex = new Lock(SWOOLE_MUTEX);
    }

    /**
     * 添加日志
     * @param string $level
     * @param string $message
     * @return bool
     * @throws \Exception
     */
    public function add(string $level, string $message)
    {
        $key = $this->makeKey();
        $len = strlen($message);
        if ($len > self::MESSAGE_LENGTH) {
            $message = substr($message, 0, self::MESSAGE_LENGTH - 8).' ...';
        }
        $full = false;
        $this->mutex->lockwait(self::MUTEX_TIMEOUT);
        try {
            $this->set($key, [
                'key' => $key,
                'time' => (new \DateTime())->format('Y-m-d H:i:s.u'),
                'level' => $level,
                'message' => $message
            ]);
            if (error_get_last() !== null) {
                error_clear_last();
            }
            $full = count($this) >= $this->limit;
        } catch(\Throwable $e) {
        }
        $this->mutex->unlock();
        return $full;
    }

    /**
     * 清空日志
     * @return array
     */
    public function flush()
    {
        $this->mutex->lockwait(self::MUTEX_TIMEOUT);
        $logs = [];
        try {
            foreach ($this as $key => $data) {
                if ($this->del($key)) {
                    $logs[$key] = $data;
                }
            }
        } catch(\Throwable $e) {
        }
        $this->mutex->unlock();
        return $logs;
    }

    /**
     * 生成键名
     * 按时间生成长度为23个字符的Key名称
     * @return string
     */
    private function makeKey()
    {
        return sprintf("l%16d%03d%03d", microtime(true) * 1000000, mt_rand(1, 999), mt_rand(1, 999));
    }
}
