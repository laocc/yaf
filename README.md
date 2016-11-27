## For YAF plugs
这是一个针对yaf的扩展插件包，须下列环境：
- PHP: >= v7.0.13
- YAF: >= v3.0.4

LastEdit: 2016/11/28 1:16

### yaf设置
本插件目前只支持命名空间的yaf，其他设置不影响本插件。
```
yaf.use_namespace = 1
```


### 使用示例：
请克隆另一个库：[https://github.com/laocc/yaf_example]，这个库也是一个相对完整的yaf结构


### 功能
##### 1.路由扩展：
1. 正则路由中，可以通过正则匹配结果指定模块、控制器、动作
2. 路由表中可以定义一些影响输出的东西
3. 修正yaf原本默认模块无效的情况

##### 2.视图扩展：
1. 添加框架视图功能
2. 控制器输出增加：json/xml/text，对于html除了视图输出外，可以直接输出html文本，相当于echo
3. 在控制器动作中很多直接对视图的操作，如加js/css等，自动组织js/css连接


##### 3.缓存扩展：
1. 缓存控制器结果
2. 视图标签可以使用smarty
3. 自动文本静态化（也可设置过期时间）

##### 4.错误处理：
1. 完整的错误信息显示
2. 出错报警（须自行设置发送程序，一个回调函数）
3. 注意：处理不到 Bootstrap 里的错误 

### 安装：
##### 1，通过composer安装：（建议）
composer.json
```json
{
  "require": {
        "php": ">=7.0.13",
        "ext-yaf": ">=3.0.4",
        "laocc/yaf": ">v1.0.0"
  }
}
```
##### 2，自行下载本插件包，用自己的方式加载

### 引用：
Bootstrap.php
```php
<?php

use Yaf\Dispatcher;
use Yaf\Config\Ini;
use laocc\yaf\Router;
use laocc\yaf\View;
use laocc\yaf\Cache;
use laocc\yaf\Mistake;

class Bootstrap extends \Yaf\Bootstrap_Abstract
{

    public function _initRoutes(Dispatcher $dispatcher)
    {
        /**
         * _ROOT：指向程序根目录
         * plugs.ini文件结果见：https://github.com/laocc/yaf_example/blob/master/config/plugs.ini
         */
        $conf = new Ini(_ROOT . 'config/plugs.ini');

        if (!\Yaf\Loader::import(_ROOT . 'vendor/autoload.php')) {
            exit('本程序依赖composer加载，请先运行[composer install]');
        }
        
        /**
         * 出错时的回调，一般用于发送管理信息，发短信或发邮件等
         * @param array $array 关于错误信息的一个数组
         * 注意：处理不到 Bootstrap 里的错误 
         */
        $callback = function ($array) {
            //print_r($array);
        };
        $dispatcher->registerPlugin(new Mistake($dispatcher, $conf['error'], $callback));

        /**
         * 如果使用redis/memcache，则需要指定连接信息，这些可以在plugs.ini中指定，也可以在这里另行设置
         */
        $redis = ['host' => '127.0.0.1', 'port' => 6379, 'db=2'];
        $cache = new Cache($dispatcher, $conf['cache'], $conf['static'], $redis);

        /**
        * 如果不要缓存插件，下面两项最后一个参数$cache可以不传
         */
        $dispatcher->registerPlugin(new Router($dispatcher, $conf['route'], $cache));
        $dispatcher->registerPlugin(new View($dispatcher, $conf['view'], $cache));


    }

}
```

### 使用：

#### 插件定义示例：
https://github.com/laocc/yaf_example/blob/master/config/plugs.ini

#### 路由表示例：
https://github.com/laocc/yaf_example/blob/master/config/routes.ini

#### 控制器中函数表：
https://github.com/laocc/yaf/blob/master/Controller.md



### 其他：

本插件包可能还不怎么完善，更多功能不断添加中。若有任何建议或意见，请联系我：QQ：459830045










