<?php
namespace UrlThumber;
/**
 * 参数解析类
 * Class Parameters
 * ${DS}
 * @package UrlThumber
 */
class Parameters
{
    protected $border;      //边框
    protected $crop;        //裁剪参数
    protected $filter;      //过滤参数
    protected $gravity;     //裁剪范围（指高度）
    protected $height;      //高度
    protected $width;       //宽度
    protected $layer;       //水印
    protected $percent;     //图片大小缩放比
    protected $quality;     //图片压缩质量
    protected $rotate;
    protected $x;           // X 轴偏移位置
    protected $y;           // Y 轴偏移位置
    protected $extension;   //图片后缀
    protected $filename;    //图片名称
    protected $imageWidth;  //图片宽度
    protected $imageHeight; //图片高度
    //参数对照
    protected $argMapping = array(
        'b' => 'border',
        'c' => 'crop',
        'f' => 'filter',
        'g' => 'gravity',
        'h' => 'height',
        'l' => 'layer',
        'p' => 'percent',
        'q' => 'quality',
        'r' => 'rotate',
        'w' => 'width',
        'x' => 'x',
        'y' => 'y',
    );
    //参数默认值
    protected $argDefaults = array(
        'border' => null,
        'crop' => 'crop',
        'filter' => null,
        'gravity' => null,
        'height' => null,
        'percent' => 100,
        'quality' => 100,
        'rotate' => 360,
        'width' => null,
        'layer' => null,
        'x' => null,
        'y' => null,
    );
    //配置文件
    protected $config; //配置文件

    /**
     * Constructor
     *
     * Enforces that we have an array, and enforces parameter access to array
     * elements.
     *
     * @param  array $values
     */
    public function __construct($imageName = null, Config\Config $config = null)
    {
        //初始化参数配置
        $this->config = $config;
        // 初始化图片名称
        if($imageName){
            //根据图片名类型选择处理方式
            if (is_string($imageName)) {
                //解析图片名中处理参数
                $this->fromString($imageName);
            } elseif (is_array($imageName)) {
                //解析图片名中处理参数
                $this->fromArray($imageName);
            }
        }
    }


    /**
     * 根据文件名参数解析处理格式
     * @example demo,c_100,h_100,w_100.jpg
     * @param  string $string
     * @return void
     */
    public function fromString($fileName)
    {
        //生成文件名和文件后缀，array(0 => 'demo,c_100,h_100,w_100', 1 => 'jpg')
        $fileNameArray = $fileName ? explode('.', $fileName) : array();
        if(!$fileNameArray || count($fileNameArray) < 2){
            //校验文件名的合法性
            throw new Exception\InvalidArgumentException('文件名称不正确');
        }
        //将数组尾部的单元移出数组，得到文件后缀，移除之后剩余 array(0 => 'demo,c_100,h_100,w_100')
        $fileExt = array_pop($fileNameArray); //'jpg'
        //重新拼接文件名，得到：'demo,c_100,h_100,w_100'
        $fileNameMain = implode('.', $fileNameArray);
        //重新拆分要处理的格式 array(0 => 'demo', 1 => 'c_100', 2 => 'h_100', 3 => 'w_100')
        $fileNameArray = explode(',', $fileNameMain); //第一个元素为文件名
        if(!$fileExt || !$fileNameArray || !$fileNameArray[0]){
            //校验文件名的合法性
            throw new Exception\InvalidArgumentException('文件名称不正确');
        }
        //过滤掉空值
        $fileNameArray = array_filter($fileNameArray);
        //将数组开头的单元移出数组，得到文件名，保留文件需要处理的格式， array(0 => 'c_100', 1 => 'h_100', 2 => 'w_100')
        $fileNameMain = array_shift($fileNameArray);

        $this->setExtension($fileExt); //设置文件名后缀：'jpg'
        $this->setFilename($fileNameMain); //设置文件名：'demo'

        //初始化所有参数
        $args = $fileNameArray;
        //获取默认参数对照数组
        $argMapping = $this->argMapping;
        $params = array();
        //循环所有需要处理的格式
        foreach($args as $arg){
            if(!$arg){
                continue;
            }
            //判断需要处理的格式是否合法
            if(strlen($arg) < 3 || strpos($arg, '_') !== 1){
                continue;
            }
            //获取处理操作的首字母
            $argKey = $arg{0};
            //判断默认处理操作中是否能够匹配到要处理的操作
            if(isset($argMapping[$argKey])){
                //从第二个字符开始截取，获取要处理的操作的参数
                $arg = substr($arg, 2);
                if($arg !== ''){
                    //生成参数数组
                    $params[$argMapping[$argKey]] = $arg;
                }
            }
        }
        //循环处理参数对应的参照表中的方法进行处理
        $this->fromArray($params);
        return $params;
    }


