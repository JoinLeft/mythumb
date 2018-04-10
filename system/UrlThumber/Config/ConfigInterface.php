<?php
/**
 * Created by PhpStorm.
 * User: jiang
 * Date: 2017/12/11
 * Time: 14:37
 */

namespace UrlThumber\Config;

/**
 * 配置文件接口类
 * Class ConfigInterface
 * ${DS}
 * @package UrlThumber\Config
 */
interface ConfigInterface
{
    /**
     * 设置全局配置项
     * @param $options
     * @return mixed
     */
    public function setOptions($options);

    /**
     * 获取全局配置项
     * @return mixed
     */
    public function getOptions();

    /**
     * 根据配置项的值设置配置项
     * @param $options  配置项名称
     * @param $value    配置项值
     * @return mixed
     */
    public function setOption($option, $value);

    /**
     * 根据配置项的名称获取配置项的值
     * @param $option   配置项名称
     * @return mixed
     */
    public function getOption($option);

    /**
     * 检查配置项的值是否存在
     * @param $option   配置项名称
     * @return mixed
     */
    public function hasOption($option);

    /**
     * 配置项转数组
     * @return mixed
     */
    public function toArray();
}