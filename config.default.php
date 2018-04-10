<?php
return array(
    'thumbers' => array(
        'd' => array(
            'debug' => 0,
            //0: redirect to error png | 1: redirect to error png with error url msg | 2: throw an exception
            'source_path' => __DIR__ . '/upload',
            'system_file_encoding' => 'UTF-8',
            'zip_file_encoding' => 'GB2312',
            'thumb_cache_path' => __DIR__ . '/thumb',
            'system_cache_path' => null,
            'adapter' => 'Imagick', //GD | Imagick | Gmagick 图片处理库
            //'prefix' => 'thumb', //文件保存目录
            //if no prefix, will use array key
            'cache' => 0,
            'error_url' => 'default_error.png', //默认错误图片
            'allow_stretch' => true, //缩放图片时是否允许拉伸，但是不会超过最大宽度和高度
            //'min_width' => 10,
            //'min_height' => 10,
            'max_width' => 2000,
            'max_height' => 2000,
            'quality' => 100,   //图片压缩质量
            'redirect_referer' => true,
            'png_optimize' => array( //PNG优化
                'enable' => 0,
                'adapter' => 'pngout',
                'pngout' => array(
                    'bin' => __DIR__ . '/bin/pngout.exe',
                ),
            ),
            'allow_extensions' => array(), //允许的扩展名称
            'allow_sizes' => array( //允许的尺寸
                //Suggest keep empty here to be overwrite
                //'200*100',
                //'100*100',
            ),
            'disable_operates' => array( //禁用的选项
                //Suggest keep empty here to be overwrite
                //'filter',
                //'crop',
            ),
            'watermark' => array( //添加水印
                'enable' => 0,
                'position' => 'br', //水印添加位置：tl:左上角 | tr: 右上角 | bl:左下角 | br:右下角 | center:中心
                'text' => '@AlloVince1111', //文字水印
                'layer_file' => __DIR__ . '/layers/watermark.png', //图片水印
                'font_file' => __DIR__ . '/layers/simhei.ttf', //字体文件
                'font_size' => 12,  //字体大小
                'font_color' => '#FFFFFF', //字体颜色
            ),
        ),
        'e' => array(
            'debug' => 0,
            //0: redirect to error png | 1: redirect to error png with error url msg | 2: throw an exception
            'source_path' => __DIR__ . '/upload',
            'system_file_encoding' => 'UTF-8',
            'thumb_cache_path' => __DIR__ . '/thumb',
            'system_cache_path' => null,
            'adapter' => 'Imagick', //GD | Imagick | Gmagick 图片处理库
            //'prefix' => 'thumb', //文件保存目录
            //if no prefix, will use array key
            'cache' => 0,
            'error_url' => 'error.png', //默认错误图片
            'allow_stretch' => true,
            //'min_width' => 10,
            //'min_height' => 10,
            'max_width' => 2000,
            'max_height' => 2000,
            'quality' => 100,   //图片压缩质量
            'redirect_referer' => true,
            'png_optimize' => array( //PNG优化
                'enable1' => 0,
                'adapter' => 'pngout',
                'pngout' => array(
                    'bin' => __DIR__ . '/bin/pngout.exe',
                ),
            ),
            'allow_extensions' => array(), //允许的扩展名称
            'allow_sizes' => array( //允许的尺寸
                //Suggest keep empty here to be overwrite
                //'200*100',
                //'100*100',
            ),
            'disable_operates' => array( //禁用的选项
                //Suggest keep empty here to be overwrite
                //'filter',
                //'crop',
            ),
            'watermark' => array( //添加水印
                'enable' => 0,
                'position' => 'br', //水印添加位置：tl:左上角 | tr: 右上角 | bl:左下角 | br:右下角 | center:中心
                'text' => '@AlloVince1111', //文字水印
                'layer_file' => __DIR__ . '/layers/watermark.png', //图片水印
                'font_file' => __DIR__ . '/layers/simhei.ttf', //字体文件
                'font_size' => 12,  //字体大小
                'font_color' => '#FFFFFF', //字体颜色
            ),
        ),
    ),
);