    /**
     * 动态处理参数的函数
     * example - array("crop" => "100", "height" => "100", "width" => "100")
     * @param  array $values
     * @return void
     */
    public function fromArray(array $params)
    {
        //循环处理参数
        foreach($params as $key => $value){
            //拼接操作名称，例如：setCrop、setHeight、setWidth
            $method = 'set' . ucfirst($key);
            if(method_exists($this, $method)){
                //执行参数操作
                $this->$method($value);
            }
        }
        //格式化参数
        $this->normalize();
        //根据配置文件中的禁用操作，删除需要禁用的操作
        $this->disableOperates($this->config->disable_operates);

        return $this;
        //#######---------到此所有的参数解析工作完毕，开始处理图片---------------#######
    }

    /**
     * 获取文件后缀名
     * @return mixed 如果文件后缀不存在，抛出异常
     */
    public function getExtension()
    {
        if(!$this->extension){
            throw new Exception\InvalidArgumentException(sprintf('路径中没有文件名'));
        }
        return $this->extension;
    }

    /**
     * 初始化文件后缀
     * @param $extension    后缀名
     * @return $this        返回对象实例
     */
    public function setExtension($extension)
    {
        $this->extension = strtolower($extension);
        return $this;
    }

    /**
     * 获取文件名
     * @return mixed 如果文件名不存在抛出异常
     */
    public function getFilename()
    {
        if(!$this->filename){
            throw new Exception\InvalidArgumentException(sprintf('路径中没有设置文件名'));
        }
        return $this->filename;
    }

    /**
     * 设置文件名
     * @param $filename 文件名
     * @return $this    返回对象实例
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
        return $this;
    }

    /**
     * 设置裁剪值
     * @name    c_100   裁剪宽度
     * @param   $crop   裁剪值
     * @return  $this   对象实例
     */
    public function setCrop($crop)
    {
        if(is_numeric($crop)){
            $crop = (int) $crop; 
        } elseif(is_string($crop)){
            //可能会出现填充模式，c_fill
            $crop = strtolower($crop);
        }
        $this->crop = $crop;
        return $this;
    }

    /**
     * 获取裁剪值
     * @return mixed
     */
    public function getCrop()
    {
        if($this->crop){
            return $this->crop;
        }
        //如果没有设置裁剪值，则采用默认值
        return $this->crop = $this->argDefaults['crop'];
    }

    /**
     * 设置边框值
     * @param $border   边框值
     * @return $this    对象实例
     */
    public function setBorder($border)
    {
        $this->border = $border;
        return $this;
    }

    /**
     * 获取边框值
     * @return mixed
     */
    public function getBorder()
    {
        return $this->border;
    }

    /**
     * 设置滤镜
     * @name    f_gray      黑白滤镜
     * @param   $filter     获取滤镜
     * @return  $this       对象实例
     */
    public function setFilter($filter)
    {
        $filter = strtolower($filter);
        $default_filter = array('gray', 'negative', 'gamma', 'sharp', 'lomo', 'carve', 'softenface');
        if(false === in_array($filter, $default_filter)){
            $this->filter = null;
            return $this;
        }
        $this->filter = $filter;
        return $this;
    }

    /**
     * 获取滤镜
     * @return mixed
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * 设置裁剪范围
     * @name    g_100   裁剪高度
     * @param   $gravity  获取裁剪范围
     * @return  $this
     */
    public function setGravity($gravity)
    {
        $gravities = array('top', 'bottom', 'left', 'right');
        if(is_numeric($gravity)){
            //如果裁剪参数是数字，代表裁剪的高度
            $gravity = (int) $gravity;
        } elseif(is_string($gravity)){
            //如果裁剪参数是字符，则按照四个方位进行裁剪
            $gravity = strtolower($gravity);
            if(false === in_array($gravity, $gravities)){
                $gravity = null;
            }
        }
        $this->gravity = $gravity;
        return $this;
    }

    /**
     * 获取裁剪范围
     * @return mixed
     */
    public function getGravity()
    {
        return $this->gravity;
    }

    /**
     * 获取水印
     * @return mixed
     */
    public function getLayer()
    {
        return $this->layer;
    }

    /**
     * 设置水印
     * @param $layer
     * @return $this
     */
    public function setLayer($layer)
    {
        $this->layer = $layer;
        return $this;
    }

    /**
     * 设置图片大小缩放比
     * @name p_80 图片大小缩小到原有的80%
     * @param $percent 1-100 的整数
     * @return $this
     */
    public function setPercent($percent)
    {
        $percent = (int) $percent;
        $percent = $percent > 100 ? 100 : $percent;
        $percent = $percent < 1 ? 1 : $percent;
        $this->percent = $percent;
        return $this;
    }

    /**
     * 获取图片大小缩放比
     * @return mixed
     */
    public function getPercent()
    {
        return $this->percent;
    }

