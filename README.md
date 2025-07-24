# Laravel Filesystem OBS

## 扩展包要求

- PHP >= 7.0

## 安装

您可以通过 composer 安装软件包

```bash
composer require back/laravel-obs -vvv
```

## 使用

1. 将服务提供者 `Back\LaravelObs\HuaweiObsServiceProvider` 注册到 config/app.php 文件:

```php
'providers' => [
    // Other service providers...
    Back\LaravelObs\HuaweiObsServiceProvider::class,
],
```

2. 在 `config/filesystems.php` 配置文件中添加你的新驱动

```php
<?php

return [
   'disks' => [
        //...
        'obs' => [
            'driver' => 'obs',
            'key' => env('OBS_KEY', ''),
            'secret' => env('OBS_SECRET', ''),
            'bucket' => env('OBS_BUCKET', ''),
            'endpoint' => env('OBS_ENDPOINT', ''),
            'cdn_domain' => env('OBS_CDN_DOMAIN', false),
            'options' => [], // 其他选项就看华为云初始化客户端参数
        ]
        //...
    ]
];
```

3. 基本使用

```php
$disk = Storage::disk('obs');

// 上传
$disk->put('avatars/example.jpg', $fileContents);

// 检查文件是否存在
$exists = $disk->has('avatars/example.jpg');

// 获取文件修改时间
$time = $disk->lastModified('avatars/example.jpg');

// 拷贝文件
$disk->copy('avatars/example.jpg', 'avatars/example-copy.jpg');

// 移动文件也可改名
$disk->move('avatars/example-copy.jpg', 'avatars/example-move.jpg');

// 获取文件内容
$contents = $disk->read('avatars/example-move.jpg');
```

### 修改日志

请参阅 [CHANGELOG](CHANGELOG.md) 了解最近发生的变化的更多信息。

### 安全

如果您发现任何与安全相关的问题，请发送电子邮件至`haoqianlei@gmail.com`或微信`Back-devil`而不是使用问题跟踪器。

## 参考

[iidestiny/laravel-filesystem-oss](https://github.com/iiDestiny/laravel-filesystem-oss)

[aobozhang/aliyun-oss-adapter](https://github.com/aobozhang/aliyun-oss-adapter)

[abcsun/qiniu-laravel](https://github.com/abcsun/qiniu-laravel-storage)

## Laravel Package Boilerplate

This package was generated using the [Laravel Package Boilerplate](https://laravelpackageboilerplate.com).

## License

[![LICENSE](https://img.shields.io/badge/license-Anti%20996-blue.svg)](https://github.com/996icu/996.ICU/blob/master/LICENSE)

