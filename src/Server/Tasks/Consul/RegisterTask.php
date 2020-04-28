<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-22
 */
namespace Uniondrug\Phar\Server\Tasks\Consul;

use GuzzleHttp\Client as GuzzleClient;
use Uniondrug\Framework\Container;
use Uniondrug\Phar\Server\Logs\Logger;
use Uniondrug\Phar\Server\Tasks\XTask;
use Uniondrug\Phar\Server\XVersion;

/**
 * 注册Consul服务
 * @package Uniondrug\Phar\Server\Tasks\Consul
 */
class RegisterTask extends XTask
{
    /**
     * 注册入参
     * <code>
     * $data = [
     *     'url' => '172.16.0.100:8500'
     * ]
     * </code>
     * @var array
     */
    protected $data;

    /**
     * @inheritdoc
     */
    public function run()
    {
        $service = $this->generatePayload();
        // 1. 计算Consul地址
        $url = "{$this->data['url']}/v1/agent/service/register";
        if (preg_match("/^https?:\/\//", $url) === 0) {
            $url = "http://{$url}";
        }
        // 2. 开始注册
        $done = false;
        $http = new GuzzleClient();
        try {
            /**
             * @var GuzzleClient $http
             */
            $http->put($url, [
                'json' => $service,
                'timeout' => 5
            ]);
            $this->getServer()->getLogger()->info("注册{%s}服务到{%s}节点", $service['Name'], $this->data['url']);
            $done = true;
        } catch(\Throwable $e) {
            $this->getServer()->getLogger()->error("服务{%s}注册到{%s}节点失败 - %s", $service['Name'], $this->data['url'], $e->getMessage());
            $this->getServer()->getLogger()->log(Logger::LEVEL_DEBUG, json_encode($service, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } finally {
            unset($http);
            file_put_contents($this->getServer()->getArgs()->logPath().'/consul.log', $service['Id']);
        }
        return $done;
    }

    /**
     * 生成服务参数
     * @return array
     */
    private function generatePayload()
    {
        // 1. 初始参数
        $data = [
            'Id' => '',
            'Name' => $this->getServer()->getConfig()->appName,
            'Port' => 80,
            'Address' => $this->getServer()->getConfig()->appName.'.'.$this->getServer()->getArgs()->getDomainSuffix(),
            'Tags' => [
                "xphar/".XVersion::get(),
                //'framework/'.Container::VERSION,
            ],
            'Check' => []
        ];
        // 2. 自定义服务名称
        $serviceName = 'consul-name';
        if ($this->getServer()->getArgs()->hasOption($serviceName)) {
            $name = (string) $this->getServer()->getArgs()->getOption($serviceName);
            $name !== '' && $data['Name'] = $name;
        }
        // 3. 自定义服务地址
        //    a): IP
        //    b): Port
        $serviceAddress = 'consul-address';
        if ($this->getServer()->getArgs()->hasOption($serviceAddress)) {
            $addr = (string) $this->getServer()->getArgs()->getOption($serviceAddress);
            if ($addr !== '') {
                if (preg_match("/^https?:\/\//i", $addr) === 0) {
                    $addr = "http://{$addr}";
                }
                if (preg_match("/^(\S+):(\d+)$/", $addr, $m) > 0) {
                    $addr = $m[1];
                    $data['Port'] = (int) $m[2];
                }
                $data['Address'] = $addr;
            }
        }
        // 4. 健康检查
        $address = $this->getServer()->getConfig()->host === '0.0.0.0' ? $this->getServer()->getConfig()->deployIp : $this->getServer()->getConfig()->host;
        $address .= ':'.$this->getServer()->getConfig()->port;
        $heartbeat = 10;
        // 4.1 自定义健康检查地址
        //     a): IP
        //     b): Port
        $healthAddress = 'consul-health';
        if ($this->getServer()->getArgs()->hasOption($healthAddress)) {
            $value = (string) $this->getServer()->getArgs()->getOption($healthAddress);
            if ($value !== '') {
                $address = $value;
            }
        }
        // 4.1 自定义心跳检测时长
        $healthHeartbeat = 'consul-heartbeat';
        if ($this->getServer()->getArgs()->hasOption($healthHeartbeat)) {
            $value = (int) $this->getServer()->getArgs()->getOption($healthHeartbeat);
            if ($value > 0) {
                $heartbeat = $value;
            }
        }
        // 4.3 配置健康片段
        $data['Check'] = [
            "args" => [
                "/bin/bash",
                "/data/scripts/consul.check.sh",
                "{$this->getServer()->getArgs()->workingPath()}",
                "{$this->getServer()->getConfig()->port}",
                "{$this->getServer()->getConfig()->deployIp}",
                "{$this->getServer()->getConfig()->host}",
            ],
            "interval" => "{$heartbeat}s",
            "timeout" => "{$heartbeat}s"
        ];
        //$data['Check'] = [
        //    "HTTP" => "http://{$address}/consul.health",
        //    "interval" => "{$heartbeat}s"
        //];
        // 4.4 unique ID
        $data['Id'] = $data['Name'].'-'.$this->getServer()->getConfig()->deployIp;
        // 5. tags
        $data['Tags'][] = 'deploy/'.$this->getServer()->getConfig()->deployIp;
        if ($this->getServer()->getConfig()->deployIp !== $this->getServer()->getConfig()->host) {
            $data['Tags'][] = 'listen/'.$this->getServer()->getConfig()->host.':'.$this->getServer()->getConfig()->port;
        } else {
            $data['Tags'][] = 'listen/'.$this->getServer()->getConfig()->port;
        }
        return $data;
    }
}
