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
    const SPAN_ID = 'X-B3-spanid';                  // 本请求ID
    const PARENT_SPAN_ID = 'X-B3-Parentspanid';     // 上级请求ID
    const SAMPLED = 'X-B3-Sampled';                 // 抽样标识
    /**
     * 是否为Task进程
     * @var bool
     */
    private $inTask = false;
    private $traceId;
    private $spanId;
    private $parentSpanId;
    private $sampled;
    private $sampledDefault = '0';
    private $loggerPrefix = '';
    /**
     * @var array
     */
    private static $lowerKeys;

    /**
     * 读取追加Headers
     * @return array
     */
    public function getAppendTrace()
    {
        return [
            self::TRACE_ID => $this->traceId,
            self::PARENT_SPAN_ID => $this->spanId,
            self::SPAN_ID => $this->makeRequestId(),
            self::SAMPLED => $this->sampled
        ];
    }

    /**
     * @return string
     */
    public function getLoggerPrefix()
    {
        return $this->loggerPrefix;
    }

    /**
     * @return string
     */
    public function getRequestId()
    {
        return $this->traceId;
    }

    /**
     * 生成链ID
     * @return string
     */
    public function makeRequestId()
    {
        $tm = explode(' ', microtime(false));
        return sprintf("%s%d-%d-%d-%d", $this->inTask ? 't' : 'r', $tm[1], $tm[0] * 1000000, mt_rand(100000, 999999), mt_rand(100000, 999999));
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
        // 1. init headers
        if (is_array($headers)) {
            // 1.1 read from headers
            if (isset($headers[self::$lowerKeys['trace']]) && is_string($headers[self::$lowerKeys['trace']]) && $headers[self::$lowerKeys['trace']] !== '') {
                $this->traceId = $headers[self::$lowerKeys['trace']];
            }
            // 1.2 read from headers
            if (isset($headers[self::$lowerKeys['span']]) && is_string($headers[self::$lowerKeys['span']]) && $headers[self::$lowerKeys['span']] !== '') {
                $this->spanId = $headers[self::$lowerKeys['span']];
            }
            // 1.3 read from headers
            if (isset($headers[self::$lowerKeys['parentSpan']]) && is_string($headers[self::$lowerKeys['parentSpan']]) && $headers[self::$lowerKeys['parentSpan']] !== '') {
                $this->parentSpanId = $headers[self::$lowerKeys['parentSpan']];
            }
            // 1.4 read from headers
            if (isset($headers[self::$lowerKeys['sampled']]) && is_string($headers[self::$lowerKeys['sampled']]) && $headers[self::$lowerKeys['sampled']] !== '') {
                $this->sampled = $headers[self::$lowerKeys['sampled']];
            }
        }
        // 2. build trace
        $this->traceId === null && $this->traceId = $this->makeRequestId();
        // 3. build span
        $this->spanId === null && $this->spanId = $this->traceId;
        // 3. build parent span
        $this->parentSpanId === null && $this->parentSpanId = '';
        // 4. build sampled
        $this->sampled === null && $this->sampled = $this->sampledDefault;
        // 5. logger prefix
        $this->loggerPrefix = sprintf("[t=%s][s=%s][p=%s]", $this->traceId, $this->spanId, $this->parentSpanId);
    }

    /**
     * @return $this
     */
    private function checkLower()
    {
        if (self::$lowerKeys === null) {
            self::$lowerKeys = [
                'trace' => strtolower(self::TRACE_ID),
                'parentSpan' => strtolower(self::PARENT_SPAN_ID),
                'span' => strtolower(self::SPAN_ID),
                'sampled' => strtolower(self::SAMPLED)
            ];
        }
        return $this;
    }
}
