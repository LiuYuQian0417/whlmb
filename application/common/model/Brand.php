<?php

namespace app\common\model;

use app\common\model\StoreBrand as StoreBrandModel;

class Brand extends BaseModel
{
    protected $pk = 'brand_id';

    public function getExtraAttr($value, $data)
    {
        return $data['brand_logo'];
    }

    public function CountBrand()
    {
        $count['Self'] = self::count();
        $count['Other'] = (new StoreBrandModel())->count();
        return $count;
    }

    public function changeIsRecommend($id)
    {
        //查询当前状态
        $curStatus = $this
            ->where([
                [$this->pk, '=', $id],
            ])
            ->value('is_recommend');
        //更改当前状态
        $this
            ->isUpdate(true)
            ->where([
                [$this->pk, '=', $id],
            ])
            ->update(['is_recommend' => $curStatus ? 0 : 1]);
    }

}