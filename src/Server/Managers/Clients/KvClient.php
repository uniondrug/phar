<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Server\Managers\Clients;

use GuzzleHttp\Client as HttpClient;

/**
 * Consul KV/管理
 * @package Uniondrug\Phar\Bootstrap\Managers\Clients
 */
class KvClient extends Abstracts\Client
{
    /**
     * 描述
     * @var string
     */
    protected static $description = '从Consul/KV中拉取配置信息并写入tmp/config.php';
    /**
     * 名称
     * @var string
     */
    protected static $title = '同步配置';
    /**
     * 选项
     * @var array
     */
    protected static $options = [
        [
            'name' => 'env',
            'short' => 'e',
            'value' => 'name',
            'desc' => '指定环境名, 可选: {yellow=development}、{yellow=testing}、{yellow=release}、{yellow=production}, 默认: {yellow=development}'
        ],
        [
            'name' => 'consul',
            'desc' => 'Consul服务地址'
        ]
    ];
    private $consulApi;

    /**
     * 导入配置
     * 合并命令行参数
     */
    public function loadConfig()
    {
        parent::loadConfig();
        $this->boot->getConfig()->mergeArgs();
    }

    /**
     * 同步配置
     * 1. 从config目录导出配置模块
     * 2. 从consul/kv中读取环境参数
     * 3. 覆盖模块字段
     * 4. 存为最终配置(tmp/config.php)文件
     */
    public function run() : void
    {
        $host = $this->boot->getArgs()->getOption('consul');
        if ($host === null) {
            $this->printLine("同步出错: 未通过'--consul=URL'选项指定服务地址");
            return;
        }
        if ($host === "") {
            $this->printLine("同步出错: 选项'--consul=URL'指定的服务地址不能为空");
            return;
        }
        // 0. 环境名称
        $this->printLine("准备模块: 创建【{$this->boot->getConfig()->environment}】环境模板");
        // 1. 读取模块
        $data = $this->scanConfig($this->boot->getConfig()->environment);
        if ($data === false) {
            return;
        }
        // 2. Key名称
        $key = null;
        if (isset($data['app'], $data['app']['appName'])) {
            $key = preg_replace("/^\/+/", "", $data['app']['appName']);
        }
        if ($key === null) {
            $this->printLine("同步出错: 【Config】项目未配置app.appName参数");
            return;
        }
        // 3. 读取远程
        preg_match("/^(http|https):\/\//", $host) > 0 || $host = "http://{$host}";
        $this->consulApi = "{$host}/v1/kv/apps";
        $remote = $this->scanConsul("{$key}/config");
        $remote === false || $remote = $this->scanConsulParse($remote);
        // 4. 合并结果
        $data = array_replace_recursive($data, $remote);
        // 5. 写入数据
        $this->boot->getArgs()->makeTmpDir();
        $file = $this->boot->getArgs()->getTmpDir().'/config.php';
        $jsonFile = $this->boot->getArgs()->getTmpDir().'/config.json';
        file_put_contents($file, "<?php\nreturn unserialize('".serialize($data)."');");
        file_put_contents($jsonFile, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        // 6. 完成同步
        $this->printLine("同步完在: 配置已导出到【{$file}】文件");
    }

    /**
     * 打印支持选项
     */
    public function runHelp() : void
    {
        $script = $this->boot->getArgs()->getScript();
        substr($script, 0, 2) === './' || $script = "php {$script}";
        $this->printLine("启动脚本: %s %s [{yellow=选项}]", $script, $this->boot->getArgs()->getCommand());
        foreach (self::$options as $option) {
            $pre = isset($option['short']) ? "-{$option['short']}," : '   ';
            $opt = "{$pre}--{$option['name']}";
            if (isset($option['value'])) {
                $opt .= '=['.$option['value'].']';
            }
            $txt = isset($option['desc']) ? $option['desc'] : '';
            $this->printLine("          {yellow=%s} %s", sprintf("%-28s", $opt), $txt);
        }
    }

    /**
     * 扫描配置文件目录
     */
    private function scanConfig(string $x)
    {
        if (defined('PHAR_WORKING')) {
            $path = __DIR__.'/../../../../../../../config';
        } else {
            $path = $this->boot->getArgs()->getBasePath().'/config';
        }
        if (!is_dir($path)) {
            $this->printLine("环境错误: 未到找【{$path}】目录");
            return false;
        }
        $this->printLine("加载模板: 扫描【{$path}】目录下的配置文件");
        $d = dir($path);
        $r = [];
        while (false !== ($e = $d->read())) {
            if (preg_match("/^(\S+)\.php/i", $e, $m) === 0) {
                continue;
            }
            $this->printLine("          发现【{$e}】文件");
            $data = include($path.'/'.$e);
            $data = is_array($data) ? $data : [];
            if (isset($data['default']) || isset($data['development']) || isset($data['testing']) || isset($data['release']) || isset($data['production'])) {
                $buff = isset($data['default']) && is_array($data['default']) ? $data['default'] : [];
                $temp = isset($data[$x]) && is_array($data[$x]) ? $data[$x] : [];
                $r[$m[1]] = array_replace_recursive($buff, $temp);
            } else {
                $r[$m[1]] = $data;
            }
        }
        $d->close();
        return $r;
    }

    /**
     * @param string $key
     * @return array|false
     */
    private function scanConsul($key)
    {
        $this->printLine("读取配置: 读取【Consul/KV】中读取配置");
        $code = $this->scanConsulApi($key);
        if ($code === false) {
            return false;
        }
        $data = json_decode($code, true);
        if (!is_array($data)) {
            return false;
        }
        return $data;
    }

    /**
     * 请求KV
     * @param string $key
     * @return string|false
     */
    private function scanConsulApi($key)
    {
        $url = $this->consulApi.'/'.$key;
        $this->printLine("          发现【{$url}】配置");
        try {
            $client = new HttpClient();
            $content = $client->get($url)->getBody()->getContents();
            $json = \GuzzleHttp\json_decode($content, true);
            if (count($json) > 0) {
                return base64_decode($json[0]['Value']);
            }
            throw new \Exception("empty");
        } catch(\Throwable $e) {
            $this->printLine("读取失败: 连接Consul失败 - %s", $e->getMessage());
        }
        return false;
    }

    /**
     * @param array $data
     * @return array
     */
    private function scanConsulParse(array $data)
    {
        foreach ($data as & $value) {
            if (is_array($value)) {
                $value = $this->scanConsulParse($value);
                continue;
            }
            if (!is_string($value)) {
                continue;
            }
            if (preg_match("/^kv:[\/]+(\S+)/i", $value, $m) > 0) {
                $buffer = $this->scanConsulApi($m[1]);
                if ($buffer === false) {
                    continue;
                }
                try {
                    $bufferArray = \GuzzleHttp\json_decode($buffer, true);
                    $value = $this->scanConsulParse($bufferArray);
                } catch(\Throwable $e) {
                    $value = $buffer;
                }
            }
        }
        return $data;
    }
}
