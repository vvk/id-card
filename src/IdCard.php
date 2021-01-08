<?php

/**
 * 身份证号码归属地查询
 * 身份证号码生成
 * 15位身份证号转18位身份证号
 */

namespace Vvk\IdCard;

class IdCard extends Base
{
    protected static $locationFile;
    protected static $locationData = [];

    protected static $errorMsg;
    protected static $errorCode;

    const SUCCESS = 0;
    const LOCATION_FILE_NOT_EXISTS = 1;
    const INVALID_ID_CARD = 2;
    const INVALID_LOCATION = 3;
    const INVALID_DATE = 4;

    /**
     * @param string $locationFile
     */
    public static function setLocationFile($locationFile)
    {
        self::$locationFile = $locationFile ?: dirname(__FILE__).'/location.json';
    }

    /**
     * @param $locationFile
     * @return bool
     */
    protected static function init($locationFile)
    {
        self::setError(self::SUCCESS, '');
        self::setLocationFile($locationFile);

        if (empty(self::$locationFile)) {
            self::setError(self::LOCATION_FILE_NOT_EXISTS, 'location file is not exits.');
            return false;
        }

        self::$locationData = self::getLocationData();
        if (empty(self::$locationData)) {
            self::setError(self::LOCATION_FILE_NOT_EXISTS, 'location file is empty or invalid.');
            return false;
        }

        return true;
    }

    /**
     * @param $idCard
     * @param string $locationFile
     * @return array
     */
    public static function parse($idCard, $locationFile = '')
    {
        if (!self::init($locationFile)) {
            return [];
        }

        //15位身份证转成18位
        if (mb_strlen($idCard) == 15) {
            $idCard = self::shortToLongIdCard($idCard);
        }

        //验证身份证号码格式
        if (!self::checkIdCard($idCard)) {
            self::setError(self::INVALID_ID_CARD, 'invalid id card.');
            return [];
        }

        //归属地
        $location = mb_substr($idCard, 0, 6);
        $data = self::parseLocation($location);
        if (empty($data)) {
            return [];
        }

        //日期
        $date = mb_substr($idCard, 6, 8);
        if (!self::validDate($date)) {
            self::setError(self::INVALID_DATE, 'invalid id card date.');
            return [];
        }

        $sexChar = mb_substr($idCard, 16, 1);
        if (strtolower($sexChar) == 'x') {
            $sex = '男';
        } else {
            $sex = $sexChar % 2 == 1 ? '男' : '女';
        }

        $data['date'] = mb_substr($date, 0, 4) . '-' . mb_substr($date, 4, 2) . '-' .mb_substr($date, 6, 2);
        $data['sex'] = $sex;
        $data['constellation'] = self::getConstellation($date);
        return $data;
    }

    /**
     * 解析归属地
     * 1-2 省
     * 3-4 城市
     * 5-6 区/县(部分身份证号不存在)
     * @param $location
     * @return array
     */
    protected static function parseLocation($location)
    {
        $provinceCode = mb_substr($location, 0, 2);
        $cityCode = mb_substr($location, 0, 4);

        $province = self::$locationData[$provinceCode] ?? [];
        if (empty($province)) {
            self::setError(self::INVALID_LOCATION, 'invalid province.');
            return [];
        }

        $city = $province['list'][$cityCode] ?? [];
        if (empty($city)) {
            self::setError(self::INVALID_LOCATION, 'invalid city.');
            return [];
        }

        //部分身份证没有区/县
        if (!in_array($cityCode, self::$specialCity)) {
            $district = $city['list'][$location] ?? [];
            if (empty($district)) {
                self::setError(self::INVALID_LOCATION, 'invalid district.');
                return [];
            }
        } else {
            $district['name'] = '';
        }

        $data = [
            'province' => $province['name'],
            'city' => $city['name'],
            'district' => $district['name'],
        ];
        $data['area'] = implode(' ', array_filter($data));
        return $data;
    }

    /**
     * @param $idCard
     * @return bool
     */
    protected static function checkIdCard($idCard)
    {
        if (!preg_match('/^\d{17}[\dx]$/i', $idCard)) {
            return false;
        }

        $lastChar = self::getIdCardLastChar($idCard);
        if (empty($lastChar) || strtoupper($lastChar) != strtoupper(substr($idCard, 17, 1))) {
            return false;
        }

        return true;
    }

    /**
     * 获取身份证号码最后一位
     * @param $idCard
     * @return string
     */
    protected static function getIdCardLastChar($idCard)
    {
        $coefficient = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];      //前17位对应的系数
        $remainderArr = ['1', '0', 'X', '9', '8', '7', '6' ,'5' ,'4', '3', '2'];   //余数对应的最后一位
        $sum = 0;    //前17位乘以系数后的和
        for ($i = 0; $i < 17; $i++) {
            $tmp = substr($idCard, $i, 1);
            $sum += intval($tmp) * $coefficient[$i];
        }

