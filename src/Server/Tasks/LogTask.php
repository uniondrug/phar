<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Tasks;

/**
 * 以异步方式, 将业务Log发送到Log中心
 * @package Uniondrug\Phar\Server\Tasks
 */
class LogTask extends XTask
{
    /**
     * 当Log数据为空/则退出执行
     * @return bool
     */
    public function beforeRun()
    {
        if (count($this->data) === 0) {
            return false;
        }
        return parent::beforeRun();
    }

    /**
     * 任务过程
     * @return mixed
     */
    public function run()
    {
        echo "Kafka begin ---- \n";
        foreach ($this->data as $key => $data) {
            echo implode("\t", $data)."\n";
        }
        echo "Kafka end ---- \n";
        // todo: 向Kafka发送业务日志
        return false;
    }
}
