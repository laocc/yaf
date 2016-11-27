<?php
namespace laocc\yaf\extend;

use laocc\plugs\Xml;
use laocc\yaf\Cache;
use Yaf\Config\Ini;
use Yaf\Dispatcher;
use Yaf\View_Interface;

class Viewer implements View_Interface
{
    private $dispatcher;
    private $_cache;
    private $_setting;
    private $_isCli;
    private $_isLayout = false;

    private $_var = [];//子视图变量

    private $_res = [
        '_css' => [],
        '_js_head' => [],
        '_js_body' => [],
        '_js_footer' => [],
        '_js_defer' => [],
    ];
    private $_meta = [];

    private $_mime = [
        'html' => 'text/html',
        'xml' => 'text/xml',
        'text' => 'text/plain',
        'json' => 'application/json',
    ];

    private $_display;

    private $_layout_default = 'layout.php';

    public function __construct(Dispatcher $dispatcher, $conf, Cache $cache = null, $isCli = false)
    {
        if ($conf instanceof Ini) $conf = ($conf->toArray());

        $this->dispatcher = $dispatcher;
        $this->_cache = $cache;
        $this->_setting = $conf;
        $this->_isCli = $isCli;
        if (isset($conf['isLayout'])) {
            $this->_isLayout = true;
        } else {
            $this->_display = [
                'force' => $conf['display'],//强制的类型
                'type' => $conf['display'],
                'value' => null,
            ];
        }
    }


    public function file($cons = null)
    {
        $this->_setting['file'] = $cons;
    }

    /**
     * @param null $cons
     * @return Viewer|bool
     */
    public function layout($cons = null)
    {
        static $layObj;
        if ($this->_isLayout) return false;
        if (is_null($cons)) {
            $conf = [
                'layout' => null,
                'smarty' => null,
                'isLayout' => true,
                'static' => false,
                'concat' => $this->_setting['concat'],
            ];
            if (is_null($layObj)) $layObj = new Viewer($this->dispatcher, $conf);
            return $layObj;
        }
        $this->_setting['layout'] = $cons;
        return true;
    }

    /**
     * @param null $cons
     * @return \Smarty|bool
     */
    public function smarty($cons = null)
    {
        if (!class_exists('\Smarty')) {
            throw new \Exception('Smarty 类不存在，或无法加载');
        }

        static $_adapter;
        if ($this->_isLayout) return false;

        if (is_null($cons)) {
            if (is_null($_adapter)) {
                if (is_string($this->_setting['smarty'])) {
                    $set = ['path' => $this->_setting['smarty']];
                } elseif (is_array($this->_setting['smarty'])) {
                    $set = $this->_setting['smarty'];
                }
                $_adapter = new \Smarty();
                if (isset($set['path'])) {
                    $_adapter->setCompileDir($set['path']);
                    $_adapter->setCacheDir($set['path']);
                }
                if (isset($set['cache'])) $_adapter->caching = boolval($set['cache']);
                if (isset($set['ttl'])) $_adapter->cache_lifetime = intval($set['ttl']);
            }
            return $_adapter;
        }
        $this->_setting['smarty'] = $cons;
        return true;
    }

    public function cache(bool $bool)
    {
        if ($this->_cache instanceof Cache)
            $this->_cache->enable($bool);
    }


    /**
     * (Yaf >= 2.2.9)
     * 传递变量到模板
     *
     * 当只有一个参数时，参数必须是Array类型，可以展开多个模板变量
     *
     * @param string | array $name 变量
     * @param string $value 变量值
     */
    public function assign($name, $value = null)
    {
        if (is_array($name)) {
            $this->_var = array_merge($this->_var, $name);
        } else {
            $this->_var[$name] = $value;
        }
    }

    public function get($key)
    {
        return isset($this->_var[$key]) ? $this->_var[$key] : null;
    }


    /**
     * 向主视图发送JS
     * @param $file
     */
    public function js($file, $position = 'footer')
    {
        if (is_array($file)) {
            array_map('self::js', $file, array_pad([], count($file), $position));
        } else {
            $this->_res["_js_{$position}"][] = '/' . ltrim($file, '/');
        }
        return $this;
    }

    /**
     * 向主视图发送CSS
     * @param $file
     */
    public function css($file)
    {
        if (is_array($file)) {
            array_map('self::css', $file);
        } else {
            $this->_res['_css'][] = '/' . ltrim($file, '/');
        }
        return $this;
    }

    /**
     * 设置META值
     * @param $name
     * @param $content
     */
    public function meta($name, $content = null)
    {
        if (is_array($name)) {
            $this->_meta = array_merge($this->_meta, $name);
        } else {
            $this->_meta[$name] = $content;
        }
    }

