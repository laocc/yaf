<?php
namespace laocc\yaf;

use laocc\yaf\extend\Viewer;
use Yaf\Config\Ini;
use Yaf\Dispatcher;
use Yaf\Plugin_Abstract;
use Yaf\Registry;
use Yaf\Request_Abstract;
use Yaf\Response_Abstract;

final class View extends Plugin_Abstract
{
    private $dispatcher;
    private $cache;
    private $setting;

    public function __construct(Dispatcher $dispatcher, $setting = [], Cache $cache = null)
    {
        if ($setting instanceof Ini) $setting = ($setting->toArray());
        $this->dispatcher = $dispatcher;
        $this->cache = $cache;
        $this->setting = $setting + ['layout' => false, 'smarty' => false, 'static' => false, 'concat' => false];
    }

    /**
     * 3.分发循环开始之前被触发
     * 注册一个视图引擎，这个视图引擎在控制中需要读取到
     * 之所以把这些放在这儿，主要避免调用缓存时，不需要注册视图插件
     */
    public function dispatchLoopStartup(Request_Abstract $request, Response_Abstract $response)
    {


        $setting = Registry::get('_route_setting');//路由值
        if (empty($setting)) $setting = [];
        $viewerConfig = $setting + [
                'type' => null,
                'value' => null,
                'path' => null,
                'file' => null,
                'ext' => null,
                'view' => true,
            ];

        foreach ($this->setting as $k => $v) {
            if (!$v) {
                $viewerConfig[$k] = false;
            } elseif (!isset($viewerConfig[$k])) {
                $viewerConfig[$k] = true;
            }
            if (!isset($viewerConfig[$k])) $viewerConfig[$k] = false;
            if ($viewerConfig[$k] == 1) $viewerConfig[$k] = true;
        }

        if ($viewerConfig['layout'] === true) {
            if (is_string($this->setting['layout']) and $this->setting['layout'] != 1) {
                $viewerConfig['layout'] = $this->setting['layout'];
            }
        }

        if ($viewerConfig['smarty'] === true) {
            if (is_string($this->setting['smarty']) and $this->setting['smarty'] != 1) {
                $viewerConfig['smarty'] = $this->setting['smarty'];
            }
        }

        //不用视图
        if (empty($viewerConfig['view'])) {
            $this->dispatcher->autoRender(false);
            $this->dispatcher->disableView();
            return;
        }

        //字串时，指的是响应类型
        if (is_string($viewerConfig['view']) and !empty($viewerConfig['view'])) {
            $viewerConfig['type'] = in_array(strtolower($viewerConfig['view']), ['html', 'json', 'text', 'xml', 'none']) ? $viewerConfig['view'] : 'html';
        }

        //视图目录
        if (is_null($viewerConfig['path'])) {
            $viewerConfig['path'] = $this->dispatcher->getApplication()->getAppDirectory();
            if ($request->getModuleName() !== 'Index') {
                $viewerConfig['path'] .= '/modules/' . $request->getModuleName();
            }
            $viewerConfig['path'] = rtrim($viewerConfig['path'], '/') . '/views/';
        }

        //读取视图后缀
        if (is_null($viewerConfig['ext'])) {
            $conf = $this->dispatcher->getApplication()->getConfig();
            if (isset($conf->application->view) and isset($conf->application->view->ext)) {
                $viewerConfig['ext'] = $conf->application->view->ext;
            } else {
                $viewerConfig['ext'] = 'phtml';
            }
        }

        //创建并注册视图引擎
        $this->dispatcher->setView(new Viewer($this->dispatcher, $viewerConfig, $this->cache, $request->isCli()));

    }


}