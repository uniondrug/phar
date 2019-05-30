<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-16
 */
namespace Uniondrug\Phar\Agents;

use GuzzleHttp\Client as GuzzleHttp;
use GuzzleHttp\Exception\ConnectException;
use Uniondrug\Phar\Exceptions\ServiceException;

/**
 * KV(Consul)
 * @package Uniondrug\Phar\Agents
 */
class KvAgent extends Abstracts\Agent
{
    protected static $title = '同步KV值';
    protected static $description = '从Consul拉取配置与项目级配置合并生成最终配置';
    private $http;
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
            'value' => 'HOST',
            'desc' => 'Consul服务地址'
        ],
        [
            'name' => 'consul-key',
            'desc' => '服务名称, 默认: 空/自动检测'
        ],
        [
            'name' => 'upload',
            'desc' => '上传配置, 当项目为空项目时, 使用此参数上传初始的Consul配置'
        ]
    ];

    /**
     * @inheritdoc
     */
    public function run()
    {
        if ($this->getRunner()->getArgs()->hasOption('upload')) {
            $this->uploadKv();
            return;
        }
        $this->printLine("同步配置: 从ConsulKV同步项目配置");
        // 1. generate host
        $host = (string) $this->getRunner()->getArgs()->getOption('consul');
        if ($host === "") {
            $this->printLine("同步出错: {red=未指定Consul地址}");
            return;
        }
        if (preg_match("/^https?:\/\//", $host) === 0) {
            $host = "http://{$host}";
        }
        // 2. generate key
        $key = (string) $this->getRunner()->getArgs()->getOption('consul-key');
        $key === '' && $key = $this->getRunner()->getConfig()->appName;
        // 3. request origin
        $this->printLine("同步地址: {yellow=%s}", $host);
        $text = $this->requestConsulKv($host, "apps/{$key}/config");
        $conf = $this->getRunner()->getConfig()->getScanned();
        $redirect = false;
        if ($text !== false) {
            $data = json_decode($text, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_array($data)) {
                $data = $this->recursiveConsulKv($host, $data);
                $conf = array_replace_recursive($conf, $data);
                $redirect = true;
            } else {
                $this->printLine("          第1级{%s}配置必须为有效的JSON", $key);
            }
        }
        // 4. 重定向配置
        if ($redirect) {
            $this->resort($conf);
            // 4.0 创建目录
            $this->getRunner()->getArgs()->buildPath();
            // 4.1 PHP配置
            $phps = "<?php\n";
            $phps .= "/**\n";
            $phps .= " * Consul Configurations\n";
            $phps .= " * @date ".date('Y-m-d')."\n";
            $phps .= " * @time ".date('H:i:s')."\n";
            $phps .= " * @link {$host}\n";
            $phps .= " */\n";
            $phps .= "return unserialize('".serialize($conf)."');\n";
            // 4.2 JSON模板
            $json = json_encode($conf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            // 4.3 写入配置
            file_put_contents($this->getRunner()->getArgs()->tmpPath().'/config.json', $json);
            file_put_contents($this->getRunner()->getArgs()->tmpPath().'/config.php', $phps);
        }
    }

    /**
     * @inheritdoc
     */
    public function runHelp()
    {
        $script = $this->getRunner()->getArgs()->getScript();
        substr($script, 0, 2) === './' || $script = "php {$script}";
        $this->printLine("启动脚本: %s %s [{yellow=选项}]", $script, $this->getRunner()->getArgs()->getCommand());
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
     * 上传初始配置
     */
    public function uploadKv()
    {
        $this->printLine("上传配置: 上传初始配置到Consul-KV");
        // 1. create instance
        if ($this->http === null) {
            $this->http = new GuzzleHttp();
        }
        // 2. Key地址
        $key = (string) $this->getRunner()->getArgs()->getOption('consul-key');
        $key === '' && $key = $this->getRunner()->getConfig()->appName;
        try {
            // 3. 扫描本地配置
            $data = $this->getRunner()->getConfig()->getScanned();
            foreach ($data as $category => & $section) {
                if (in_array($category, [
                    'app',
                    'logger',
                    'middleware',
                    'routes'
                ])) {
                    unset($data[$category]);
                    continue;
                }
                if (is_array($section) && isset($section['key'])) {
                    unset($section['key']);
                }
                if ($category === 'server') {
                    if (!isset($section['value']['logger'])) {
                        $section['value']['logger'] = "kv://globals/log/default";
                    }
                    if (!isset($section['value']['settings'])) {
                        $section['value']['settings'] = "kv://globals/swoole/worker";
                    }
                }
            }
            if (!isset($data['sdk'])) {
                $data['sdk'] = [
                    'value' => 'kv://globals/sdk/v2'
                ];
            }
            $this->resort($data);
            // 4. 域名指替换
            $body = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $suffix = $this->getRunner()->getArgs()->getDomainSuffix();
            $suffixes = $this->getRunner()->getArgs()->getDomainSuffixes();
            foreach ($suffixes as $domain) {
                $body = str_replace($domain, $suffix, $body);
            }
            // 5. 提交配置
            $this->http->put("http://{$this->getRunner()->getArgs()->getOption('consul')}/v1/kv/apps/{$key}/config?cas=0", [
                'body' => $body
            ]);
            $this->printLine("          %s", "apps/{$key}/config");
        } catch(\Throwable $e) {
            $this->printLine("          %s", $e->getMessage());
        }
    }

    /**
     * 配置参数递归
     * @param string $host
     * @param array  $data
     * @return array
     */
    private function recursiveConsulKv($host, array $data)
    {
        foreach ($data as & $value) {
            if (is_array($value)) {
                $value = $this->recursiveConsulKv($host, $value);
            } else if (is_string($value) && preg_match("/^kv:[\/]+(\S+)$/i", $value, $m) > 0) {
                $req = $this->requestConsulKv($host, $m[1]);
                if ($req === false) {
                    continue;
                }
                $json = json_decode($req, true);
                if (is_array($json)) {
                    $value = $this->recursiveConsulKv($host, $json);
                } else if (is_string($req)) {
                    switch (strtolower($req)) {
                        case 'true' :
                            $value = true;
                            break;
                        case 'false' :
                            $value = false;
                            break;
                        default :
                            $value = $req;
                            break;
                    }
                }
            }
        }
        return $data;
    }

    /**
     * @param string $host
     * @param string $key
     * @return false|string
     */
    private function requestConsulKv($host, $key)
    {
        // 1. create instance
        if ($this->http === null) {
            $this->http = new GuzzleHttp();
        }
        // 2. build URL
        $url = "{$host}/v1/kv/{$key}";
        // 3. request base64
        try {
            $text = (string) $this->http->get($url, [
                'headers' => [],
                'timeout' => 10
            ])->getBody()->getContents();
            if ($text === '') {
                throw new ServiceException("KV空值");
            }
        } catch(ConnectException $e) {
            $this->printLine("          [{red=%s}] 连接{{yellow=%s}}失败", $key, $host);
            return false;
        } catch(\Throwable $e) {
            $this->printLine("          [{red=%s}] 返回{{red=%d}}错误", $key, $e->getCode());
            return false;
        }
        // 4. json decode
        $json = json_decode($text, true);
        if (!is_array($json) || count($json) !== 1) {
            $this->printLine("          [{red=%s}] 无效的JSON返回结果", $key);
            return false;
        }
        // 5. base64 decode
        $this->printLine("          [{green=%s}] 发现配置", $key);
        $json[0]['Value'] = isset($json[0]['Value']) && $json[0]['Value'] !== '' ? $json[0]['Value'] : '';
        if ($json[0]['Value'] === '') {
            return $json[0]['Value'];
        }
        return trim(base64_decode($json[0]['Value']));
    }

    /**
     * @param array $data
     */
    private function resort(& $data)
    {
        if (!is_array($data)) {
            return;
        }
        ksort($data);
        reset($data);
        foreach ($data as & $temp) {
            if (is_array($temp)) {
                $this->resort($temp);
            }
        }
    }
}
