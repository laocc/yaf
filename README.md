# For YAF plugs
这是一个针对yaf的扩展插件包，须下列环境：
- PHP: >= v7.0.13
- YAF: >= v3.0.4
- YAC: >= v2.0.1

# 使用示例：
请克隆另一个库：https://github.com/laocc/yaf_example

这个库也是一个相对完整的yaf结构

# 安装：
## 1，通过composer安装：（建议）
composer.json
```json
{
  "require": {
        "php": ">=7.0.13",
        "ext-yaf": ">=3.0.4",
        "ext-yac": ">=2.0.1",
        "laocc/yaf": "dev-master"
  }
}
```
## 2，直接使用：
直接克隆本项目，或下载版本包，放至网站根目录。将下面使用中import部分改为：
```php
<?php

\Yaf\Loader::import(_ROOT . '/本插件包目录名/kernel/autoload.php');

```


# 使用：
Bootstrap.php
```php
<?php

use \Yaf\Dispatcher;

use \laocc\yaf\Router;
use \laocc\yaf\View;

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

