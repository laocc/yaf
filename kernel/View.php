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
        $vSetting = $setting + [
                'path' => null,
                'file' => null,
                'ext' => null,
                'display' => null,
                'view' => true,//这个值有可能是true/false,或array
            ];


        foreach ($this->setting as $k => $v) {
            if (!$v) {
                $vSetting[$k] = false;
            } elseif (!isset($vSetting[$k])) {
                $vSetting[$k] = true;
            }
            if (!isset($vSetting[$k])) $vSetting[$k] = false;
            if ($vSetting[$k] == 1) $vSetting[$k] = true;
        }

        if ($vSetting['layout'] === true) {
            if (is_string($this->setting['layout']) and $this->setting['layout'] != 1) {
                $vSetting['layout'] = $this->setting['layout'];
            }
        }

        if ($vSetting['smarty'] === true) {
            if (is_string($this->setting['smarty']) and $this->setting['smarty'] != 1) {
                $vSetting['smarty'] = $this->setting['smarty'];
            }
        }

        //不用视图
        if (empty($vSetting['view'])) {
            $this->dispatcher->autoRender(false);
            $this->dispatcher->disableView();
            return;
        }

        //强制响应类型
        $vSetting['display'] = in_array(strtolower($vSetting['display']), ['json', 'text', 'xml']) ? strtolower($vSetting['display']) : null;
        if ($vSetting['display'] === 'html') $vSetting['display'] = null;

        //视图目录
        if (is_null($vSetting['path'])) {
            $vSetting['path'] = $this->dispatcher->getApplication()->getAppDirectory();
            if ($request->getModuleName() !== 'Index') {
                $vSetting['path'] .= '/modules/' . $request->getModuleName();
            }
            $vSetting['path'] = rtrim($vSetting['path'], '/') . '/views/';
        }

        //读取视图后缀
        if (is_null($vSetting['ext'])) {
            $conf = $this->dispatcher->getApplication()->getConfig();
            if (isset($conf->application->view) and isset($conf->application->view->ext)) {
                $vSetting['ext'] = $conf->application->view->ext;
            } else {
                $vSetting['ext'] = 'phtml';
            }
        }

        //创建并注册视图引擎
        $this->dispatcher->setView(new Viewer($this->dispatcher, $vSetting, $this->cache, $request->isCli()));

    }


}