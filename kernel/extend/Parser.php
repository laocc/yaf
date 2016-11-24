<?php
namespace laocc\yaf\extend;

use Yaf\Dispatcher;
use Yaf\Request\Simple;
use Yaf\Route_Interface;
use Yaf\Request_Abstract;


class Parser implements Route_Interface
{
    private $_routes;
    private $dispatcher;

    public function __construct(Dispatcher $dispatcher, array $routes)
    {
        $this->_routes = $routes;
        $this->dispatcher = $dispatcher;
    }

    /**
     * (Yaf >= 2.2.9)
     * 路由请求
     *
     * @param Request_Abstract $request
     *
     * @return Boolean
     */
    public function route($request)
    {
        $uri = preg_replace_callback('/(.+)(\/index\.php)$/i', function ($mat) {//请求URI
            return $mat[1];//去掉最后/index.php
        }, $request->getRequestUri());

        //GET,POST,HEAD,PUT,CLI
        $method = strtoupper($request->getMethod());

        foreach (array_reverse($this->_routes) as $key => &$route) {
            switch (isset($route['type']) and $route['type']) {
                case 'regex':   //目前只实现了针对正则的路由规定
                    if (preg_match($route['match'], $uri, $matches)) {

                        //分别获取模块、控制器、动作的实际值
                        list($module, $controller, $action) = $this->fill_route($matches, isset($route['route']) ? $route['route'] : null);
                        if (!$controller and !$action) return false;

                        //分别获取各个指定参数
                        if (isset($route['map'])) {
                            foreach ($route['map'] as $mi => $mk) {
                                $matches[$mk] = isset($matches[$mi]) ? $matches[$mi] : null;
                            }
                        }

                        //构造一个虚拟的请求Request，以替换系统本来的Request，也就是路由结果
                        $Abstract = new Simple($method, $module, $controller, $action, $matches);
                        $Abstract->setParam('_effect_route', $key);//标记生效的路由到Request中，供后面程序判断用

                        $this->dispatcher->setRequest($Abstract);
                        return true;
                    }
                    break;

                default:
                    exit("当前插件只实现了针对[regex]的解析方法。");
                    break;
            }
        }
        return false;
    }

    /**
     * 分别获取模块、控制器、动作的实际值
     * @param array $matches 路由匹配的正则结果集
     * @param $route
     * @return array
     * @throws \Exception
     */
    private function fill_route($matches, $route)
    {
        $module = $controller = $action = 'index';
        //正则结果中没有指定结果集，则都以index返回
        if (empty($matches) or !isset($matches[1])) return [$module, $controller, $action];

        //没指定'module', 'controller', 'action'的值对象，按顺序填入MCA
        if (!is_array($route)) {
            if ($this->isModuleName($matches[1])) {//第一个是模块
                $module = $matches[1];
                if (isset($matches[2])) {
                    $controller = $matches[2];
                    if (isset($matches[3])) $action = $matches[3];
                }
            } else {//否则第一模块指的就是控制器
                $controller = $matches[1];
                if (isset($matches[2])) $action = $matches[2];
            }
        } else {
            //分别获取MCA结果
            foreach (['module', 'controller', 'action'] as $key) {
                ${$key} = isset($route[$key]) ? $route[$key] : ${$key};
                if (is_numeric(${$key})) {
                    if (!isset($matches[${$key}])) exit("自定义路由规则中需要第{${$key}}个正则结果，实际无此数据。");
                    ${$key} = $matches[${$key}];
                }
            }
        }
        return [$module, $controller, $action];
    }

    /**
     * 是否定义过的模块
     * @param $key
     * @return bool
     *
     * Yaf_Router::isModuleName() 在3.0.4中没有实现
     * 如果想用，得先改一下YAF的安装源码：
     * todo vi yaf_router.c
     * 在第320行所在的yaf_router_methods[]里加一行：
     *    PHP_ME(yaf_router, isModuleName,        NULL, ZEND_ACC_PUBLIC)
     * 也就是复制这儿任意一行，将其中函数名改为isModuleName就可以了
     * 然后重新编译一次
     *
     * 这儿就不用那个函数了，反正都得要get好几层，这里直接从yaf的配置中读取
     *
     */
    private function isModuleName($key)
    {
        static $modules = null;
        if ($modules === null) {
            $modules = $this->dispatcher->getApplication()->getConfig()->application->modules;
            if (empty($modules)) {
                $modules = ['index'];
            } else {
                $modules = explode(',', strtolower($modules));
            }
        }
        return in_array($key, $modules);
    }

    /**
     * (Yaf >= 2.3.2)
     * 组合uri，路由解析的逆操作
     *
     * @param array $info
     * @param mixed $query
     * @return String
     */
    public function assemble(array $info, array $query = NULL)
    {
        return '';
    }

}