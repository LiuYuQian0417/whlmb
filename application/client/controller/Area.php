<?php
// 区域切换
declare(strict_types = 1);

namespace app\client\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\Area as AreaModel;

class Area extends Controller
{
    public function search(Request $request,AreaModel $area)
    {

        return $this->fetch('',[
            'provinces'=>$area->where(['parent_id'=>0])->field('area_id,area_name')->select(),
        ]);
    }


    public function changeArea(Request $request, AreaModel $area)
    {
        $data = $area->where(['parent_id' => $request::get('id')])
            ->field('area_id,area_name')->select();

        return $data;
    }
    
}