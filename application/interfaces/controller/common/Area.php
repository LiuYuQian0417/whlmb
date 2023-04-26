<?php
declare(strict_types=1);

namespace app\interfaces\controller\common;

use app\common\model\Area as AreaModel;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Request;

/**
 * 城市管理
 * Class Area
 * @package app\interfaces\controller\common
 */
class Area extends BaseController
{

    /**
     * 城市列表 - Joy
     * @param RSACrypt $crypt
     * @param AreaModel $AreaModel
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          AreaModel $AreaModel)
    {
        // 热门城市
        $hot = $AreaModel
            ->where([
                ['deep', '=', 2],
                ['status', '=', 1],
                ['is_hot', '=', 1],
            ])
            ->field('area_name,initials')
            ->order(['sort' => 'desc', 'initials' => 'asc', 'area_id' => 'asc'])
            ->select();
        // 城市列表
        $result = $AreaModel
            ->where([
                ['deep', '=', 2],
                ['status', '=', 1],
            ])
            ->field('area_name,initials')
            ->order(['sort' => 'desc', 'initials' => 'asc', 'area_id' => 'asc'])
            ->select()
            ->toArray();
        $reCarding = arrayGrouping($result, 'initials', 'initials', 'list');
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'hot' => $hot->isEmpty() ? [] : $hot->toArray(),
            'result' => $reCarding,
        ], true);
    }

    /**
     * 根据ID 获取列表
     * @param RSACrypt $crypt
     * @param AreaModel $area
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getListById(RSACrypt $crypt,
                                AreaModel $area)
    {
        $_areaList = $area
            ->where([
                'parent_id' => Request::get('area_id', 0)
            ])
            ->field(['area_id', 'area_name', 'parent_id'])
            ->select();
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $_areaList,
        ], true);
    }
}