<?php
/**
 * Created by PhpStorm.
 * User: weng
 * Date: 7/16/21
 * Time: 1:19 PM
 */
namespace Uniondrug\Phar\Server\Processes;

use Uniondrug\Redis\Client;

class ServicePushProcess extends XProcess
{
    private $_secondTimer = 1000;
    private $_isPush = false;
    private $_timeId = 0;

    public $client;

    public function run()
    {
        /** @var \Uniondrug\HttpClient\Client $client */
        $this->client =$this->getServer()->getContainer()->getShared('httpClient');
        //判断服务是否可以上报
        if (array_key_exists('servicePush', $this->getServer()->getConfig()->getScanned())) {
            $this->_isPush = true;
        }
        $this->_timeId = $this->getServer()->after($this->_secondTimer, [
            $this,
            'handlePush'
        ]);
    }

    public function handlePush()
    {
        try {
            if ($this->getServer()->getConfig()->getScanned()['servicePush']['value']['servicePush'] == 1) {
                //进行服务上报
                $PushData = $this->servicePush();
                if (is_array($PushData)) {
                    $config = $this->getServer()->getConfig()->getScanned()['app'];
                    $this->getUrlPush($PushData, $config['value']);
                    $this->getServer()->getLogger()->info("执行时间：".$this->_secondTimer);
                    $this->setSecondTimer();
                    //清除定时器
                    //$server->clearTimer($this->_timeId);
                    //再次执行定时器
                    $this->_timeId = $this->getServer()->after($this->_secondTimer, [
                        $this,
                        'handlePush'
                    ]);

                } else {
                    $this->getServer()->getLogger()->info("非phar 包版本不进行服务上报");
                }

            }
        } catch(\Throwable $e) {
            $this->getServer()->getLogger()->error("服务运行上报出错 - %s", $e->getMessage());
        }
    }

    /**
     * 上报间隔时间拉去
     */
    public function setSecondTimer()
    {
        $time = 43200000;
        try {
            $request = $this->client->request("POST", $this->getServer()->getConfig()->getScanned()['servicePush']['value']['pushUrl']."/uom/rpt/findConfig", [
                'headers' => ['content-type' => 'application/json;charset=utf-8']
            ]);
            $json = json_decode($request->getBody()->getContents(), true);

            if (key_exists("errno", $json)) {
                if($json['errno'] == 0){
                    //进行拉去服务上报时间
                    $time = $json['data']['serviceReportInterval']*3600000;
                    $this->getServer()->getLogger()->info("定时上报时间拉去:".$time);
                }else{
                    $this->getServer()->getLogger()->error("拉取服务时间解析错误：".$request->getBody()->getContents());
                }
            } else {
                //数据解析错误
                $this->getServer()->getLogger()->error("拉取服务时间解析错误：".$request->getBody()->getContents());
            }

            //上报完成进行定时处理
        } catch(\Exception $exception) {
            $this->getServer()->getLogger()->error("拉取服务错误：".$exception->getMessage());
        } finally {
            $this->_secondTimer = $time;
        }
    }

    /**
     * @return bool
     */
    public function beforeRun()
    {
        if ($this->_isPush == true) {
            $this->handlePush();
        }
        return parent::beforeRun();
    }

    /**
     * 读取基本配置
     * @return array
     */
    private function servicePush()
    {
        $file = PHAR_ROOT.'/info.json';
        if (!file_exists($file)) {
            $this->getServer()->getLogger()->error("查看出错: {red=未找到%s文件}", $file);
            return;
        }
        // 3. read file
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        return $data;
    }

    /**
     * 服务上报
     * @param array $data
     * @param array $config
     * @throws \Throwable
     */
    public function getUrlPush(array $data, array $config)
    {
        $serviceNode = getenv("NODE_NAME");
        if (!$serviceNode) {
            $serviceNode = 'dev';
        }
        $di = $this->getServer()->getContainer();
        $postRpt = [
            'productNo' => $this->getServer()->getConfig()->getScanned()['servicePush']['value']['productNo'],
            //产品编码
            'moduleNo' => $this->getServer()->getConfig()->getScanned()['servicePush']['value']['moduleNo'],
            //模块编码
            'projectVersion' => $config['appVersion'],
            //模块版本
            'gitCommitId' => $data['commit'],
            //git commit id
            'gitTag' => $data['branch'],
            //gitTag
            'gitBranch' => $data['branch'],
            //git 打包分支
            'gitCommitTime' => date("Y-m-d H:i:s", strtotime($data['time'])),
            //git 提交时间
            'sdate' => date("Y-m-d", time()),
            'runEnv' => $di->environment(),
            'serviceNode' => $serviceNode,
            'serviceIp' => $serviceNode,
            //服务k8s节点名称 环境变量的NODE_NAME
        ];
        /** @var \Uniondrug\HttpClient\Client $client */
        $client = $di->getShared('httpClient');
        //var_dump($client);
        try {
            $request = $client->request("POST", $this->getServer()->getConfig()->getScanned()['servicePush']['value']['pushUrl']."/uom/rpt/saveRecord", [
                'headers' => ['content-type' => 'application/json;charset=utf-8'],
                'json' => $postRpt
            ]);
            $this->getServer()->getLogger()->info("服务上报完成:".$request->getBody()->getContents());
            //上报完成进行定时处理
        } catch(\Exception $exception) {
            $this->getServer()->getLogger()->error("上报服务错误：".$exception->getMessage());
        }
    }
}