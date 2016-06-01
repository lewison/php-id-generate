# id-generate-php
A PHP library for generating unique 64 bit ID, just like the snowflake which design by twitter. The ID contains timestamp, worker,  sequence and version
Compare to snowflake, this library is not a network ID generate. It ensure to generate a uniq Id in one machine but maybe not in cluster network;


# Usage
```
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

$params = array(
'work_id' => $workId,
);
$idGenerate = IdGenerate::getInstance($params);
$id     = $idGenerate->generatorNextId();
return $id;

```




