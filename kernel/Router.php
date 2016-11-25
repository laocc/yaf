<?php
namespace laocc\yaf;

use laocc\yaf\extend\Parser;
use Yaf\Config\Ini;
use Yaf\Dispatcher;
use Yaf\Plugin_Abstract;
use Yaf\Registry;
use Yaf\Request_Abstract;
use Yaf\Response_Abstract;

final class Router extends Plugin_Abstract
{
    private $dispatcher;
    private $_ini_root = [];
    private $_routes = [];

    public function __construct(Dispatcher $dispatcher, string $file = null, string $root = 'product')
    {
        $this->dispatcher = $dispatcher;
        if (is_null($file)) $file = _ROOT . 'config/routes.ini';
        if (!is_readable($file)) exit("Ini文件不存在或不可读：{$file}");
        $this->_ini_root = [$file, $root];
    }

    /**
     * 1.在路由之前触发
     */
    public function routerStartup(Request_Abstract $request, Response_Abstract $response)
    {
        $routeConfig = new Ini($this->_ini_root[0]);
        $routeConfig = $routeConfig->{$this->_ini_root[1]};
        if (!$routeConfig) return;

        $this->_routes = $routeConfig = array_reverse($routeConfig->toArray());
        unset($routeConfig['_default']);
        if (!isset($this->_routes['_default'])) {
            $this->_routes['_default'] = [];
        }

        //把指定用自定义解析器的部分给剐下来
        $private = [];
        foreach ($routeConfig as $key => $route) {
            if (isset($route['custom']) and $route['custom']) {
                $private[$key] = $route;
                unset($routeConfig[$key]);
            }
        }
        $router = $this->dispatcher->getInstance()->getRouter();
        $router->addConfig($routeConfig);

        //注册自定义的解析器
        if (!empty($private)) {
            $router->addRoute('_custom_route', new Parser($this->dispatcher, $private));
        }
    }

    //2,路由结束之后触发
    public function routerShutdown(Request_Abstract $request, Response_Abstract $response)
    {
        $app = $this->dispatcher->getApplication();

        //设置默认模块，YAF.ini中的默认模块并不是真的指向相应模块，只是将默认模块的名称改了
        $defaultModule = ucfirst(strtolower($app->getConfig()->application->dispatcher->defaultModule));
        if ($request->getModuleName() === 'Index' and !!$defaultModule and $defaultModule !== 'Index') {
            $request->setModuleName($defaultModule);
        }

        //修正最后生效路由名称，也就是如果没有采用自定义路由，则将系统路由器的结果写入_effect_route
        //获取生效的路由名称，并读取该路由的相关设置
        $_ef_rt = $request->getParam('_effect_route');
        if (!$_ef_rt) $request->setParam('_effect_route', $_ef_rt = $this->dispatcher->getRouter()->getCurrentRoute());
        if (!isset($this->_routes[$_ef_rt])) exit("发生未知错误，应该不会出现这情况的：设置的路由表中没有实际生效路由[{$_ef_rt}]");

        $route = $this->_routes[$_ef_rt];

        //默认请求类型，指get,post这些
        if (!isset($route['method'])) $route['method'] = 'ALL';

        //检查请求类型
        if (!$this->method_check($route['method'], $request->getMethod(), $request->isXmlHttpRequest(), $request->isCli())) {
            exit("请求类型不符合要求");
        }

        //指定工作目录
        if (isset($route['directory'])) {
            $app->setAppDirectory($route['directory']);
        }

        $set = [];
        $set['view'] = true;
        if (isset($route['layout'])) $set['layout'] = $route['layout'];
        if (isset($route['smarty'])) $set['smarty'] = $route['smarty'];
        if (isset($route['static'])) $set['static'] = $route['static'];
        if (isset($route['concat'])) $set['concat'] = $route['concat'];

        //缓存设置，结果可能为：true/false，或array(参与cache的$_GET参数)
        if (isset($route['cache'])) $set['cache'] = is_array($route['cache']) ? $route['cache'] : !!$route['cache'];


        //视图相关设置，结果有可能是：true/false，或array(view的一系列定义)
        if (isset($route['view'])) {
            if (!$route['view']) {
                $set['view'] = false;
            } else {
                $set['view'] = $route['view'];
            }
        }

        Registry::set('_route_setting', $set);

    }


    /**
     * 访问请求类型判断
     * @param string $mode 路由中指定的类型
     * @param string $method 当前请求的实际类型，get,post,put,head,delete之一
     * @return bool
     *
     * $mode格式 ：
     * ALL,HTTP,AJAX,CLI，这四项只能选一个，且必须是第一项
     * ALL  =   仅指get,post,cli这三种模式
     * HTTP/AJAX两项后可以跟具体的method类型，如：HTTP,GET,POST
     * CLI  =   只能单独出现
     */
    private function method_check($mode, $method, $isAjax, $isCli)
    {
        if (!$mode) return true;
        list($mode, $method) = [strtoupper($mode), strtoupper($method)];
        if ($mode === $method) return true;//正好相同
        $modes = explode(',', $mode);

        if ($modes[0] === 'ALL') {
            if (count($modes) === 1) $modes = ['GET', 'POST'];
            $check = in_array($method, $modes) or $isCli;

        } elseif ($modes[0] === 'HTTP') {//限HTTP时，不是_AJAX
            if (count($modes) === 1) $modes = ['GET', 'POST'];
            $check = !$isAjax and in_array($method, $modes);

        } elseif ($modes[0] === 'AJAX') {//限AJAX状态
            if (count($modes) === 1) $modes = ['GET', 'POST'];
            $check = $isAjax and in_array($method, $modes);

        } elseif ($modes[0] === 'CLI') {//限CLI
            $check = $isCli;

        } else {
            $check = in_array($method, $modes);
        }
        return $check;
    }

}