<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-01-09
 */
namespace Uniondrug\Phar\Server\Tasks\Consul;

use GuzzleHttp\Client;
use Uniondrug\Phar\Server\Tasks\XTask;
use Uniondrug\Phar\Server\XVersion;

/**
 * RegisterTask/注册Consul服务
 * @package Uniondrug\Phar\Server\Tasks\Consul
 */
class RegisterTask extends XTask
{
    /**
     * 预定义域名后缀
     * @var array
     */
    private static $domains = [
        'development' => 'dev.dovecot.cn',
        'testing' => 'turboradio.cn',
        'release' => 'uniondrug.net',
        'production' => 'uniondrug.cn'
    ];

    /**
     * 任务过程
     * {"Name":"token.module","Address":"token.module.uniondrug.cn","Port":80}
     * @return mixed
     */
    public function run()
    {
        $container = $this->getServer()->getContainer();
        // 1. 服务名称
        $name = $this->getServer()->getArgs()->getOption('consul-name');
        $name || $name = $container->getConfig()->path('app.appName');
        // 2. 服务地址
        //    example.domain.com
        //    example.domain.com:8080
        $port = 80;
        $addr = $this->getServer()->getArgs()->getOption('consul-address');
        if ($addr !== null && $addr !== "") {
            if (preg_match("/([_a-zA-Z0-9\-\.]+):(\d+)$/", $addr, $m) > 0) {
                $addr = $m[1];
                $port = (int) $m[2];
            }
        } else {
            $env = $container->environment();
            $addr = $container->getConfig()->path('app.appName').'.'.(isset(self::$domains[$env]) ? self::$domains[$env] : self::$domains['development']);
        }
        // 3. 健康检查
        //    "Check": {"HTTP": "http://192.168.3.195:8118/consul.health","Interval": "5s","TTL": "5s"}
        $healthHost = $this->getServer()->getConfig()->getDeployIp();
        $healthPort = $this->getServer()->getConfig()->port;
        if ($healthHost === "0.0.0.0" || $healthHost === "127.0.0.1") {
            $healthHost = $addr;
            $healthPort = $port;
        }
        // 4. 服务注册结构
        $tag = defined("PHAR_WORKING_TAG") ? PHAR_WORKING_TAG : $this->getServer()->getConfig()->version;
        $body = [
            'Name' => $name,
            'Address' => $addr,
            'Port' => $port,
            'Tags' => [
                "ver/".$tag,
                "xphar/".XVersion::get(),
                //"php/".PHP_VERSION,
                //'swoole/'.SWOOLE_VERSION,
                //'phalcon/'.\Phalcon\Version::get(),
                'framework/'.\Uniondrug\Framework\Container::VERSION
            ],
            'check' => [
                "HTTP" => sprintf("http://%s:%d/consul.health", $healthHost, $healthPort),
                "Interval" => "5s"
            ]
        ];
        // 5. 注册服务
        try {
            /**
             * @var Client $client
             */
            $this->getServer()->getLogger()->info("注册Consul服务 - %s", json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $regurl = $this->getServer()->getArgs()->getOption('consul-register');
            $client = $container->getShared('httpClient');
            $client->put("http://{$regurl}/v1/agent/service/register", [
                'json' => $body
            ]);
        } catch(\Throwable $e) {
            $this->getServer()->getLogger()->fatal("注册Consul服务失败 - %s", $e->getMessage());
            return false;
        }
        return true;
    }
}
