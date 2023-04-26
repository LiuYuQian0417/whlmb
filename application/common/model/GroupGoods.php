<?php
declare(strict_types=1);

namespace app\common\model;

use think\Db;
use app\common\model\Goods as GoodsModel;
use app\common\model\Products as ProductsModel;
use app\common\model\GroupActivity as GroupActivityModel;

/**
 * 拼团商品信息表
 * Class GroupGoods
 * @package app\common\model
 */
class GroupGoods extends BaseModel
{
    protected $pk = 'group_goods_id';

    /**
     * 获取秒数
     * @param $value
     * @return mixed
     */
    public function getContinueTimeAttr($value, $data)
    {
        $continue_time = $value;
        if (array_key_exists('end_time', $data)) {
            $last = strtotime($data['end_time']) - time();
            $continue_time = ($last >= 0) ? $last : 0;
        }
        return $continue_time;
    }


    /**
     * 更改商品属性表里的团购金额(一对多关联更新没有)
     * @param $param
     * @throws \Exception
     */
    public function setSub($param)
    {
        $subArr = [];
        if ($param['products_id'])
            foreach ($param['products_id'] as $key => $value) {
                array_push($subArr, ['products_id' => $value, 'attr_group_price' => $param['attr_group_price'][$key]]);
            }
        if ($subArr) {
            (new ProductsModel())->isUpdate(true)->saveAll($subArr);
        }
    }

    /**
     * 更改商品表的信息
     * @param $param
     */
    public function setGoods($param)
    {
        $goodsArr = [
            'goods_id' => $param['goods_id'],
            'active_begin_time' => $param['up_shelf_time'],
            'active_end_time' => $param['down_shelf_time'],
            'group_price' => $param['group_price'],
            'group_num' => $param['group_num'],
            'is_group' => $param['status']
        ];
        if ($goodsArr) {
            (new GoodsModel())->isUpdate(true)->save($goodsArr);
        }
    }


    /**
     * 删除团购商品
     * @param $groupId
     * @return bool
     */
    public function groupDelete($groupId)
    {
        try {
            Db::startTrans();
            $goodsId = implode($this
                ->where([
                    ['group_goods_id', 'in', $groupId],
                ])
                ->column('goods_id'), ',');
            // 更改商品属性团购价格为0
            ProductsModel::where('goods_id', 'in', $goodsId)->update(['attr_group_price' => 0]);
            // 更改商品主表
            GoodsModel::where('goods_id', 'in', $goodsId)
                ->update(['group_price' => 0, 'group_num' => 0, 'is_group' => 0, 'active_begin_time' => null, 'active_end_time' => null]);
            // 删除主表
            $this::destroy($groupId);
            // 提交事务
            Db::commit();
            return true;
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return false;
        }
    }

    /**
     * 活动状态获取器
     * @param $value
     * @param $data
     * @return string
     */
    public function getActivityTextAttr($value, $data)
    {
        $now = date('Y-m-d H:i:s');
        if ($data['status'] == 0) {
            $activityText = '未通过';
        } elseif ($data['status'] == 2) {
            $activityText = '待审核';
        } else {
            if ($now < $data['up_shelf_time']) {
                $activityText = '未开始';
            } else if ($now > $data['down_shelf_time']) {
                $activityText = '已结束';
            } else {
                $activityText = '进行中';
            }
        }
        return $activityText;
    }


    /**
     * 活动状态获取器
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getStatusTextAttr($value, $data)
    {
        $statusArr = [0 => '未通过', 1 => '已通过', 2 => '待审核'];
        return $statusArr[$data['status']];
    }


    /**
     * 团购购买人次获取器
     * @param $value
     * @param $data
     * @return int|string
     */
    public function getCountTextAttr($value, $data)
    {
        $count = (new GroupActivityModel())
            ->where([
                ['group_goods_id', '=', $data['group_goods_id']],
            ])->count();
        return $count;
    }


    /**
     * 团购商品 商品属性关联
     * @return \think\model\relation\HasMany
     */
    public function Products()
    {
        return $this->hasMany('products', 'goods_id', 'goods_id');
    }


    /**
     * 团购商品 商品表关联
     * @return \think\model\relation\HasOne
     */
    public function Goods()
    {
        return $this->hasOne('Goods', 'goods_id', 'goods_id')
            ->where('is_putaway', 1)
            ->field('goods_id,goods_name,shop_price,group_price,file');
    }

    /**
     * 统计团购商品
     * @return mixed
     */
    public function countGroup()
    {
        $count['Self'] = $this
            ->alias('g')
            ->join(['ishop_goods' => 'go'], 'go.goods_id = g.goods_id')
            ->join(['ishop_store' => 'ss'], 'ss.store_id = go.store_id')
            ->where([
                ['ss.shop', '=', 0],
            ])
            ->count();
        $count['Other'] = $this
            ->alias('g')
            ->join(['ishop_goods' => 'go'], 'go.goods_id = g.goods_id')
            ->join(['ishop_store' => 'ss'], 'ss.store_id = go.store_id')
            ->where([
                ['ss.shop', 'in', '1,2'],
            ])
            ->count();
        return $count;
    }
}