<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-15
 */
namespace Uniondrug\Phar\Server\Logs\Adapters;

use Uniondrug\Phar\Server\Logs\Abstracts\Adapter;

/**
 * Logger发到Kafka
 * * @package Uniondrug\Phar\Server\Logs\Adapters
 */
class KafkaAdapter extends Adapter
{
    /**
     * 以HTTP方式
     * @inheritdoc
     */
    public function run(array $datas)
    {
        // 1. init logger datas
        $logs = [];
        foreach ($datas as $i => $data) {
            $logs[] = $this->parserLogger($data);
        }
        try {
            $url = $this->logger->getConfig()->getKafkaUrl();
            $timeout = $this->logger->getConfig()->getKafkaTimeout();
            $http = $this->logger->getServer()->getContainer()->getShared('httpClient');
            $http->post($url, [
                'timeout' => $timeout,
                'headers' => [
                    'content-type' => 'application/json'
                ],
                'json' => [
                    'logs' => $logs
                ]
            ]);
            return true;
        } catch(\Throwable $e) {
            throw $e;
        }
    }
}
