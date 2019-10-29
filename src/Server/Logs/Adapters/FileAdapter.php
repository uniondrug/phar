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
    private static $logDate;
    private static $logFile;

    /**
     * 计算日志文件
     * @return string
     */
    public function getLogFile()
    {
        $date = (string) date('Y-m-d');
        if ($date !== self::$logDate) {
            self::$logDate = $date;
            $path = $this->logger->getConfig()->getArgs()->logPath().'/'.date('Y-m');
            if (!is_dir($path)) {
                mkdir($path, 0777);
            }
            self::$logFile = $path.'/'.$date.'.log';
        }
        return self::$logFile;
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
        try {
            $file = self::getLogFile();
            $mode = file_exists($file) ? 'a+' : 'wb+';
            if (false !== ($handle = @fopen($file, $mode))) {
                fwrite($handle, $text);
                fclose($handle);
                return true;
            }
        } catch(\Throwable $e) {
        }
        return false;
    }
}
