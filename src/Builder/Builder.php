<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Builder;

use GuzzleHttp\Client as HttpClient;
use Symfony\Component\Console\Output\OutputInterface;
use Uniondrug\Framework\Container;

/**
 * BuildTask PHAR
 * @package Uniondrug\Phar
 */
class Builder
{
    private $basePath;
    /**
     * 是否压缩
     * @var bool
     */
    private $compress = false;
    /**
     * @var Container
     */
    private $container;
    /**
     * 导出包名称
     * @var string
     */
    private $name = "phar";
    /**
     * 控制台输出对象
     * @var OutputInterface
     */
    private $output;
    /**
     * 标签/版本号名称
     * @var string
     */
    private $tag = "latest";
    private $pharName;
    private $pharFile;
    private $folders = [
        'app',
        'config',
        'vendor'
    ];
    private $ignoreFolders = [
        "/^\./",
        "/^tests$/i",
        "/^examples$/i",
        "/^samples$/i",
    ];
    private $files = [
        "/\.php$/i"
    ];
    private $countFiles = 0;
    private $consulApi = null;

    /**
     * BuildTask constructor.
     * @param OutputInterface $output
     */
    public function __construct(Container $container, OutputInterface $output)
    {
        $this->container = $container;
        $this->basePath = realpath($this->container->tmpPath().'/../');
        $this->output = $output;
    }

    /**
     * 开始构建
     */
    public function run()
    {
        // 1. before run
        $this->pharName = sprintf("%s-%s.phar", $this->name, $this->tag);
        $this->pharFile = $this->basePath.'/'.$this->pharName;
        // 2. begin build
        $appName = $this->container->getConfig()->path('app.appName');
        $appVersion = $this->container->getConfig()->path('app.appVersion');
        $this->output->writeln("开始构建: 【{$appName}/{$appVersion}】项目PHP Archive包【{$this->pharName}】文件");
        $phar = new \Phar($this->pharFile, 0, $this->pharName);
        // 3. signature
        $this->output->writeln("设置签名: 【SHA1】格式");
        $phar->setSignatureAlgorithm(\Phar::SHA1);
        // 4. 构建信息
        $this->runInfo($phar);
        // 5. 导入consul配置
        if ($this->consulApi !== null) {
            if (!$this->runConsul($phar)) {
                return;
            }
        }
        // 6. 扫描文件
        $this->output->writeln("开始打包: 【".count($this->folders)."】个目录");
        $lastOffset = 0;
        foreach ($this->folders as $folder) {
            $this->runScanner($phar, $folder);
            $countOffset = $this->countFiles - $lastOffset;
            $lastOffset = $this->countFiles;
            $this->output->writeln("          【{$folder}】发现{$countOffset}个文件");
        }
        // 7. 启动脚本
        $this->runBootstrap($phar);
        // 8. 完成构建
        if (file_exists($this->pharFile)) {
            $size = sprintf("%.02f", filesize($this->pharFile) / 1024 / 1024);
            $this->output->writeln("构建完成: 【{$this->countFiles}】个文件共占用【{$size}】MB空间");
        } else {
            $this->output->writeln("构建失败: 导出文件失败");
        }
    }

