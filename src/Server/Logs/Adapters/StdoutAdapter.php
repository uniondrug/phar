<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-15
 */
namespace Uniondrug\Phar\Server\Logs\Adapters;

use Uniondrug\Phar\Server\Logs\Abstracts\Adapter;
use Uniondrug\Phar\Server\Logs\Logger;

/**
 * 在控制台打印Logger
 * @package Uniondrug\Phar\Server\Logs\Adapters
 */
class StdoutAdapter extends Adapter
{
    /**
     * Level颜色字义
     * debug: 白底蓝字
     *  info: 白底绿字
     *  warn: 黄底黑字
     * error: 黄底红字
     * fatal: 红底黄字
     * @var array
     */
    private $colors = [
        Logger::LEVEL_DEBUG => [
            37,
            49
        ],
        Logger::LEVEL_INFO => [
            34,
            48
        ],
        Logger::LEVEL_WARNING => [
            33,
            45
        ],
        Logger::LEVEL_ERROR => [
            31,
            43
        ],
        Logger::LEVEL_FATAL => [
            33,
            41
        ]
    ];

    /**
     * @param array $datas
     * @return bool
     */
    public function run(array $datas)
    {
        foreach ($datas as $data) {
            $this->println($this->logger->makeLevel($data['level']), $data);
        }
        return true;
    }

    /**
     * 打印内容
     * @param string $level
     * @param        $data
     */
    private function println(string $level, $data)
    {
        $c = isset($this->colors[$data['level']]) ? $this->colors[$data['level']] : [
            0,
            0
        ];
        file_put_contents("php://stdout", sprintf("\033[%dm%s\033[0m\n", $c[0], sprintf("[%s][%s][%s][%s]%s", $data['time'], $data['deploy'], $data['app'], $level, $data['message'])));
        //file_put_contents("php://stdout", sprintf("\033[%d;%dm%s\033[0m\n", $c[0], $c[1], sprintf("[%s][%s][%s][%s]%s", $data['time'], $data['deploy'], $data['app'], $level, $data['message'])));
    }
}
