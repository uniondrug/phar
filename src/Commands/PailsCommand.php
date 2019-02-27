<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-02-26
 */
namespace Uniondrug\Phar\Commands;

use Pails\Console\Command;
use Uniondrug\Phar\Builder\Builder;

/**
 * 生成PHAR
 * 本类在老版本Phalcon框架中继承
 * <code>
 * php pails phar --name  --tag
 * </code>
 * @package App\Commands
 */
abstract class PailsCommand extends Command
{
    protected $pharName = 'sketch';
    /**
     * 命令名称
     * @var string
     */
    protected $signature = 'phar
        {--name= : 包名称}
        {--tag= : 包标签/版本号名称}
        {--ignore : 包标签/版本号名称}';
    /**
     * 命令描述
     * @var string
     */
    protected $description = '构建PHAR包/PHP Archive';

    /**
     * 执行构建
     */
    public function handle()
    {
        $this->canBuilder();
        $this->getBuilder()->run();
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function canBuilder()
    {
        if (defined("PHAR_WORKING")) {
            throw new \Exception("can not work in phar.");
        }
        return true;
    }

    /**
     * @return Builder
     * @throws \Throwable
     */
    public function getBuilder()
    {
        // 1. phar name
        $name = $this->option('name');
        $name || $name = $this->pharName;
        // 2. phar tag
        $tag = $this->option('tag');
        $tag || $tag = date('ymd');
        // 4. ignore/override
        $override = $this->hasOption('ignore');
        // 5. env
        $env = $this->option('env');
        $env || $env = 'development';
        // 6. runtime
        $builder = new Builder();
        $builder->setName($name);
        $builder->setTag($tag);
        $builder->setOverride($override === true);
        $builder->setEnvironment($env);
        return $builder;
    }
}
