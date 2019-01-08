<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server\Managers\Clients;

/**
 * 帮助中心
 * @package Uniondrug\Phar\Bootstrap\Managers\Clients
 */
class HelpClient extends Abstracts\Client
{
    public function run() : void
    {
        $this->runHelp();
    }

    public function runHelp() : void
    {
        // 1. guard
        $script = $this->boot->getArgs()->getScript();
        substr($script, 0, 2) === './' || $script = "php {$script}";
        $this->printLine("接受命令: %s [{yellow=命令}]", $script);
        /**
         * @var IClient $class
         */
        $path = __DIR__;
        $d = dir($path);
        while (false !== ($e = $d->read())) {
            if (!preg_match("/^(\S+)Client\.php$/", $e, $m)) {
                continue;
            }
            if (strlen($m[1]) > 1 && $m[1] !== 'Help') {
                $class = "\\Uniondrug\\Phar\\Server\\Managers\\Clients\\{$m[1]}Client";
                if (is_a($class, IClient::class, true)) {
                    $title = $class::getTitle();
                    $name = preg_replace("/^\-+/", "", preg_replace_callback("/([A-Z])/", function($a){
                        return "-".strtolower($a[1]);
                    }, $m[1]));
                    $this->printLine("          {yellow=%s} %s", sprintf("%-28s", $name), $title);
                }
            }
        }
        $d->close();
    }
}
