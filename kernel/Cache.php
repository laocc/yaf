<?php
namespace laocc\yaf;

use laocc\dbs\Memcache;
use laocc\dbs\Memcached;
use laocc\dbs\Redis;
use laocc\dbs\Yac;
use Yaf\Dispatcher;
use Yaf\Request_Abstract;
use Yaf\Response_Abstract;

class Cache
{
    private $_block = '/#CONTENT_TYPE#/';
    private $_token = 'esp';
    private $_key;
    private $dispatcher;
    private $setting;

    public function __construct(Dispatcher $dispatcher, $setting = [])
    {
        $this->dispatcher = $dispatcher;
        $this->setting = $setting;
    }

    public function enable($bool)
    {

    }

    public function display(Request_Abstract $request)
    {
        $this->build_cache_key($request);
        if (is_null($this->_key)) return false;
        return $this->cache_display();
    }

    /**
     * 静态化
     * @return bool
     */
    private function static_save()
    {
        //静态化
        $save = 0;
        if ($this->_config['static']) {
            $save = @file_put_contents($this->_config['static'], $html, LOCK_EX);
            if ($save !== strlen($html)) {
                @unlink($this->_config['static']);
                $save = 0;
            }
        }


        if ($this->request->get('_disable_static')) return false;
        $pattern = Config::get('cache.static');
        if (empty($pattern) or !$pattern) return false;
        $filename = null;
        foreach ($pattern as &$ptn) {
            if (preg_match($ptn, $this->request->uri)) {
                $filename = dirname(getenv('SCRIPT_FILENAME')) . getenv('REQUEST_URI');
                break;
            }
        }
        if (is_null($filename)) return false;
        $html = $this->response->render();
        $save = save_file($filename, $html);
        if ($save !== strlen($html)) {
            @unlink($filename);
            return false;
        }
        return true;
    }

    public function cache_save($type, $html)
    {
//        if ($this->static_save()) return;
        if ($this->cache_expires() < 1) return;
        if (empty($this->_key)) return;

        $zip = 0;

        //连续两个以上空格变成一个
//        $value = preg_replace(['/\x20{2,}/'], ' ', $value);

        //删除:所有HTML注释
        $html = preg_replace(['/\<\!--.*?--\>/'], '', $html);

        //删除:HTML之间的空格
        $html = preg_replace(['/\>([\s\x20])+\</'], '><', $html);

        //全部HTML归为一行
        if ($zip) $html = preg_replace(['/[\n\t\r]/s'], '', $html);

        if ($this->cache_medium()->set($this->_key, "{$html}{$this->_block}{$type}", $this->cache_expires())) {
            $this->cache_header('by save');
        }
    }

    public function cache_display()
    {
        if ($this->cache_expires() < 1) goto no_cache;

        $cache = $this->cache_medium()->get($this->_key);
        if (!$cache) goto no_cache;

        $cache = explode($this->_block, $cache);
        if (!!$cache[0]) {
            if (isset($cache[1]) and $cache[1]) header('Content-type:' . $cache[1], true);
            $this->cache_header('by display');
            exit($cache[0]);
        }
        return true;

        no_cache:
        $this->cache_disable_header('disable');
        return false;
    }

    /**
     * 创建用于缓存的key
     */
    private function build_cache_key(Request_Abstract $request)
    {
        if (!is_null($this->_key)) return;

        //合并需要请求的公共KEY，并反转数组，最后获取与$_GET的交集
        $bud = [];
        if (!empty($_GET)) {
            $bud = array_intersect_key($_GET, array_flip(array_merge([], $this->setting['keys'])));
        }
        //路由结果
        $params = $request->getParams();
        $this->_key = (md5(json_encode($params) . json_encode($bud) . $this->_token));
    }

    public function cacheDelete($key)
    {
        return $this->cache_medium()->del($key);
    }

    private function cache_expires()
    {
        return $this->setting['ttl'];
    }

    /**
     * 选择存储介质
     */
    private function cache_medium()
    {
        static $medium;
        if (!is_null($medium)) return $medium;
        if (class_exists('yac')) {
            return $medium = new Yac('cache');
        }
        if (class_exists('redis')) {
            return $medium = new Redis();
        }
        if (class_exists('memcache')) {
            return $medium = new Memcache();
        }
        if (class_exists('memcached')) {
            return $medium = new Memcached();
        }
        throw new \Exception('请至少安装一种cache扩展，建议yac');
    }


    /**
     * 设置缓存的HTTP头
     */
    private function cache_header($label = null)
    {
        if (headers_sent()) return;
        $NOW = time();//编辑时间
        $expires = $this->cache_expires();

        //判断浏览器缓存是否过期
        if (getenv('HTTP_IF_MODIFIED_SINCE') && (strtotime(getenv('HTTP_IF_MODIFIED_SINCE')) + $expires) > $NOW) {
            $protocol = getenv('SERVER_PROTOCOL') ?: 'HTTP/1.1';
            header("{$protocol} 304 Not Modified", true, 304);
        } else {
            $Expires = time() + $expires;//过期时间
            $maxAge = $Expires - (getenv('REQUEST_TIME') ?: 0);//生命期
            header('Cache-Control: max-age=' . $maxAge . ', public');
            header('Expires: ' . gmdate('D, d M Y H:i:s', $Expires) . ' GMT');
            header('Pragma: public');
            if ($label) header('CacheLabel: ' . $label);
        }
    }

    /**
     * 禁止向浏览器缓存
     */
    private function cache_disable_header($label = null)
    {
        if (headers_sent()) return;
        header('Cache-Control: no-cache, must-revalidate, no-store', true);
        header('Pragma: no-cache', true);
        header('Cache-Info: no cache', true);
        if ($label) header('CacheLabel: ' . $label);
    }


}

