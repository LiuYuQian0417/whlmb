<?php
declare(strict_types=1);

namespace app\common\model;


class FreightExpressClassify extends BaseModel
{

    protected $pk = 'freight_express_classify_id';

    public function getTypeStringAttr($val, $data)
    {
        $_values = ['', '按件数', '按重量'];
        return $_values[$data['type']];
    }

    public function freightExpressCor()
    {
        return $this->hasMany('FreightExpress', 'freight_express_classify_id', 'freight_express_classify_id');
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

    /**
     * 关联运费模板附表
     * @return \think\model\relation\HasMany
     */
    public function freightExpress()
    {
        return $this->hasMany('FreightExpress', 'freight_express_classify_id', 'freight_express_classify_id');
    }

    /**
     * 运费模板附表列表
     * @return \think\model\relation\HasMany
     */
    public function freightExpressList()
    {
        return self::freightExpress()
            ->field('freight_express_id,freight_express_classify_id,store_id,upper_num,
            base_amount,extend_num_unit,extend_amount,distribution_area_id');
    }
}