<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-01-02
 */
namespace Uniondrug\Phar\Server\Tables;

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
    const MESSAGE_SIZE = 65536;
    /**
     * 内存表名称
     */
    const NAME = 'logTable';
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
     * 内存表名称
     * @var string
     */
    protected $name = self::NAME;
    private $limit = 100;

    /**
     * 初始化内存
     * 内存表最大条目数由常量指定, 参数$size设置批量提交频率
     * 即每遇内存积累$size条记录时, 上报Log
     * @param XHttp $server
     * @param int   $size 范围在32-1024间任意数字
     */
    public function __construct($server, $size)
    {
        parent::__construct($server, $size);
    }

    /**
     * 添加日志
     * @param string $level
     * @param string $message
     * @return bool|null
     * @throws \Exception
     */
    public function add(string $level, string $message)
    {
        $key = $this->makeKey();
        $len = strlen($message);
        if ($len > self::MESSAGE_LENGTH) {
            $message = substr($message, 0, self::MESSAGE_LENGTH - 8).' ...';
        }
        $mutex = $this->getServer()->getMutex();
        if ($mutex->lock()) {
            try {
                $done = $this->set($key, [
                    'key' => $key,
                    'time' => (new \DateTime())->format('Y-m-d H:i:s.u'),
                    'level' => $level,
                    'message' => $message
                ]);
                if (error_get_last() !== null) {
                    error_clear_last();
                }
                if ($done) {
                    return $this->count() >= $this->limit;
                }
            } catch(\Throwable $e) {
            } finally {
                $mutex->unlock();
            }
        }
        return null;
    }

    /**
     * @return array|false
     */
    public function pop()
    {
        $i = 0;
        $mutex = $this->getServer()->getMutex();
        if ($mutex->lock()) {
            try {
                $data = [];
                foreach ($this as $key => $row) {
                    if ($this->del($key)) {
                        $i++;
                        $data[$key] = $row;
                        if ($i >= $this->limit) {
                            break;
                        }
                    }
                }
            } catch(\Throwable $e) {
            } finally {
                $mutex->unlock();
            }
        }
        return $i > 0 ? $data : false;
    }

    /**
     * 设置限额
     * @param int $limit
     * @return $this
     */
    public function setLimit(int $limit)
    {
        $this->limit = $limit;
        return $this;
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
