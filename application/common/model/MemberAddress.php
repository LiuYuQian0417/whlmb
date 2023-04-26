<?php
declare(strict_types = 1);

namespace app\common\model;

/**
 * 会员收货地址模型
 * Class Manage
 * @package app\common\model
 */
class MemberAddress extends BaseModel
{
    protected $pk = 'member_address_id';
    
    
    public static function init()
    {
        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s');
            if (isset($e->is_default) && $e->is_default == 1) {
                self::where([
                    ['member_id', '=', $e->member_id],
                ])->update(['is_default' => 0]);
            }
        });
    }
    
    /**
     * 获取地址信息id
     * @param $value
     * @param $data
     * @return array
     */
    public function getAddressInfoAttr($value, $data)
    {
        return self::optAddress($data);
    }
    
    public function optAddress($data)
    {
        $areaModel = app('app\common\model\Area');
        $ret = [
            'province_id' => 0,
            'city_id' => 0,
            'area_id' => 0,
            'street_id' => 0,
        ];
        $provinceWhere = $cityWhere = $areaWhere = [];
        if ((isset($data['province']) && $data['province'])) {
            $ret['province_id'] = $areaModel
                ->removeOption('delete_time')
                ->where([
                    ['area_name', '=', $data['province']],
                ])
                ->value('area_id');
            if ($ret['province_id']) {
                array_push($provinceWhere, ['parent_id', '=', $ret['province_id']]);
            }
        }
        if (isset($data['city']) && $data['city']) {
            array_push($provinceWhere, ['area_name', '=', $data['city']]);
            $ret['city_id'] = $areaModel
                ->removeOption('delete_time')
                ->where($provinceWhere)
                ->value('area_id');
            if ($ret['city_id']) {
                array_push($cityWhere, ['parent_id', '=', $ret['city_id']]);
            }
        }
        if (isset($data['area']) && $data['area']) {
            array_push($cityWhere, ['area_name', '=', $data['area']]);
            $ret['area_id'] = $areaModel
                ->removeOption('delete_time')
                ->where($cityWhere)
                ->value('area_id');
            if ($ret['area_id']) {
                array_push($areaWhere, ['parent_id', '=', $ret['area_id']]);
            }
        }
        if (isset($data['street']) && $data['street']) {
            array_push($areaWhere, ['area_name', '=', $data['street']]);
            $ret['street_id'] = $areaModel
                ->removeOption('delete_time')
                ->where($areaWhere)
                ->value('area_id');
        }
        return $ret;
    }
    
    /**
     * 获取用户当前地址数量
     * @param $member_id
     * @return float|string
     */
    public function getCurNum($member_id)
    {
        $num = $this
            ->where([
                ['member_id', '=', $member_id],
            ])
            ->count('member_address_id');
        return intval($num);
    }
    
}