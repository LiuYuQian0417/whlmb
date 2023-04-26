<?php
// 充值提现申请
declare(strict_types=1);

namespace app\master\controller;

use think\Db;
use think\Controller;
use think\facade\Request;
use app\common\model\Member as MemberModel;
use app\common\model\Consumption as ConsumptionModel;

class Consumption extends Controller
{
    /**
     * 充值提现列表
     * @param Request $request
     * @param ConsumptionModel $consumption
     * @return array|mixed
     */
    public function index(Request $request, ConsumptionModel $consumption)
    {
        try {
            // 获取数据
            $param = $request::get();

            // 条件筛选
            $condition[] = ['consumption_id', '>', 0];

            $condition[] = ['co.type', '=', !empty($param['type']) ?: 0];

            if (!empty($param['keyword'])) $condition[] = ['mem.nickname', 'like', '%' . $param['keyword'] . '%'];
            if (array_key_exists('way', $param) && $param['way'] != -1) $condition[] = ['co.way', 'eq', $param['way']]; // 支付方式

            if (empty($param['start_date'])){
                unset($param['start_date']);
            }
            if (empty($param['end_date'])){
                unset($param['end_date']);
            }
            // 获取数据
            $data = $consumption->alias('co')
                ->join('ishop_member mem', 'co.member_id=mem.member_id')
                ->join('store store', 'store.member_id=co.member_id', 'left')
                ->field('co.consumption_id,co.member_id,co.type,co.order_number,co.price,
                co.way,co.balance,co.status,co.create_time,mem.nickname,store.store_name')
                ->where($condition)
                ->whereTime(
                    'co.create_time',
                    'between', [
                    $param['start_date'] ?? '1968-01-01 00:00:00',
                    $param['end_date'] ?? date('Y-m-d H:i:s', time()),
                ])
                ->group(['co.consumption_id'])
                ->order('create_time', 'desc')
                ->paginate(10, FALSE, ['query' => $param]);

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }


        return $this->fetch('', [
            'data' => $data,
        ]);
    }


    /**
     * 添加申请
     * @param Request $request
     * @param ConsumptionModel $consumption
     * @param MemberModel $member
     * @return array|mixed
     */
    public function create(Request $request, ConsumptionModel $consumption, MemberModel $member)
    {
        if ($request::isPost()) {

            Db::startTrans();

            try {
                // 获取数据
                $param = $request::post();

                // 验证
                $check = $consumption->valid($param, 'create');
                if ($check['code']) return $check;

                // 更改会员资金
                $balance = $consumption->updateUsableMoney($param);
                $param['balance'] = $balance;
                // 更改主表
                $consumption->allowField(TRUE)->save($param);

                // 提交事务
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/consumption/index'];

            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }

        }

        $memberClassify = $member->where('status', 0)->field('username,member_id,phone')->select();


        return $this->fetch('', [
            'memberClassify' => $memberClassify,
        ]);
    }


    /**
     * 审核提现
     * @param Request $request
     * @param ConsumptionModel $consumption
     * @return array|mixed
     */
    public function edit(Request $request, ConsumptionModel $consumption)
    {
        if ($request::isPost()) {

            Db::startTrans();
            try {
                // 获取数据
                $param = $request::post();

                // 验证
                $check = $consumption->valid($param, 'edit');
                if ($check['code']) return $check;

                // 更改会员资金
                $balance = $consumption->updateUsableMoney($param);
                $param['balance'] = $balance;

                // 更改主表
                $consumption->allowField(TRUE)->isUpdate(TRUE)->save($param);

                // 提交事务
                Db::commit();

                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/consumption/index'];

            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }

        }

        return $this->fetch('', [
            'item' => $consumption->relation(['Member' => function ($query) {
                $query->field('username,nickname,avatar');
            }])->where('consumption_id', $request::get('id'))->find(),
        ]);
    }


}