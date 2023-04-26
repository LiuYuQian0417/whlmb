<?php
declare(strict_types=1);

namespace app\merchant\controller\platform;

use think\Controller;
use think\Db;
use think\Request;
use think\facade\Env;
use app\common\model\Store;
use app\common\model\Member;
use app\common\model\OrderAttach;

class Index extends Controller
{
    /**首页
     * @param Request $request
     * @param Store $store
     * @param Member $member
     * @param OrderAttach $orderAttach
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(Request $request, Store $store, Member $member, OrderAttach $orderAttach)
    {
        $param = $request->param();
        $type = $param['type'];//数据查询分类 all为全部，0为自营，1为入驻，2为店铺
        if ($type == 2)
        {
            $store_id = $param['store_id'];
        }
        Env::load(Env::get('app_path') . 'common/ini/.config');
        $distributionSet = Env::get();
        // 平台logo
        $result['logo'] = $distributionSet['LOGO'];
        //平台名称
        $result['title'] = $distributionSet['TITLE'];
        //店铺总数
        $result['store_number'] = $store->where([
                ['status', '=', 4],
                ['end_time', ['exp', Db::raw('is null')], ['>=', date('Y-m-d')], 'or'],
            ])->count();

        //用户总数
        $result['member_number'] = $member->count();


        if ($type == 'all')
        {
            $today_price_where = [
                ['status', 'in', '1,2,3,4'],
            ];
        }
        elseif ($type == 0)
        {
            //查询自营店铺列表
            $shop_list = $store->where([
                ['shop', '=',0],
                ['status', '=', 4],
                ['end_time', ['exp', Db::raw('is null')], ['>=', date('Y-m-d')], 'or'],
            ])->field('store_id')->select();
            $shop_list_arr = array();
            foreach ($shop_list as $value)
            {
                $shop_list_arr[] = $value['store_id'];
            }
            $today_price_where = [
                ['status', 'in', '1,2,3,4'],
                ['store_id', 'in', implode(',',$shop_list_arr)],
            ];
        }
        elseif ($type == 1)
        {
            //查询入驻店铺列表
            $shop_list = $store->where([
                ['shop', '=',1],
                ['status', '=', 4],
                ['end_time', ['exp', Db::raw('is null')], ['>=', date('Y-m-d')], 'or'],
            ])->field('store_id')->select();
            $shop_list_arr = array();
            foreach ($shop_list as $value)
            {
                $shop_list_arr[] = $value['store_id'];
            }
            $today_price_where =[
                ['status', 'in', '1,2,3,4'],
                ['store_id', 'in', implode(',',$shop_list_arr)],
            ];
        }
        elseif ($type == 2)
        {
            $today_price_where['store_id'] = ['eq',$store_id];
            $today_price_where =[
                ['status', 'in', '1,2,3,4'],
                ['store_id', 'eq', $store_id],
            ];
        }

        //今日支付金额
        $result['today_price'] = $orderAttach->where($today_price_where)->whereTime('pay_time', 'today')->sum('subtotal_price');
        //昨日支付金额
        $result['yesterday_price'] = $orderAttach->where($today_price_where)->whereTime('pay_time', 'yesterday')->sum('subtotal_price');
        //访客数（未定）
        $result['visitor_number'] = 0;
        //浏览量（未定）
        $result['browse_number'] = 0;
        //支付订单数
        $result['today_order'] = $orderAttach->where($today_price_where)->whereTime('pay_time', 'today')->count();
        //支付买家数
        $result['today_pay_number'] = $orderAttach->where($today_price_where)->whereTime('pay_time', 'today')->group('member_id')->count();
        //店铺支付金额榜单
        $condition[] = ['status','eq',4];
        $result['payment_list'] = $store->field('store_id,store_name,shop,logo')
        ->withSum('GoodsAllPrice','subtotal_price')
        ->where($condition)
        ->order('goods_all_price_sum desc')
        ->limit(5)
        ->select();
        foreach ($result['payment_list'] as $value)
        {
            if($value['goods_all_price_sum'] == null)
            {
                $value['goods_all_price_sum'] = 0;
            }
        }
        $data['code'] = 0;
        $data['message'] = '成功';
        $data['data'] = $result;
        return json($data);
    }

    /**首页榜单
     * @param Request $request
     * @param Store $store
     * @return \think\response\Json
     * @throws \think\exception\DbException
     */
    public function ranking_list(Request $request,Store $store)
    {
        $param = $request->param();
        if ($param['type'] == 'all')
        {
            $condition[] = ['shop','in','0,1,2'];
        }
        elseif ($param['type'] == 0)
        {
            $condition[] = ['shop','eq',0];
        }
        elseif ($param['type'] == 1)
        {
            $condition[] = ['shop','eq',1];
        }
        if (!empty($param['keys'])) $condition[] = ['store_name','like','%'.$param['keys'].'%'];
        $result['payment_list'] = $store->field('store_id,store_name,shop,logo')
            ->withSum('GoodsAllPrice','subtotal_price')
            ->where($condition)
            ->order('goods_all_price_sum desc')
            ->paginate(5);
        if(!empty($param['start_time']) && !empty($param['end_time']))
        {
            $result['payment_list'] = $store->field('store_id,store_name,shop,logo')
                ->withSum(['GoodsAllPrice'=>function($query) use ($param){
                    $query->where('pay_time', 'between time', [$param['start_time'], $param['end_time']]);
                }],'subtotal_price')
                ->where($condition)
                ->order('goods_all_price_sum desc')
                ->paginate(5);
        }
        //店铺支付金额榜单
        $condition[] = ['status','eq',4];

        foreach ($result['payment_list'] as $value)
        {
            if($value['goods_all_price_sum'] == null)
            {
                $value['goods_all_price_sum'] = 0;
            }
        }
        $data['code'] = 0;
        $data['message'] = '成功';
        $data['data'] = $result;
        return json($data);
    }

    /**店铺列表
     * @param Request $request
     * @param Store $store
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function store_list(Request $request,Store $store)
    {
        $param = $request->param();
        if(!empty($param['type'])) $condition[] = ['shop','eq',$param['type']];
        $condition[] = ['status','eq',4];
        $result['list'] = $store->where($condition)->field('store_id,store_name')->select();
        $data['code'] = 0;
        $data['message'] = '成功';
        $data['data'] = $result;
        return json($data);
    }
}