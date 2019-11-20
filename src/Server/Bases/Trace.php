<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-10-28
 */
namespace Uniondrug\Phar\Server\Bases;

/**
 * 请求链
 * @package Uniondrug\Phar\Server\Bases
 */
class Trace
{
    const TRACE_ID = 'X-B3-Traceid';                // 主链名称
    const SPAN_ID = 'X-B3-Spanid';                  // 本请求ID
    const PARENT_SPAN_ID = 'X-B3-Parentspanid';     // 上级请求ID
    const SAMPLED = 'X-B3-Sampled';                 // 抽样标识
    const POINT_VERSION = 'X-B3-Version';           // 链版本号
    /**
     * 是否为Task进程
     * @var bool
     */
    private $inTask = false;
    private $traceId = '';
    private $spanId = '';
    private $parentSpanId = '';
    private $sampled = '';
    private $sampledDefault = '0';
    private $loggerPoint = 0;
    private $loggerPointVersion = '0';
    private $loggerPrefix = '';
    /**
     * @var array
     */
    private static $lowerKeys;
    private static $traceIdLength = 8;
    private static $spanIdLength = 8;

    /**
     * 读取追加Headers
     * @param bool $lower
     * @return array
     */
    public function getAppendTrace(bool $lower = false)
    {
        if ($lower) {
            return [
                strtolower(self::TRACE_ID) => $this->traceId,
                strtolower(self::PARENT_SPAN_ID) => $this->spanId,
                strtolower(self::SPAN_ID) => $this->makeSpanId(),
                strtolower(self::SAMPLED) => $this->sampled,
                strtolower(self::POINT_VERSION) => $this->getLoggerVersion()
            ];
        }
        return [
            self::TRACE_ID => $this->traceId,
            self::PARENT_SPAN_ID => $this->spanId,
            self::SPAN_ID => $this->makeSpanId(),
            self::SAMPLED => $this->sampled,
            self::POINT_VERSION => $this->getLoggerVersion()
        ];
    }

    /**
     * 读取日志前缀
     * @return string
     */
    public function getLoggerPrefix()
    {
        return $this->loggerPrefix;
    }

    public function getLoggerPoint()
    {
        return $this->loggerPoint;
    }

    public function getLoggerVersion()
    {
        return $this->loggerPointVersion.'.'.$this->loggerPoint;
    }

    /**
     * 读取主链ID
     * @return string
     */
    public function getRequestId()
    {
        return $this->traceId;
    }

    /**
     * 生成主链ID
     * @return string
     */
    public function makeTraceId()
    {
        $tm = explode(' ', microtime(false));
        return sprintf("%s%s%s%d%d", $this->inTask ? 'b' : 'a', $tm[1], (int) ($tm[0] * 1000000), mt_rand(10000000, 99999999), mt_rand(1000000, 9999999));
    }

    /**
     * 生成请求链ID
     * @return string
     */
    public function makeSpanId()
    {
        $tm = explode(' ', microtime(false));
        return sprintf("c%s%s%d%d", $tm[1], (int) ($tm[0] * 1000000), mt_rand(10000000, 99999999), mt_rand(1000000, 9999999));
    }

    /**
     * 生成请求链ID
     * @return string
     */
    public function makeRequestId()
    {
        $tm = explode(' ', microtime(false));
        return sprintf("%s%s%s%d%d", $this->inTask ? 'b' : 'a', $tm[1], (int) ($tm[0] * 1000000), mt_rand(10000000, 99999999), mt_rand(1000000, 9999999));
    }

    public function plusPoint()
    {
        $this->loggerPoint++;
    }

    /**
     * 重置
     * @param array $headers
     * @param bool  $inTask
     */
    public function reset($headers = null, bool $inTask = false)
    {
        $this->inTask = $inTask;
        $this->checkLower();
        // 1. 初始化链参数
        $spanId = false;
        $traceId = false;
        $parentSpanId = false;
        $sampled = false;
        $version = false;
        // 2. 从Header读取
        if (is_array($headers)) {
            // 2.1 SpanId
            if (isset($headers[self::$lowerKeys['span']]) && is_string($headers[self::$lowerKeys['span']]) && $headers[self::$lowerKeys['span']] !== '') {
                $spanId = $headers[self::$lowerKeys['span']];
            }
            // 2.2 TraceId
            if (isset($headers[self::$lowerKeys['trace']]) && is_string($headers[self::$lowerKeys['trace']]) && $headers[self::$lowerKeys['trace']] !== '') {
                $traceId = $headers[self::$lowerKeys['trace']];
            }
            // 2.3 ParentSpanId
            if (isset($headers[self::$lowerKeys['parentSpan']]) && is_string($headers[self::$lowerKeys['parentSpan']]) && $headers[self::$lowerKeys['parentSpan']] !== '') {
                $parentSpanId = $headers[self::$lowerKeys['parentSpan']];
            }
            // 2.4 Sampled
            if (isset($headers[self::$lowerKeys['sampled']]) && is_string($headers[self::$lowerKeys['sampled']]) && $headers[self::$lowerKeys['sampled']] !== '') {
                $sampled = $headers[self::$lowerKeys['sampled']];
            }
            // 2.5 Version
            if (isset($headers[self::$lowerKeys['version']]) && is_string($headers[self::$lowerKeys['version']]) && $headers[self::$lowerKeys['version']] !== '') {
                $version = $headers[self::$lowerKeys['version']];
            }
        }
        // 3. 分配变更
        $this->spanId = $spanId === false ? $this->makeSpanId() : $spanId;
        $this->traceId = $traceId === false ? $this->makeTraceId() : $traceId;
        $this->parentSpanId = $parentSpanId === false ? '' : $parentSpanId;
        $this->sampled = $sampled === false ? $this->sampledDefault : $parentSpanId;
        // 4. 日志前缀
        $this->loggerPoint = 0;
        $this->loggerPointVersion = $version === false ? '0' : $version;
        $this->loggerPrefix = sprintf("[t=%s][s=%s][p=%s]", $this->traceId, $this->spanId, $this->parentSpanId);
    }

    /**
     * 初始化字写值
     * @return $this
     */
    private function checkLower()
    {
        if (self::$lowerKeys === null) {
            self::$lowerKeys = [
                'trace' => strtolower(self::TRACE_ID),
                'parentSpan' => strtolower(self::PARENT_SPAN_ID),
                'span' => strtolower(self::SPAN_ID),
                'sampled' => strtolower(self::SAMPLED),
                'version' => strtolower(self::POINT_VERSION)
            ];
        }
        return $this;
    }
}
