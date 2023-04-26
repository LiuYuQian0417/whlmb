<?php
declare(strict_types = 1);
namespace app\common\service;

use think\Exception;
use think\facade\Cache;
use think\facade\Config;


/**
 * 蜂鸟配送
 * Class BeeBird
 * @package app\common\service
 */
class BeeBird
{
    /**
     * 配置信息
     * @var array
     */
    private $config = [];
    /**
     * token令牌(全局接口调用凭证)
     * expire 24h
     * @var null
     */
    private $access_token = null;
    /**
     * 当前场景 联调 couplet_uri 正式 formal_uri
     * @var string
     */
    private $scene = 'couplet_uri';
    /**
     * access_token缓存有效时间
     * @var int
     */
    private $expire_time = 60 * 60 * 20;
    /**
     * 请求common
     * @var array
     */
    private $res_common = [];

    public function __construct()
    {
        $this->config = Config::get('beeBird.');
        if (empty($this->config)) throw new Exception('beeBird config is empty');
        $this->access_token = self::getAccessToken();
        $this->res_common = [
            'app_id' => $this->config['app_id'],
            'salt' => mt_rand(1000, 9999),
        ];
    }

    /**
     * token签名
     * @param $salt
     * @return string
     */
    protected function getTokenSign($salt)
    {
        // make signature
        $sign = sprintf('app_id=%s&salt=%d&secret_key=%s', $this->config['app_id'], $salt, $this->config['secret_key']);
        $sign = md5(urlencode($sign));
        return $sign;
    }

    /** 凭证接口 **/

    /**
     * 请求签名
     * @param $salt
     * @param $data array 请求data
     * @return string
     */
    protected function requestSign($salt, $data)
    {
        // make signature
        $data = urlencode(json_encode($data));
        $sign = sprintf('app_id=%s&access_token=%s&data=%s&salt=%s', $this->config['app_id'], $this->access_token, $data, $salt);
        return md5($sign);
    }

    /**
     * 获取令牌
     * @return mixed
     * @throws Exception
     */
    protected function getAccessToken()
    {
        try {
            $access_token = Cache::store('file')->get('beeBird_at', null);
            if (is_null($access_token)) {
                $this->res_common['signature'] = self::getTokenSign($this->res_common['salt']);
                $res = curl(1, $this->config[$this->scene] . 'get_access_token', $this->res_common);
                if (!$res || $res['code'] != 200)
                    throw new Exception($res['data']);
                Cache::store('file')->set('beeBird_at', $access_token = $res['data']['access_token'], $this->expire_time);
            }
            return $access_token;
        } catch (\Exception $e) {
            throw new Exception('蜂鸟access_token获取失败');
        }
    }

    /**
     * 远程接口请求
     * @param $type int 远程接口的方法
     * @param $data array 请求数据
     * @param $method int 请求方式 1get 2post
     * @return array 查询类请求返回查询数据
     * @throws Exception
     */
    public function request($type, $data = [], $method = 2)
    {
        try {
            $this->res_common['data'] = [
                // 门店编号(支持数字、字母的组合),32位
                'chain_store_code' => '001',
                // 门店名称(支持汉字、符号、字母的组合),32位
                'chain_store_name' => '测试门店',
                // 门店联系信息(手机号或座机或400)
                'contact_phone' => '15612312312',
                // 门店地址(支持汉字、符号、字母的组合)
                'address' => '哈尔滨市',
                // 坐标属性（1:腾讯地图, 2:百度地图, 3:高德地图），蜂鸟建议使用高德地图
                'position_source' => 3,
                // 门店经度(数字格式, 包括小数点, 取值范围0～180),16位
                'longitude' => '126.643278',
                // 门店纬度(数字格式, 包括小数点, 取值范围0～90),16位
                'latitude' => '45.77656',
                // 配送服务(1:蜂鸟配送, 2:蜂鸟优送, 3:蜂鸟快送)
                'service_code' => 1,
            ];
            $this->res_common['signature'] = self::requestSign($this->res_common['salt'], $this->res_common['data']);
            $res = curl($method, $this->config[$this->scene] . $type, $this->res_common);
            if (!$res || $res['code'] != 200) return ['code' => -1, 'message' => '远程请求失败,原因:' . $res['msg']];
            return ['code' => 0, 'message' => 'success', 'data' => $res['data']];
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}