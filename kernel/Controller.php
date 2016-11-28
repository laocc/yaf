<?php
namespace laocc\yaf;

use laocc\yaf\extend\Viewer;
use Yaf\Controller_Abstract;
use Yaf\View\Simple;
use Yaf\View_Interface;

/**
 * 控制器与视图通信接口，本类只能被控制器扩展
 *
 * 所有路由参数
 * $params = $this->getRequest()->getParams();
 */
abstract class Controller extends Controller_Abstract
{

    /**
     * 1：is_string($tpl)   =设置视图文件
     * 2：is_null($tpl)     =返回Visual对象，也就是不加参数
     *
     * @param null $tpl
     * @return Viewer|View_Interface|bool
     */
    final protected function view($tpl = null)
    {
        static $obj;
        if (!is_null($obj)) return $obj;
        $obj = $this->getView();
        if (is_null($tpl)) return $obj;
        if (is_bool($tpl)) return $this->view()->enable($tpl);//开关
        return $this->view()->file($tpl);                 //设置视图文件
    }

    /**
     * $set=null 返回layout对象，也就是不带参数
     * $set=string 设置layout框架文件
     * $set=bool 设置是否使用layout
     *
     * @param $use
     * @param null $value
     * @return Simple
     */
    final protected function layout($set = null)
    {
        return $this->view()->layout($set);
    }

    /**
     *
     * 在启用了smarty的情况下，可以在控制器中用类似下面的方法直接调用smarty的方法
     * 如果用的是其他标签类，用法一样
     * $this->smarty()->testInstall()
     *
     * 如果指定了$name，则是指开启/关闭标签解析引擎，或指定引擎名称，且返回的是设置的值
     *
     * 手册：http://www.smarty.net/docs/zh_CN/
     *
     * @return bool|\Smarty
     *
     */
    final protected function smarty($use = null)
    {
        return $this->view()->smarty($use);
    }

    /**
     * @param bool $bool
     * @return bool
     */
    final protected function cache(bool $bool)
    {
        return $this->view()->cache($bool);
    }

    final protected function statics(bool $bool = true)
    {
        return $this->view()->statics($bool);
    }


    final protected function assign($key, $value)
    {
        $this->view()->assign($key, $value);
        return $this;
    }

    final protected function set($key, $value)
    {
        $this->view()->assign($key, $value);
        return $this;
    }

    final protected function get($key)
    {
        return $this->view()->get($key);
    }

    final protected function js($file, $position = 'footer')
    {
        $this->view()->js($file, $position);
        return $this;
    }

    final protected function css($file)
    {
        $this->view()->css($file);
        return $this;
    }

    final protected function meta($name, $content = null)
    {
        $this->view()->meta($name, $content);
        return $this;
    }

    final protected function title($value, $full = false)
    {
        if ($full) {
            $this->view()->meta('title', $value);
            $this->view()->meta('_title', null);
        } else {
            $this->view()->meta('_title', $value);
        }
        return $this;
    }

    final protected function keywords($value)
    {
        $this->view()->meta('keywords', $value);
        return $this;
    }

    final protected function description($value)
    {
        $this->view()->meta('description', $value);
        return $this;
    }

    /**
     * ======================================================设置网页展示方式===========================
     */
    final protected function json(array $value, $force = false)
    {
        $this->view()->out_value('json', $value, $force);
    }

    final protected function xml($value, $key = 'xml', $force = false)
    {
        if (!is_array($value) and !is_array($key))
            throw new \Exception('XML内容须要求为数组格式');
        if (is_bool($key)) list($key, $force) = ['xml', $key];

        $this->view()->out_value('xml', [$key, $value], $force);
    }

    final protected function text($value, $force = false)
    {
        $this->view()->out_value('text', $value, $force);
    }

    final protected function html($value = null, $force = false)
    {
        $this->view()->out_value('html', $value, $force);
    }

    final protected function charset($value = 'utf-8')
    {
        $this->view()->charset($value);
    }


    /**
     * 设置视图目录
     * @param $path
     * @return $this
     */
    final protected function setScriptPath($path)
    {
        $this->view()->setScriptPath($path);
        return $this;
    }

    final protected function getScriptPath()
    {
        return $this->view()->getScriptPath();
    }


    /**
     * 显示当前路由的结果
     */
    final protected function display_route()
    {
        $request = $this->getRequest();
        $route = [
            'effect' => $request->getParam('_effect_route'),
            'module' => $request->getModuleName(),
            'control' => $request->getControllerName(),
            'action' => $request->getActionName(),
            'params' => $request->getParams(),
        ];
        pre($route);
    }


}