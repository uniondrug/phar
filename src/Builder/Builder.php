<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-21
 */
namespace Uniondrug\Phar\Builder;

/**
 * 构建PHAR包
 * @package Uniondrug\Phar
 */
class Builder
{
    private $_basePath;
    /**
     * Consul地址
     * 构建镜像时, 从Consul拉取配置信息
     * @var string
     */
    private $_environment = 'development';
    /**
     * 文件扩展名
     * @var array
     */
    private $_exts = [
        'php',
        'xml',
        'yml'
    ];
    /**
     * 扫描文件夹
     * @var array
     */
    private $_folders = [
        'app',
        'config',
        'lib',
        'vendor'
    ];
    /**
     * 白名单目录
     * @var array
     */
    private $_whiteListFolders = [];
    /**
     * 忽略规则
     * @var array
     */
    private $_folderIgnores = [
        "/^\./",
        "/^tests$/i",
        "/^examples$/i",
        "/^samples$/i"
    ];
    private $_name = 'sketch';
    private $_tag = '0.0.0';
    private $_override = false;
    private $_scanFiles = 0;
    private $_scanAllFiles = 0;
    private $_scanTotalFiles = 0;
    private $_scanFolders = 0;
    /**
     * @var \Phar
     */
    private $phar = null;
    private $pharBegin = 0.0;
    private $pharFilename = '';
    private $pharFilepath = '';

    /**
     * 设置基础目录
     * @param string|null $basePath
     */
    public function __construct(string $basePath = null)
    {
        $this->_basePath = $basePath === null ? getcwd() : $basePath;
        $this->_name = 'sketch';
        $this->_tag = date('ymd');
    }

    /**
     * 开始构建
     */
    public function run()
    {
        // 1. init phar basic
        $this->pharBegin = microtime(true);
        $this->pharFilename = sprintf("%s-%s.phar", $this->_name, $this->_tag);
        $this->pharFilepath = $this->_basePath.'/'.$this->pharFilename;
        // 2. is exists or not
        if (file_exists($this->pharFilepath)) {
            if ($this->_override) {
                unlink($this->pharFilepath);
            } else {
                throw new \Exception("包文件[{$this->pharFilename}]已存在, 若重新构建请先删除.");
            }
        }
        // 3. build progress
        $this->println("PHAR: package {%s} building...", $this->pharFilename);
        $this->phar = new \Phar($this->pharFilepath, 0, $this->pharFilename);
        $this->phar->setSignatureAlgorithm(\Phar::SHA1);
        $this->runInfo();
        foreach ($this->_folders as $i => $folder) {
            if (!is_dir($this->_basePath.'/'.$folder)) {
                $this->println("      ignore not exists folder, {%s}", $folder);
                continue;
            }
            $this->_scanFiles = 0;
            $this->_scanFolders = 0;
            $this->_scanTotalFiles = 0;
            $this->runScan($folder);
            $this->_scanAllFiles += $this->_scanFiles;
            $this->println("      add %4d files, found {%4d} files in {%3d} directories under {%s} folder.", $this->_scanFiles, $this->_scanTotalFiles, $this->_scanFolders, $folder);
        }
        $this->runBootstrap();
        $this->runEnded();
    }

    /**
     * 加入文件到PHAR包
     * @param string $path
     */
    private function runAdd(string $path)
    {
        $this->_scanFiles++;
        $this->phar->addFile($path);
    }

    /**
     * 添加入口
     */
    private function runBootstrap()
    {
        $this->println("      set bootstrap for phar.");
        $path = 'vendor/uniondrug/phar/src/server.php';
        $appDebug = strtolower($this->_environment) === 'production' ? 'true' : 'false';
        $stub = <<<STUB
#!/usr/bin/env php
<?php
define("PHAR_WORKING", true);
define("PHAR_WORKING_DIR", getcwd());
define("PHAR_WORKING_TAG", "{$this->_tag}");
define("PHAR_WORKING_NAME", "{$this->pharFilename}");
define("PHAR_WORKING_FILE", __FILE__);
define("APP_DEBUG", {$appDebug});
define("BasePath", __FILE__);
Phar::mapPhar('{$this->pharFilename}');
include('phar://{$this->pharFilename}/{$path}');
__HALT_COMPILER();
STUB;
        $this->phar->setStub($stub);
    }

    /**
     * 完成创建
     */
    private function runEnded()
    {
        if (file_exists($this->pharFilepath)) {
            $this->println("      successed added {%.02f}M of {%d} files, with {%.02f} seconds.", (filesize($this->pharFilepath) / 1024 / 1024), $this->_scanAllFiles, microtime(true) - $this->pharBegin);
            return true;
        }
        throw new \Exception("      build failure, file {".$this->pharFilepath."} not found. ");
    }

