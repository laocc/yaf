<?php
namespace laocc\yaf;

use Yaf\Config\Ini;
use Yaf\Dispatcher;
use Yaf\Plugin_Abstract;
use Yaf\Request_Abstract;
use Yaf\Response_Abstract;

class Mistake extends Plugin_Abstract
{
    private $dispatcher;
    private $_request;
    private $_setting;
    private $_root;

    public function __construct(Dispatcher $dispatcher, $setting, $callback = null)
    {
        if ($setting instanceof Ini) $setting = ($setting->toArray());
        $dispatcher->throwException(true);//出错的时候抛出异常
        $this->dispatcher = $dispatcher;
        $this->callback($callback);
        $this->_setting = $setting + ['route' => false, 'fontsize' => '100%', 'root' => null];
        if ($this->_setting['root']) {
            $this->_root = rtrim($this->_setting['root'], '/');
        }
    }

    /**
     * 构造回调，主要用于发送通知
     * @param $object
     */
    private function callback($object)
    {
        static $obj;
        if (is_callable($object)) {
            $obj = $object;
            return;
        }
        if (is_callable($obj)) $obj($object);
    }


    /**
     *
     * 产生一个错误信息，具体处理，由\plugins\Mistake处理
     * @param $str
     * @param int $level 错误级别，012，
     *
     * 0：系统停止执行，严重级别
     * 1：提示错误，继续运行
     * 2：警告级别，在生产环境中不提示，仅发给管理员
     *
     * error("{$filePath} 不是有效文件。");
     */
    public static function try_error($str, int $level = 0, $trace = null)
    {
        if ($level < 0) $level = 0;
        if ($level > 2) $level = 2;
        $level = 256 << $level;
        if (is_string($str)) {
            $err = $trace ?: debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            unset($err['function']);
            $err['message'] = $str;
            $err['code'] = $level;
            $str = json_encode($err, 256);
        }
        //产生一个用户级别的 error/warning/notice 信息
        trigger_error($str, $level);
    }


    /**
     * 1.在路由之前触发
     */
    public function routerStartup(Request_Abstract $request, Response_Abstract $response)
    {
        $handler_yaf = function ($errNo, $errStr, $errFile, $errLine) use ($request) {
            if ($errNo === E_RECOVERABLE_ERROR) {
            }

            $err = [];
            if (in_array($errNo, [256, 512, 1024])) {
                $err = json_decode($errStr, 256);
            } else {
                $err['message'] = $errStr;
                $err['code'] = $errNo;
                $err['file'] = $errFile;
                $err['line'] = $errLine;
            }
            if (!$err) return;
            $this->displayError('warn', $err, [], $request->isCli());
        };

        $handler_error = function ($err) use ($request) {
            ($err instanceof \Error) and 1;
            $arr = [];
            $arr['message'] = $err->getMessage();
            $arr['code'] = $err->getCode();
            $arr['file'] = $err->getFile();
            $arr['line'] = $err->getLine();
            $this->displayError('error', $arr, $err->getTrace(), $request->isCli());
        };

        /**
         * 注册出错时的处理方法，等同于set_error_handler($handler_error)
         * 处理类型：
         * 1，yaf自身出错；
         * 2，PHP原生错误比如：除以0，语法错误等；
         * 3，程序中error()抛出的错误；
         * 4，找不到控制器，找不到控制动作等；
         */
        $this->dispatcher->setErrorHandler($handler_yaf);
//        set_error_handler($handler_yaf);

        /**
         * 注册【异常】处理方法，
         * 处理类型：
         * 1，调用了不存在的函数；
         * 2，函数参数不对；
         * 3，throw new \Exception抛出的异常
         */
        set_exception_handler($handler_error);
    }

    public function dispatchLoopStartup(Request_Abstract $request, Response_Abstract $response)
    {
        //重新拉取request，因为前面注册set_exception_handler时用的request已过时
        $this->_request = $request;
    }