    /**
     * 设置图片压缩质量
     * @name q_80 图片质量压缩到原有的80%
     * @param $quality 1-100 的整数
     * @return $this
     */
    public function setQuality($quality)
    {
        $extension = $this->getExtension();
        if(false === in_array($extension, array('jpg', 'jpeg'))){
            $this->quality = null;
            return $this;
        }
        $this->quality = (int) $quality;
        return $this;
    }

    /**
     * 获取图片压缩质量
     * @return mixed
     */
    public function getQuality()
    {
        if($this->quality){
            return $this->quality;
        }
        //如果没有设置压缩质量，则使用默认值
        return $this->quality = $this->argDefaults['quality'];
    }

    /**
     * 设置图片宽度
     * @name w_100 设置100的宽度
     * @param $width
     * @return $this
     */
    public function setWidth($width)
    {
        $width = (int) $width;
        //判断是否允许拉伸
        if(!$this->config->allow_stretch){
            //获取最大宽度默认值
            $maxWidth = $this->argDefaults['width'];
            $width = $maxWidth && $width > $maxWidth ? $maxWidth : $width;
        }
        $this->width = $width;
        return $this;
    }

    /**
     * 获取图片宽度
     * @return mixed
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * 设置图片高度
     * @name    h_100   100px
     * @param   $height 图片高度
     * @return  $this
     */
    public function setHeight($height)
    {
        $this->height = (int) $height;
        return $this;
    }
    /**
     * 获取图片高度
     * @return mixed
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * 设置裁剪时 X 轴的偏移位置
     * @name    x_0     以图片左上角为坐标原点
     * @param   $x      X 轴偏移位置
     * @return  $this
     */
    public function setX($x)
    {
        $this->x = (int) $x;
        return $this;
    }

    /**
     * 获取裁剪时 X 轴的偏移位置
     * @return mixed
     */
    public function getX()
    {
        return $this->x;
    }

    /**
     * 设置裁剪时 Y 轴的偏移位置
     * @name    y_0     以图片左上角为坐标原点
     * @param   $y      Y 轴偏移位置
     * @return  $this
     */
    public function setY($y)
    {
        $this->y = (int) $y;
        return $this;
    }

    /**
     * 获取裁剪时 Y 轴的偏移位置
     * @return mixed
     */
    public function getY()
    {
        return $this->y;
    }

    /**
     * 获取旋转参数
     * @return mixed
     */
    public function getRotate()
    {
        return $this->rotate;
    }

    /**
     * 设置旋转参数
     * @param   $rotate     旋转角度 1-360 度
     * @return  $this
     */
    public function setRotate($rotate)
    {
        $rotate = (int) $rotate;
        //rotate is between 1 ~ 360
        $this->rotate = $rotate % 360;
        return $this;
    }

    /**
     * 设置图片大小
     * @param $imageWidth
     * @param $imageHeight
     * @return $this
     */
    public function setImageSize($imageWidth, $imageHeight)
    {
        $this->imageWidth = $imageWidth;
        $this->imageHeight = $imageHeight;
        $this->normalize();
        return $this;
    }

