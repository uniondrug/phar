<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-17
 */
namespace Uniondrug\Phar\Server\Tasks;

interface ITask
{
    public function afterRun(& $data);
    public function beforeRun();
    public function run();
}
