<?php

/**
 * update location data
 */

namespace Vvk\IdCard;

use GuzzleHttp\Client;

class UpdateLocation extends Base
{
    private static $locationUrl = 'http://www.stats.gov.cn/tjsj/tjbz/tjyqhdmhcxhfdm/2019/index.html';
    private static $locationFile = './location.json';
    private static $client = '';
    private static $logFile = '';
    private static $filePath = '';
    private static $dataFilePath = '';

    protected static function init($locationFile = '')
    {
        self::$filePath = dirname(__FILE__);
        self::$logFile = self::$filePath.'/log/'.date('Ymd').'.log';
        self::$locationFile = $locationFile ?: self::$filePath.'/'.self::$locationFile;
        self::$dataFilePath = self::$filePath.'/file';

        $locationFileDir = trim(dirname(self::$locationFile), '.');
        if (!is_writable($locationFileDir)) {
            die('dir '.$locationFileDir. ' can not be written.'.PHP_EOL);
        }
    }

    public static function run($locationFile = '')
    {
        $data = self::getProvinceList();
        foreach ($data as $k => $item) {
            echo $item['name'].' start...'.PHP_EOL;
            $cityList = self::getCityList($item);
            foreach ($cityList as $cityKey => $cityItem) {
                if (in_array($cityItem['code'], self::$specialCity)) {
                    $cityList[$cityKey] = [
                        'code' => $cityItem['code'],
                        'name' => $cityItem['name'],
                        'list' => [],
                    ];
                    continue;
                }
                echo $item['name']."\t".$cityItem['name'].' start...'.PHP_EOL;
                $districtList = self::getDistrict($cityItem);
                $districtList = array_map(function ($item) {
                    return ['name' => $item['name'], 'code' => $item['code']];
                }, $districtList);
                $districtList = array_column($districtList, null, 'code');

                $cityNmae = $cityItem['name'] == '市辖区' ? $item['name'] : $cityItem['name'];
                $cityList[$cityKey] = [
                    'name' => $cityNmae,
                    'code' => $cityItem['code'],
                    'list' => array_column($districtList, null, 'code'),
                ];
                usleep(500);
            }
            $cityList = array_column($cityList, null, 'code');
            $data[$k] = [
                'name' => $item['name'],
                'code' => $item['code'],
                'list' => array_column($cityList, null, 'code'),
            ];

            echo $item['name'].' end...'.PHP_EOL;
            usleep(200);
        }

        $data = array_column($data, null, 'code');
        file_put_contents(self::$locationFile, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * get province list
     * @return array
     */
    protected static function getProvinceList()
    {
        $xpath = '//tr[@class="provincetr"]/td/a';
        $data = self::getXpathData(self::$locationUrl, $xpath);
        if (count($data) == 0) {
            $data = self::getXpathData(self::$locationUrl, $xpath);
        }
        return $data;
    }

    /**
     * get city list
     * @param $item
     * @return array
     */
    protected static function getCityList($item)
    {
        if (!isset($item['sub_url'])) {
            return [];
        }

        $xpath = '//tr[@class="citytr"]/td/a';
        $data = self::getXpathData($item['sub_url'], $xpath, true);
        if (count($data) == 0) {
            $data = self::getXpathData($item['sub_url'], $xpath, true);
        }
        return $data;
    }

    /**
     * get district list
     * @param $item
     * @return array|mixed
     */
    protected static function getDistrict($item)
    {
        try {
            if (!isset($item['sub_url'])) {
                return [];
            }

            $xpath = '//tr[@class="countytr"]/td/a';
            $data = self::getXpathData($item['sub_url'], $xpath, true);
            if (count($data) == 0) {
                $data = self::getXpathData($item['sub_url'], $xpath, true);
            }
            return $data;
        } catch (\Exception $e) {
            echo $e->getFile().PHP_EOL;
            echo $e->getMessage().PHP_EOL;
        }
        return [];
    }

    protected static function getXpathData($url, $path, $fullCode = false)
    {
        $fileName = self::$dataFilePath . '/' . md5($url);
        $result = file_exists($fileName) ? file_get_contents($fileName) : '';
        if (!empty($result)) {
            return json_decode($result, true);
        }

        $option = [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
            'Cookie' => '_trs_uv=kdi6cv7e_6_i2ci; SF_cookie_1=37059734; wzws_cid=63b1582089e0309142a1a912b7391b0192cbd231353d25b2e8e827fca0a201695efb61f9efaf9df0e37c7490e15fc6ff6f52565e05cb85820dea24879813abf114d7d43dacd9047eb72fbf7f0985ff44',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.121 Safari/537.36',
        ];

        for ($i = 0; $i < 5; $i++) {
            $response = self::getClient()->get($url, $option);
            $html = $response->getBody()->getContents();
            if (!empty($html)) {
                break;
            }
            $log  = $url. ' retry '.($i+1). ' times'.PHP_EOL;
            echo $log;
            sleep(1);
        }

        if (empty($html)) {
            $log = 'get  '.$url.' fails'.PHP_EOL;
            echo $log;
            error_log($log, 3, self::$logFile);
            return [];
        }

        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        $dom->normalize();

        $xpath = new \DOMXPath($dom);
        $data = $xpath->query($path);

        $result = [];
        $k = 0;
        $baseUrl = dirname($url).'/';
        for ($i = 0; $i < $data->length; $i++) {
            $name = $data->item($i)->textContent;
            foreach ($data->item($i)->attributes as $attr) {
                $arr = explode('/', $attr->textContent);
                $id = intval(end($arr));
                if ($attr->name == 'href') {
                    $result[$k] = [
                        'name' => $name,
                        'code' => $id,
                        'sub_url' => $baseUrl.$attr->textContent
                    ];
                    break;
                }
            }

            if ($fullCode) {
                $result[$k]['name'] = $data->item($i+1)->textContent;
                $result[$k]['full_code'] = $data->item($i)->textContent;
                $i++;
            }

            $k++;
        }

        if (!empty($result)) {
            file_put_contents($fileName, json_encode($result, JSON_UNESCAPED_UNICODE));
        }

        return $result;
    }

    /**
     * get http request client
     * @return GuzzleHttp\Client
     */
    private static function getClient()
    {
        if (empty(self::$client)) {
            self::$client = new Client();
        }

        return self::$client;
    }
}
