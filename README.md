# 身份证号码归属地查询、身份证号码生成、15位身份证号转18位
**注：** 本代码仅供学习交流使用，请勿用于商业或其他违法行为，否则后果自负。

## 说明

* 数据来源为 [国家统计局](http://www.stats.gov.cn/tjsj/tjbz/tjyqhdmhcxhfdm/2019/index.html)，最新更新于2019年10月31日。
* 仅支持中国大陆的身份证号



## 功能 ##

* 身份证号归属地查询，包括省、城市、区/县
* 根据省、市、区/县及出生日期生成身份证号
* 15位短身份证号码生成18位身份证证号码

## 安装 ##

使用 composer 安装：

```shell
composer require vvk/id-card	
```

## 使用

### 身份证号码归属地查询 

```php
include "vendor/autoload.php";
use Vvk\IdCard\IdCard;

$idCard = '110101192009309116';
$location = IdCard::parse($idCard);//支持15位、18位身份证号
print_r($location);
/*
结果：
Array
(
    [province] => 北京市
    [city] => 北京市
    [district] => 东城区
    [area] => 北京市 北京市 东城区
    [date] => 1920-09-30
    [sex] => 男
    [constellation] => 天秤座
)
*/
```

### 身份证号码生成

```php
include "vendor/autoload.php";
use Vvk\IdCard\IdCard;

//通过 IdCard::getLocation() 获取到的对应的区、县code
//广东东莞市、广东中山市、海南儋州三个城市下面没有区、县，只在在对应的城市后面补助 00 组成6位字符串即可
$location = '110101';

// 日期格式为 YYYmmdd
$date = '20201003';
$result = IdCard::generate($location, $date, 1);
echo $result.PHP_EOL;
/**
结果：
110101202010035331
*/
```

### 15位身份证号码转18位

```php
include "vendor/autoload.php";
use Vvk\IdCard\IdCard;

$idCard = '320506720102256';
$result = IdCard::getLongIdCard($idCard);
echo $result.PHP_EOL;
/*
结果：
320506197201022567
*/
```
### 数据更新

身份证号前6位为归属地，可以从 [国家统计局](http://www.stats.gov.cn/tjsj/tjbz/tjyqhdmhcxhfdm/2019/index.html) 获取，由于数据可能会变化（感觉机率比较小，毕竟身份证号不会变化 ），会造成身份证号识别不准确的情况，所以数据需要定时更新，可以通过下面的方式更新：

```php
include "vendor/autoload.php";
use Vvk\IdCard\UpdateLocation;

UpdateLocation::run();
```


### 说明

* 如果结果返回空，可以使用方法 `IdCard::getError()` 获取失败原因。
* 广东东莞市、广东中山市、海南儋州市三个城市下面不再区分区、县，即前6位整个城市相同。

