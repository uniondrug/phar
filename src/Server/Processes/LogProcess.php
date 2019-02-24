<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Processes;

use Uniondrug\Phar\Server\Tables\LogTable;
use Uniondrug\Phar\Server\Tables\StatsTable;
use Uniondrug\Phar\Server\Tasks\LogTask;

/**
 * 日志管理器
 * @package Uniondrug\Phar\Server\Processes
 */
class LogProcess extends XProcess
{
    /**
     * 禁用状态
     * @var bool
     */
    private $disabled;
    /**
     * 内存表对象
     * @var LogTable
     */
    private $table;
    /**
     * 内存统计表
     * @var StatsTable
     */
    private $statsTable;
    /**
     * 检查周期
     * @var int
     */
    private $delayms = 1000;
    /**
     * 上报数量
     * 单位: 条
     * 范围: 32-1024
     * 说明: 当内存表中日志积累数量, 在此范围时
     *      从内存表中提出Log, 上报到Kafka(或落盘)
     * @var int
     */
    private $limit = 100;
    /**
     * 提交频次
     * 单位: 秒
     * 范围: 3-300
     * 说明: 连续2次上报Log时间间隔
     * @var int
     */
    private $seconds = 60;
    /**
     * 最近上报
     * 最近一次上报Log的Unix时间戳
     * @var int
     */
    private $timestamp = 0;
    /**
     * 上报总数
     * 进程自动启动至今共上报数量
     * @var int
     */
    private $timerCount = 0;

    /**
     * 前置
     */
    public function beforeRun()
    {
        parent::beforeRun();
        // 1. 基础参数
        $this->disabled = $this->getServer()->getArgs()->hasOption('log-stdout');
        $this->table = $this->getServer()->getLogTable();
        $this->statsTable = $this->getServer()->getStatsTable();
        $this->timestamp = time();
        // 2. 单次提交数量
        $limit = (int) $this->getServer()->getConfig()->logBatchLimit;
        if ($limit >= 32 && $limit <= 1024) {
            $this->limit = $limit;
        }
        // 3. 提交频次
        $seconds = (int) $this->getServer()->getConfig()->logBatchSeconds;
        if ($seconds >= 3 && $seconds <= 300) {
            $this->seconds = (int) $seconds;
        }
    }

    /**
     * 执行过程
     */
    public function run()
    {
        // 1. 未启用
        //    当启动应用时设置了`--log-stdout`选项, 则视为禁用了
        if ($this->disabled) {
            return;
        }
        // 2. 循环执行
        while (true) {
            // todo: 如此设置, CPU占用比较多
            //       实现逻辑得优化, 本次暂设置
            //       1秒检查一次内存表, 进行数据
            //       上报, 不保证能解决问题
            // date: 2019-02-24
            $this->timerCount++;
            $this->timer();
            usleep($this->delayms * 1000);
        }
    }

    /**
     * 检查数量
     * @return bool
     */
    public function timer()
    {
        // echo "[".date('H:i:s')."] - Run({$this->timerCount})\n";
        $time = time();
        // 1. 定时提交
        if (($time - $this->timestamp) >= $this->seconds) {
            $this->save();
            return true;
        }
        // 2. 检查数量
        if ($this->table->count() >= $this->limit) {
            $this->save();
            return true;
        }
        // 3. 无需提交
        return false;
    }

    /**
     * 读取数量并提交
     */
    public function save()
    {
        // 1. 读取内容
        $limit = 0;
        $datas = [];
        foreach ($this->table as $key => $data) {
            if ($this->table->del($key)) {
                $datas[$key] = $data;
                $limit++;
                if ($limit >= $this->limit) {
                    break;
                }
            }
        }
        // 2. 更新记时
        $this->timestamp = time();
        if ($limit === 0) {
            return false;
        }
        // 3. 执行Task
        $this->statsTable->incrLogs();
        $this->statsTable->incrLogsCount($limit);
        $this->statsTable->incrLogsStored($this->table->count());
        $this->getServer()->runTask(LogTask::class, $datas);
        return true;
    }
}
