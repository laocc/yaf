
# 加载说明：

```php
<?php

# 通过composer安装的，加载composer autoload
\Yaf\Loader::import(_ROOT . '/../vendor/autoload.php');


# 如果不喜欢composer autoload就加载本程序提供的autoload
\Yaf\Loader::import(_ROOT . '/../kernel/autoload.php');


```
如果都不喜欢，就自己想办法吧。

将命名空间`laocc\yaf\`指向本程序的`kernel`目录就可了，这目录下边还有一个`extend`。

都不乐意？那就施展`copy`大法！


# 文件说明：

这个不想说，自己看。