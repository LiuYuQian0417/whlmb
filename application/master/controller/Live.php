<?php


namespace app\master\controller;


use app\common\push\PubNum;
use think\Controller;
use think\facade\Cache;
use think\facade\Request;
use think\paginator\driver\Bootstrap;

class Live extends Controller
{
    public function clear_list()
    {
        Cache::rm('live_list');
        return [
            'code' => 0,
            'message' => '刷新成功',
        ];
    }
    
    public function index(Request $request)
    {
        $page = $request::get('page', 1);
        $list = Bootstrap::make([], 10, 1, 0);
        $data = self::getLive(($page - 1) * 10, 10);
        if ($data['errcode'] === 0) {
            // $list = [
            //     'total' => $data['total'],
            //     'per_page' => 20,
            //     'last_page' => ceil($data['total'] / 20),
            //     'list' => $data['room_info'],
            // ];
            if ($data['room_info']) {
                foreach ($data['room_info'] as &$ri) {
                    $ri['replay_url'] = '';
                    if ($ri['live_status'] == 103) {
                        $ri['replay_url'] = self::get_replay($ri['roomid']);
                    }
                }
            }
            $list = Bootstrap::make($data['room_info'], 10, $page, $data['total']);
        }
        return $this->fetch('', [
            'data' => $list,
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
    
    protected function get_replay($room_id)
    {
        $url = 'https://api.weixin.qq.com/wxa/business/getliveinfo';
        $access_token = (new PubNum([]))->getAccessToken(1);
        $body = ['start' => 0, 'limit' => 100, 'action' => 'get_replay', 'room_id' => $room_id];
        $res = curl(2, "{$url}?access_token={$access_token}", $body);
        $url = '';
        if ($res['errcode'] == 0 && $res['total'] > 0) {
            foreach ($res['live_replay'] as $lr) {
                if ($lr['media_url']){
                    $path = pathinfo($lr['media_url']);
                    if ( $path['extension'] == 'mp4') {
                        $url = $lr['media_url'];
                        break;
                    }
                }
            }
        }
        return $url;
    }
    
}