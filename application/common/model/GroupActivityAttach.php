<?php
declare(strict_types = 1);

namespace app\common\model;

/**
 * 拼团活动附表
 * Class GroupActivityAttach
 * @package app\common\model
 */
class GroupActivityAttach extends BaseModel
{
    protected $pk = 'group_activity_attach_id';
    
    /**
     * 获取秒数(拼团剩余时间)
     * @param $value
     * @param $data
     * @return false|int
     */
    public function getContinueTimeAttr($value, $data)
    {
        $ct = strtotime($data['end_time']) - time();
        return $ct < 0 ? -1 : $ct;
    }
    
    /**
     * 获取参团状态
     * @param $value
     * @param $data
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getStateAttr($value, $data)
    {
        $member_id = request(true)->mid ?? '';
        return $member_id ? ((new GroupActivityAttach())
            ->where([
                ['group_activity_id', '=', $data['group_activity_id']],
                ['member_id', '=', $member_id]
            ])
            ->value('group_activity_attach_id') ?: 0) : 0;
    }
    
    /**
     * 获取拼团价格(原价或规格价)
     * @param $value
     * @param $data
     * @return int
     */
    public function getOriginalPriceAttr($value, $data)
    {
        if (array_key_exists('attr_shop_price', $data) && $data['attr_shop_price']) {
            return $data['attr_shop_price'];
        } elseif (array_key_exists('shop_price', $data) && $data['shop_price']) {
            return $data['shop_price'];
        }
        return 0;
    }
    
    /**
     * 团长
     * @param $value
     * @param $data
     * @return array|null|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getRegimentalAttr($value, $data)
    {
        return (new Member())
            ->where([
                ['member_id', '=', $data['owner']]
            ])
            ->field('member_id,nickname,avatar')
            ->find();
    }
    
    /**
     * 参与的人
     * @param $value
     * @param $data
     * @return array
     */
    public function getTakeAttr($value, $data)
    {
        $take = $this
            ->where([
                ['group_activity_id', '=', $data['group_activity_id']]
            ])
            ->order(['create_time' => 'asc'])
            ->column('member_id');
        return $take;
    }
    
    /**
     * 会员表 团购活动附属表 关联
     * @return \think\model\relation\BelongsTo
     */
    public function Member()
    {
        return $this
            ->belongsTo('member', 'member_id', 'member_id')
            ->field('member_id,username,phone,nickname,web_open_id,
            subscribe_time,micro_open_id,phone');
    }
}