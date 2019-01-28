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
    const TABLE_NAME = 'statsTable';
    const TABLE_COUNT = 'count';
    /**
     * 列信息
     * @var array
     */
    protected $columns = [
        self::TABLE_COUNT => [
            parent::TYPE_INT,
            4
        ]
    ];
    protected $name = self::TABLE_NAME;

    /**
     * StatsTable constructor.
     * @param XHttp|XOld $server
     * @param int        $size
     */
    public function __construct($server, $size)
    {
        parent::__construct($server, $size);
    }

    /**
     * 追加Logs数量
     * @return int
     */
    public function incrLogs()
    {
        return $this->withIncr('logTable');
    }

    /**
     * 重置Logs统计
     * @return $this
     */
    public function resetLogs()
    {
        return $this->withInit('logTable');
    }

    /**
     * 读取统计值
     * @param string $key
     * @return int
     */
    public function getCount(string $key)
    {
        $data = $this->withInit($key)->get($key);
        return is_array($data) && isset($data[self::TABLE_COUNT]) ? (int) $data[self::TABLE_COUNT] : 0;
    }

    /**
     * 初始化统计字段
     * @param string $key
     * @return $this
     */
    public function withInit(string $key)
    {
        if ($this->exist($key)) {
            return $this;
        }
        $this->set($key, [self::TABLE_COUNT => 0]);
        return $this;
    }

    /**
     * 数量加
     * @param string $key
     * @param int    $count
     * @return int
     */
    public function withIncr(string $key, int $count = 1)
    {
        $this->withInit($key)->incr($key, self::TABLE_COUNT, $count);
        return $this->getCount($key);
    }

    /**
     * 数量减
     * @param string $key
     * @param int    $count
     * @return int
     */
    public function withDecr(string $key, int $count = 1)
    {
        $this->withInit($key)->decr($key, self::TABLE_COUNT, $count);
        return $this->getCount($key);
    }
}
