<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Builder;

use Composer\Script\Event;

/**
 * @package Uniondrug\Phar
 */
class Composer
{
    /**
     * @param Event $e
     */
    public static function init($e)
    {
        echo "Initialize Composer after install/upadte\n";
    }
}
