<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-17
 */
namespace Uniondrug\Phar\Server\Tables;

use Uniondrug\Phar\Exceptions\ServiceException;
use Uniondrug\Phar\Server\Services\Http;
use Uniondrug\Phar\Server\Services\Socket;

/**
 * 内存表基类
 * @package Uniondrug\Phar\Server\Tables
 */
class XTable extends \Swoole\Table implements ITable
{
    /**
     * 列定义
     * @var array
     */
    protected $columns = [];
    /**
     * 表命名
     * @var string
     */
    public static $name;
    /**
     * @var Http|Socket
     */
    private $_server;

    /**
     * @param Http|Socket $server
     * @param int         $size
     * @throws ServiceException
     */
    public function __construct($server, int $size)
    {
        $this->_server = $server;
        if (static::$name === null) {
            throw new ServiceException("table {".get_class($this)."} name can not be null.");
        }
        if (!is_numeric($size) || $size < 2) {
            throw new ServiceException("table size can not be {$size}.");
        }
        parent::__construct($size);
        foreach ($this->columns as $name => $cols) {
            $this->column($name, $cols[0], $cols[1]);
        }
        $this->create();
    }

    /**
     * 读取Server对象
     * @return Http|Socket
     */
    public function getServer()
    {
        return $this->_server;
    }

    /**
     * Key列表
     * @return array
     */
    public function keys()
    {
        $keys = [];
        foreach ($this as $key => $temp) {
            $keys[] = $key;
        }
        return $keys;
    }

    /**
     * 内存表数据转数组
     * @return array
     */
    public function toArray()
    {
        $datas = [];
        foreach ($this as $key => $data) {
            $datas[$key] = $data;
        }
        return $datas;
    }

    /**
     * 内表数据转JSON
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
