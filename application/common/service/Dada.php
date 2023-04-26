<?php
declare(strict_types = 1);
namespace app\common\service;

use EasyWeChat\Kernel\Exceptions\Exception;
use think\facade\Config;

/**
 * 达达配送
 * Class Dada
 * @package app\common\service
 */
class Dada
{
    /**
     * 请求参数
     * @var array
     */
    private $data = [];

    public function __construct($source_id, $data)
    {
        $config = Config::get('dada.');
        $this->data = [
            'app_key' => $config['app_key'],
            'app_secret' => $config['app_secret'],
            'format' => 'json',
            'timestamp' => time(),
            'v' => '1.0',
//            'source_id' => $source_id ?: '',
            'source_id' => '73753',
            'body' => (is_array($data) ? json_encode($data) : $data),
        ];
        $this->data['signature'] = self::makeSign();
    }

    /**
     * 制作请求签名
     * @return mixed|string
     * @throws Exception
     */
    private function makeSign()
    {
        try {
            $sign = $app_secret = $this->data['app_secret'];
            unset($this->data['app_secret']);
            ksort($this->data);
            foreach ($this->data as $key => $value) {
                $sign .= $key . $value;
            }
            $sign .= $app_secret;
            $sign = strtoupper(md5($sign));
            return $sign;
        } catch (\Exception $e) {
            throw new Exception('生成签名失败' . $e->getMessage());
        }
    }

    /**
     * 请求远程接口
     * @param $type string 远程接口方法
     * @return array 查询接口返回数据
     * @throws Exception
     */
    public function request($type)
    {
        try {
//            $domain = 'https://newopen.imdada.cn/';
            $domain = 'http://newopen.qa.imdada.cn/';

            $res = curl(2, $domain . $type, $this->data);
            if (!$res){
                throw new Exception("达达接口请求出错");
            }
            if (!array_key_exists('result', $res)) $res['result'] = [];
            if (!$res || $res['code'] != 0)
                return ['code' => $res['code'], 'message' => $res['msg'], 'data' => $res['result']];

            return ['code' => 0, 'message' => '请求成功', 'data' => $res['result']];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => '请求成功', 'data' => $res['result']];
        }
    }

    public function verifySign ($arr)
    {
        $arr = json_decode( $arr , TRUE );
        $_tmpArr = [
            'client_id'   => $arr['client_id'] ,
            'order_id'    => $arr['order_id'] ,
            'update_time' => $arr['update_time'] ,
        ];
        // 第一步：将参与签名的字段的值进行升序排列
        sort( $_tmpArr );
        // 将排序过后的参数，进行字符串拼接
        $_string = '';
        foreach ( $_tmpArr as $__key => $__value )
        {
            $_string .= $__value;
        };
        // 第三步：对第二步连接的字符串进行md5加密
        $_string = md5( $_string );
        return $_string === $arr['signature'] ? [ 'status' => TRUE , 'result' => $arr ] : [
            'status' => FALSE ,
            'msg'    => '签名验证失败' ,
        ];
    }
}