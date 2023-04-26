<?php
// 店铺品牌
declare(strict_types = 1);
namespace app\computer\model;

use think\model\concern\SoftDelete;

class StoreBrand extends BaseModel
{
    use SoftDelete;
    protected $pk='store_brand_id';

    // 模型事件
    public static function init()
    {
        self::beforeInsert(function ($e){
            $e->create_time = date('Y-m-d H:i:s',time());
            $e->update_time = date('Y-m-d H:i:s',time());
        });


        self::beforeWrite(function ($e){
            $e->update_time = date('Y-m-d H:i:s',time());
        });
    }

    /**
     * 获取图片oss地址
     * @param $value
     * @return mixed
     */
    public function getBrandLogoAttr($value)
    {

        if ($this->isPrivateOss && $value) {
            $ossManage = app('app\\common\\service\\OSS');
//            $res = $ossManage->getSignUrlForGet($value . config('oss.')['style'][2]);
            $res = $ossManage->getSignUrlForGet($value);
            $value = ($res['code'] === 0) ? $res['url'] : '';
        }
        return $value;
    }

    public function getExtraAttr($value,$data){
        return $data['brand_logo'];
    }
}