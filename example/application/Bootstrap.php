<?php

use \Yaf\Dispatcher;

use \laocc\yaf\Router;
use \laocc\yaf\View;
use \laocc\yaf\Cache;

class Bootstrap extends \Yaf\Bootstrap_Abstract
{

    /**
     * 注册路由插件
     * @param Dispatcher $dispatcher
     */
    public function _initRoutes(Dispatcher $dispatcher)
    {
        \Yaf\Loader::import(_ROOT . '/../vendor/autoload.php');

        //符合此规则的，静态化
        $static = [
            '/^\/\w+\/.+\.(html)([\?\#].*)?$/i',
            '/^\/tmp.+$/i',
        ];

        $setting = [
            'keys' => [],
            'driver' => 'yac',
            'ttl' => 120,
            'html_regex' => $static
        ];
        $cache = new Cache($dispatcher, $setting);

        $setting = [
            'file' => _ROOT . 'config/routes.ini',
            'root' => 'product',
        ];
        $dispatcher->registerPlugin(new Router($dispatcher, $cache, $setting));

        $setting = [
            'layout' => true,
            'smarty' => 'cache',//缓存的目录
        ];
        $dispatcher->registerPlugin(new View($dispatcher, $cache, $setting));


    }

}