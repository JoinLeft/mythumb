<?php
/**
 * Created by PhpStorm.
 * User: jiang
 * Date: 2017/12/11
 * Time: 14:35
 */

namespace UrlThumber;

use Imagine;
use Imagine\Image\ImageInterface;
use Imagine\Image\Box;
use Imagine\Image\Palette;
use Imagine\Image\Point;

class Thumber
{
    protected $config; //配置类
    protected $url; // url 解析类
    protected $params; // 参数解析类
    protected $urlImageName; //url中的图片文件名
    protected $imageOptions = array(); //预期的图片大小、压缩等参数
    protected $thumber; // Imageine\Image\ImagineInterface
    protected $image; // Imagine\Image\Image

    protected $sourcefile; //文件来源
    protected $faker; //未知用途
    protected $cacher; //缓存管理
    protected $processed = false; //处理状态
    protected $optimized = false; //是否开启了PNG优化
    protected $optimizedImage; // PNG图片优化过的资源

    /**
     * 处理 url 路径，获取路径分组对应的配置文件
     * Thumber constructor.
     * @param $config
     * @param null $url
     */
    public function __construct($config, $url = null)
    {
        //如果传入的配置项不是 Config\Config 的实例，需要进行实例化
        if(!$config instanceof Config\Config){
            $config = new Config\Config($config);
        }
        $this->url  = new Url($url, $config); //初始化 url 解析类
        $this->urlImageName = $this->url->getUrlImageName(); //获取文件名，demo,c_100,h_100,w_100.jpg
        $this->config = $this->url->getConfig(); //根据 url 获取相应分组的配置
        $this->params = new Parameters($this->urlImageName, $this->config);   //初始化 参数解析类
    }

    /**
     * 生成图片
     * @return mixed
     */
    public function show()
    {
        //获取文件扩展名
        $extension = $this->params->getExtension();
        //处理图片（核心业务）
        $this->process();
        //判断配置文件中是否允许缓存
        if($this->config->cache){
            $this->saveImage();
        }
        //显示图片
        return $this->showImage($extension);
    }

    /**
     * 显示图片
     * @param $extension 图片后缀名
     */
    protected function showImage($extension)
    {
        //判断是否开启 PNG 优化
        if(true === $this->optimized){
            $mimeTypes = array(
                'jpeg' => 'image/jpeg',
                'jpg'  => 'image/jpeg',
                'gif'  => 'image/gif',
                'png'  => 'image/png',
                'wbmp' => 'image/vnd.wap.wbmp',
                'xbm'  => 'image/xbm',
            );
            //构造头部
            header('Content-type: ' . $mimeTypes[$extension]);
            $handle = fopen ($this->optimizedImage, "r");
            echo stream_get_contents($handle);
            unlink($this->optimizedImage);
            fclose($handle);
            exit();
        }
        //显示未优化的普通图片
        return $this->image->show($extension, $this->imageOptions);
    }

    /**
     * 保存图片
     * @param null $path
     * @return mixed
     */
    protected function saveImage($path = null)
    {
        if(!$path){
            $cacheRoot = $this->config->thumb_cache_path;
            //获取分组key 和图片路径
            $imagePath = '/' . $this->url->getUrlKey() . $this->url->getImagePath();
            $cachePath = $cacheRoot . $imagePath . '/' . $this->url->getUrlImageName();
            $pathLevel = count(explode('/', $imagePath)); //判断目录级别
            $filesystem = new Filesystem(); //初始化文件管理类
            $filesystem->prepareDirectoryStructure($cachePath, $pathLevel);
            $path = $cachePath;
        }
        //判断PNG优化是否开启
        if(true === $this->optimized){
            //保存PNG优化图片
            return @copy($this->optimizedImage, $path);
        }
        //保存普通图片
        return $this->image->save($path, $this->imageOptions);
    }

