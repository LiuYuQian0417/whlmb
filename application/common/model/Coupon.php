<?php
declare(strict_types=1);

namespace app\common\model;

class Coupon extends BaseModel
{
    protected $pk = 'coupon_id';

    // 模型事件
    public static function init()
    {
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
        });

        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s');
        });
    }

    /**
     * 获取有效优惠券数量
     * @param $is_gift int 是否为礼物优惠券
     * @return int
     */
    public function hasValid($is_gift = 1)
    {
        $where = [
            ['status', '=', 1],
            ['receive_start_time', '<=', date('Y-m-d')],
            ['receive_end_time', '>=', date('Y-m-d')],
            ['exchange_num', '>', 0],               //剩余券数量
        ];
        if ($is_gift) {
            array_push($where, ['is_gift', '=', 1]);
        }
        $count = $this
            ->where($where)
            ->count();
        return intval($count);
    }

    /**
     * 优惠券所属分类
     * @param $value
     * @return mixed
     */
    public function getTypeNameTextAttr($value)
    {
        $typeArr = [0 => '店铺', 1 => '平台'];
        return $typeArr[$value];
    }

    /**
     * 所属店铺/所属平台商品分类修改器 距离开始时间
     * @param $value
     * @param $data
     * @return mixed|string
     */
    public function getDistanceStartTimeAttr($value, $data)
    {
        return strtotime($data['receive_start_time']) - strtotime(date('Y-m-d H:i:s'));
    }

    /**
     * 所属店铺/所属平台商品分类修改器 距离结束时间
     * @param $value
     * @param $data
     * @return mixed|string
     */
    public function getDistanceEndTimeAttr($value, $data)
    {
        return strtotime($data['end_time']) - strtotime(date('Y-m-d H:i:s'));
    }

    /**
     * 所属店铺/所属平台商品分类修改器
     * @param $value
     * @param $data
     * @return mixed|string
     */
    public function getBelongGoodsTextAttr($value, $data)
    {
        if ($data['type'] == 0) {
            $storeModel = app('app\\common\\model\\Store');
            $attach = $storeModel
                ->where([
                    ['store_id', '=', $data['classify_str'] ?: ''],
                ])
                ->value('store_name');
            $belongText = $attach ?: '';
        } else {
            $goodsClassifyModel = app('app\\common\\model\\GoodsClassify');
            $attach = $goodsClassifyModel
                ->where([
                    ['goods_classify_id', 'in', $data['classify_str'] ?: ''],
                ])
                ->column('title');
            $belongText = $attach ? implode($attach, '/') : '';
        }
        return $belongText;
    }

    /**
     * 领取时间状态获取器（商家后台使用）
     * @param $value
     * @param $data
     * @return string
     */
    public function getReceiveTextAttr($value, $data)
    {
        $now = date('Y-m-d H:i:s');
        if ($now < $data['receive_start_time']) {
            $receiveText = 0; // 允许编辑
        } else {
            $receiveText = 1; // 过了领取开始时间 禁止编辑
        }
        return $receiveText;
    }

    /**
     * 图片源路径
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getFileExtraAttr($value, $data)
    {
        return $data['file'];
    }

    /**
     * 会员领取状态
     * @param $value
     * @param $data
     * @return array|mixed|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getMemberStateAttr($value, $data)
    {
        $number = (new MemberCoupon())
            ->where([
                ['member_id', '=', request()->mid ?? ''],
                ['coupon_id', '=', $data['coupon_id']],
            ])
            ->count();
        return ($data['limit_num'] != 0 && $number >= $data['limit_num']) ? 1 : 0;
    }

    // 变更优惠券大礼包状态
    public function changeIsGit($id)
    {
        //查询当前状态
        $curStatus = $this->where([$this->pk => $id])->value('is_gift');
        //更改当前状态
        $this->isUpdate(true)->where([$this->pk => $id])->update(['is_gift' => $curStatus ? 0 : 1]);
    }

    // 优惠券关联抽奖活动商品
    public function CouponActivityPrize()
    {
        return $this->hasMany('LotteryPrize', 'prize_info', 'coupon_id');
    }

    /**
     * 关联用户领取优惠券
     * @return \think\model\relation\HasMany
     */
    public function memberCoupon()
    {
        return $this->hasMany('MemberCoupon', 'coupon_id', 'coupon_id');
    }


    /**
     * 更改商品属性表里的砍价金额(一对多关联更新没有)
     * @param $param
     * @param $coupon_id
     * @throws \Exception
     */
    public function setSub($param,$coupon_id)
    {
        $data = (new CouponAttr())->where(['coupon_id' => $coupon_id])->find();

        $subArr = [];
        if ($data) {
            foreach ($param['products_id'] as $key => $value) {
                array_push($subArr, [
                    'coupon_attr_id' => $param['coupon_attr_id'][$key],
                    'products_id' => $value,
                    'coupon_id' => $coupon_id,
                    'goods_id' => $param['classify_str'][$key],
                    'attr_full_subtraction_price' => $param['attr_full_subtraction_price'][$key],
                    'attr_actual_price' => $param['attr_actual_price'][$key]]);
            }
            (new CouponAttr())->isUpdate(true)->saveAll($subArr);
        }else{
            foreach ($param['products_id'] as $key => $value) {
                array_push($subArr, [
                    'products_id' => $value,
                    'coupon_id' => $coupon_id,
                    'goods_id' => $param['classify_str'],
                    'attr_full_subtraction_price' => $param['attr_full_subtraction_price'][$key],
                    'attr_actual_price' => $param['attr_actual_price'][$key]]);
            }
            (new CouponAttr())->isUpdate(false)->saveAll($subArr);
        }

    }
}