<?php
declare(strict_types=1);

namespace app\common\model;

use think\Db;
use app\common\model\Goods as GoodsModel;
use app\common\model\Products as ProductsModel;

/**
 * 砍价商品信息表
 * Class CutGoods
 * @package app\common\model
 */
class CutGoods extends BaseModel
{
    protected $pk = 'cut_goods_id';

    /**
     * 统计限时抢购商品
     * @return mixed
     */
    public function countCut()
    {
        $count['Self'] = $this
            ->alias('g')
            ->join('goods go', 'go.goods_id = g.goods_id and go.review_status = 1 and go.is_putaway = 1 and go.delete_time is null')
            ->join('store ss', 'ss.store_id = go.store_id and ss.status = 1 and ss.delete_time is null')
            ->where([
                ['ss.shop', '=', 0],
                ['g.status', '=', 1],
            ])
            ->count();
        $count['Other'] = $this
            ->alias('g')
            ->join('goods go', 'go.goods_id = g.goods_id and go.review_status = 1 and go.is_putaway = 1 and go.delete_time is null')
            ->join('store ss', 'ss.store_id = go.store_id and ss.status = 1 and ss.delete_time is null')
            ->where([
                ['ss.shop', 'in', '1,2'],
                ['g.status', '=', 1],
            ])
            ->count();
        return $count;
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
     * 砍价商品信息表 砍价表关联
     * @return \think\model\relation\HasOne
     */
    public function Goods()
    {
        return $this
            ->hasOne('Goods', 'goods_id', 'goods_id')
            ->field('goods_id,goods_name,shop_price,cut_price,file');
    }

    /**
     * 砍价列表
     * @return \think\model\relation\HasOne
     */
    public function listGoods()
    {
        return $this
            ->hasOne('Goods', 'goods_id', 'goods_id')
            ->where([
                ['is_putaway', '=', 1],
            ])
            ->field('goods_name,goods_id,store_id,shop_price,cut_price,file')
            ->with(['store']);
    }


    /**
     * 更改商品属性表里的砍价金额(一对多关联更新没有)
     * @param $param
     * @throws \Exception
     */
    public function setSub($param)
    {
        $subArr = [];
        if ($param['products_id']) {
            foreach ($param['products_id'] as $key => $value) {
                array_push($subArr, ['products_id' => $value, 'attr_cut_price' => $param['attr_cut_price'][$key],
                    'attr_single_cut_min' => $param['attr_single_cut_min'][$key],
                    'attr_single_cut_max' => $param['attr_single_cut_max'][$key]]);
            }
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
            'cut_price' => $param['cut_price'],
            'active_begin_time' => $param['up_shelf_time'],
            'active_end_time' => $param['down_shelf_time'],
            'is_bargain' => $param['status']
        ];
        if ($goodsArr) {
            (new GoodsModel())->isUpdate(true)->save($goodsArr);
        }
    }


    /**
     * 删除砍价
     * @param $cutId
     * @return bool
     */
    public function cutDelete($cutId)
    {
        Db::startTrans();
        try {
            $goodsId = implode($this
                ->where([
                    ['cut_goods_id', 'in', $cutId],
                ])
                ->column('goods_id'), ',');
            // 更改商品属性团购价格为0
            ProductsModel::where('goods_id', 'in', $goodsId)->update(['attr_cut_price' => 0,
                'attr_single_cut_min' => 0, 'attr_single_cut_max' => 0]);
            // 更改商品主表
            GoodsModel::where('goods_id', 'in', $goodsId)->update(['cut_price' => 0,
                'is_bargain' => 0, 'active_begin_time' => null, 'active_end_time' => null]);
            // 删除主表
            $this::destroy($cutId);
            // 提交事务
            Db::commit();
            return true;
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return false;
        }
    }
}