<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server;

/**
 * @package Uniondrug\Phar\Server
 */
class XVersion
{
    const VERSION_MAJOR = 1;
    const VERSION_MINOR = 2;
    const VERSION_RELEASE = 1;

    /**
     * 读取版本号
     * @return string
     */
    public static function get()
    {
        return sprintf("%d.%d.%d", self::VERSION_MAJOR, self::VERSION_MINOR, self::VERSION_RELEASE);
    }
}