    /**
     * 整体接受js/css变量，用在layout中接收
     * @param $name
     * @param null $content
     */
    public function resource($name, $content = null)
    {
        if (is_array($name)) {
            $this->_res = array_merge($this->_res, $name);
        } else {
            $this->_res[$name] = $content;
        }
    }

    /**
     * (Yaf >= 2.2.9)
     * 渲染模板并直接输出
     *
     * @param string $tpl 模板文件名
     * @param array $var_array 模板变量
     *
     * @return Boolean
     */
    public function display($tpl, $var_array = array())
    {
        echo $this->render($tpl, $var_array);
        return true;
    }

    /**
     * (Yaf >= 2.2.9)
     * 渲染模板并返回结果
     *
     * @param string $tpl 模板文件名
     * @param array $var_array 模板变量
     *
     * @return String
     */
    public function render($tpl, $var_array = array())
    {
        if ($this->_isLayout) {
            return $this->fetch($tpl, $var_array);
        }

        if (!!$this->_display['force'] and $this->_display['type'] !== $this->_display['force']) {
            if ($this->_display['force'] === 'html' and $this->_display['type'] === null) {
                //=null时为HTML
            } elseif ($this->_display['type'] === null) {
                throw new \Exception("当前路由设置要求输出类型须为[{$this->_display['force']}]");
            } else {
                throw new \Exception("页面响应类型[{$this->_display['type']}]与路由表中设置[{$this->_display['force']}]不符");
            }
        }

        if ($this->_display['type'] === 'none') {
            return null;

        } elseif (!!$this->_display['type']) {//json,xml,text,html
            $this->dispatcher->disableView();//关闭自动渲染

            if (!$this->_isCli) header('Content-type:' . $this->_mime[$this->_display['type']], true);

            $html = $this->finishing_display();

        } else {
            $this->real_tpl($tpl);//修正最后视图文件名称
            if (!is_readable($tpl)) throw new \Exception("视图文件不存在或不可读 {$tpl}");

            $html = $this->render_all($tpl, $var_array);
        }

        if ($this->_cache instanceof Cache) {//缓存
            $type = $this->_display['type'] ?: 'html';
            $this->_cache->cache_save($this->_mime[$type], $html, $this->_setting['static']);
        }

        return $html;
    }


    public function out_value($type, $value)
    {
        if ($type === 'html' and is_null($value)) {
            $this->_display['type'] = null;
            $this->_display['value'] = null;
        } else {
            $this->_display['type'] = $type;
            $this->_display['value'] = $value;
        }
    }


    /**
     * 整理响应内容，只处理数组
     */
    private function finishing_display()
    {
        if (!is_array($this->_display['value'])) return $this->_display['value'];

        switch ($this->_display['type']) {
            case 'text':
            case 'html':
                return print_r($this->_display['value'], true);

            case 'json':
                $value = json_encode($this->_display['value'], 256);
                $callback = isset($_GET['callback']) ? $_GET['callback'] : null;
                if (!!$callback) $callback = preg_match('/^\w+$/', $callback) ? $callback : null;
                if (!!$callback) $value = "{$callback}({$value})";
                return $value;

            case 'xml':
                return (new Xml(...$this->_display['value']))->render();

            default:
                throw new \Exception("未知的响应类型{$this->_display['type']}");
        }
    }


    public function static (bool $bool)
    {
        $this->_setting['static'] = $bool;
    }


    private function render_all($file, array $value)
    {
        //加一个检查静态的连接
        if ($this->_setting['static']) {
            $uri = $this->_cache->create_static_uri();
            if ($uri) $this->_res['_js_defer'][] = $uri;
        }

        if ($this->_setting['layout']) {
            if (is_string($this->_setting['layout'])) {
                $i = strpos($this->_setting['layout'], '/');
                if ($i === false and strpos($this->_setting['layout'], '.')) {//指定的是文件类型
                    $this->_layout_default = $this->_setting['layout'];
                } elseif ($i > 0) {
                    $layout = $this->getScriptPath() . $this->_setting['layout'];
//                    var_dump($layout);
                } else {
                    $layout = $this->_setting['layout'];
                    var_dump($layout);
                }
            }

            if (!isset($layout)) {
                $layout = dirname($file) . '/' . $this->_layout_default;
                if (!is_file($layout)) {
                    $layout = dirname(dirname($file)) . '/' . $this->_layout_default;
                }
            }
            if (!is_readable($layout))
                exit('框架视图文件不存在或不可读，请在当前控制器目录或视图根目录创建框架文件' . $this->_layout_default);

            $this->layout()->meta($this->_meta + ['_title' => null]);
            $this->layout()->resource($this->_res);

            $this->_meta = $this->_res = [];

            return $this->layout()->render($layout, ['_view_html' => $this->fetch($file, $value)]);
        }
        noLayout:
        return $this->fetch($file, $value);
    }


