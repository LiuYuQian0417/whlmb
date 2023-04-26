<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 拼团活动主表
 * Class GroupActivity
 * @package app\common\model
 */
class GroupActivity extends BaseModel
{
    protected $pk = 'group_activity_id';

    // 各种情况团的统计
    public function countOrder()
    {

        $_whereAll = [];
        $_whereIng = [
            ['GA.status', '=', 1],
        ];
        $_whereSuccess = [
            ['GA.status', '=', 2],
        ];
        $_whereFail = [
            ['GA.status', '=', 3],
        ];

        // 单店
        if (config('user.one_more') != 1) {
            $_oneStoreId = config('user.one_store_id');

            $_whereAll[] = $_whereIng[] = $_whereSuccess[] = $_whereFail[] = [
                'G.store_id',
                '=',
                $_oneStoreId,
            ];
        }

        //所有团
        $ret['all'] = $this->alias('GA')
            ->join('GroupGoods GG', 'GG.group_goods_id = GA.group_goods_id')
            ->join('Goods G','G.goods_id = GG.goods_id')
            ->where($_whereAll)
            ->count();
        //进行中团
        $ret['ing'] = $this->alias('GA')
            ->join('GroupGoods GG', 'GG.group_goods_id = GA.group_goods_id')
            ->join('Goods G','G.goods_id = GG.goods_id')
            ->where($_whereIng)
            ->count();
        //成功团
        $ret['success'] = $this->alias('GA')
            ->join('GroupGoods GG', 'GG.group_goods_id = GA.group_goods_id')
            ->join('Goods G','G.goods_id = GG.goods_id')
            ->where($_whereSuccess)
            ->count();
        //失败团
        $ret['fail'] = $this->alias('GA')
            ->join('GroupGoods GG', 'GG.group_goods_id = GA.group_goods_id')
            ->join('Goods G','G.goods_id = GG.goods_id')
            ->where($_whereFail)
            ->count();
        return $ret;
    }

    // 团购活动 商品关联
    public function Goods()
    {
        return $this->hasOne('goods', 'goods_id', 'goods_id');
    }

    // 团购活动 团购商品信息 关联
    public function GroupGoods()
    {
        return $this->belongsTo('group_goods', 'group_goods_id', 'group_goods_id');
    }

    /**
     * 团购活动 团购活动附属表 关联
     * @return \think\model\relation\HasMany
     */
    public function GroupActivityAttach()
    {
        return $this->hasMany('group_activity_attach', 'group_activity_id', 'group_activity_id');
    }

    /**
     * 团购详情(关联团购附表)
     * @return \think\model\relation\HasMany
     */
    public function groupActivityAttachDetails()
    {
        return self::GroupActivityAttach()->field('delete_time', TRUE);
    }

    /**
     * 拼团消息设定
     * @return \think\model\relation\HasMany
     */
    public function groupActivityAttachMsg()
    {
        return self::groupActivityAttachDetails()->with(['Member']);
    }


}