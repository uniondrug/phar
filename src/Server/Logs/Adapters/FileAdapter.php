<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-15
 */
namespace Uniondrug\Phar\Server\Logs\Adapters;

use Uniondrug\Phar\Server\Logs\Abstracts\Adapter;

/**
 * 业务Logger写入文件
 * @package Uniondrug\Phar\Server\Logs\Adapters
 */
class FileAdapter extends Adapter
{
    private $handle;

    /**
     * 关闭文件
     */
    public function __destruct()
    {
        if ($this->handle) {
            fclose($this->handle);
        }
    }

    /**
     * @param array $datas
     * @return bool
     */
    public function run(array $datas)
    {
        $text = "";
        $count = 0;
        foreach ($datas as $data) {
            $text .= sprintf("[%s][%s][%s][%s]%s\n", $data['time'], $data['deploy'], $data['app'], $this->logger->makeLevel($data['level']), $data['message']);
            $count++;
        }
        if ($count === 0) {
            return false;
        }
        return $this->writeLogger($text);
    }

    /**
     * @return bool
     */
    public function writeLogger(string $text)
    {
        // 1. 计算路径与名称
        $name = date('Y-m-d').'.log';
        $path = $this->logger->getConfig()->getArgs()->logPath().'/'.date('Y-m');
        $file = $path.'/'.$name;
        // 2. 创建目录
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        // 3. 打开文件
        $mode = file_exists($file) ? 'a+' : 'wb+';
        if (false !== ($this->handle = @fopen($file, $mode))) {
            fwrite($this->handle, $text);
            fclose($this->handle);
            unset($this->handle);
            return true;
        }
        return false;
    }
}
