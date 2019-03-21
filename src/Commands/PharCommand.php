<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2019-03-21
 */
namespace Uniondrug\Phar\Commands;

use Phalcon\Di;
use Uniondrug\Console\Command;
use Uniondrug\Framework\Container;
use Uniondrug\Phar\Builder\Builder;

/**
 * 生成项目级文档
 * <code>
 * php console postman
 * </code>
 * @package App\Commands
 */
abstract class PharCommand extends Command
{
    /**
     * 命令名称
     * @var string
     */
    protected $signature = 'phar
        {--name= : 包名称}
        {--tag= : 包标签/版本号名称}
        {--ignore : 忽略已构建的Phar包}';
    /**
     * 命令描述
     * @var string
     */
    protected $description = '构建PHAR包/PHP Archive';

    /**
     * @inheritdoc
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
        if (defined("PHAR_WORKING_FILE")) {
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
        /**
         * @var Container $container
         */
        $container = Di::getDefault();
        // 1. name
        $name = $this->input->getOption('name');
        $name || $name = $container->getConfig()->path('app.appName');
        // 2. tag
        $tag = $this->input->getOption('tag');
        $tag || $tag = $container->getConfig()->path('app.appVersion');
        // 3. ignore/override
        $override = $this->input->hasOption('ignore');
        // 4. environment
        $env = $this->option('env');
        $env || $env = 'development';
        // n. builder
        $builder = new Builder();
        $builder->setName($name);
        $builder->setTag($tag);
        $builder->setOverride($override);
        $builder->setEnvironment($env);
        return $builder;
    }
}

