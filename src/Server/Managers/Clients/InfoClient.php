<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server\Managers\Clients;

/**
 * 查看PHAR构建信息
 * 1. date
 * 2. git remote
 * 3. git branch
 * @package Uniondrug\Phar\Bootstrap\Managers\Clients
 */
class InfoClient extends Abstracts\Client
{
    /**
     * 描述
     * @var string
     */
    protected static $description = '打印包(PHP Archive)的构建信息';
    /**
     * 名称
     * @var string
     */
    protected static $title = '查看信息';

    /**
     * 查看详情
     */
    public function run() : void
    {
        // 1. phar only
        if (!defined('PHAR_WORKING_FILE')) {
            $this->printLine("查看出错: {red=限PHAR模式查看}");
            return;
        }
        // 2. file path
        $file = __DIR__.'/../../../../../../../info.json';
        if (!file_exists($file)) {
            $this->printLine("查看出错: {red=未找到%s文件}", $file);
            return;
        }
        // 3. read file
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            $this->printLine("查看出错: {red=解析info.json出错} - %s", json_last_error_msg());
            return;
        }
        // 4. init info
        $data['time'] = isset($data['time']) ? $data['time'] : '';
        $data['repository'] = isset($data['repository']) ? $data['repository'] : '';
        $data['branch'] = isset($data['branch']) ? $data['branch'] : '';
        $data['commit'] = isset($data['commit']) ? $data['commit'] : '';
        $data['environment'] = isset($data['environment']) ? $data['environment'] : '';
        $data['machine'] = isset($data['machine']) ? $data['machine'] : '';
        // 5. print info
        $this->printLine("          包名: {blue=%s}", PHAR_WORKING_NAME);
        $this->printLine("          仓库: git pull {blue=%s} {yellow=%s}", $data['repository'], $data['branch']);
        $this->printLine("          源码: {blue=%s}/{yellow=%s}", $data['environment'], $data['commit']);
        $this->printLine("          时间: {blue=%s}于{yellow=%s}主机", $data['time'], $data['machine']);
    }
}
