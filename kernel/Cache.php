<?php
namespace laocc\yaf;

use laocc\dbs\ext\KeyValue;
use \Yaf\Config\Ini;
use \Yaf\Dispatcher;
use \Yaf\Request_Abstract;
use \Yaf\Response_Abstract;

class Cache
{
    private $dispatcher;
    private $_block = '/#CONTENT_TYPE#/';
    private $_key;
    private $_enable = true;
    private $_setting;
    private $_static;
    private $_kvConfig;

    public function __construct(Dispatcher $dispatcher, $setting = [], $static = [], $conn = null)
    {
        if ($setting instanceof Ini) $setting = ($setting->toArray());
        if ($static instanceof Ini) $static = ($static->toArray());
        if ($conn instanceof Ini) $conn = ($conn->toArray());

        $this->dispatcher = $dispatcher;
        $this->_setting = $setting + ['ttl' => 120, 'driver' => 'yac', 'token' => 'token', 'autorun' => true, 'fix' => 'yafCache'];
        $this->_kvConfig = $conn;//redis等连接信息
        $this->_static = $static + ['action' => '_check_static_expires_', 'token' => 'my static token', 'expires' => 86400 * 365];
        $this->_enable = boolval($setting['autorun']);
    }

    public function enable($bool)
    {
        $this->_enable = $bool;
    }

    public function display(Request_Abstract $request)
    {
        if (!$this->_enable) return null;
        $this->build_cache_key($request);
        if (is_null($this->_key)) return false;
        if ($this->cache_expires() < 1) goto no_cache;

        $cache = $this->db()->get($this->_key);
        if (!$cache) goto no_cache;

        $cache = explode($this->_block, $cache);
        if (!!$cache[0]) {
            if (isset($cache[1]) and $cache[1]) header('Content-type:' . $cache[1], true);
            $this->cache_header('cache display');
            exit($cache[0]);
        }
        return true;

        no_cache:
        $this->cache_disable_header('disable');
        return false;
    }

    /**
     * 静态化
     * @return bool
     */
    private function static_save($html)
    {
        if (!isset($this->_static['match']) or !is_array($this->_static['match'])) return false;
        $request = $this->dispatcher->getRequest();
        $uri = $request->getRequestUri();

        $filename = null;
        foreach ($this->_static['match'] as &$ptn) {
            if (preg_match($ptn, $uri)) {
                $filename = dirname(getenv('SCRIPT_FILENAME')) . getenv('REQUEST_URI');
                break;
            }
        }
        if (is_null($filename) or preg_match('/^.+((\.php)|\/)$/i', $filename)) return false;

        $save = $this->save_file($filename, $html);
        if ($save !== strlen($html)) {
            @unlink($filename);
            return false;
        }
        return true;
    }

    private function save_file($file, $content)
    {
        if (is_array($content)) $content = json_encode($content, 256);
        @mkdir(dirname($file), 0740, true);
        return file_put_contents($file, $content, LOCK_EX);
    }


    /**
     * 检查静态是否过期
     * @param Request_Abstract $request
     */
    public function check_static_expires(Request_Abstract $request)
    {
        if (($ttl = intval($this->_static['expires'])) === 0) return;
        if (preg_match('/^\/' . $this->_static['action'] . '\/(\d+)\/([0-9a-f]{32})$/i', $request->getRequestUri(), $match)) {
            $ref = getenv('HTTP_REFERER');
            if (!$ref) exit;
            $file = getenv('DOCUMENT_ROOT') . '/' . implode('/', array_slice(explode('/', $ref), 3));
            if (!$file) return;
            $md5 = md5('/' . $this->_static['action'] . '/' . $match[1] . '/' . $this->_static['token']);
            if ($md5 !== $match[2]) return;
            $ttl = $ttl + filemtime($file) - time();
            echo ((1 > $ttl) ? @unlink($file) : 2) . '>>' . strval($ttl);
            exit;
        }
    }


    /**
     * 创建放在静态文件中的URI
     * @return string
     */
    public function create_static_uri()
    {
        if (intval($this->_static['expires']) === 0) return null;
        $uri = '/' . $this->_static['action'] . '/' . time() . '/';
        return $uri . md5($uri . $this->_static['token']);
    }


    /**
     *
     * @param $type
     * @param $html
     * @param bool $static
     */
    public function cache_save($type, $html, $static = true)
    {
        if (!$this->_enable) return;
        $zip = 0;

        //连续两个以上空格变成一个
//        $value = preg_replace(['/\x20{2,}/'], ' ', $value);

        //删除:所有HTML注释
        $html = preg_replace(['/\<\!--.*?--\>/'], '', $html);

        //删除:HTML之间的空格
        $html = preg_replace(['/\>([\s\x20])+\</'], '><', $html);

        //全部HTML归为一行
        if ($zip) $html = preg_replace(['/[\n\t\r]/s'], '', $html);

        if ($static and $this->static_save($html)) return;
        if (empty($this->_key) or $this->cache_expires() < 1) return;

        if ($this->db()->set($this->_key, "{$html}{$this->_block}{$type}", $this->cache_expires())) {
            $this->cache_header('cache save');
        }
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
            $bud = array_intersect_key($_GET, array_flip(array_merge([], $this->_setting['keys'])));
        }
        //路由结果
        $params = $request->getParams();
        $this->_key = (md5(json_encode($params) . json_encode($bud) . $this->_setting['token']));
    }

    public function cacheDelete($key)
    {
        return $this->db()->del($key);
    }

    private function cache_expires()
    {
        if (isset($this->_setting['expires'])) return intval($this->_setting['expires']);
        return $this->_setting['ttl'] ? intval($this->_setting['ttl']) : 120;
    }

    /**
     * 选择存储介质
     */
    private function db()
    {
        static $medium;
        if (!is_null($medium)) return $medium;

        $drv = strtolower($this->_setting['driver']);
        if (!in_array($drv, ['yac', 'apcu', 'redis', 'memcache', 'memcached']))
            exit('缓存驱动只能是:yac/apcu/redis/memcache(d)');

        $conf = $this->_kvConfig ?: (isset($this->_setting[$drv]) ? $this->_setting[$drv] : null);
        if (in_array($drv, ['yac', 'apcu'])) $conf = $this->_setting['fix'];

        $obj = '\\laocc\\dbs\\' . ucfirst($drv);

        $medium = (new $obj($conf));
        $medium instanceof KeyValue and 1;
        if (in_array($drv, ['memcache', 'memcached'])) $medium->table($this->_setting['fix']);
        //redis不能设置table，否则都是永不过期

        return $medium;
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