    /**
     * 图片处理
     * 处理流程：裁剪->调整缩放->图片旋转->图片滤镜->未知->水印功能->压缩质量->PNG优化
     * @return $this|void
     */
    protected function process()
    {
        //判断是否已经处理
        if(true === $this->processed){
            return $this;
        }
        //得到处理过的文件名
        $newImageName = $this->params->toString();
        //保证url的唯一性
        if($this->urlImageName !== $newImageName){
            //如果处理过的文件名和原文件名不同，说明需要的格式不符合标准，则使用处理过的图片名进行处理
            return $this->redirect($newImageName);
        }
        //获取源文件地址
        $sourcefile = $this->getSourcefile();

        //读取源文件
        $this->thumber = $this->getThumber($sourcefile);

        //获取图片宽高
        $this->params->setImageSize($this->image->getSize()->getWidth(), $this->image->getSize()->getHeight());
        //生成处理过的图片名称
        $newImageName = $this->params->toString();

        //获得图像宽度和高度时，重新判断新文件名和请求文件名是否一致
        if($this->urlImageName !== $newImageName){
            return $this->redirect($newImageName);
        }
        //处理流程：裁剪->调整缩放->图片旋转->图片滤镜->未知->水印功能->压缩质量->PNG优化
        $this->crop()->resize()->rotate()->filter()->layer()->quality()->optimize();
        //标注处理状态
        $this->processed = true;

        return $this;
    }

    /**
     * 图片重定向
     * @param $imageName
     */
    public function redirect($imageName)
    {
        $this->url->setUrlImageName($imageName);
        //生成url地址
        $newUrl = $this->url->toString();
        return header("location:$newUrl"); //+old url + server referer
    }

    /**
     * 获取图片源文件
     * @return string
     */
    public function getSourcefile()
    {
        if($this->sourcefile){
            return $this->sourcefile;
        }
        //读取配置文件中的源文件目录
        $fileRootPath = $this->config->source_path;
        $filePath = $this->url->getImagePath(); //获取文件路径
        $fileName = $this->url->getImageName(); //获取文件名
        //判断源文件目录是否存在，这里去掉了压缩文件读取功能
        if(is_dir($fileRootPath)){
            if(!$fileName){
                //抛出文件名不存在异常
                throw new Exception\InvalidArgumentException(sprintf("请求的文件名不能为空"));
            }
            //生成源文件地址
            $sourcefile = $fileRootPath . $filePath . '/' . $fileName;
            $systemEncoding =  $this->config->system_file_encoding; //获取系统编码
            $sourcefile = urldecode($sourcefile); //url解码
            if($systemEncoding || $systemEncoding != 'UTF-8') {
                //如果不是 utf-8 则进行转码处理
                $sourcefile = iconv('UTF-8', $this->config->system_file_encoding, $sourcefile);
            }
            if (!file_exists($sourcefile)) {
                //文件不存在
                throw new Exception\IOException(sprintf(
                    "您读取的图片不存在：%s", $sourcefile
                ));
            }
        } else {
            throw new Exception\IOException(sprintf(
                "您设置的源文件目录不存在：%s", $fileRootPath
            ));
        }

        return $this->sourcefile = $sourcefile;
    }

    public function setSourcefile($sourcefile)
    {
        $this->sourcefile = $sourcefile;
        return $this;
    }

    /**
     * 根据适配器获取缩略图处理类
     * @param null $sourcefile 源文件地址
     * @param null $adapter 适配器
     * @return Imagine\Gd\Imagine|Imagine\Gmagick\Imagine|Imagine\Imagick\Imagine
     */
    public function getThumber($sourcefile = null, $adapter = null)
    {
        if($this->thumber){
            return $this->thumber;
        }
        //根据适配器类型选择适合的适配器
        $this->thumber = $this->createThumber($adapter);

        if($sourcefile){
            //通过适配器打开源文件
            $this->image = $this->thumber->open($sourcefile);
        }
        return $this->thumber;
    }

    /**
     * 根据适配器创建不同的处理类(三种图片处理库)
     * @param null $adapter
     * @return Imagine\Gd\Imagine|Imagine\Gmagick\Imagine|Imagine\Imagick\Imagine
     */
    protected function createThumber($adapter = null)
    {
        //判断图片处理适配器参数，如果不存在默认使用 配置文件中的适配器配置
        $adapter = $adapter ? $adapter : strtolower($this->config->adapter);
        switch ($adapter) {
            case 'gd':
                $thumber = new Imagine\Gd\Imagine();
                break;
            case 'imagick':
                $thumber = new Imagine\Imagick\Imagine();
                break;
            case 'gmagick':
                $thumber = new Imagine\Gmagick\Imagine();
                break;
            default:
                $thumber = new Imagine\Gd\Imagine();
        }
        return $thumber;
    }