    /**
     * 解析子视图
     * @param $file
     * @param null $value
     * @return string
     */
    private function fetch($file, array $value)
    {
        if ($this->_setting['smarty']) {
            return $this->smarty()->fetch($file, $value + $this->_var);
        }
        $this->re_resource();

        ob_start();
        extract($value + $this->_var + $this->_res);
        include($file);
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }


    /**
     * 读取最后的视图文件
     * @param $file
     */
    private function real_tpl(&$file)
    {
        $file = $this->_setting['file'] ?: $file;
        if (substr($file, -6) != ".{$this->_setting['ext']}") $file .= ".{$this->_setting['ext']}";
        if (substr($file, 0, 1) !== '/') $file = $this->getScriptPath() . $file;
    }

    /**
     * (Yaf >= 2.2.9)
     * 设置模板文件目录
     *
     * @param string $tpl_dir 模板文件目录路径
     *
     */
    public function setScriptPath($tpl_dir)
    {
        $this->_setting['path'] = trim($tpl_dir, '/') . '/';
    }

    /**
     * (Yaf >= 2.2.9)
     * 获取模板目录文件
     *
     * @return String
     */
    public function getScriptPath()
    {
        return $this->_setting['path'];
    }

    /**
     * 重新组合网页几个要素
     * js/css/meta
     */
    private function re_resource()
    {
        if (empty($this->_res) or empty($this->_meta)) return;

        $dom = '';
        $rand = time();
        $domain = function ($item) use ($dom) {
            if (substr($item, 0, 4) === 'http') return $item;
            return $dom . $item;
        };

        if ($this->_setting['concat']) {
            $css = $dom . '??' . implode(",", $this->_res['_css']) . $rand;
            $js0 = $dom . '??' . implode(",", $this->_res['_js_head']) . $rand;
            $js1 = $dom . '??' . implode(",", $this->_res['_js_body']) . $rand;
            $js2 = $dom . '??' . implode(",", $this->_res['_js_footer']) . $rand;
            $js3 = $dom . '??' . implode(",", $this->_res['_js_defer']) . $rand;

            $this->_res['_css'] = "<link rel=\"stylesheet\" href=\"{$css}\" charset=\"utf-8\"/>";
            $this->_res['_js_head'] = "<script type=\"text/javascript\" src=\"{$js0}\" charset=\"utf-8\"></script>\n";
            $this->_res['_js_body'] = "<script type=\"text/javascript\" src=\"{$js1}\" charset=\"utf-8\"></script>\n";
            $this->_res['_js_footer'] = "<script type=\"text/javascript\" src=\"{$js2}\" charset=\"utf-8\"></script>\n";
            $this->_res['_js_defer'] = "<script type=\"text/javascript\" src=\"{$js3}\" charset=\"utf-8\" defer=\"defer\"></script>\n";
        } else {
            $css = $js0 = $js1 = $js2 = $js3 = [];
            foreach ($this->_res['_css'] as $item) {
                $css[] = "<link rel=\"stylesheet\" href=\"{$domain($item)}\" charset=\"utf-8\"/>";
            }
            foreach ($this->_res['_js_head'] as $item) {
                $js0[] = "<script type=\"text/javascript\" src=\"{$domain($item)}\" charset=\"utf-8\"></script>";
            }
            foreach ($this->_res['_js_body'] as $item) {
                $js1[] = "<script type=\"text/javascript\" src=\"{$domain($item)}\" charset=\"utf-8\"></script>";
            }
            foreach ($this->_res['_js_footer'] as $item) {
                $js2[] = "<script type=\"text/javascript\" src=\"{$domain($item)}\" charset=\"utf-8\"></script>";
            }
            foreach ($this->_res['_js_defer'] as $item) {
                $js3[] = "<script type=\"text/javascript\" src=\"{$domain($item)}\" charset=\"utf-8\" defer=\"defer\"></script>";
            }
            $this->_res['_css'] = "\n" . implode("\n", $css);
            $this->_res['_js_head'] = implode("\n", $js0);
            $this->_res['_js_body'] = implode("\n", $js1);
            $this->_res['_js_footer'] = implode("\n", $js2);
            $this->_res['_js_defer'] = implode("\n", $js3);
        }
        $this->_res['_title'] = $this->_meta['_title'];
        unset($this->_meta['_title']);
        foreach ($this->_meta as $name => $content) {
            $this->_meta[$name] = "<meta name=\"{$name}\" content=\"{$content}\" />";
        }
        $this->_res['_meta'] = implode("\n", $this->_meta);
        unset($this->_meta);
    }

}