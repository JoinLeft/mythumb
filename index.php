<?php
/**
 * UrlThumb
 * 通过自定义url访问图片地址，参考自 evathumber
 * Created by PhpStorm.
 * User: jiang
 * Date: 2017/12/11
 * Time: 14:11
 */

//设置错误级别
define('ENVIRONMENT', isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] : 'development');
switch (ENVIRONMENT)
{
    case 'development':
        error_reporting(-1);
        ini_set('display_errors', 1);
        break;
    case 'testing':
    case 'production':
        ini_set('display_errors', 0);
        if (version_compare(PHP_VERSION, '7.0', '>='))
        {
            error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
        }
        else
        {
            error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_USER_NOTICE);
        }
        break;
    default:
        header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
        echo 'The application environment is not set correctly.';
        exit(1); // EXIT_ERROR
}

$dir = __DIR__; //入口文件所在的目录
$autoloader = "$dir/vendor/autoload.php"; //第三方扩展目录
$localConfig = "$dir/config.default.php"; //自定义配置文件（你可以自己配置这个文件，去覆盖默认配置）

if ( file_exists($autoloader) ) {
    $loader = require_once $autoloader; //自动加载第三方扩展
} else {
    exit('第三方扩展没有安装，请先在当前目录下运行 "composer install"');
}

//通过引用文件返回 autoloader 的实例，并添加 UrlThumber 的命名空间
$loader->add('UrlThumber', "$dir/system");

//加载默认配置文件
$config = new UrlThumber\Config\Config(require_once $localConfig);

//初始化缩略图类
$thumber = new UrlThumber\Thumber($config);
//生成图片
try {
    //输出图片
    $thumber->show();
} catch (Exception $e){
    echo $e;
    die;
    $thumber->redirect('error.png');
}
