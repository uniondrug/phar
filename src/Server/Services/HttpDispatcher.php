<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-16
 */
namespace Uniondrug\Phar\Server\Services;

use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;

/**
 * Http服务
 * @package Uniondrug\Phar\Server\Services
 */
class HttpDispatcher
{
    private $_isAssets;
    private $_isHealth;
    private $_isHealthName = '';
    private $_isTable;
    private $_isTableName = '';
    private $_begin = 0.0;
    private $_content = '';
    private $_memoryBegin = 0;
    private $_requestId = null;
    private $_requestUrl = '';
    private $_requestMethod = '';
    private $_responseCookies = [];
    private $_responseHeaders = [];
    private $_status;
    private $swooleRequest;
    private $swooleResponse;
    private $server;

    /**
     * HttpDispatcher constructor.
     * @param Http|Socket    $server
     * @param SwooleRequest  $request
     * @param SwooleResponse $response
     */
    public function __construct($server, $request, $response)
    {
        // 1. tips: TIME/Memory
        $this->_begin = microtime(true);
        $this->_memoryBegin = memory_get_usage(true);
        $this->swooleRequest = $request;
        $this->swooleResponse = $response;
        // 2. logger profile
        $this->_requestUrl = $request->server['request_uri'];
        $this->_requestMethod = strtoupper($request->server['request_method']);
        $this->server = $server;
        $this->server->getLogger()->setPrefix("[r=%s][m=%s][u=%s]", $this->getRequestId(), $this->_requestMethod, $this->_requestUrl)->startProfile();
        $this->server->getLogger()->debugOn() && $this->server->getLogger()->debug("开始HTTP请求, 初始{%.01f}M内存", ($this->_memoryBegin / 1024 / 1024));
        // 3. super variables
        $this->mergeSuperVariables();
        $this->prepareInput();
        $this->prepareHeaders();
    }

    /**
     * Destroy
     */
    public function __destruct()
    {
    }

    /**
     * HTTP请求结束
     * @return bool
     */
    public function end()
    {
        // 1. http status code
        $this->swooleResponse->status($this->_status);
        // 2. append header
        foreach ($this->_responseHeaders as $key => $value) {
            $this->swooleResponse->header($key, $value);
        }
        // 3. append cookie
        foreach ($this->_responseCookies as $cookie) {
            $this->swooleResponse->cookie(... $cookie);
        }
        // 4. assign contents
        $this->swooleResponse->end($this->_content);
        // 5. completed
        $duration = microtime(true) - $this->_begin;
        // 6. debug logger
        if ($this->server->getLogger()->debugOn()) {
            $this->server->getLogger()->debug("请求HTTP结果 - %s", preg_replace("/\n\s*/", "", $this->_content));
        }
        if ($duration > $this->server->getConfig()->slowRequestDuration) {
            $this->server->getLogger()->warning("HTTP慢请求 - 共用时{%.06f}秒", $duration);
        }
        $memory = memory_get_usage(true);
        $this->server->getLogger()->debugOn() && $this->server->getLogger()->debug("[d=%.06f]完成HTTP请求, 占用{%.01f}M内存", $duration, $memory / 1024 / 1024);
        $this->server->getLogger()->endProfile();
        // 7. mark memory
        return $memory >= $this->server->getConfig()->memoryLimit;
    }

    /**
     * @return string
     */
    public function getHealthName()
    {
        return $this->_isHealthName;
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->_isTableName;
    }

    /**
     * RawBody
     * @return string
     */
    public function getRawBody()
    {
        return $this->swooleRequest->rawContent();
    }

    /**
     * 请求链ID
     * @return string
     */
    public function getRequestId()
    {
        if ($this->_requestId === null) {
            if (isset($this->swooleRequest->header['request-id']) && is_string($this->swooleRequest->header['request-id']) && $this->swooleRequest->header['request-id'] !== '') {
                $this->_requestId = $this->swooleRequest->header['request-id'];
            } else {
                $requestId = 'r';
                $requestId .= (int) (microtime(true) * 1000000);
                $requestId .= mt_rand(1000000, 9999999);
                $requestId .= mt_rand(10000000, 99999999);
                $this->_requestId = $requestId;
            }
        }
        return $this->_requestId;
    }

    public function getUrl()
    {
        return $this->_requestUrl;
    }

    /**
     * 是否静态资源
     * @return bool
     */
    public function isAssets()
    {
        if ($this->_isAssets === null) {
            $this->_isAssets = preg_match("/\.([_a-zA-Z0-9\-]+)$/", $this->_requestUrl) > 0;
        }
        return $this->_isAssets;
    }

    /**
     * 是否为健康检查
     * @return bool
     */
    public function isHealth()
    {
        if ($this->_isHealth === null) {
            if ($this->isAssets()) {
                $this->_isHealth = preg_match("/([\w]+)\.health$/i", $this->_requestUrl, $m) > 0;
                $this->_isHealth && $this->_isHealthName = $m[1];
            } else {
                $this->_isHealth = false;
            }
        }
        return $this->_isHealth;
    }

