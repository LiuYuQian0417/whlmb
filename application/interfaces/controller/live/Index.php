<?php
declare(strict_types = 1);

namespace app\interfaces\controller\live;


use app\common\push\PubNum;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Cache;
use think\facade\Request;

/**
 * 直播信息
 * Class Index
 * @package app\interfaces\controller\live
 */
class Index extends BaseController
{
    /**
     * 获取直播列表
     * @param RSACrypt $crypt
     * @param Request $request
     * @return array
     */
    public function list(RSACrypt $crypt, Request $request)
    {
        $query = $request::get();
        // $data = Cache::get('live_list', []);
        // $data = [];
        // if (empty($data)) {
        // $start = 1;
        // while ($chunk = self::getLive(($start - 1) * 100, 100)) {
        //     $start++;
        //     $data = array_merge($data, $chunk);
        // }
        // if (!empty($data)) {
        // Cache::set('live_list', $data, 86400);
        // }
        // }
        $ret = [
            'total' => 0,
            'per_page' => 20,
            'last_page' => 0,
            'list' => [],
        ];
        $data = self::getLive(($query['page'] - 1) * 20, 20);
        if ($data['errcode'] === 0){
            $ret = [
                'total' => $data['total'],
                'per_page' => 20,
                'last_page' => ceil($data['total'] / 20),
                'list' => $data['room_info'],
            ];
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $ret,
        ]);
    }
    
    /**
     * 获取直播数据
     * @param $start
     * @param $limit
     * @return array|mixed|string
     */
    protected function getLive($start, $limit)
    {
        $url = 'https://api.weixin.qq.com/wxa/business/getliveinfo';
        $access_token = (new PubNum([]))->getAccessToken(1);
        $body = ['start' => $start, 'limit' => $limit];
        return curl(2, "{$url}?access_token={$access_token}", $body);
    }
    
    /**
     * 添加成员管理员
     */
    public function add_manage()
    {
        $url = 'https://api.weixin.qq.com/wxaapi/broadcast/role/addrole';
        $access_token = (new PubNum([]))->getAccessToken(1);
        $data = curl(2, "{$url}?access_token={$access_token}", [
            // 'username' => 'ZD----1994',
            // 'role' => 2,
        ]);
        halt($data);
    }
}