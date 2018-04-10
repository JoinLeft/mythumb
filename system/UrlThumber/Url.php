<?php
/**
 * UrlThumber
 * Url地址处理类
 */

namespace UrlThumber;

/**
 * 将Url解析为 UrlThumber 需要的结构
 * - Example : http://localhost/UrlThumber/thumb/zip/archive/zipimage,w_100.jpg?query=123
 * 解析格式为 :
 * -- scheme : http
 * -- host : localhost
 * -- query : query=123
 * -- urlScriptName : /EvaThumber/index.php
 * -- urlRewritePath : /EvaThumber
 * -- urlPrefix : thumb
 * -- urlKey : zip
 * -- urlImagePath : /thumb/zip/archive/zipimage,w_100.jpg
 * -- urlImageName : zipimage,w_100.jpg
 * -- urlRewriteEnabled : true
 * -- imagePath : /archive
 * -- imageName : zipimage.jpg
 *
 */
class Url
{
    protected $urlString; // 完整 url 地址
    protected $urlArray; //通过 parse_url 解析得到的 url 结构数组
    protected $urlPrefix;
    protected $urlScriptName;
    protected $urlImagePath; //url中的图片路径
    protected $urlImageName; //url中的图片名称
    protected $urlRewriteEnabled; //是否开启url重写
    protected $urlRewritePath; //url重写的脚本路径地址
    protected $imagePath; // 图片路径
    protected $imageName; //图片名称
    protected $config; //配置参数

    /**
     * Url constructor.
     * @param null $url //url地址
     * @param null $config //配置项
     */
    public function __construct($url = null, Config\Config $config)
    {
        //设置配置参数对象
        $this->config = $config;
        //判断配置文件是否为空
        if (!$this->config) return;
        //获取当前url地址，返回：http://www.localhost.com/thumb/d/demo,c_100,h_100,w_100.jpg
        $this->urlString = $url ? $url : $this->getCurrentUrl();
        if ($this->urlString) {
            //解析url结构
            $this->setUrlArray($this->urlString);
        }

        $configKey = $this->getUrlKey(); //获取路径中分组目录名称，例如：thumers->d 中的 d 分组
        //判断配置文件中是否存在该分组
        if (isset($this->config->thumbers->$configKey)) {
            //根据分组 key 获取配置项
            $this->config = $this->config->thumbers->$configKey;
        } else {
            //不存在时抛出配置文件中不存在分组key异常
            throw new Exception\InvalidArgumentException(
                sprintf('配置文件中没有配置分组 %s', $configKey)
            );
        }
    }

    /**
     * 读取配置文件
     * @return Config\Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * 获取当前 url 并生成新的 url 地址
     * - Example : http://localhost/index.php/UrlThumber/thumb/zip/archive/zipimage,w_100.jpg?query=123
     * @return string
     */
    public function getCurrentUrl()
    {
        //获取 当前运行脚本所在的服务器的主机名，例如: 'localhost'
        $serverName = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
        //获取要访问的图片地址，例如：'/index.php/UrlThumber/thumb/zip/archive/zipimage,w_100.jpg?query=123'
        $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if (!$serverName) {
            return '';
        }

        //查询是否通过  HTTPS 协议被访问
        $pageURL = 'http';
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            $pageURL .= 's';
        }
        $pageURL .= '://';

