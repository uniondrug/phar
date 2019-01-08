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
        $this->withFile();
        return false;
    }

    /**
     * 使用文件模式
     */
    private function withFile()
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
        }
    }

    private function withKafka()
    {
        // todo: send log to kafka
    }
}
