<?php
namespace app\master\controller;

use think\Controller;
use app\common\model\Store;
use app\common\model\Member;
use app\common\model\Goods;
use app\common\model\StoreClassify;
use think\facade\Env;

class Desk extends Controller
{
    /**
     * 系统设置文件路径
     * @var
     */
    private $filename;

    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
        $this->filename = Env::get('APP_PATH') . 'common/ini/.config';
    }

    public function index(Store $store, Member $member, StoreClassify $storeClassify)
    {
        // 店铺数
        $data['store'] = $store->where('status',1)->cache(true,60)->count();
        // 用户数
        $data['member'] = $member->cache(true,60)->count();
        // 店铺待审核
        $data['store_status'] = $store->where([['status', '=', 2]])->cache(true,60)->count();
        // 自营店铺数量
        $data['self_store'] = $store->where([['shop', '=', 0]])->cache(true,60)->count();
        // 入驻店铺数
        $data['enter_store'] = $store->where([['shop', 'in', '1,2']])->cache(true,60)->count();
        // 品牌甄选
        $data['store_brand'] = $store->where('brand_classify_id', 'not null')->cache(true,60)->count();
        // 发现好店
        $data['store_is_good'] = $store->where('is_good', 1)->cache(true,60)->count();
        // 主营类目
        $data['store_classify'] = $storeClassify->where('status',1)->cache(true,60)->count();
        return $this->fetch('',[
            'item' => $data,
        ]);
    }
}