        //获取端口，默认使用 80 端口
        if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != '80') {
            //重新拼接请求路径
            $pageURL .= $serverName . ':' . $_SERVER['SERVER_PORT'] . $requestUri;
        } else {
            $pageURL .= $serverName . $requestUri;
        }
        return $pageURL;
    }

    /**
     * 根据 parse_url 解析 url 为数组结构
     * - Example : http://localhost/index.php/UrlThumber/thumb/zip/archive/zipimage,w_100.jpg?query=123
     * - shceme : http
     * - host : localhost
     * - port : null
     * - query : 123
     * - urlPath : /index.php/UrlThumber/thumb/zip/archive/zipimage,w_100.jpg
     * @param  string url
     * @return array
     */
    public function setUrlArray($url = '') {
        //解析url路径
        $url_array = parse_url($url);
        $this->urlArray['scheme'] = isset($url_array['scheme']) ? $url_array['scheme'] : null; //http协议（http|https）
        $this->urlArray['host'] = isset($url_array['host']) ? $url_array['host'] : null; //域名
        $this->urlArray['port'] = isset($url_array['port']) ? $url_array['port'] : ''; //端口号
        $this->urlArray['query'] = isset($url_array['query']) ? $url_array['query'] : null; //查询参数
        $this->urlArray['urlPath'] = isset($url_array['path']) ? $url_array['path'] : null; //url路径地址
        return $this->urlArray;
    }

    /**
     * - Example : http://localhost/index.php/UrlThumber/thumb/zip/archive/zipimage,w_100.jpg?query=123
     * - configKey : thumb
     * 通过 url 路径地址获取 configKey 因为一个UrlThumber 可以对应多组配置文件，这里用来区分当前正在使用哪一组配置。
     * @return string
     */
    public function getUrlKey()
    {
        $urlImagePath = $this->getUrlImagePath(); //获取图片路径，example:'/thumb/d/demo,c_100,h_100,w_100.jpg'
        $urlImagePathArray = explode('/', ltrim($urlImagePath, '/'));
        //路径中必须包含三层结构  http://localhost/index.php/图片目录/分组目录/*,w_100.jpg，至少要配置分组目录，否则返回空
        if (count($urlImagePathArray) < 3) {
            return '';
        }
        return $this->urlKey = $urlImagePathArray[1];
    }

    /**
     * 获取图片路径，初始化的时候会被 getUrlKey 调用
     * - Example : http://localhost/index.php/UrlThumber/thumb/zip/archive/zipimage,w_100.jpg?query=123
     * - urlImagePath
     */
    public function getUrlImagePath()
    {
        if ($this->urlImagePath) {
            return $this->urlImagePath;
        }

        //判断 url 路径是否存在
        if (!$this->urlArray['urlPath']) {
            return '';
        }
        //获取 url 中的脚本路径
        $urlScriptName = $this->getUrlScriptName();
        if ($urlScriptName) {
            //判断 重写是否开启
            $urlRewriteEnabled = $this->getUrlRewriteEnabled();
            if ($urlRewriteEnabled) {
                return $this->urlImagePath = str_replace($this->getUrlRewritePath(), '', $this->urlArray['urlPath']);
            } else {
                return $this->urlImagePath = str_replace($urlScriptName, '', $this->urlArray['urlPath']);
            }
        } else {
            return $this->urlImagePath = $this->urlArray['urlPath'];
        }
    }

    /**
     * 获取包含当前脚本的路径
     * - Example : http://localhost/index.php/UrlThumber/thumb/zip/archive/zipimage,w_100.jpg?query=123
     * - SCRIPT_NAME : /index.php
     * @return string
     */
    public function getUrlScriptName()
    {
        if ($this->urlScriptName) {
            return $this->urlScriptName;
        }

        //获取当前脚本路径
        if (isset($_SERVER['SCRIPT_NAME']) && $_SERVER['SCRIPT_NAME']) {
            $scriptName = $_SERVER['SCRIPT_NAME'];
            //判断脚本文件后缀是否是 php
            if (false === strpos($scriptName, '.php')) {
                return $this->urlScriptName = '';
            }

            //Nginx 可能会把 SCRIPT_NAME 设置为完整路径名
            if (($scriptNameEnd = substr($scriptName, -4)) && $scriptNameEnd === '.php') {
                $scriptNameArray = explode('/', $scriptName);
                array_shift($scriptNameArray); //移除第一个空元素
                array_pop($scriptNameArray); //删除数组最后一个元素
                $scriptNameFront = implode('/', $scriptNameArray);
                //在url中找不到服务器script_name，请删除script_name，因为script_name可能不正确
                if ($scriptNameFront && $this->urlString && false === strpos($this->urlString, $scriptNameFront)) {
                    return $this->urlScriptName = '';
                }
                return $this->urlScriptName = $scriptName;
            } else {
                $scriptNameArray = explode('/', $scriptName);
                $scriptName = array();
                foreach ($scriptNameArray as $scriptNamePart) {
                    $scriptName[] = $scriptNamePart;
                    //只获取到 .php 结束
                    if (false !== strpos($scriptNamePart, '.php')) {
                        break;
                    }
                }
                return $this->urlScriptName = implode('/', $scriptName);
            }
        }
        return '';
    }

    /**
     * 获取 url 重写是否开启
     * @return bool
     */
    public function getUrlRewriteEnabled()
    {
        if ($this->urlRewriteEnabled !== null) {
            return $this->urlRewriteEnabled;
        }

        //判断url路径中是否存在 .php 来确定是否开启了重写
        if (false === strpos($this->urlArray['urlPath'], '.php')) {
            return $this->urlRewriteEnabled = true;
        }
        return $this->urlRewriteEnabled = false;
    }

    /**
     * 获取重写路径
     * @return string
     */
    public function getUrlRewritePath()
    {
        //获取脚本路径名称
        $scriptName = $this->getUrlScriptName();
        if (!$scriptName) {
            return $this->urlRewritePath = '';
        }
        //判断重写是否开启
        if (false === $this->getUrlRewriteEnabled()) {
            return $this->urlRewritePath = $scriptName;
        }

        $rewitePathArray = explode('/', $scriptName);
        //移除脚本路径中的 index.php
        array_pop($rewitePathArray);
        return $this->urlRewritePath = implode('/', $rewitePathArray);
    }

    /**
     * 获取url前缀
     * @return mixed|string
     */
    public function getUrlPrefix()
    {
        $urlImagePath = $this->getUrlImagePath(); //获取图片路径
        $urlImagePathArray = explode('/', ltrim($urlImagePath, '/'));
        if (count($urlImagePathArray) < 2) {
            return '';
        }
        return $this->urlPrefix = array_shift($urlImagePathArray);
    }

    public function getUrlImageName()
    {
        if ($this->urlImageName) {
            return $this->urlImageName;
        }

        $urlImagePath = $this->getUrlImagePath(); //获取图片路径，example:'/thumb/d/demo,c_100,h_100,w_100.jpg'
        if (!$urlImagePath) {
            return $this->urlImageName = '';
        }
        //去掉首尾反斜杠，解析得到：array(0 => "thumb", 1 => "d", 2 => "demo,c_100,h_100,w_100.jpg")
        $urlImagePathArray = explode('/', ltrim($urlImagePath, '/'));
        //使用 array_pop 获取数组最后一个元素得到文件名
        $urlImageName = array_pop($urlImagePathArray);

        //文件必须是包含后缀的合法文件名
        $urlImageNameArray = explode('.', $urlImageName);
        $urlImageNameCount = count($urlImageNameArray);
        if ($urlImageNameCount < 2 || !$urlImageNameArray[$urlImageNameCount - 1]) {
            //不存在文件后缀名时，返回空值
            return $this->urlImageName = '';
        }
        return $this->urlImageName = $urlImageName;
    }

    /**
     * 设置图片名称
     * @param $imageName
     * @return $this
     */
    public function setUrlImageName($imageName)
    {
        $this->urlImageName = $imageName;
        return $this;
    }

    /**
     * 获取图片路径
     * @return string
     */
    public function getImagePath()
    {
        $urlImagePath = $this->getUrlImagePath();
        $urlImagePathArray = explode('/', ltrim($urlImagePath, '/'));
        if (count($urlImagePathArray) < 4) {
            return '';
        }
        //remove url prefix
        array_shift($urlImagePathArray);
        //remove url key
        array_shift($urlImagePathArray);
        //remove imagename
        array_pop($urlImagePathArray);
        return $this->imagePath = '/' . implode('/', $urlImagePathArray);

    }

    /**
     * 获取图片名
     * @return string
     */
    public function getImageName()
    {
        $urlImageName = $this->getUrlImageName();
        if (!$urlImageName) {
            return $this->imageName = '';
        }

        //拆分图片名和后缀名
        $fileNameArray = explode('.', $urlImageName);
        if (!$fileNameArray || count($fileNameArray) < 2) {
            //没有获取到图片名和后缀名返回空值
            return $this->imageName = '';
        }

        //获取后缀名
        $fileExt = array_pop($fileNameArray); // 弹出并返回 array 数组的最后一个单元
        //获取图片名
        $fileNameMain = implode('.', $fileNameArray);
        /**
         * 从图片名中解析处理参数
         * Example ：demo,h_100,w_200
         * result ：array(3) { [0]=> string(4) "demo" [1]=> string(5) "h_100" [2]=> string(5) "w_200" }
         */
        $fileNameArray = explode(',', $fileNameMain);
        if (!$fileExt || !$fileNameArray || !$fileNameArray[0]) {
            //没有获取到文件名或图片名中解析的参数为空
            return $this->imageName = '';
        }

        // url with class
        if (is_object($this->config) && isset($this->config->class_separator) && strpos(
                $fileExt,
                $this->config->class_separator
            ) !== false
        ) {
            $fileExt = substr($fileExt, 0, strpos($fileExt, $this->config->class_separator));
        }
        $fileNameMain = array_shift($fileNameArray);

        return $this->imageName = $fileNameMain . '.' . $fileExt;
    }

    /**
     * 生成 url 链接
     * Thumber.php 中调用
     * @return string
     */
    public function toString()
    {
        //如果域名不存在返回空值
        if (!$this->urlArray['host']) {
            return '';
        }
        //获取端口
        $port = $this->urlArray['port'] ? ':' . $this->urlArray['port'] : '';
        //获取重定向路径
        $path = $this->getUrlRewritePath();
        if ($prefix = $this->getUrlPrefix()) {
            $path .= "/$prefix";
        }
        //获取分组 key
        if ($urlKey = $this->getUrlKey()) {
            $path .= "/$urlKey";
        }
        //获取图片路径
        if ($imagePath = $this->getImagePath()) {
            $path .= $imagePath;
        }

        if ($imageName = $this->getUrlImageName()) {
            $path .= '/' . $imageName;
        }

        $url = $this->urlArray['scheme'] . '://' . $this->urlArray['host'] . $port . $path;
        $url .= $this->urlArray['query'] ? '?' . $this->urlArray['query'] : '';
        return $url;
    }
}
