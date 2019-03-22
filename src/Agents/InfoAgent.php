<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-16
 */
namespace Uniondrug\Phar\Agents;

/**
 * PHAR包明细
 * @package Uniondrug\Phar\Agents
 */
class InfoAgent extends Abstracts\Agent
{
    protected static $title = '查看信息';
    protected static $description = '当以PHAR部署时, 查询包的明细信息';

    /**
     * @inheritdoc
     */
    public function run()
    {
        // 1. phar only
        if (!defined('PHAR_WORKING_FILE')) {
            $this->printLine("查看出错: {red=限PHAR模式查看}");
            return;
        }
        // 2. file path
        $file = PHAR_ROOT.'/info.json';
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
        $data['logs'] = isset($data['logs']) ? $data['logs'] : [];
        // 5. print info
        $this->printLine("          包名: {blue=%s}", PHAR_WORKING_NAME);
        $this->printLine("          仓库: git pull {blue=%s} {yellow=%s}", $data['repository'], $data['branch']);
        $this->printLine("          源码: {blue=%s}/{yellow=%s}", $data['environment'], $data['commit']);
        $this->printLine("          时间: {blue=%s}于{yellow=%s}主机", $data['time'], $data['machine']);

        foreach ($data['logs'] as $log){
            $this->printLine("                {$log}");
        }
    }

    public function runHelp()
    {
        $this->run();
    }
}