    /**
     * 根据配置文件格式化图片默认处理参数
     * @return $this
     */
    protected function normalize()
    {
        //设置默认参数
        $defaults = $this->argDefaults;
        //读取配置文件
        if( $this->config ) {
            //获取配置文件中的最大宽度和最大高度
            $maxWidth = $this->config->max_width;
            $maxHeight = $this->config->max_height;
            if($maxWidth){
                $defaults['width'] = $maxWidth; //重写最大宽度
            }
            if($maxHeight){
                $defaults['height'] = $maxHeight; //设置最大高度
            }

            //获取配置文件中是否允许拉伸配置
            $allowStretch = $this->config->allow_stretch;
            $imageWidth = $this->imageWidth;
            $imageHeight = $this->imageHeight;
            if($imageWidth && $imageHeight){
                if($maxWidth && $maxWidth < $imageWidth){
                    //如果图片宽度超过最大宽度，则使用最大宽度
                    $defaults['width'] = $maxWidth;
                } else {
                    //如果图片宽度没有超过最大宽度，并且允许拉伸，则使用最大宽度，否则最大宽度只能是当前图片宽度
                    $maxWidth = $allowStretch ? $maxWidth : $imageWidth;
                    $defaults['width'] = $maxWidth;
                }

                if($maxHeight && $maxHeight < $imageHeight){
                    //如果图片高度超过最大高度，则使用最大高度
                    $defaults['height'] = $maxHeight;
                } else {
                    //如果图片高度没有超过最大高度，并且允许拉伸，则使用最大高度，否则最大高度只能是当前图片高度
                    $maxHeight = $allowStretch ? $maxHeight : $imageHeight;
                    $defaults['height'] = $maxHeight;
                }
            }

            //获取需要输出的宽高
            $width = $this->width;
            $height = $this->height;
            if($width && $maxWidth){
                //如果输出宽度大于最大宽度则使用最大宽度
                $this->width = $width = $width > $maxWidth ? $maxWidth : $width;
            }
            if($height && $maxHeight){
                //如果输出高度大于最大高度则使用最大高度
                $this->height = $height = $height > $maxHeight ? $maxHeight : $height;
            }

            //获取配置文件中允许的尺寸，如果设置了允许尺寸，要进行判断输出尺寸是否合规
            if($this->config->allow_sizes && $this->config->allow_sizes->count() > 0){
                $allowSizes = $this->config->allow_sizes;
                $matched = false;
                foreach($allowSizes as $allowSize){
                    list($allowWidth, $allowHeight) = explode('*', $allowSize);
                    if($allowWidth && $width == $allowWidth && $allowHeight && $height == $allowHeight){
                        //如果尺寸在允许尺寸的列表中，设置通过
                        $matched = true;
                        break;
                    }
                }
                //判断尺寸是否允许，不允许的情况下要清空宽高
                if(false === $matched){
                    $this->width = $width = null;
                    $this->height = $height = null;
                }
            }
            //获取配置文件中的，默认压缩质量
            if($this->config->quality){
                $defaults['quality'] = $this->config->quality;
            }
        }

        //X 和 Y 偏移量只有在裁剪的时候需要 c_100（填充模式无效），否则设置了也是无效
        if(!$this->crop || $this->crop == 'fill'){
            $this->x = null;
            $this->y = null;
        }

        //填充裁剪模式，必须指定输出宽高，否则无效
        if($this->crop == 'fill' & (!$this->width || !$this->height)){
            $defaults['crop'] = 'fill';
        }

        // 如果只是数字裁剪模式，则必须同时要求 X 和 Y 轴的偏移量，单独设置一个偏移量无效
        if(is_numeric($this->crop)){
            //数字裁剪模式
            if($this->x === null || $this->y === null){
                $this->x = null;
                $this->y = null;
            }
        } else {
            //填充模式
            //在填充模式下，裁剪范围（指高度）只能是位置不能是数字，位置范围参考 setGravity() 方法
            if('fill' === $this->crop){
                //偏移量无效
                $this->x = null;
                $this->y = null;
                if($this->gravity && is_numeric($this->gravity)){
                    $this->gravity = null;
                }
            }
        }
        //如果设置图片大小缩放比，则输出宽度无效
        if($this->percent){
            $this->width = null;
            $this->height = null;
        }
        //初始化默认参数
        $this->argDefaults = $defaults;
        return $this;
    }

    /**
    * Serialize to native PHP array
    *
    * @return array
    */
    public function toArray()
    {
        return array(
            'filter' => $this->getFilter(),         //获取滤镜参数
            'width' => $this->getWidth(),           //获取输出宽度
            'height' => $this->getHeight(),         //获取输出高度
            'percent' => $this->getPercent(),       //获取图片大小缩放比
            'border' => $this->getBorder(),         //获取边框值
            'layer' => $this->getLayer(),           //获取水印
            'quality' => $this->getQuality(),       //获取图片压缩质量
            'crop' => $this->getCrop(),             //获取裁剪值
            'x' => $this->getX(),                   //获取裁剪时 X 轴的偏移位置
            'y' => $this->getY(),                   //获取裁剪时 Y 轴的偏移位置
            'rotate' => $this->getRotate(),         //获取旋转参数
            'gravity' => $this->getGravity(),       //获取裁剪范围
        );
    }

    /**
    * 格式化查询字符串
    * @return string
    */
    public function toString()
    {
        //获取所有处理参数的数组结构
        $params = $this->toArray();
        //键名升序排序
        ksort($params);
        //交换参数对照数组的键值对
        $mapping = array_flip($this->argMapping);
        //获取默认参数值
        $defaults = $this->argDefaults;

        $nameArray = array();
        foreach($params as $key => $value){
            //如果与默认设置相同，则删除值
            if($value !== null && $value !== $defaults[$key]){
                //重写生成处理结构数组
                $nameArray[$mapping[$key]] = $mapping[$key] . '_' . $value;
            }
        }
        //处理结构数组转字符串
        $nameArray = $nameArray ? ',' . implode(',', $nameArray) : '';
        //拼接文件名 + 处理参数 + 文件后缀
        return $this->filename . $nameArray . '.' . $this->extension;
    }

    /**
     * 读取配置文件中的禁用操作
     * @param Config\Config $disabledOperates
     * @return $this
     */
    public function disableOperates(Config\Config $disabledOperates)
    {
        foreach($disabledOperates as $key => $operate){
            if(isset($this->$operate)){
                $this->$operate = null;
            }
        }
        return $this;
    }
}
