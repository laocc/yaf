# For YAF plugins
这是一个针对yaf的扩展插件包



# 安装：
先安装好yaf，详见：
再在项目composer.json中加：
```
{
  "require": {
        "php": ">7.0.0",
        "ext-yaf": ">3.0",
        "laocc/yaf": "dev-master"
  }
}
```

# 使用：
```php
<?php

use \Yaf\Dispatcher;

use laocc\yaf\Router;
use laocc\yaf\View;

class Bootstrap extends \Yaf\Bootstrap_Abstract
{
    public function _initRoutes(Dispatcher $dispatcher)
    {
        \Yaf\Loader::import(_ROOT . '/../vendor/autoload.php');
        $dispatcher->registerPlugin(new Router($dispatcher));
        $dispatcher->registerPlugin(new View($dispatcher));
    }
}
```