    /**
     * 裁剪
     * @return $this
     */
    protected function crop()
    {
        //获取裁剪类型
        $crop = $this->params->getCrop();
        if(!$crop){
            //如果没有设置裁剪，则原图返回
            return $this;
        }

        //获取裁剪高度
        $gravity = $this->params->getGravity();
        if(false === is_numeric($crop)){
            //填充模式不会进行裁剪，而是直接执行 resize 生成缩略
            return $this;
        }
        //如果裁剪高度没有指定，则设置宽高一样
        $gravity = $gravity ? $gravity : $crop;
        //获取裁剪的偏移坐标
        $x = $this->params->getX();
        $y = $this->params->getY();
        //获取图片原始宽高
        $imageWidth = $this->image->getSize()->getWidth();
        $imageHeight = $this->image->getSize()->getHeight();

        //判断裁剪的宽高是否大于图片自身高度
        if ($crop > $imageWidth || $gravity > $imageHeight) {
            $crop = $crop && $crop > $imageWidth ? $imageWidth : $crop; //超出范围时使用最大宽度
            $gravity = $gravity && $gravity > $imageHeight ? $imageHeight : $gravity; //超出范围时使用最大高度
        }

        //如果没有设置裁剪坐标，则从图片中心位置裁剪
        $x = $x !== null ? $x : ($imageWidth - $crop) / 2;
        $y = $y !== null ? $y : ($imageHeight - $gravity) / 2;

        if ($this->params->getExtension() == 'gif') {
            //gif压缩比
            $gif_compress = 0.7; //gif压缩比，默认采用 0.8 的压缩比
            //循环处理 gif 的帧
            $this->image->layers()->coalesce();
            $layers = $this->image->layers();
            $layer_count = count($layers);
            //$layer_count = 0;
            //gif 需要丢弃的 key 列表
            $gif_compress_key = array();
            //如果 gif 超过 10 帧，丢弃 30% 的帧
            if ($layer_count > 10) {
                //获取需要抛弃的帧数
                $gif_compress_num = floor($layer_count * (1 - $gif_compress));
                //生成需要抛弃的帧的索引
                $gif_compress_limit = ceil($layer_count/$gif_compress_num);
                for($i = 0; $i < $gif_compress_limit; $i++) {
                    array_push($gif_compress_key, ($i*$gif_compress_num) + 1);
                }
            }
            //die;
            foreach ($layers as $key => $frame) {
                var_dump($key);
                var_dump(in_array($key, $gif_compress_key));
                if (!in_array($key, $gif_compress_key)) {
                    $frame->crop(new Imagine\Image\Point($x, $y), new Imagine\Image\Box($crop, $gravity));
                } else {
                    $layers->remove($key);
                }
            }
            die;
            $this->imageOptions['animated'] = true;
        } else {
            //ImageInterface crop
            $this->image = $this->image->crop(new Imagine\Image\Point($x, $y), new Imagine\Image\Box($crop, $gravity));
        }
        return $this;
    }

    /**
     * 调整
     * @return $this
     */
    protected function resize()
    {
        //获取图片缩放比
        $percent = $this->params->getPercent();
        if($percent) {
            //根据缩放比调整图片
            $this->resizeByPercent();
        } else {
            //根据图片大小调整图片
            $this->resizeBySize();
        }
        return $this;
    }

