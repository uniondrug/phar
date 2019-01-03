<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-01-02
 */
namespace Uniondrug\Phar\Server\Tables;

use Swoole\Table;

/**
 * 内存表基类
 * @package Uniondrug\Phar\Server\Tables
 */
abstract class XTable extends Table implements ITable
{
    /**
     * 列定义
     * <code>
     * $columns = [
     *     'id' => [
     *         parent::TYPE_INT, 4
     *     ]
     * ]
     * </code>
     * @var array
     */
    protected $columns = [];
    /**
     * 表名称
     * @var string
     */
    protected $name;

    /**
     * @param int $size
     */
    public function __construct($size)
    {
        parent::__construct($size);
        foreach ($this->columns as $name => $opts) {
            $this->column($name, $opts[0], $opts[1]);
        }
    }

    /**
     * 转Array数组
     * @return array
     */
    public function toArray()
    {
        $data = [];
        foreach ($this as $row) {
            $data[] = $row;
        }
        return $data;
    }

    /**
     * 转JSON字符串
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
