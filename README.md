# For YAF plugs
这是一个针对yaf的扩展插件包，须下列环境：
- PHP: >= v7.0.13
- YAF: >= v3.0.4
- YAC: >= v2.0.1

# 使用示例：
请克隆另一个库：https://github.com/laocc/yaf_example

这个库也是一个相对完整的yaf结构


# 功能
## 路由扩展：
1. 正则路由中，可以通过正则匹配结果指定模块、控制器、动作；
2. 路由表中可以定义一些影响输出的东西
3. 修正yaf原本默认模块无效的情况

## 视图扩展：
1. 添加框架视图功能；
2. 控制器输出增加：json/xml/text，对于html除了视图输出外，可以直接输出html文本，相当于echo；
3. 在控制器动作中很多直接对视图的操作，如加js/css等，自动组织js/css连接；


## 缓存扩展：
1. 缓存控制器结果；
2. 视图标签可以使用smarty；
3. 自动文本静态化（也可设置过期时间）；



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


