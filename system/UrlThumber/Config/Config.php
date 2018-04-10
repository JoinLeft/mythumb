<?php
/**
 * Created by PhpStorm.
 * User: jiang
 * Date: 2017/12/11
 * Time: 14:43
 */

namespace UrlThumber\Config;

/**
 * Class Config
 * 需要实现 对象元素统计接口、迭代器接口、数组式访问接口
 * Countable 统计一个对象的元素个数
 * Iterator 可在内部迭代自己的外部迭代器或类的接口。
 * ArrayAccess  提供像访问数组一样访问对象的能力的接口。
 * ${DS}
 * @package UrlThumber\Config
 */
class Config implements \Countable, \Iterator, \ArrayAccess
{
    /**
     * 是否允许修改配置文件，默认不允许修改
     * @var bool
     */
    protected $allow_modify;

    /**
     * 配置项中元素的个数
     * @var integer
     */
    protected $count = 0;

    /**
     * 配置项中的数据
     * @var array
     */
    protected $data = array();

    /**
     * 在迭代期间取消设置值时使用，以确保我们不跳过下一个元素。
     * @var
     */
    protected $skipNextIteration = true;

    /**
     * Config 构造函数
     * @param array $options  配置项数组
     * @param bool $allow_modify    是否允许修改配置项
     */
    public function __construct(array $options, $allow_modify = false)
    {
        //初始化配置文件读写权限
        $this->allow_modify = (bool) $allow_modify;

        //因为配置项中有多个分组配置，这里需要进行循环处理，并分别实例化存入 $this->data 数组
        foreach ($options as $key => $value) {
            if ( is_array($value) ) {
                //后期静态绑定
                $this->data[$key] = new static($value, $this->allow_modify);
            } else {
                $this->data[$key] = $value;
            }
            $this->count++;
        }
    }

    /**
     * 根据名称获取配置项的值
     * @param $name 配置项名称
     * @param null $value   配置项值，默认 null
     * @return mixed|null
     */
    public function get($name, $value = null)
    {
        if ( array_key_exists($name, $this->data) ) {
            return $this->data[$name];
        }
        return $value;
    }

    /**
     * 实现 __get() 魔术方法，使 $ojb->name 这样直接获取配置项的值可以使用
     * @param $name 配置项名称
     * @return mixed|null
     */
    public function __get($name)
    {
        // TODO: Implement __get() method.
        //通过get方法获取配置项的值
        return $this->get($name);
    }

    /**
     * 实现 __set() 魔术方法，使 $obj->name = $value 可以增加到配置项中
     * @param $name 配置项名称
     * @param $value    配置项值
     */
    public function __set($name, $value)
    {
        // TODO: Implement __set() method.
        //判断是否允许修改配置项
        if ( $this->allow_modify ) {
            if ( is_array($value) ) {
                $value = new static($value, true);
            }

            if (null == $name) {
                //没有设置配置项名称时，使用数字索引
                $this->data[] = $value;
            } else {
                $this->data[$name] = $value;
            }
            //配置项 +1
            $this->count++;
        } else {
            //抛出异常
            throw new Exception\RuntimeException('配置文件只读，无法添加配置项');
        }
    }

    /**
     * 实现 __clone，以确保 cofnig 的对象实例唯一性
     */
    public function __clone()
    {
        // TODO: Implement __clone() method.
        $array = array();

        foreach ($this->data as $key => $value) {
            //检查 配置项的值是否是 配置类的实例
            if ( $value instanceof self ) {
                $array[$key] = clone $value;
            } else {
                $array[$key] = $value;
            }
        }
        $this->data = $array;
    }

    /**
     * 返回 $this->data 对象的数组结构
     * @return array
     */
    public function toArray()
    {
        $array = array();
        $data = $this->data;

        foreach ($data as $key => $value) {
            //检查 配置项的值是否是 配置类的实例
            if ( $value instanceof self ) {
                $array[$key] = $value->toArray();
            } else {
                $array[$key] = $value;
            }
        }
        return $array;
    }

    /**
     * 实现 __isset() 魔术方法，检查配置项是否存在
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        // TODO: Implement __isset() method.
        return isset( $this->data[$name] );
    }

    /**
     * 实现 __unset() 魔术方法，移除配置项
     * @param $name
     */
    public function __unset($name)
    {
        // TODO: Implement __unset() method.
        // 如果配置项允许修改，且配置项存在
        if ( $this->allow_modify && isset($this->data[$name])) {
            unset( $this->data[$name] );
            //配置项数量 -1
            $this->count--;
            $this->skipNextIteration = true;
        } else {
            //抛出参数不是预期类型的异常
            throw new Exception\InvalidArgumentException('配置只能读取');
        }
    }

