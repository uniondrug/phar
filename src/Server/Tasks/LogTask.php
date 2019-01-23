<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Tasks;

/**
 * LogTask/异步Log任务
 * @package Uniondrug\Phar\Server\Tasks
 */
class LogTask extends XTask
{
    /**
     * 当Log数据为空/则退出执行
     * @return bool
     */
    public function beforeRun() : bool
    {
        if (count($this->data) === 0) {
            return false;
        }
        $data = [];
        foreach ($this->data as $tmp) {
            if (isset($tmp['key']) && $tmp['key'] !== '') {
                $data[$tmp['key']] = $tmp;
            }
        }
        ksort($data);
        reset($data);
        $this->data = $data;
        return parent::beforeRun();
    }

    /**
     * 任务过程
     * @return mixed
     */
    public function run()
    {
        if ($this->getServer()->getConfig()->logKafkaOn && $this->withKafka()) {
            return true;
        }
        return $this->withFile();
    }

    /**
     * 将日志写入文件
     * @return bool
     */
    private function withFile() : bool
    {
        $dir = $this->getServer()->getArgs()->getLogDir();
        $path = $dir.'/'.date('Y-m');
        if (!is_dir($path)) {
            @mkdir($path, 0777);
        }
        $file = $path.'/'.date('Y-m-d').'.log';
        $mode = file_exists($file) ? 'a+' : 'wb+';
        $text = "";
        foreach ($this->data as $line) {
            $text .= sprintf("[%s][%s] %s\n", $line['time'], $line['level'], $line['message']);
        }
        if (false !== ($fp = @fopen($file, $mode))) {
            fwrite($fp, $text);
            fclose($fp);
            return true;
        }
        return false;
    }

    /**
     * 将日志提交到Kafka
     * @return bool
     */
    private function withKafka() : bool
    {
        // todo: send log to kafka
        return false;
    }
}