    /**
     * 是否为内存数据请求
     * @return bool
     */
    public function isTable()
    {
        if ($this->_isTable === null) {
            if ($this->isAssets()) {
                $this->_isTable = preg_match("/([\w]+)\.table$/i", $this->_requestUrl, $m) > 0;
                $this->_isTable && $this->_isTableName = $m[1];
            } else {
                $this->_isTable = false;
            }
        }
        return $this->_isTable;
    }

    /**
     * Cookie透传
     * @param        $key
     * @param        $value
     * @param int    $expire
     * @param string $path
     * @param string $domain
     * @param bool   $secure
     * @param bool   $httponly
     * @return $this
     */
    public function setCookie($key, $value, $expire = 0, $path = '/', $domain = '', $secure = false, $httponly = false)
    {
        $this->_responseCookies[] = func_get_args();
        return $this;
    }

    /**
     * 设置返回内容
     * Response: content
     * @param string $content
     * @return $this
     */
    public function setContent(string $content = null)
    {
        if ($content === null) {
            $content = '';
        }
        $this->_content = $content;
        return $this;
    }

    /**
     * 设置文档类型
     * Response: content type
     * @param string $contentType
     * @return $this
     */
    public function setContentType(string $contentType)
    {
        $this->setHeader("content-type", $contentType);
        return $this;
    }

    /**
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function setHeader(string $key, string $value = null)
    {
        $key = ucfirst(preg_replace_callback("/[\-]+(\S)/", function($a){
            return "-".strtoupper($a[1]);
        }, strtolower($key)));
        $this->_responseHeaders[$key] = $value;
        return $this;
    }

    /**
     * 设置HTTP状态码
     * Response: http status code
     * @param int $status
     * @return $this
     */
    public function setStatus(int $status)
    {
        $this->_status = $status;
        return $this;
    }

    /**
     * 合并超全局变量
     */
    private function mergeSuperVariables()
    {
        // 1. GET/POST/REQUEST/FILES
        $_GET = isset($this->swooleRequest->get) && is_array($this->swooleRequest->get) ? $this->swooleRequest->get : [];
        $_POST = isset($this->swooleRequest->post) && is_array($this->swooleRequest->post) ? $this->swooleRequest->post : [];
        $_REQUEST = array_replace_recursive($_GET, $_POST);
        $_FILES = isset($this->swooleRequest->files) && is_array($this->swooleRequest->files) ? $this->swooleRequest->files : [];
        // 2. COOKIE/SERVER
        $_COOKIE = isset($this->swooleRequest->cookie) && is_array($this->swooleRequest->cookie) ? $this->swooleRequest->cookie : [];
        // 3. SERVER
        $_SERVER = [
            'REQUEST-ID' => $this->_requestId,
            'HTTP_REQUEST_ID' => $this->_requestId
        ];
        foreach ($this->swooleRequest->server as $key => $value) {
            $_SERVER[strtoupper($key)] = $value;
        }
        foreach ($this->swooleRequest->header as $key => $value) {
            $key = strtoupper(str_replace("-", "_", $key));
            if (preg_match("/^HTTP_/", $key) === 0) {
                $key = "HTTP_{$key}";
            }
            $_SERVER[$key] = $value;
        }
    }

    /**
     * 预置Header信息
     */
    private function prepareHeaders()
    {
        $this->setStatus($this->server->getConfig()->statusCode);
        $this->setContentType($this->server->getConfig()->contentType);
        $this->setHeader("server", $this->server->getConfig()->appName."/".$this->server->getConfig()->appVersion);
        $this->setHeader("request-id", $this->getRequestId());
    }

    /**
     * 标记入参
     */
    private function prepareInput()
    {
        $debugOn = $this->server->getLogger()->debugOn();
        if ($debugOn) {
            if (isset($this->swooleRequest->header) && is_array($this->swooleRequest->header) && count($this->swooleRequest->header) > 0) {
                $this->server->getLogger()->debug("Headers: %s", http_build_query($this->swooleRequest->header));
            }
            if (isset($this->swooleRequest->get) && is_array($this->swooleRequest->get) && count($this->swooleRequest->get) > 0) {
                $this->server->getLogger()->debug("QString: %s", http_build_query($this->swooleRequest->get));
            }
            /**
             * 请求入参/去除boundary
             */
            if (isset($this->swooleRequest->files) && is_array($this->swooleRequest->files) && count($this->swooleRequest->files) > 0) {
                $this->server->getLogger()->debug("Upload: %s", json_encode($this->swooleRequest->files, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } else {
                $this->server->getLogger()->debug("RawBody: %s", preg_replace("/\n\s*/", "", $this->swooleRequest->rawContent()));
            }
        }
    }
}
