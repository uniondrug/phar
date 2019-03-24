<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-19
 */
namespace Uniondrug\Phar\Server\Tasks;

/**
 * 定时任务基类
 * 定时任务XCron为异步任务XTask的一种特殊行式, 不同点再于定时任务由
 * PharProcess进程触发, 同时不含任何参数, 而异步任务由业务代码调用
 * runTask()方法投递, 投递时接受数组参数
 * @package Uniondrug\Phar\Server\Tasks
 */
abstract class XCron extends XTask implements ICron
{
}
