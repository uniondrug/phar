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
        'serverAddr' => '',
        'taskId' => 0,
        'taskName' => '',
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
     * @param $fields
     */
    protected function parserLoggerContent(& $fields, & $data)
    {
        $message = $data['message'];
        $prepends = [];
        if (preg_match_all($this->loggerFieldsRexp, $message, $m) > 0) {
            $message = preg_replace($this->loggerFieldsRexp, "", $message);
            foreach ($m[1] as $i => $v) {
                $vs = explode('=', $v);
                $vl = count($vs);
                if ($vl === 0) {
                    $prepends[] = $m[0][$i];
                } else {
                    switch (strtolower($vs[0])) {
                        case 'a' :
                            $fields['action'] = $vs[1];
                            break;
                        case 'd' :
                            $fields['duration'] = (double) $vs[1];
                            break;
                        case 'm' :
                            $fields['requestMethod'] = $vs[1];
                            break;
                        case 'r' :
                            $fields['requestId'] = $vs[1];
                            break;
                        case 'u' :
                            $fields['requestUrl'] = $vs[1];
                            break;
                        case 'x' :
                            $fields['pid'] = (int) $vs[1];
                            break;
                        case 'y' :
                            $fields['taskName'] = $vs[1];
                            break;
                        case 'z' :
                            $fields['taskId'] = (int) $vs[1];
                            break;
                        default :
                            $prepends[] = $m[0][$i];
                            break;
                    }
                }
            }
        }
        $fields['content'] = implode('', $prepends).$message;
    }
}
