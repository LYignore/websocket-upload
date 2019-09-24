<h1 align="center"> websocket-upload </h1>

<p align="center"> websocket upload img ansy receive messages.</p>


## Installing
### 安装插件
```shell
$ composer require lyignore/websocket-upload -vvv
```
### 添加 ServiceProvider
```angular2html
在文件夹.\config\app.php的providers数组中添加：
Lyignore\WebsocketUpload\SwooleServiceProvider::class
```
### 注册配置信息
```angular2html
php artisan vendor:publish --tag=swoole-upload-img

成功后.\config\文件夹下生成swoole.php文件，自定义配置信息
```

## Usage

TODO

## Contributing

You can contribute in one of three ways:

1. File bug reports using the [issue tracker](https://github.com/lyignore/websocket-upload/issues).
2. Answer questions or fix bugs on the [issue tracker](https://github.com/lyignore/websocket-upload/issues).
3. Contribute new features or update the wiki.

_The code contribution process is not very formal. You just need to make sure that you follow the PSR-0, PSR-1, and PSR-2 coding guidelines. Any new code contributions must be accompanied by unit tests where applicable._

## License

MIT