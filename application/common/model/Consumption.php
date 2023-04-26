<?php
// 会员提现充值表
declare(strict_types=1);

namespace app\common\model;

use app\common\model\Member as MemberModel;

class Consumption extends BaseModel
{
    protected $pk = 'consumption_id';

    /**
     * 消费类型获取器
     * @param $value
     * @return mixed
     */
    public function getTypeAttr($value)
    {
        $typesArr = [0 => '充值', 1 => '佣金转入', 2 => '消费', 3 => '退款'];
        return $typesArr[$value];
    }

    /**
     * 资金方式获取器
     * @param $value
     * @return mixed
     */
    public function getWayAttr($value)
    {
        $wayArr = [1 => '支付宝', 2 => '微信', 3 => '银行卡', 4 => '余额', 5 => '线下'];
        return $wayArr[$value];
    }

    /**
     * 审核状态获取器
     * @param $value
     * @return mixed
     */
    public function getStatusAttr($value)
    {
        $statusArr = [0 => '未通过', 1 => '通过', 2 => '待审核'];
        return $statusArr[$value];
    }

    /**
     * 更改会员金额
     * @param $param
     * @return mixed|string
     */
    public function updateUsableMoney($param)
    {
        $usable_money = (string)(new MemberModel())
            ->where([
                ['member_id', '=', $param['member_id']],
            ])
            ->value('usable_money');
        if ($param['type'] == 1) {
            $usable_money = bcsub($usable_money, $param['price'], 2);
            (new MemberModel())
                ->allowField(true)
                ->isUpdate(true)
                ->save(['usable_money' => $usable_money], ['member_id' => $param['member_id']]);
        } else {
            $usable_money = bcadd($usable_money, $param['price'], 2);
            (new MemberModel())
                ->allowField(true)
                ->isUpdate(true)
                ->save(['usable_money' => $usable_money], ['member_id' => $param['member_id']]);
        }
        return $usable_money;
    }

    // 会员消费明细表 会员表关联
    public function Member()
    {
        return $this->hasOne('Member', 'member_id', 'member_id');
    }


}