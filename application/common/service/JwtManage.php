<?php
declare(strict_types = 1);
namespace app\common\service;

use Firebase\JWT\JWT;
use think\facade\Cache;
use think\facade\Config;

/**
 * jWT管理类
 * Class JwtManage
 * @package app\front\service
 */
class JwtManage
{
    /**
     * token有效期应为EXPIRE+LEEWAY总时间
     */

    //token到期余地时间
    const LEEWAY = 300;
    //token有效期
    const EXPIRE = 3600 * 4;
    const MERCHANT_EXPIRE = 3600 * 24 * 365;
    const TYPES = 1;
    //加密方式
    const ALG = 'RS256';
    //私钥
    private $privateKey;
    //公钥
    private $publicKey;
    //参数
    private $param;
    //当前时间戳
    private $now;

    public function __construct($param,$types=1)
    {
        $conf = Config::pull('jwt');
        $this->privateKey = $conf['privateKey'];
        $this->publicKey = $conf['publicKey'];
        $this->param = $param;
        $this->types = $types;
        $this->now = time();
    }

    /**
     * 发布token
     * @return string
     */
    public function issueToken()
    {
        return self::createToken();
    }

    /**
     * 解析token
     * @return object
     */
    public function parseToken()
    {
        $parseData = JWT::decode($this->param, $this->publicKey, [self::ALG]);
        return $parseData;
    }

    /**
     * 创建token
     * @return string
     */
    private function createToken()
    {
        //设置余地时间
        JWT::$leeway = self::LEEWAY;

        return JWT::encode(self::setParam(), $this->privateKey, self::ALG);
    }

    /**
     * 设置有效荷载
     * @return array
     */
    private function setParam()
    {
        $token = [
            'jti' => uniqid('', true),
            'iat' => $this->now,  //发布时间
        ];
        if (self::TYPES == 1)
        {
            $token['exp'] = $this->now + self::EXPIRE;  //到期时间

        }
        else
        {
            $token['exp'] = $this->now + self::MERCHANT_EXPIRE;  //到期时间

        }
        return array_merge($token, $this->param);
    }

    /**
     * 过期token加入黑名单
     * @param $payload
     */
    public function addBlackList($payload)
    {
        $str = $payload->jti . '_' . $payload->iat;
        $list = Cache::store('file')->get('tokenBlackList', []);
        array_push($list, $str);
        Cache::store('file')->set('tokenBlackList', $list);
    }

}