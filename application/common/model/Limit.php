<?php
declare(strict_types=1);

namespace app\common\model;

use think\Db;
use app\common\model\Goods as GoodsModel;
use app\common\model\Products as ProductsModel;

/**
 * 限时抢购商品信息表
 * Class Limit
 * @package app\common\model
 */
class Limit extends BaseModel
{
    protected $pk = 'limit_id';

    public static function init()
    {
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
            $e->exchange_num = $e->available_sale;
        });

        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s');
        });
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
     * 获取场次
     * @param $value
     * @param $data
     * @return string
     */
    public function getScreeningAttr($value, $data)
    {
        $arr = (new LimitInterval())
            ->where([
                ['limit_interval_id', 'in', $data['interval_id']],
            ])
            ->column('interval_name');
        return implode('/', $arr);
    }

    /**
     * 活动审核获取器
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
     * 更改商品属性表里的抢购金额(一对多关联更新没有)
     * @param $param
     * @throws \Exception
     */
    public function setSub($param)
    {
        $subArr = [];
        if ($param['products_id'])
            foreach ($param['products_id'] as $key => $value) {
                array_push($subArr, [
                    'products_id' => $value,
                    'attr_time_limit_price' => $param['attr_time_limit_price'][$key],
                    'attr_time_limit_number' => $param['attr_time_limit_number'][$key],
                ]);
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
            'time_limit_price' => $param['limit_price'],
            'is_limit' => $param['status'],
            'active_begin_time' => $param['up_shelf_time'],
            'active_end_time' => $param['down_shelf_time']
        ];
        if ($goodsArr) {
            (new GoodsModel())->isUpdate(true)->save($goodsArr);
        }
    }


    /**
     * 删除限时抢购商品
     * @param $limitId
     * @return bool
     */
    public function limitDelete($limitId)
    {
        try {
            Db::startTrans();
            $goodsId = implode($this->where('limit_id', 'in', $limitId)->column('goods_id'), ',');
            // 更改商品属性团购价格为0
            ProductsModel::where('goods_id', 'in', $goodsId)->update(['attr_time_limit_price' => 0, 'attr_time_limit_number' => 0]);
            // 更改商品主表
            GoodsModel::where('goods_id', 'in', $goodsId)
                ->update(['time_limit_price' => 0, 'active_begin_time' => null, 'active_end_time' => null, 'is_limit' => 0]);
            // 删除主表
            $this::destroy($limitId);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            return false;
        }
    }

    /**
     * 统计限时抢购商品
     * @return mixed
     */
    public function countLimit()
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

    /**
     * 限时抢购表 商品表 一对一关联
     * @return \think\model\relation\HasOne
     */
    public function Goods()
    {
        return $this->hasOne('Goods', 'goods_id', 'goods_id');
    }

}