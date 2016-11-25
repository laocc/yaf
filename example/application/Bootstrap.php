<?php

use \Yaf\Dispatcher;

use \laocc\yaf\Router;
use \laocc\yaf\View;

class Bootstrap extends \Yaf\Bootstrap_Abstract
{

    /**
     * 注册路由插件
     * @param Dispatcher $dispatcher
     */
    public function _initRoutes(Dispatcher $dispatcher)
    {
        \Yaf\Loader::import(_ROOT . '/../vendor/autoload.php');
        $dispatcher->registerPlugin(new Router($dispatcher));

        $setting = [
            'layout' => true,
            'smarty' => 'cache',
            'cache' => true,
        ];

        $dispatcher->registerPlugin(new View($dispatcher, $setting));
    }

}