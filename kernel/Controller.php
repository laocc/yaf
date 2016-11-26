<?php
namespace laocc\yaf;

use \Yaf\Controller_Abstract;
use \Yaf\View\Simple;
use \Yaf\View_Interface;

use \laocc\yaf\extend\Xml;
use \laocc\yaf\extend\Viewer;

/**
 * 控制器与视图通信接口，本类只能被控制器扩展
 *
 * 所有路由参数
 * $params = $this->getRequest()->getParams();
 */
abstract class Controller extends Controller_Abstract
{
    private $_use_view;


    /**
     * 1：is_string($tpl)   =设置视图文件
     * 2：is_null($tpl)     =返回Visual对象，也就是不加参数
     *
     * @param null $tpl
     * @return Viewer|View_Interface|bool
     */
    final protected function view($tpl = null)
    {
        if (is_bool($tpl)) return $this->_use_view = $tpl;  //开关

        static $obj;
        if (!is_null($obj)) return $obj;
        $obj = $this->getView();
        if (is_null($tpl)) return $obj;

        return $this->view()->file($tpl);                 //设置视图文件
    }

    /**
     * 向框架赋值，或设置是否使用框架
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

    final protected function static(bool $bool = true)
    {
        return $this->view()->static($bool);
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

    /**
     * 向框架视图发送一些变量，实际上这儿暂时发送的是视图插件，而不是框架视图，
     * 如果向框架视图送值，应该用$this->layout()->XXX
     * 但是如果在没启用layout时，还可以把这些值释放到普通视图里
     * 也就是说这儿利用视图插件做中转，解析页面时再决定往哪里释放
     *
     * @param $file
     * @param int $position head/body/defer/footer(默认)
     * @return $this
     */
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

    final protected function title($value)
    {
        $this->view()->meta('_title', $value);
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
    final protected function json(array $value)
    {
        $value = json_encode($value, 256);
        $callback = isset($_GET['callback']) ? $_GET['callback'] : null;
        if (!!$callback) $callback = preg_match('/^\w+$/', $callback) ? $callback : null;
        if (!!$callback) $value = "{$callback}({$value})";
        $this->view()->out_value('json', $value);
    }

    final protected function xml($key, array $value = [])
    {
        if (is_array($key) and empty($value)) list($key, $value) = ['xml', $key];
        $value = (new Xml($value, $key))->render();
        $this->view()->out_value('xml', $value);
    }


    final protected function text(string $value)
    {
        $this->view()->out_value('text', $value);
    }

    final protected function html($value)
    {
        $value = is_array($value) ? json_encode($value, 256) : $value;
        $this->view()->out_value('html', $value);
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