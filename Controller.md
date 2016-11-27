
# 控制器：
```php
<?php

#原来：
class IndexController extends Yaf\Controller_Abstract
{
    public function indexAction()
    {
        //业务内容
    }
}

#现在：
class IndexController extends laocc\yaf\Controller
{
    public function indexAction()
    {
        //业务内容
    }
}

#也就是extends本插件控制器

```

# 函数表：
以下函数在控制器中可以直接调用

|函数|说明|
|---|---|
|$this->view();<br>$this->view(false);<br>$this->view('path/file.phtml');|返回视图对象<br>关闭视图<br>设置视图文件名称
|$this->layout();<br>$this->layout(false);<br>$this->layout('path/layout002.php');<br>$this->layout()->assign('key','value')|返回layout对象<br>关闭框架视图<br>设置框架视图文件名称<br>向框架发送变量
|$this->smarty()->setCompileDir(PATH);<br>$this->smarty(false);|返回smarty对象操作<br>关闭smarty调用
|$this->cache(false);|不缓存
|$this->statics(false);|不静态化，若当前不缓存，静态化也会被关闭
|$this->assign($key,$value)<br>$this->set($key,$value)|向视图发送变量
|$this->get($key)|读取视图中变量
|$this->meta($name, $content);|向视图发送一个meta值，也可发送数组，不指定第二个参数时为删除该项
|$this->js($file,$position='footer');|向视图发送一个js文件引用，也可发送数组，$position可选：<br>head：网页HEAD中<br>body：网页BODY开始时<br>footer：网页BODY结束时（默认）<br>defer：延时加载
|$this->css($file);|向视图发送一个或多个css文件调用
|$this->title($string);<br>$this->keywords($string);<br>$this->description($string);|网页标题<br>关键词<br>网页描述
|$this->json($array);<br>$this->xml($key,$array);<br>$this->text($string);<br>$this->html($string);|网页的四种响应格式，以最后定义的为准，但也受制于路由中的强制值
|$this->setScriptPath($path);<br>$this->getScriptPath();|设置和读取视图所在目录
|$this->display_route();|返回当前路由匹配分配的结果

# layout.php
```php
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="UTF-8">
    <?php
    /**
     * 这些是layout中可读取的系统变量，除最后一个$_view_html，若当前没有layout生成，则这些变量会发送至子视图，也就是和控制器动作对应的原本的视图。
     * 如果业务中需要向layout发送其他变量，请避开这几个变量。 
     * @var $_title ;
     * @var $_meta ;
     * @var $_css ;
     * @var $_js_head ;
     * @var $_js_body ;
     * @var $_js_footer ;
     * @var $_js_defer ;
     * @var $_view_html ; 这是子视图全部内容
     */
    ?>
    <?= $_meta; ?>
    <?= $_css; ?>
    <?= $_js_head; ?>
    <title><?= $_title ?></title>
</head>
<body>
<?= $_js_body ?>
<?= $_view_html ?>
</body>
<?= $_js_footer ?>
<?= $_js_defer ?>
</html>
```