    //下边是实现的 Countable 接口的方法
    // SPL是用于解决典型问题(standard problems)的一组接口与类的集合。

    /**
     * count() 实现自 Countable 接口
     * @seee Countable::count()
     */
    public function count()
    {
        // TODO: Implement count() method.
        return $this->count;
    }

    // 下边是实现的 迭代器接口 的方法

    /**
     * current() 实现自 Iterator 接口，返回当前元素
     * @see Iterator::current()
     * @return mixed
     */
    public function current()
    {
        // TODO: Implement current() method.
        $this->skipNextIteration = false;
        //返回数组中的当前单元，默认指向一个单元
        return current( $this->data );
    }

    /**
     * key() 实现自 Iterator 接口， 返回当前元素的key
     * @see Iterator::key()
     * @return mixed
     */
    public function key()
    {
        // TODO: Implement key() method.
        //当前内部指针位置返回元素键名
        return key($this->data);
    }

    /**
     * next() 实现自 Iterator 接口，将数组中的内部指针向前移动一位
     * @see \Iterator::key()
     * @return void
     */
    public function next()
    {
        // TODO: Implement next() method.
        if ($this->skipNextIteration) {
            $this->skipNextIteration = false;
            return;
        }
        //将数组中的内部指针向前移动一位
        next($this->data);
    }

    /**
     * rewind() 实现自 Iterator 接口，返回到迭代器的第一个元素
     * @see \Iterator::rewind()
     * @return void
     */
    public function rewind()
    {
        // TODO: Implement rewind() method.
        $this->skipNextIteration = false;
        //将数组的内部指针指向第一个单元
        reset($this->data);
    }

    /**
     * valid() 实现自 Iteartor 接口，检查当前位置是否有效
     * @see \Iterator::valid
     * @return bool
     */
    public function valid()
    {
        // TODO: Implement valid() method.
        return ($this->key() !== null);
    }


    //下边是实现的 数组式访问接口 的方法

    /**
     * offsetExists() 实现自 ArrayAccess 接口，检查一个偏移位置是否存在
     * @see \ArrayAccess::offsetExists
     * @return bool
     */
    public function offsetExists($offset)
    {
        // TODO: Implement offsetExists() method.
        return $this->__isset($offset);
    }

    /**
     * offsetGet() 实现自 ArrayAccess 接口，获取一个偏移位置的值
     * @see \ArrayAccess::offsetGet
     * @param mixed $offset
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        // TODO: Implement offsetGet() method.
        return $this->__get($offset);
    }

    /**
     * offsetSet() 实现自 ArrayAccess 接口，设置一个偏移位置的值
     * @see \ArrayAccess::offsetSet
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        // TODO: Implement offsetSet() method.
        $this->__set($offset, $value);
    }

    /**
     * offsetUnset() 实现自 ArrayAccess 接口，移除一个偏移位置的值
     * @see \ArrayAccess::offsetUnset
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        // TODO: Implement offsetUnset() method.
    }


    /**
     * 合并其他分组配置项到当前配置项
     * 对于重复键，将执行以下操作：
     * - 嵌套的配置将被递归合并。
     * - 使用数字key的项目将被追加。
     * - 使用相同字符串key的项目将被覆盖
     *
     * @param Config $merage
     * @return $this
     */
    public function merge(Config $merage)
    {
        //循环配置实例
        foreach ($merage as $key => $value) {

            //检查配置项key是否已存在
            if (array_key_exists($key, $this->data)) {
                if (is_int($key)) {
                    //数组 key 追加到数组中
                    $this->data[] = $value;
                } elseif ($value instanceof self && $this->data[$key] instanceof self) {
                    //查询是否是同一类配置项（instanceof config）,如果是进行递归处理
                    $this->data[$key]->merge($value);
                    die;
                } else {
                    if ($value instanceof self) {
                        $this->data[$key] = new static($value->toArray(), $this->allow_modify);
                    } else {
                        $this->data[$key] = $value;
                    }
                }
            } else {
                if ($value instanceof self) {
                    $this->data[$key] = new static($value->toArray(), $this->allow_modify);
                } else {
                    $this->data[$key] = $value;
                }
                $this->count++;
            }
        }

        return $this;
    }

    /**
     * 防止对此实例进行更多的修改
     * merge（）之后有用，用于将多个Config对象合并成一个对象，然后不应该再次修改。
     */
    public function setReadOnly()
    {
        $this->allow_modify = false;
        foreach ($this->data as $value) {
            if ($value instanceof self) {
                $value->setReadOnly();
            }
        }
    }

    /**
     * 判断配置项的可读性
     * @return bool
     */
    public function isReadOnly()
    {
        return !$this->allow_modify;
    }
}