    private $html = <<<HTML
<!DOCTYPE html><html lang="zh-cn"><head>
    <meta charset="UTF-8"><title>%s</title>
    <style>
    body {margin: 0;padding: 0;font-size: %s;}
    table {width: 80%%;margin: 1em auto;border: 1px solid #456;box-shadow: 5px 5px 2px #ccc;}
    tr,td {overflow: hidden;}
    td {text-indent: 0.5em;line-height: 2em;}
    table.head {background: %s;}table.head td.l {width: 6em;font-weight: bold;}td.msg{color:red;}
    table.trade tr:nth-child(odd){background: %s;} 
    table.trade tr.nav{background: %s;} 
    table.trade td {border-bottom: 1px solid #abc;}table.trade td.l {width: 40%%;}</style>
</head><body>
<table class="head" cellpadding="0" cellspacing="0">
<tr><td class="l">错误代码：</td><td>%s</td></tr>
<tr><td class="l">错误信息：</td><td class="msg">%s</td></tr>
<tr><td class="l">错误文件：</td><td>%s</td></tr>%s
</table><table class="trade" cellpadding="0" cellspacing="0">
<tr class="nav"><td colspan="2"><b>Trace</b> : (执行顺序从上到下)</td></tr>%s</table></body></html>
HTML;


    /**
     * 显示成一个错误状态
     * @param $code
     */
    private function displayState($code)
    {
        $state = $this->states($code);
        $server = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : null;
        $html = "<html>\n<head><title>{$code} {$state}</title></head>\n<body bgcolor=\"white\">\n<center><h1>{$code} {$state}</h1></center>\n<hr><center>{$server}</center>\n</body>\n</html>";
        if (!stripos(PHP_SAPI, 'cgi')) {
            header("Status: {$code} {$state}", true);
        } else {
            $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
            header("{$protocol} {$code} {$state}", true, $code);
        }
        header('Content-type:text/html', true);
        exit($html);
    }

    private function displayError($type, $err, $trace, $isCli)
    {
        $request = $this->_request;
        ($request instanceof Request_Abstract) && 1;

        if ($isCli) {
            echo "\n\e[40;31;m================ERROR=====================\e[0m\n";
            print_r($err);
            if (!empty($trace)) print_r($trace);
            exit;
        }

        if (is_numeric($err['message'])) {
            $this->displayState($err['message']);
            return;
        }

        $traceHtml = '';
        foreach (array_reverse($trace) as $tr) {
            $str = '<tr><td class="l">';
            if (isset($tr['file'])) $str .= $this->filter_root($tr['file']);
            if (isset($tr['line'])) $str .= " ({$tr['line']})";
            $str .= '</td><td>';

            if (isset($tr['class'])) $str .= $tr['class'];
            if (isset($tr['type'])) $str .= $tr['type'];
            if (isset($tr['function'])) {
                if (empty($tr['args'])) {
                    $args = null;
                } else {
                    $args = '"' . implode('","', $tr['args']) . '"';
                }
                $str .= "{$tr['function']}({$args})";
            }
            $str .= '</td></tr>';
            $traceHtml .= $str;
        }

        $fontSize = $this->_setting['fontsize'] ?: '75%';
        if (is_numeric($fontSize)) $fontSize .= ($fontSize > 50 ? '%' : 'px');


        if ($this->_setting['route']) {
            $route = $this->get_routes_info($request);
            $Params = empty($route['Params']) ? '' : (implode(',', $route['Params']));
            $mca = $route['Path'] . $route['Module'] . $route['Control'] . '->' . $route['Action'] . '(' . $Params . ')';
            $routeHtml = '<tr><td class="l">生效路由：</td><td>' . $route['Router'] . '</td></tr><tr><td class="l">路由MCA：</td><td>';
            $routeHtml .= $this->filter_root($mca) . '</td></tr>';
            $err['route_name'] = $route['Router'];
            $err['route_mca'] = $mca;
        } else {
            $routeHtml = '';
        }


        $color = ['#def', '#ffe', '#f0c040'];
        $html = printf($this->html,
            $this->filter_root($err['message']),
            $fontSize,
            $color[0],
            $color[1],
            $color[2],
            $type . '=' . $err['code'],
            $this->filter_root($err['message']),
            "{$this->filter_root($err['file'])} ({$err['line']})",
            $routeHtml,
            $traceHtml
        );
        $this->callback($err);
        exit($html);
    }

    private function filter_root($str)
    {
        return str_replace($this->_root, '', $str);
    }


    private function get_routes_info(Request_Abstract $request)
    {
        $app = $this->dispatcher->getApplication();
        $config = $app->getConfig();
        $Params = $request->getParams();
        unset($Params['_effect_route']);
        $route = [];
        $route['Path'] = $app->getAppDirectory();
        $route['Module'] = $request->getModuleName();

        $separator = ini_get('yaf.name_separator');
        if (boolval(ini_get('yaf.name_suffix'))) {
            $route['Control'] = "{$request->getControllerName()}{$separator}Controller";
            $route['Action'] = "{$request->getActionName()}{$separator}Action";
        } else {
            $route['Control'] = "Controller{$separator}{$request->getControllerName()}";
            $route['Action'] = "Action{$separator}{$request->getActionName()}";
        }

        $route['Params'] = $Params ?: [];
        $route['Router'] = $request->getParam('_effect_route');
        $defaultModule = ucfirst(strtolower($config->application->dispatcher->defaultModule));
        if ($route['Module'] === $defaultModule) {
            $route['Module'] = '/controllers/';
        } else {
            $route['Module'] = "/{$route['Module']}/controllers/";
        }

        return $route;
    }


    public static function states($code)
    {
        switch (intval($code)) {
            case 200:
                return 'OK';
            case 201:
                return 'Created';
            case 202:
                return 'Accepted';
            case 203:
                return 'Non-Authoritative Information';
            case 204:
                return 'Not Content';
            case 205:
                return 'Reset Content';
            case 206:
                return 'Partial Content';
            case 300:
                return 'Multiple Choices';
            case 301:
                return 'Moved Permanently';
            case 302:
                return 'Found';
            case 303:
                return 'See Other';
            case 304:
                return 'Not Modified';
            case 305:
                return 'Use Proxy';
            case 307:
                return 'Temporary Redirect';
            case 400:
                return 'Bad Request';
            case 401:
                return 'Unauthorized';
            case 403:
                return 'Forbidden';
            case 404:
                return 'Not Found';
            case 405:
                return 'Method Not Allowed';
            case 406:
                return 'Not Acceptable';
            case 407:
                return 'Proxy Authentication Required';
            case 408:
                return 'Request Timeout';
            case 409:
                return 'Conflict';
            case 410:
                return 'Gone';
            case 411:
                return 'Length Required';
            case 412:
                return 'Precondition Failed';
            case 413:
                return 'Request Entity Too Large';
            case 414:
                return 'Request-URI Too Long';
            case 415:
                return 'Unsupported Media Type';
            case 416:
                return 'Requested Range Not Satisfiable';
            case 417:
                return 'Expectation Failed';
            case 422:
                return 'Unprocessable Entity';
            case 500:
                return 'Internal Server Error';
            case 501:
                return 'Not Implemented';
            case 502:
                return 'Bad Gateway';
            case 503:
                return 'Service Unavailable';
            case 504:
                return 'Gateway Timeout';
            case 505:
                return 'HTTP Version Not Supported';
            default:
                return null;
        }
    }

}