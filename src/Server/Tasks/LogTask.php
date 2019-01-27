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
     * Log结构
     * @var array
     */
    private $logFields = [
        'time' => '',
        'level' => '',
        'action' => '',
        'host' => '',
        'module' => '',
        'duration' => 0.0,
        'pid' => 0,
        'requestId' => '',
        'requestMethod' => '',
        'requestUrl' => '',
        'taskId' => 0,
        'taskName' => '',
        'content' => ''
    ];

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
        return false;
    }

    /**
     * 日志数据逐条解析
     */
    private function parseRows()
    {
        $num = 0;
        $logs = [];
        foreach ($this->data as $data) {
            if (isset($data['message'])) {
                $buffer = $this->logFields;
                $message = $data['message'];
                $this->collectDeploy($buffer, $message);
                $this->collectKeywords($buffer, $message);
                $buffer['time'] = $data['time'];
                $buffer['level'] = $data['level'];
                $buffer['content'] = $message;
                $logs[] = $buffer;
                $num++;
            }
        }
    }

    /**
     * 收集/WHERE
     * 从Log中收集当前记录由哪台机器产生
     * @param array  $data
     * @param string $text
     */
    private function collectDeploy(array & $data, string & $text)
    {
        $rexp = "/\[(\d+\.\d+\.\d+\.\d+:\d+)\]\[([^\]]*)\]/";
        if (preg_match($rexp, $text, $m) > 0) {
            $data['host'] = $m[1];
            $data['module'] = $m[2];
            $text = preg_replace($rexp, "", $text);
        }
    }

    /**
     * @param array  $data
     * @param string $text
     */
    private function collectKeywords(array & $data, string & $text)
    {
        $rexp = "/\[([_a-zA-Z0-9\-]+)=([^\]]+)\]/";
        if (preg_match_all($rexp, $text, $m) > 0) {
            foreach ($m[1] as $i => $k) {
                switch ($k) {
                    case 'a' :
                        $data['action'] = $m[2][$i];
                        break;
                    case 'd' :
                        $data['duration'] = (double) $m[2][$i];
                        break;
                    case 'm' :
                        $data['requestMethod'] = strtoupper($m[2][$i]);
                        break;
                    case 'r' :
                        $data['requestId'] = $m[2][$i];
                        break;
                    case 'u' :
                        $data['requestUrl'] = $m[2][$i];
                        break;
                    case 'x' :
                        $data['pid'] = (int) $m[2][$i];
                        break;
                    case 'y' :
                        $data['taskName'] = $m[2][$i];
                        break;
                    case 'z' :
                        $data['taskId'] = (int) $m[2][$i];
                        break;
                }
            }
            $text = preg_replace($rexp, "", $text);
        }
    }
}
