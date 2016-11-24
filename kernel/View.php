<?php
namespace laocc\yaf;

use laocc\yaf\extend\Viewer;
use Yaf\Dispatcher;
use Yaf\Plugin_Abstract;
use Yaf\Registry;
use Yaf\Request_Abstract;
use Yaf\Response_Abstract;

final class View extends Plugin_Abstract
{
    private $dispatcher;

    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * 3.分发循环开始之前被触发
     * 注册一个视图引擎，这个视图引擎在控制中需要读取到
     * 之所以把这些放在这儿，主要避免调用缓存时，不需要注册视图插件
     */
    public function dispatchLoopStartup(Request_Abstract $request, Response_Abstract $response)
    {
        $setting = Registry::get('_route_setting');

        if (empty($setting)) {
            $_view = ['path' => null, 'ext' => null];

        } else {
            //不用视图
            if ($setting['view'] === false) {
                $this->dispatcher->autoRender(false);
                $this->dispatcher->disableView();
                return;
            }
            if (is_string($setting['view'])) {
                $setting['view'] = ['type' => in_array(strtolower($setting['view']), ['html', 'json', 'text', 'xml', 'none']) ? $setting['view'] : 'html'];
            } elseif (!is_array($setting['view'])) return;

            $_view = $setting['view'] + ['path' => null, 'ext' => null,];

            //视图目录
            if (is_null($_view['path'])) {
                $_view['path'] = $this->dispatcher->getApplication()->getAppDirectory();
                if ($request->getModuleName() !== 'Index') {
                    $_view['path'] .= '/modules/' . $request->getModuleName();
                }
                $_view['path'] = rtrim($_view['path'], '/') . '/views/';
            }

            //读取视图后缀
            if (is_null($_view['ext'])) {
                $conf = $this->dispatcher->getApplication()->getConfig();
                if (isset($conf->application->view) and isset($conf->application->view->ext)) {
                    $_view['ext'] = $conf->application->view->ext;
                } else {
                    $_view['ext'] = 'phtml';
                }
            }
        }

        //创建并注册视图引擎
        $this->dispatcher->setView(new Viewer($_view, $this->dispatcher));

    }


}