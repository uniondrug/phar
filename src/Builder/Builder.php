<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-12-29
 */
namespace Uniondrug\Phar\Builder;

use Symfony\Component\Console\Output\OutputInterface;
use Uniondrug\Framework\Container;

/**
 * Builder PHAR
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

    /**
     * Builder constructor.
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
        $this->output->writeln("开始构建: 【{$this->name}/{$this->tag}】项目PHP Archive包【{$this->pharName}】文件");
        $phar = new \Phar($this->pharFile, 0, $this->pharName);
        // 3. signature
        $this->output->writeln("设置签名: 【SHA1】格式");
        $phar->setSignatureAlgorithm(\Phar::SHA1);
        // 4. 扫描文件
        foreach ($this->folders as $folder) {
            $this->runScanner($phar, $folder);
        }
        // 5. 启动脚本
        $this->runBootstrap($phar);
        // 6. 完成构建
        if (file_exists($this->pharFile)) {
            $size = sprintf("%.02f", filesize($this->pharFile) / 1024 / 1024);
            $this->output->writeln("构建完成: 【{$this->countFiles}】个文件共占用【{$size}】MB空间");
        } else {
            $this->output->writeln("构建失败: 导出文件失败");
        }
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
require 'phar://{$this->pharName}/{$path}';
__HALT_COMPILER();
STUB;
        $phar->setStub($stub);
    }

    /**
     * 采集文件内容
     * @param \Phar $phar
     * @param       $path
     */
    private function runCollector(\Phar $phar, $path)
    {
        $n = sprintf("【%3s】", ++$this->countFiles);
        $this->output->writeln("          {$n}: {$path}");
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
