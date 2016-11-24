<?php
namespace laocc\yaf\extend;


use Yaf\View_Interface;

class Viewer implements View_Interface
{

    private $_var = [];//子视图变量

    private $_res = [
        'stylesheet' => [],
        'javascript_head' => [],
        'javascript_body' => [],
        'javascript_footer' => [],
        'javascript_defer' => [],
    ];
    private $_keys = [];
    private $_meta = [];

    private $_mime = [
        'html' => 'text/html',
        'xml' => 'text/xml',
        'text' => 'text/plain',
        'json' => 'application/json',
    ];


    private $_out_type;
    private $_out_value;


    public function file(bool $bool = null)
    {

    }

    public function layout(bool $bool = null)
    {

    }

    public function smarty()
    {

    }

    public function cache(bool $bool)
    {

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
            $this->_res["javascript_{$position}"][] = '/' . ltrim($file, '/');
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
            $this->_res['stylesheet'][] = '/' . ltrim($file, '/');
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
     * 用于设置title,keywords,description三个网页要素
     * @param $name
     * @param null $content
     */
    public function keys($name, $content = null)
    {
        $this->_keys[$name] = $content;
    }


    public function out_value($type, string $value)
    {
        $this->_out_type = $this->_mime[$type];
        $this->_out_value = $value;
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
    }

    /**
     * (Yaf >= 2.2.9)
     * 设置模板文件目录
     *
     * @param string $tpl_dir 模板文件目录路径
     *
     * @return Boolean
     */
    public function setScriptPath($tpl_dir)
    {
    }

    /**
     * (Yaf >= 2.2.9)
     * 获取模板目录文件
     *
     * @return String
     */
    public function getScriptPath()
    {
    }


    /**
     * 重新组合网页几个要素
     * 便于解析时向视图释放
     * js/css/meta
     */
    private function re_res()
    {
        $resource = Registry::get('app')->resource;
        $dom = rtrim($resource->domain, '/');
        $rand = time();
        $domain = function ($item) use ($dom) {
            if (substr($item, 0, 4) === 'http') return $item;
            return $dom . $item;
        };

        if ($resource->concat) {
            $css = $dom . '??' . implode(",", $this->_res['stylesheet']) . $rand;
            $js0 = $dom . '??' . implode(",", $this->_res['javascript0']) . $rand;
            $js1 = $dom . '??' . implode(",", $this->_res['javascript1']) . $rand;
            $js2 = $dom . '??' . implode(",", $this->_res['javascript2']) . $rand;

            $this->_res['stylesheet'] = "<link rel=\"stylesheet\" href=\"{$css}\" charset=\"utf-8\" />";
            $this->_res['javascript0'] = "<script type=\"text/javascript\" src=\"{$js0}\" charset=\"utf-8\" ></script>\n";
            $this->_res['javascript1'] = "<script type=\"text/javascript\" src=\"{$js1}\" charset=\"utf-8\" ></script>\n";
            $this->_res['javascript2'] = "<script type=\"text/javascript\" src=\"{$js2}\" charset=\"utf-8\" defer=\"defer\" ></script>\n";
        } else {
            $css = $js0 = $js1 = $js2 = [];
            foreach ($this->_res['stylesheet'] as $item) {
                $css[] = "<link rel=\"stylesheet\" href=\"{$domain($item)}\" charset=\"utf-8\" />";
            }
            foreach ($this->_res['javascript0'] as $item) {
                $js0[] = "<script type=\"text/javascript\" src=\"{$domain($item)}\" charset=\"utf-8\" ></script>";
            }
            foreach ($this->_res['javascript1'] as $item) {
                $js1[] = "<script type=\"text/javascript\" src=\"{$domain($item)}\" charset=\"utf-8\" ></script>";
            }
            foreach ($this->_res['javascript2'] as $item) {
                $js2[] = "<script type=\"text/javascript\" src=\"{$domain($item)}\" charset=\"utf-8\" defer=\"defer\" ></script>";
            }
            $this->_res['stylesheet'] = "\n" . implode("\n", $css);
            $this->_res['javascript0'] = implode("\n", $js0);
            $this->_res['javascript1'] = implode("\n", $js1);
            $this->_res['javascript2'] = implode("\n", $js2);
        }

        if (isset($this->_keys['title'])) {
            $this->_keys['title'] .= ' - ' . $this->_keys['_title'];
        } else {
            $this->_keys['title'] = $this->_keys['_title'];
        }
        $this->_meta = array_merge($this->_meta, $this->_keys);
        foreach ($this->_meta as $name => $content) {
            $this->_meta[$name] = "<meta name=\"{$name}\" content=\"{$content}\" />";
        }
        unset($this->_keys['_title'], $this->_meta['_title'], $this->_meta['title']);
        $this->_keys['meta'] = implode("\n", $this->_meta);
        unset($this->_meta);
    }

}