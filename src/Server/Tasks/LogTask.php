<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-30
 */
namespace Uniondrug\Phar\Server\Tasks;

use GuzzleHttp\Client;

/**
 * LogTask/异步Log任务
 * @package Uniondrug\Phar\Server\Tasks
 */
class LogTask extends XTask
{
    /**
     * Log结构
     * <ul>
     * <li>time: 日志产生时间</li>
     * <li>level: 日志级别/INFO,ERROR,DEBUG等</li>
     * <li>action: 操作动作/CURL和增、删、改、查</li>
     * <li>module: 模块名称, 由哪个模块提交的日志</li>
     * <li>duration: 执行时长, 记录哪些脚本/接口跑的比较慢</li>
     * <li>pid: 进程ID</li>
     * <li>requestId: 请求标识, 同个一请求产生的Log, 都会带此参数</li>
     * <li>requestMethod: 请求方式, Restful的GET/POST等</li>
     * <li>requestUrl: 请求URL地址</li>
     * <li>serverAddr: 服务地址</li>
     * <li>taskId: 异步任务ID</li>
     * <li>taskName: 异步任务名称</li>
     * <li>content: 日志内容</li>
     * </ul>
     * @var array
     */
    private $logFields = [
        'time' => '',
        'level' => '',
        'action' => '',
        'module' => '',
        'duration' => 0.0,
        'pid' => 0,
        'requestId' => '',
        'requestMethod' => '',
        'requestUrl' => '',
        'serverAddr' => '',
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
        ksort($this->data);
        reset($this->data);
        return parent::beforeRun();
    }

    /**
     * 任务过程
     * @return mixed
     */
    public function run()
    {
        try {
            if ($this->getServer()->getConfig()->logKafkaOn && $this->withKafka()) {
                return true;
            }
            return $this->withFile();
        } catch(\Throwable $e) {
            return false;
        }
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
        try {
            $parsed = $this->parseRows();
            if ($parsed['count'] > 0) {
                $url = $this->getServer()->getConfig()->logKafkaUrl;
                /**
                 * @var Client $client
                 */
                $client = $this->getServer()->getContainer()->getShared('httpClient');
                $client->post($url, [
                    'timeout' => 3,
                    'headers' => [
                        'content-type' => 'application/json'
                    ],
                    'json' => [
                        'logs' => $parsed['logs']
                    ]
                ]);
                return true;
            }
        } catch(\Throwable $e) {
            $this->getServer()->getLogger()->warning("%s向{%s}提交Log失败 - %s", $this->logPrefix, $this->getServer()->getConfig()->logKafkaUrl, $e->getMessage());
        }
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
        return [
            'count' => $num,
            'logs' => $logs
        ];
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
            $data['serverAddr'] = $m[1];
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
