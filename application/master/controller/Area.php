<?php
declare(strict_types=1);

namespace app\master\controller;

use app\common\service\Spell;
use think\Controller;
use think\facade\Request;
use app\common\model\Area as AreaModel;

class Area extends Controller
{

    /**
     * 城市列表
     * @param Request $request
     * @param AreaModel $areaModel
     * @return array|mixed
     */
    public function index(Request $request, AreaModel $areaModel)
    {


        try {

            // 获取参数
            $param = $request::get();

            $area_id = !empty($param['area_id']) ? $param['area_id'] : 0;

            $data = $areaModel->withTrashed()
                ->where('parent_id', $area_id)
                ->field('update_time,parent_id,create_time', true)
                ->order(['area_id' => 'asc'])
                ->select();



        } catch (\Exception $e) {

            return ['code' => -100, 'message' => $e->getMessage()];

        }

        return $this->fetch('', [
            'data' => $data
        ]);
    }

    /**
     * 地区新增
     * @param Request $request
     * @param AreaModel $areaModel
     * @return array
     */
    public function create(Request $request, AreaModel $areaModel)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $areaModel->valid($param, 'create');
                if ($check['code']) return $check;

                // 查找是否有重名的
                $area = (new AreaModel())->where(
                    [
                        ['area_name', '=', $param['area_name']],
                    ]
                )->find();

                if ($area)
                {
                    return [
                        'code' => -1,
                        'message' => '无法创建相同名字的地区',
                    ];
                }


                // 首字母
                $param['initials'] = _getFirstCharter($param['area_name']);
                // 全拼
                $param['spell'] = (new Spell())->convert($param['area_name']);

                if (empty($param['initials']) || empty($param['spell']))
                {
                    return [
                        'code'=>'-1',
                        'message'=>'请输入合法的地区名称'
                    ];
                }

                $state = $areaModel->allowField(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/area/index?area_id=' . $param['parent_id'].'&deep='.$param['deep']];

            } catch (\Exception $e) {
                // return $e->getMessage();
                return ['code' => -100, 'message' => config('message.')[-1]];

            }
        }

    }

    /**
     * 删除地区
     * @param Request $request
     * @param AreaModel $areaModel
     * @return array
     */
    public function destroy(Request $request, AreaModel $areaModel)
    {
        if ($request::isPost()) {

            try {

                $time = $areaModel->withTrashed()->where([['area_id','=',$request::post('id')]])->value('delete_time');
                if(!empty($time)){
                    $areaModel->withTrashed()->where([['area_id','=',$request::post('id')]])->update(['delete_time' => NULL]);
                }else{
                    $areaModel::destroy($request::post('id'));
                }

                return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }
    }



    /**
     * 显示状态更新
     * @param Request $request
     * @param AreaModel $areaModel
     * @return array
     */
    public function auditing(Request $request, AreaModel $areaModel)
    {

        if ($request::isPost()) {
            try {
                $areaModel->changeStatus($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 热门状态更新
     * @param Request $request
     * @param AreaModel $areaModel
     * @return array
     */
    public function hot(Request $request, AreaModel $areaModel)
    {

        if ($request::isPost()) {
            try {
                $areaModel->changeStatus($request::post('id'),'is_hot');
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 文本更新
     * @param Request $request
     * @param AreaModel $areaModel
     * @return array
     */
    public function text_update(Request $request, AreaModel $areaModel)
    {

        if ($request::isPost()) {
            try {
                $areaModel->clickEdit($request::post());
                return ['code' => 0, 'message' => ''];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

}