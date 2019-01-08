<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-01-02
 */
namespace Uniondrug\Phar\Server\Tables;

/**
 * 数据统计表
 * @package Uniondrug\Phar\Server\Tables
 */
class StatsTable extends XTable
{
    const TABLE_NAME = 'statsTable';
    /**
     * 列信息
     * @var array
     */
    protected $columns = [
        'count' => [
            parent::TYPE_INT,
            4
        ]
    ];
    protected $name = self::TABLE_NAME;

    /**
     * 追加Logs数量
     * @return int
     */
    public function incrLogs()
    {
        $key = 'logTable';
        if (!$this->exist($key)) {
            $this->set($key, [
                'count' => 1
            ]);
            return 1;
        }
        $this->incr($key, 'count', 1);
        $data = $this->get($key);
        return $data['count'];
    }

    /**
     * 重置Logs统计
     * @return int
     */
    public function resetLogs()
    {
        $key = 'logTable';
        $value = 0;
        $this->set($key, ['count' => $value]);
        return $value;
    }
}
