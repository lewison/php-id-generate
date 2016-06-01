<?php
/**
 * ID 生成策略
 * 毫秒级时间41位+机器ID10位+毫秒内序列10位+版本号2位
 * 0         41     51         62        64
 * +-----------+-------+-----------+--------+
 * |timestamp  |worker |sequence   |version |
 * +-----------+-------+-----------+--------+
 *  前41bits是以微秒为单位的timestamp。
 *  接着10bits是事先配置好的机器worker ID。不同worker ID负责生成不同业务场景的id，例如用户、宝宝、主题id等等
 *  接着11bits是累加计数器sequence id。
 *  最后2bits是版本version id，初始版本为1
 *  worker id(10bits)标明最多只能有1024台机器同时产生ID，sequence number(10bits)也标明1台机器1ms中最多产生1024个ID，
 */

namespace \Lib\Id;

class IdGenerate {
    const DEBUG = 1;

    //worker
    const WORKER_ID_MAX = 1023;     //work最大数
    const WORKER_ID_MIN = 0;
    const WORKER_ID_BITS = 4;
    const WORKER_ID_DEFAULT = 1;

    const SEQUENCE_BITS = 11;
    const SEQUENCE_DEFAULT = 1;
    const SEQUENCE_MASK = 2047;     //队列最大数

    const OFFSET_LEFT_TIMESTAMP = 23;
    const OFFSET_LEFT_WORKER = 13;
    const OFFSET_LEFT_SEQUENCE = 2;

    const VERSION_DEFAULT = 1;

    private static $versionNum = 1; //版本号
    private static $workerId;       //机器id，可以通过随机整数对1024取余得到
    private static $sequence = 0;   //队列id
    private static $basicTimestamp = 1420041600000; //2015年01月01日0点毫秒时间戳，作为系统基础时间戳
    private static $lastTimestamp = -1;

    private static $_instance = array();

    /**
     * 构造函数，可以根据不同的业务类型分配不同段的id生产机器
     *
     * @param $params
     */
    private function __construct($params){
        $workId = isset($params['work_id']) ? $params['work_id'] : self::WORKER_ID_DEFAULT;
        $workId = $workId % self::WORKER_ID_MAX;
        self::$workerId = $workId;

        self::$versionNum = isset($params['version_num']) ? $params['version_num'] : self::VERSION_DEFAULT;
        self::$sequence = isset($params['sequence']) ? $params['sequence'] : self::SEQUENCE_DEFAULT;
    }

    private function __clone() {
        die("Cannot clone the single class " . __CLASS__ . E_USER_ERROR);
    }

    /**
     * 单例实现，根据work_id来实现单例数组
     * @author: Lewison(lewisonchen@gmail.com)
     * @version: 1.0
     * @param $params
     * @return IdPool|null
     */
    public static function getInstance($params) {
        if (isset($params['work_id'])) {
            $params['work_id'] = self::WORKER_ID_DEFAULT;
        }

        if (empty(self::$_instance[$params['work_id']])) {
            self::$_instance[$params['work_id']] = new IdPool($params);
        }

        return self::$_instance[$params['work_id']];
    }

    /**
     * 获取毫秒时间戳
     * @author: Lewison(lewisonchen@gmail.com)
     * @version: 1.0
     * @return string
     */
    private function _getMillisecond(){
        //获得当前时间戳
        $time = explode(' ', microtime());
        $time2= substr($time[0], 2, 3);
        return  $time[1].$time2;
    }

    /**
     * 获取下一个毫秒时间戳
     * @author: Lewison(lewisonchen@gmail.com)
     * @version: 1.0
     * @param $lastTimestamp
     * @return string
     */
    public function toNextMillis($lastTimestamp) {
        $timestamp = $this->_getMillisecond();
        while ($timestamp <= $lastTimestamp) {
            $timestamp = $this->_getMillisecond();
        }

        return $timestamp;
    }

    /**
     * 获取消息池中的下一个id
     * @author: Lewison(lewisonchen@gmail.com)
     * @version: 1.0
     * @return bool|int
     */
    public function  generatorNextId()
    {
        $timestamp=$this->_getMillisecond();
        if(self::$lastTimestamp == $timestamp) {
            //并发请求的时候如果timestamp一致，则列入不同的队列中
            self::$sequence = (self::$sequence + 1) & self::SEQUENCE_MASK;
            if (self::$sequence == 0) {
                //已经超出最大队列2047
                $timestamp = $this->toNextMillis(self::$lastTimestamp);
            }
        } else {
            //脚本第一次请求本方法，被放置入默认队列中
            self::$sequence  = self::SEQUENCE_DEFAULT;
        }

        if ($timestamp < self::$lastTimestamp) {
            return false;
        }

        self::$lastTimestamp = $timestamp;
        $intervalTimestamp = sprintf('%.0f', $timestamp) - sprintf('%.0f', self::$basicTimestamp);
        $nextId = ($intervalTimestamp << self::OFFSET_LEFT_TIMESTAMP )
            | ( self::$workerId << self::OFFSET_LEFT_WORKER )
            | (self::$sequence << self::OFFSET_LEFT_SEQUENCE)
            | self::$versionNum;
        return $nextId;
    }

}