    /**
     * 记录包信息
     */
    private function runInfo()
    {
        $this->println("      add php archive information.");
        $data = [];
        $data['time'] = date('r');
        $data['environment'] = $this->_environment;
        $data['repository'] = 'null';
        $data['branch'] = 'null';
        $data['commit'] = 'null';
        $data['machine'] = 'null';
        $data['logs'] = [];
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
        // Logs
        $showlogs = shell_exec("cd '".getcwd()."' && git log -5 --pretty=format:\"%ad|%h|%an|%s\" --date=iso");
        foreach (explode("\n", $showlogs) as $showlog) {
            $showlog = trim($showlog);
            if ($showlog !== '') {
                $data['logs'][] = $showlog;
            }
        }
        // 写入Phar
        $this->phar->addFromString('info.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 扫描目录
     * @param string $folder
     * @param string $sub
     */
    private function runScan(string $folder, string $sub = '')
    {
        $this->_scanFolders++;
        $p = $folder.($sub === '' ? '' : '/'.$sub);
        $d = dir($this->_basePath.'/'.$p);
        while (false !== ($e = $d->read())) {
            // 1. ignore system, first char with '.'
            if (preg_match("/^\./", $e)) {
                continue;
            }
            // 2. found file
            $f = $p.'/'.$e;
            if (is_file($this->_basePath.'/'.$f)) {
                $this->_scanTotalFiles++;
                if ($this->isWhiteListFolder($p)) {
                    // 2.1
                    $this->runAdd($f);
                } else {
                    // 2.2
                    $fx = explode('.', $e);
                    $fi = count($fx);
                    if ($fi > 0) {
                        $ext = $fx[$fi - 1];
                        if (in_array($ext, $this->_exts)) {
                            $this->runAdd($f);
                        }
                    }
                }
                continue;
            }
            // 3. sub directory
            if (is_dir($this->_basePath.'/'.$f)) {
                // ignored or not
                // for not whitelist
                if (!$this->isWhiteListFolder($f)) {
                    $folderIgnored = false;
                    foreach ($this->_folderIgnores as $rexp) {
                        if (preg_match($rexp, $e) > 0 || preg_match($rexp, $f) > 0) {
                            $folderIgnored = true;
                            break;
                        }
                    }
                    if ($folderIgnored) {
                        continue;
                    }
                }
                // append subdirectory
                $se = ($sub === '' ? '' : $sub.'/').$e;
                $this->runScan($folder, $se);
                continue;
            }
        }
        $d->close();
    }

    /**
     * 打印内容
     * @param string $text
     * @param array  ...$args
     */
    private function println(string $text, ... $args)
    {
        $args = is_array($args) ? $args : [];
        array_unshift($args, $text);
        $format = @call_user_func_array('sprintf', $args);
        if ($format === false) {
            $format = implode('/', $args);
        }
        file_put_contents('php://stdout', "{$format}\n");
    }

    public function addExts(string $folder)
    {
        in_array($folder, $this->_exts) || $this->_exts[] = $folder;
        return $this;
    }

    public function addFolder(string $folder)
    {
        in_array($folder, $this->_folders) || $this->_folders[] = $folder;
        return $this;
    }

    public function addFolderIgnore(string $regexp)
    {
        in_array($regexp, $this->_folderIgnores) || $this->_folderIgnores[] = $regexp;
        return $this;
    }

    public function addWhiteListFolder(string $folder)
    {
        in_array($folder, $this->_whiteListFolders) || $this->_whiteListFolders[] = $folder;
        return $this;
    }

    public function isWhiteListFolder(string $folder)
    {
        $found = false;
        foreach ($this->_whiteListFolders as $whiteListFolder) {
            if ($whiteListFolder === $folder) {
                $found = true;
                break;
            }
        }
        return $found;
    }

    /**
     * 指定环境
     * @param string $environment
     * @return $this
     */
    public function setEnvironment(string $environment)
    {
        $this->_environment = $environment;
        return $this;
    }

    /**
     * 指定目录
     * @param array $folders
     * @return $this
     */
    public function setFolders(array $folders)
    {
        $this->_folders = $folders;
        return $this;
    }

    /**
     * 忽略目录
     * @param array $ignores
     * @return $this
     */
    public function setFolderIgnores(array $ignores)
    {
        $this->_folderIgnores = $ignores;
        return $this;
    }

    /**
     * 设置包名
     * @param string $name
     * @return string
     */
    public function setName(string $name)
    {
        return $this->_name = $name;
    }

    /**
     * 设置标签
     * @param string $tag
     * @return $this
     */
    public function setTag(string $tag)
    {
        $this->_tag = $tag;
        return $this;
    }

    /**
     * 重复构建
     * 覆盖/即构建PHAR包时, 若目标文件已存在则删除原文件
     * @param bool $override
     * @return $this
     */
    public function setOverride($override = true)
    {
        $this->_override = $override === true;
        return $this;
    }
}
