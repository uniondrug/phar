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
    /**
     * 表名称
     * @var string
     */
    protected $name = self::TABLE_NAME;

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
     * 追加Logs数量
     * @return int
     */
    public function incrLogs()
    {
        $key = 'logTable';
        $this->incrCount($key);
        return $this->getCount($key);
    }

    /**
     * 重置Logs统计
     * @return bool
     */
    public function resetLogs()
    {
        return $this->resetCount('logTable');
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
            if (is_array($data) && isset($data[self::TABLE_COUNT])) {
                return (int) $data[self::TABLE_COUNT];
            }
        }
        return 0;
    }

    /**
     * 统计递加
     * @param string $key
     * @param int    $count
     * @return int
     */
    public function incrCount(string $key, int $count = 1)
    {
        if ($this->exist($key)) {
            return $this->incr($key, self::TABLE_COUNT, $count);
        }
        return $this->resetCount($key);
    }

    /**
     * 重置统计值
     * @param string $key
     * @param int    $count
     * @return bool
     */
    public function resetCount(string $key, int $count = 0)
    {
        return $this->set($key, [self::TABLE_COUNT => $count]);
    }
}
