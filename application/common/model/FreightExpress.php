<?php
/**
 * 快递邮寄运费模板
 * Class FreightExpress
 * @package app\common\model
 */
declare(strict_types=1);

namespace app\common\model;

use app\common\model\Store as StoreModel;

class FreightExpress extends BaseModel
{
    protected $pk = 'freight_express_id';

    /**
     * 反向关联运费模板列表
     *
     * @return \think\model\relation\BelongsTo
     */
    public function freightExpressClassifyBlt()
    {
        return $this->belongsTo('FreightExpressClassify', 'freight_express_classify_id');
    }

    /**
     * 反向管理店铺
     *
     * @return \think\model\relation\BelongsTo
     */
    public function storeBlt()
    {
        return $this->belongsTo('Store', 'store_id');
    }

    public function getStoreNameTextAttr($value, $data)
    {
        return (new StoreModel())->where('store_id', $data['store_id'])->value('store_name');
    }
}