        $remainder = $sum % 11;
        return $remainderArr[$remainder] ?? '';
    }

    /**
     * 身份证号码15位转18位
     * @param $idCard
     * @return string
     */
    protected static function shortToLongIdCard($idCard)
    {
        $idCard = mb_substr($idCard, 0, 6) . '19' . mb_substr($idCard, 6);
        $idCard .= self::getIdCardLastChar($idCard);
        return $idCard;
    }
    /**
     * 验证日期是否合法
     * @param $date
     * @return bool
     */
    protected static function validDate($date)
    {
        if (mb_strlen($date) != 8) {
            return false;
        }
        $year = mb_substr($date, 0, 4);
        $month = mb_substr($date, 4, 2);
        $day = mb_substr($date, 6, 2);
        return checkdate($month, $day, $year);
    }

    /**
     * @return array|mixed
     */
    protected static function getLocationData()
    {
        $data = file_get_contents(self::$locationFile);
        return empty($data) ? [] : json_decode($data, true);
    }

    /**
     * 身份证号生成
     * @param static|array $location
     * @param string $date
     * @param int $sex
     * @param string $locationFile
     * @return string
     */
    public static function generate($location, $date, $sex = 0, $locationFile = '')
    {
        self::init($locationFile);
        if (!is_array($location)) {
            $location = [
                'province' => mb_substr($location, 0, 2),
                'city' => mb_substr($location, 0, 4),
                'district' => $location,
            ];
        }

        if (!self::checkLocation($location)) {
            self::setError(self::INVALID_LOCATION, 'invalid location.');
            return '';
        }

        if (!self::validDate($date)) {
            self::setError(self::INVALID_DATE, 'invalid date.');
            return '';
        }

        if (!in_array($sex, [1, 2])) {
            $sex = mt_rand(1, 2);
        }

        $sexStr = 2 * mt_rand(0, 4);
        if ($sex == 1) {
            $sexStr += 1;
        }

        $idCard = $location['district'] . $date . self::getRandNum(2) . $sexStr;
        $idCard .= self::getIdCardLastChar($idCard);
        return $idCard;
    }

    /**
     * 15位转18位身份证号
     * @param string $idCard
     * @param string $locationFile
     * @return string
     */
    public static function getLongIdCard($idCard, $locationFile = '')
    {
        self::init($locationFile);
        if (mb_strlen($idCard) != 15) {
            self::setError(self::INVALID_ID_CARD, 'invalid id card.');
            return '';
        }

        $location = mb_substr($idCard, 0, 6);
        if (!self::parseLocation($location)) {
            self::setError(self::INVALID_LOCATION, 'invalid location');
            return '';
        }

        $date = '19' . mb_substr($idCard, 6, 6);
        if (!self::validDate($date)) {
            self::setError(self::INVALID_DATE, 'invalid date.');
            return '';
        }

        $idCard = mb_substr($idCard, 0, 6) . '19' . mb_substr($idCard, 6);
        $idCard .= self::getIdCardLastChar($idCard);
        return $idCard;
    }

    /**
     * 检测location是否合法
     * @param $location
     * @return bool
     */
    protected static function checkLocation($location)
    {
        $locationData = self::$locationData;
        $province = $location['province'] ?? '';
        if (empty($province) || !isset($locationData[$province])) {
            return false;
        }

        $provinceInfo = $locationData[$province];
        $city = $location['city'] ?? '';
        if (empty($city) || !isset($provinceInfo['list'][$city])) {
            return false;
        }

        $district = $location['district'] ?? '';
        if (in_array($city, self::$specialCity)) {
            return !empty($district) && mb_substr($district, -2, 2) == '00';
        }

        $cityInfo = $provinceInfo['list'][$city];
        if (empty($district) || !isset($cityInfo['list'][$district])) {
            return false;
        }

        return true;
    }

    /**
     *
     * @param string $locationFile
     * @return array|mixed
     */
    public static function getLocation($locationFile = '')
    {
        self::init($locationFile);
        return self::$locationData;
    }

    protected static function setError($errorCode, $errorMsg)
    {
        self::$errorCode = $errorCode;
        self::$errorMsg = $errorMsg;
    }

    public static function getError()
    {
        return ['code' => self::$errorCode, 'msg' => self::$errorMsg];
    }

    /**
     * 星座
     * @param $date
     * @return string
     */
    protected static function getConstellation($date)
    {
        $month = mb_substr($date, 4, 2);
        $day = mb_substr($date, 6, 2);
        $constellations = [
            ['20' => '水瓶座'],
            ['19' => '双鱼座'],
            ['21' => '白羊座'],
            ['20' => '金牛座'],
            ['21' => '双子座'],
            ['22' => '巨蟹座'],
            ['23' => '狮子座'],
            ['23' => '处女座'],
            ['23' => '天秤座'],
            ['24' => '天蝎座'],
            ['22' => '射手座'],
            ['22' => '摩羯座']
        ];

        $arr = $constellations[(int)$month-1];
        $startDay = current(array_keys($arr));
        $name = current($arr);

        if ($day < $startDay) {
            $month = $month - 2 < 0 ? 11 : $month - 2;
            $arr = $constellations[$month];
            $name = current($arr);
        }
        return $name;
    }

    /**
     * 生成随机数字
     * @param $length
     * @return string
     */
    protected static function getRandNum($length)
    {
        $num = '';
        for ($i = 0; $i < $length; $i++) {
            $num .= mt_rand(0, 9);
        }
        return $num;
    }
}
