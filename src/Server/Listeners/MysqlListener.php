<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-24
 */
namespace Uniondrug\Phar\Server\Listeners;

use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\Db\Profiler;
use Phalcon\Events\Event;
use Uniondrug\Phar\Server\Logs\Logger;
use Uniondrug\Phar\Server\XHttp;
use Uniondrug\Phar\Server\XOld;
use Uniondrug\Phar\Server\XSocket;

class MysqlListener
{
    /**
     * @var XHttp|XOld|XSocket
     */
    private $server;
    private $profiler;

    public function __construct($server)
    {
        $this->server = $server;
        $this->profiler = new Profiler();
    }

    /**
     * SQL执行结束
     * @param Event $event
     * @param Mysql $connection
     */
    public function afterQuery($event, $connection)
    {
        $this->profiler->stopProfile();
        /**
         * 1. read profiler
         * @var Profiler\Item $item
         */
        $item = $this->profiler->getLastProfile();
        if (!($item instanceof Profiler\Item)) {
            return;
        }
        // 2. duration
        $alerm = $this->server->getConfig()->mysqlListenerAlerm();
        $duration = (double) $item->getTotalElapsedSeconds();
        if ($duration <= $alerm) {
            return;
        }
        // 3. slow query
        $this->server->getLogger()->log(Logger::LEVEL_ERROR, "[d=%.06f]SQL查询查询预警 - %s", $duration, $this->renderSqlStatment($item));
    }

    /**
     * 执行前记录
     * @param Event $event
     * @param Mysql $connection
     */
    public function beforeQuery($event, $connection)
    {
        $this->profiler->startProfile($connection->getSQLStatement(), $connection->getSqlVariables(), $connection->getSQLBindTypes());
    }

    /**
     * 组织SQL语句
     * @param Profiler\Item $item
     * @return string
     */
    private function renderSqlStatment($item)
    {
        $sql = (string) $item->getSqlStatement();
        $vars = $item->getSqlVariables();
        if (is_array($vars) && count($vars) > 0) {
            foreach ($vars as $holder => $value) {
                $type = strtolower(gettype($value));
                switch ($type) {
                    case 'double' :
                    case 'float' :
                    case 'int' :
                    case 'integer' :
                        $sql = str_replace(":{$holder}", $value, $sql);
                        break;
                    case 'string' :
                        $sql = str_replace(":{$holder}", "'".addslashes(stripcslashes($value))."'", $sql);
                        break;
                    case 'NULL' :
                        $sql = str_replace(":{$holder}", "NULL", $sql);
                        break;
                }
            }
        }
        return $sql;
    }
}
