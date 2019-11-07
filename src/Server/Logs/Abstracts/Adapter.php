<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-15
 */
namespace Uniondrug\Phar\Server\Logs\Abstracts;

use Uniondrug\Phar\Server\Logs\Logger;

abstract class Adapter
{
    /**
     * Logger对象
     * @var Logger
     */
    protected $logger;
    /**
     * Log结构
     * <ul>
     * <li>time: 日志产生时间</li>
     * <li>level: 日志级别/INFO,ERROR,DEBUG等</li>
     * <li>action: 操作动作/CURL和增、删、改、查</li>
     * <li>module: 模块名称, 由哪个模块提交的日志</li>
     * <li>duration: 执行时长, 记录哪些脚本/接口跑的比较慢</li>
     * <li>pid: 进程ID</li>
     * <li>traceId: 主链ID</li>
     * <li>spanId: 当前请求ID</li>
     * <li>parentSpanId: 来源请求ID</li>
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
    private $loggerFields = [
        'time' => '',
        'level' => '',
        'action' => '',
        'module' => '',
        'duration' => 0.0,
        'pid' => 0,
        'requestId' => '',
        'requestMethod' => '',
        'requestUrl' => '',
        'traceId' => '',
        'spanId' => '',
        'parentSpanId' => '',
        'serverAddr' => '',
        'taskId' => 0,
        'taskName' => '',
        'version' => '',
        'content' => ''
    ];
    private $loggerFieldsRexp = "/\[([^\]]+)\]/";

    /**
     * Logger实例
     * @param Logger $logger
     * @return $this
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * 运行Adapter
     * @param array $datas
     * @return mixed
     */
    abstract function run(array $datas);

    /**
     * 解析Logger
     * 将Log数据转成KafkaLogger可识别格式
     * <code>
     * $data = [
     *     'time' => '2019-03-21 12:44:55.5678912',
     *     'deploy' => '172.16.0.100:8080',
     *     'app' => 'module.user',
     *     'level' => 1,
     *     'message' => 'Log Content'
     * ]
     * </code>
     * @param array $data
     * @return array
     */
    protected function parserLogger(array $data)
    {
        // 1. base
        $fields = $this->loggerFields;
        $fields['time'] = $data['time'];
        $fields['module'] = $data['app'];
        $fields['level'] = $this->logger->makeLevel($data['level']);
        $fields['serverAddr'] = $data['deploy'];
        // 2. content
        $this->parserLoggerContent($fields, $data);
        return $fields;
    }

    /**
     * @param array $fields
     * @param array $data
     */
    protected function parserLoggerContent(& $fields, & $data)
    {
        $fields['content'] = preg_replace_callback($this->loggerFieldsRexp, function($m) use (&$fields){
            $s = explode('=', $m[1]);
            $c = count($s);
            // 1. 保持原样
            if ($c < 2) {
                return $m[0];
            }
            // 2. 结构计算
            $r = true;
            $s[0] = strtolower($s[0]);
            switch ($s[0]) {
                case 'a' :
                    $fields['action'] = $s[1];
                    break;
                case 'd' :
                    $fields['duration'] = (double) $s[1];
                    break;
                case 'm' :
                    $fields['requestMethod'] = $s[1];
                    break;
                case 'p' :
                    $fields['parentSpanId'] = $s[1];
                    break;
                case 'r' :
                    $fields['requestId'] = $s[1];
                    break;
                case 's' :
                    $fields['spanId'] = $s[1];
                    break;
                case 't' :
                    $fields['traceId'] = $s[1];
                    break;
                case 'u' :
                    $fields['requestUrl'] = $s[1];
                    break;
                case 'v' :
                    $fields['version'] = $s[1];
                    break;
                case 'x' :
                    $fields['pid'] = (int) $s[1];
                    break;
                case 'y' :
                    $fields['taskName'] = $s[1];
                    break;
                case 'z' :
                    $fields['taskId'] = (int) $s[1];
                    break;
                default :
                    $r = false;
                    break;
            }
            // 3. 数据转换
            return $r ? "" : $m[0];
        }, $data['message']);
    }
}
