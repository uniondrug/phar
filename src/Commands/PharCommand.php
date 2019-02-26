<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-10-30
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
        {--ignore : 包标签/版本号名称}';
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
        if (defined("PHAR_WORKING_NAME")) {
            $this->getOutput()->writeln("ERROR - can not worker in ".PHAR_WORKING_NAME." file.");
            return;
        }
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
        $builder->run();
    }
}
