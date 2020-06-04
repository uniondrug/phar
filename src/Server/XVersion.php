<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-21
 */
namespace Uniondrug\Phar\Server;

/**
 * @package Uniondrug\Phar\Server
 */
class XVersion
{
    const VERSION_MAJOR = 1;
    const VERSION_MINOR = 4;
    const VERSION_RELEASE = 41;

    /**
     * 读取版本号
     * @return string
     */
    public static function get()
    {
        return sprintf("%d.%d.%d", self::VERSION_MAJOR, self::VERSION_MINOR, self::VERSION_RELEASE);
    }
}
