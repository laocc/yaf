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
    private $setting;

    public function __construct(Dispatcher $dispatcher, $setting = [])
    {
        $this->dispatcher = $dispatcher;
        $this->setting = $setting + ['layout' => false, 'smarty' => false, 'cache' => false, 'static' => false, 'concat' => false];
    }

    /**
     * 3.分发循环开始之前被触发
     * 注册一个视图引擎，这个视图引擎在控制中需要读取到
     * 之所以把这些放在这儿，主要避免调用缓存时，不需要注册视图插件
     */
    public function dispatchLoopStartup(Request_Abstract $request, Response_Abstract $response)
    {
        $setting = Registry::get('_route_setting');
        $_view = [
            'type' => null,
            'value' => null,
            'path' => null,
            'file' => null,
            'ext' => null,
            'layout' => $this->setting['layout'],//这5项，有可能在router中有指定
            'smarty' => $this->setting['smarty'],
            'static' => $this->setting['static'],
            'concat' => $this->setting['concat'],
            'cache' => $this->setting['cache'],
        ];

        if (!!empty($setting)) {
            //不用视图
            if ($setting['view'] === false) {
                $this->dispatcher->autoRender(false);
                $this->dispatcher->disableView();
                return;
            }
            if (is_string($setting['view'])) {
                //字串时，当个响应类型
                $setting['view'] = ['type' => in_array(strtolower($setting['view']), ['html', 'json', 'text', 'xml', 'none']) ? $setting['view'] : 'html'];
            }
            if (!is_array($setting['view'])) {
                return;
            }
            $_view = $setting['view'] + $_view;
        }

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

        //创建并注册视图引擎
        $this->dispatcher->setView(new Viewer($this->dispatcher, $request->isCli(), $_view));

    }


}