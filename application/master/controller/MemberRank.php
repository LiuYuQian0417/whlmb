<?php
declare(strict_types = 1);
namespace app\master\controller;

use think\Controller;
use think\Db;
use think\facade\Request;
use app\common\model\MemberRank as MemberRankModel;
use app\common\model\MemberGrowthRecord;

class MemberRank extends Controller
{

    /**
     * 会员等级列表
     * @param MemberRankModel $memberRankModel
     * @return array|mixed
     */
    public function index(MemberRankModel $memberRankModel)
    {


        try {

            $data = $memberRankModel->all();

        } catch (\Exception $e) {

            return ['code' => -100, 'message' => $e->getMessage()];

        }

        return $this->fetch('', [
            'data' => $data
        ]);
    }

    /**
     * 会员等级新增
     * @param Request $request
     * @param MemberRankModel $memberRankModel
     * @return array|mixed
     */
    public function create(Request $request, MemberRankModel $memberRankModel)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $memberRankModel->valid($param, 'create');
                if ($check['code']) return $check;

                $state = $memberRankModel->allowField(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/member_rank/index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch('');
    }

    /**
     * 会员等级编辑
     * @param Request $request
     * @param MemberRankModel $memberRankModel
     * @return array|mixed
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, MemberRankModel $memberRankModel)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $memberRankModel->valid($param, 'edit');
                if ($check['code']) return $check;

                $state = $memberRankModel->allowField(true)->isUpdate(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/member_rank/index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        $data = $memberRankModel->get($request::get('member_rank_id'));
        $data['file_data'] = $data->getData('file');
        $data['file2_data'] = $data->getData('file2');
        return $this->fetch('create', [
            'item' => $data,
        ]);
    }

    /**
     * 删除会员等级
     * @param Request $request
     * @param MemberRankModel $memberRankModel
     * @return array
     */
    public function destroy(Request $request, MemberRankModel $memberRankModel, MemberRankModel $memberRank)
    {
        if ($request::isPost()) {

            try {

                $growth_value = Db::name('member_growth_record')
                    ->where([
                        ['create_time', 'between time', [date("Y-m-31", strtotime("-1 year")), date('Y-m-31')]]
                    ])
                    ->field('IFNULL(sum(growth_value),0) as growth')
                    ->group('member_id')
                    ->select();

                foreach ($growth_value as $k => $v) {
                    if (!empty($memberRank->where('min_points', '<=', $v['growth'])
                        ->where('max_points', '>=', $v['growth'])
                        ->where('member_rank_id','in', $request::post('id'))
                        ->value('member_rank_id')))
                        return ['code' => -100, 'message' => '删除失败，该等级下存在用户'];
                }

                $memberRankModel::destroy($request::post('id'));

                return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }
    }

    /**
     * 会员等级价格状态更新
     * @param Request $request
     * @param MemberRankModel $memberRankModel
     * @return array
     */
    public function auditing(Request $request, MemberRankModel $memberRankModel)
    {

        if ($request::isPost()) {
            try {
                $memberRankModel->changeStatus($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 会员等级文本更新
     * @param Request $request
     * @param MemberRankModel $memberRankModel
     * @return array
     */
    public function text_update(Request $request, MemberRankModel $memberRankModel)
    {

        if ($request::isPost()) {
            try {
                $memberRankModel->clickEdit($request::post());
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

}