    /**
     * 设置的大小生成缩略
     * @return $this
     */
    protected function resizeBySize()
    {

        $width = $this->params->getWidth(); //获取url中输入的图片宽度
        $height = $this->params->getHeight(); //获取url中输入的图片高度
        $maxWidth = $this->config->max_width; //获取配置文件中允许的最大宽度
        $maxHeight = $this->config->max_height; //获取配置文件中允许的最大高度
        $imageWidth = $this->image->getSize()->getWidth(); //获取裁剪过的图片宽度
        $imageHeight = $this->image->getSize()->getHeight(); //获取裁剪过的图片高度

        //如果url中没有输入限制宽高
        if(!$width && !$height){
            //如果配置文件中没有配置最大宽高直接输出
            if(!$maxWidth && !$maxHeight){
                return $this;
            }
            //判断裁剪过的宽高和配置文件中的宽高大小
            if($maxWidth && $imageWidth > $maxWidth || $maxHeight && $imageHeight > $maxHeight){
                $width = $maxWidth && $imageWidth > $maxWidth ? $maxWidth : $width;
                $height = $maxHeight && $imageHeight > $maxHeight ? $maxHeight : $height;

                //If only width or height, resize by image size radio
                $width = $width ? $width : ceil($height * $imageWidth / $imageHeight);
                $height = $height ? $height : ceil($width * $imageHeight / $imageWidth);
            } else {
                return $this;
            }

        } else {
            if($width === $imageWidth || $height === $imageHeight){
                return $this;
            }

            //If only width or height, resize by image size radio
            $width = $width ? $width : ceil($height * $imageWidth / $imageHeight);
            $height = $height ? $height : ceil($width * $imageHeight / $imageWidth);

            $allowStretch = $this->config->allow_stretch;

            if(!$allowStretch){
                $width = $width > $maxWidth ? $maxWidth : $width;
                $width = $width > $imageWidth ? $imageWidth : $width;
                $height = $height > $maxHeight ? $maxHeight : $height;
                $height = $height > $imageHeight ? $imageHeight : $height;
            }
        }

        $size    = new Imagine\Image\Box($width, $height);
        $crop = $this->params->getCrop();
        if($crop === 'fill'){
            //是否使用填充模式
            $mode    = Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND;
        } else {
            //使用数字裁剪
            $mode    = Imagine\Image\ImageInterface::THUMBNAIL_INSET;
        }
        if ($this->params->getExtension() == 'gif') {
            //如果是gif不能直接进行缩略图生成，还是要执行裁剪
            //循环处理 gif 的帧
            $this->image->layers()->coalesce();
            //从 gif 图片中心位置裁剪
            $x = ($imageWidth - $width) / 2;
            $y = ($imageHeight - $height) / 2;
            foreach ($this->image->layers() as $key => $frame) {
                $frame->crop(new Imagine\Image\Point($x, $y), new Imagine\Image\Box($width, $height));

            }
            $this->imageOptions['animated'] = true;
        } else {
            $this->image = $this->image->thumbnail($size, $mode);
        }
        return $this;
    }

    /**
     * 根据设置的缩放比设置缩略
     * @return $this
     */
    protected function resizeByPercent()
    {
        $percent = $this->params->getPercent();
        if(!$percent || $percent == 100){
            return $this;
        }

        $imageWidth = $this->image->getSize()->getWidth();
        $imageHeight = $this->image->getSize()->getHeight();

        $box =  new Imagine\Image\Box($imageWidth, $imageHeight);
        $mode    = Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND;
        $box = $box->scale($percent / 100);
        $this->image = $this->image->thumbnail($box, $mode);
        return $this;
    }

    /**
     * 设置旋转
     * @return $this
     */
    protected function rotate()
    {
        //从url处理中获取旋转参数
        $rotate = $this->params->getRotate();
        if($rotate){
            //设置旋转
            $this->image->rotate($rotate);
        }
        return $this;
    }

    /**
     * 滤镜
     * @return $this
     */
    protected function filter()
    {
        //获取 url 解析中的滤镜
        $filter = $this->params->getFilter();
        if(!$filter){
            //如果没有设置滤镜则跳过
            return $this;
        }

        $effects = $this->image->effects();
        switch($filter){
            case 'gray':
                $effects->grayscale();
                break;
            case 'gamma':
                $effects->gamma(0.7);
                break;
            case 'negative':
                $effects->negative();
                break;
            case 'sharp':
                //only in imagine develop version
                $effects->sharpen();
                break;
            case 'carve':
                $layer = $this->image->copy();
                $effects->mosaic()->borderline()->emboss();
                $this->image->paste($layer, new Point(0, 0));
                break;

            case 'softenface':
                $layer = $this->image->copy();
                $effects->gaussBlur();
                $this->image->paste($layer, new Point(0, 0));
                $effects->brightness(-10);
                break;
            case 'lomo':
                break;
            default:
        }
        return $this;
    }

