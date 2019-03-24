<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-16
 */
namespace Uniondrug\Phar\Agents;

use Uniondrug\Phar\Agents\Abstracts\IAgent;

/**
 * 关于服务
 * @package Uniondrug\Phar\Agents
 */
class HelpAgent extends Abstracts\Agent
{
    protected static $title = '关于服务';

    /**
     * 运行服务
     */
    public function run()
    {
        $this->runHelp();
    }

    /**
     * 帮助中心
     */
    public function runHelp()
    {
        // 1. guard
        $script = $this->getRunner()->getArgs()->getScript();
        substr($script, 0, 2) === './' || $script = "php {$script}";
        $this->printLine("接受命令: %s [{yellow=命令}]", $script);
        /**
         * @var IAgent $class
         */
        $path = __DIR__;
        $d = dir($path);
        while (false !== ($e = $d->read())) {
            if (!preg_match("/^(\S+)Agent\.php$/", $e, $m)) {
                continue;
            }
            if (strlen($m[1]) > 1 && $m[1] !== 'Help') {
                $class = "\\Uniondrug\\Phar\\Agents\\{$m[1]}Agent";
                if (is_a($class, IAgent::class, true)) {
                    $title = $class::getTitle();
                    $description = $class::getDescription();
                    $description === '' || $description = "; {$description}";
                    $name = preg_replace("/^\-+/", "", preg_replace_callback("/([A-Z])/", function($a){
                        return "-".strtolower($a[1]);
                    }, $m[1]));
                    $this->printLine("          {yellow=%s} %s", sprintf("%-28s", $name), $title.$description);
                }
            }
        }
        $d->close();
    }
}