    /**
     * 读取项目信息
     */
    private function runInfo(\Phar $phar)
    {
        $data = [];
        $data['time'] = date('r');
        $data['environment'] = $this->container->environment();
        $data['repository'] = 'null';
        $data['branch'] = 'null';
        $data['commit'] = 'null';
        $data['machine'] = 'null';
        // GIT地址
        $buffer = shell_exec("cd '".getcwd()."' && git remote -v");
        if (preg_match("/origin\s+(\S+)/i", $buffer, $m) > 0) {
            $data['repository'] = $m[1];
        }
        // GIT分支
        $buffer = shell_exec("cd '".getcwd()."' && git branch -a | grep '\*'");
        if (preg_match("/\*\s+([^\n]+)/i", $buffer, $m) > 0) {
            $data['branch'] = $m[1];
        }
        // GIT Commit
        $buffer = shell_exec("cd '".getcwd()."' && git log -1");
        if (preg_match("/commit\s+([^\n]+)/i", $buffer, $m) > 0) {
            $data['commit'] = $m[1];
        }
        // Machine
        $buffer = shell_exec('echo ${HOSTNAME}');
        $buffer = trim($buffer);
        if ($buffer !== '') {
            $data['machine'] = $buffer;
        }
        // 写入Phar
        $phar->addFromString('info.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 添加入口脚本
     */
    private function runBootstrap(\Phar $phar)
    {
        $path = 'vendor/uniondrug/phar/src/server.php';
        $this->output->writeln("设置入口: 【phar://{$this->pharName}/{$path}】启动脚本");
        $stub = <<<STUB
#!/usr/bin/env php
<?php
define("PHAR_WORKING_DIR", getcwd());
define("PHAR_WORKING_NAME", "{$this->pharName}");
define("PHAR_WORKING_FILE", __FILE__);
Phar::mapPhar('{$this->pharName}');
include('phar://{$this->pharName}/{$path}');
__HALT_COMPILER();
STUB;
        $phar->setStub($stub);
    }

    /**
     * 覆盖Config
     */
    private function runConsul(\Phar $phar)
    {
        $this->output->writeln("读取配置: 从Consul的KV配置中读取");
        $main = $this->runConsulApi($this->container->getConfig()->path('app.appName'));
        $data = json_decode($main, true);
        if (is_array($data)) {
            $data = $this->runConsulParse($data);
            $data = array_replace_recursive($this->container->getConfig()->toArray(), $data);
            $phar->addFromString('config.php', "<?php\nreturn unserialize('".serialize($data)."');");
            return true;
        }
        $this->output->writeln("读取失败: 连接Consul失败");
        return false;
    }

    /**
     * @return false|array
     */
    private function runConsulApi(string $key)
    {
        $url = $this->consulApi.'/'.$key;
        $this->output->writeln("          {$url}");
        try {
            $client = new HttpClient();
            $content = $client->get($url)->getBody()->getContents();
            $data = \GuzzleHttp\json_decode($content, true);
            if (count($data) > 0) {
                return base64_decode($data[0]['Value']);
            }
            throw new \Exception("empty value");
        } catch(\Throwable $e) {
            $this->output->writeln("          Error={$e->getMessage()}");
        }
        return false;
    }

    /**
     * 从Consul KV值中递归
     * 过滤: kv://name
     * @param array $data
     * @return array
     */
    private function runConsulParse(array $data)
    {
        foreach ($data as & $value) {
            if (is_array($value)) {
                $value = $this->runConsulParse($value);
                continue;
            }
            if (!is_string($value)) {
                continue;
            }
            if (preg_match("/^kv:[\/]+(\S+)/i", $value, $m) > 0) {
                $buffer = $this->runConsulApi($m[1]);
                if ($buffer === false) {
                    $value = $buffer;
                    continue;
                }
                try {
                    $bufferArray = \GuzzleHttp\json_decode($buffer, true);
                    $value = $this->runConsulParse($bufferArray);
                } catch(\Throwable $e) {
                    $value = $buffer;
                }
            }
        }
        return $data;
    }

    /**
     * 采集文件内容
     * @param \Phar $phar
     * @param       $path
     */
    private function runCollector(\Phar $phar, $path)
    {
        $this->countFiles++;
        $phar->addFile($path);
    }

    /**
     * 扫描项目目录
     * @param        $phar
     * @param string $path
     */
    private function runScanner($phar, string $path)
    {
        $p = $this->basePath.'/'.$path;
        $d = dir($p);
        while (false !== ($e = $d->read())) {
            if ($e == '.' || $e == '..') {
                continue;
            }
            $x = $p.'/'.$e;
            if (is_dir($x)) {
                $nest = true;
                foreach ($this->ignoreFolders as $rexp) {
                    if (preg_match($rexp, $e) > 0) {
                        $nest = false;
                        break;
                    }
                }
                $nest && $this->runScanner($phar, $path.'/'.$e);
                continue;
            }
            foreach ($this->files as $rexp) {
                if (preg_match($rexp, $e) > 0) {
                    $this->runCollector($phar, $path.'/'.$e);
                    break;
                }
            }
        }
        $d->close();
    }

    /**
     * 设置包压缩状态
     * @param bool $compress
     * @return $this
     */
    public function setCompress(bool $compress)
    {
        $this->compress = $compress;
        return $this;
    }

    /**
     * 设置Consul地址
     * @param string $host
     * @return $this
     */
    public function setConsul(string $host)
    {
        if ($host) {
            preg_match("/^(http|https):\/\/\S+/i", $host) > 0 || $host = "http://{$host}";
            $this->consulApi = $host.'/v1/kv';
        }
        return $this;
    }

    /**
     * 设置导出包名
     * @param string $name
     * @return $this
     */
    public function setName(string $name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * 设置导出包标签/版本号
     * @param string $tag
     * @return $this
     */
    public function setTag(string $tag)
    {
        $this->tag = $tag;
        return $this;
    }
}