    /**
     * 添加水印
     * @return $this
     */
    protected function layer()
    {
        //获取配置文件中的水印选项
        $watermark_config = $this->config->watermark;
        if(!$watermark_config || !$watermark_config->enable){
            return $this;
        }

        $textLayer = false;
        $text = $watermark_config->text;  //获取文字水印
        //首先获取图片水印
        if($watermark_config->layer_file){
            //创建图片水印对象
            $waterLayer = $this->createThumber()->open($watermark_config->layer_file);
            $layerWidth = $waterLayer->getSize()->getWidth(); //获取水印图片宽度
            $layerHeight = $waterLayer->getSize()->getHeight(); //获取水印图片高度
        } else {
            //如果没有开启图片水印，同时也没有设置水印，则跳过水印设置
            if(!$text || !$watermark_config->font_file || !$watermark_config->font_size || !$watermark_config->font_color){
                return $this;
            }
            //这里删除二维码水印功能，只使用文字功能
            $font = $this->createFont($watermark_config->font_file, $watermark_config->font_size, (new Palette\RGB())->color($watermark_config->font_color));
            $layerBox = $font->box($text);
            $layerWidth = $layerBox->getWidth();
            $layerHeight = $layerBox->getHeight();
            $textLayer = true;
        }
        //获取图片宽高
        $imageWidth = $this->image->getSize()->getWidth();
        $imageHeight = $this->image->getSize()->getHeight();

        $x = 0;
        $y = 0;
        //获取水印位置
        $position = $watermark_config->position;
        switch($position){
            case 'tl':
                //左上角
                break;
            case 'tr':
                //右上角
                $x = $imageWidth - $layerWidth;
                break;
            case 'bl':
                //左下角
                $y = $imageHeight - $layerHeight;
                break;
            case 'center':
                //中间位置
                $x = ($imageWidth - $layerWidth) / 2;
                $y = ($imageHeight - $layerHeight) / 2;
                break;
            case 'br':
                //右下角
            default:
                //右下角，默认采用
                $x = $imageWidth - $layerWidth;
                $y = $imageHeight - $layerHeight;
        }
        //创建位置
        $point = new Imagine\Image\Point($x, $y);

        if($textLayer){
            $this->image->draw()->text($text, $font, $point);
        } else {
            $this->image->paste($waterLayer, $point);
        }

        return $this;
    }

    /**
     * 创建文字水印
     * @param $font 文字
     * @param $size 大小
     * @param $color 颜色
     * @return mixed
     */
    protected function createFont($font, $size, $color)
    {
        $thumberClass = get_class($this->image);
        $classPart = explode('\\', $thumberClass);
        $classPart[2] = 'Font';
        $fontClass = implode('\\', $classPart);
        return new $fontClass(new \Imagick(), $font, $size, $color);
    }

    /**
     * 设置压缩质量
     * @return $this
     */
    protected function quality()
    {
        $quality = $this->params->getQuality();
        if($quality){
            $this->imageOptions['quality'] = $quality;
        }
        return $this;
    }

    /**
     * PNG图片无损优化
     * @return $this
     */
    protected function optimize()
    {
        //获取文件扩展名称
        $extension = $this->params->getExtension();
        if($extension === 'gif'){
            return $this;
        }
        //判断图片是否 PNG 格式，是否开启了 PNG 无损优化选项
        if($extension === 'png' && $this->config->png_optimize->enable){
            $toolClass = 'UrlThumber\Tool\Pngout';
            if(false === $toolClass::isSupport()){
                return $this;
            }
            //使用PNG无损优化
            $feature = new $toolClass($this->config->png_optimize->pngout->bin);
            $this->optimizedImage = $feature->filterDump($this->image); //后去优化的图片
            if($this->optimizedImage){
                $this->optimized = true;
            }
        }
        return $this;
    }

    /**
     * 析构函数
     */
    public function __destruct()
    {
        //销毁优化的图片
        if($this->optimizedImage){
            unlink($this->optimizedImage);
        }
    }
}