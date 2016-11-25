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
    private $cache;
    private $setting;

    public function __construct(Dispatcher $dispatcher, Cache $cache, $setting = [])
    {
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
        $_view = $setting + [
                'type' => null,
                'value' => null,
                'path' => null,
                'file' => null,
                'ext' => null,

                //下面取自本对象创建时的设置
                'layout' => $this->setting['layout'],
                'smarty' => $this->setting['smarty'],
                'static' => $this->setting['static'],
                'concat' => $this->setting['concat'],
                'view' => true,
            ];

        //不用视图
        if (empty($_view['view'])) {
            $this->dispatcher->autoRender(false);
            $this->dispatcher->disableView();
            return;
        }
        //字串时，指的是响应类型
        if (is_string($_view['view']) and !empty($_view['view'])) {
            $_view['type'] = in_array(strtolower($_view['view']), ['html', 'json', 'text', 'xml', 'none']) ? $_view['view'] : 'html';
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
        $this->dispatcher->setView(new Viewer($this->dispatcher, $_view, $this->cache, $request->isCli()));

    }


}