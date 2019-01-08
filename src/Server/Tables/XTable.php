<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-01-02
 */
namespace Uniondrug\Phar\Server\Tables;

use Swoole\Table;
use Uniondrug\Phar\Server\Exceptions\ErrorExeption;
use Uniondrug\Phar\Server\XHttp;

/**
 * 内存表基类
 * @package Uniondrug\Phar\Server\Tables
 */
abstract class XTable extends Table implements ITable
{
    /**
     * @var XHttp
     */
    private $server;
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
     * @param XHttp $server
     * @param int   $size
     */
    public function __construct($server, $size)
    {
        parent::__construct($size);
        $this->server = $server;
        foreach ($this->columns as $name => $opts) {
            $this->column($name, $opts[0], $opts[1]);
        }
        if (!$this->create()) {
            throw new ErrorExeption("创建{%s}表失败", get_class($this));
        }
    }

    /**
     * 读取表名称
     * @return string
     */
    public function getName()
    {
        if ($this->name === null) {
            $name = get_class($this);
            if (preg_match("/([_a-zA-Z0-9]+)$/", $name, $m)) {
                $name = $m[1];
            }
            $this->name = $name;
        }
        return $this->name;
    }

    /**
     * @return XHttp
     */
    public function getServer()
    {
        return $this->server;
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
