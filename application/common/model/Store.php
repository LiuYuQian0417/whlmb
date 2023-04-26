<?php
declare(strict_types=1);

namespace app\common\model;

use app\common\model\Member as MemberModel;
use app\common\model\Goods as GoodsModel;
use think\facade\Env;

/**
 * 店铺模型
 * Class Manage
 * @package app\common\model
 */
class Store extends BaseModel
{
    protected $pk = 'store_id';

    public static function init()
    {
        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s');
            if (property_exists($e, 'end_time') || empty($e->end_time)) {
                $e->end_time = NULL;
            }
        });
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
        });
    }

    /**
     * 获取用户入驻状态
     * @param $member_id
     * @return int
     */
    public function inStore($member_id)
    {
        $val = null;
        if ($member_id) {
            $val = $this
                ->where([
                    ['member_id', '=', $member_id],
                ])
                ->value('member_id');
        }
        return is_null($val) ? 0 : 1;
    }

    /**
     * 统计商家得商品数量
     * @param $goods_id
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function countGoodsNum($goods_id)
    {
        $store_id = array_unique(GoodsModel::withTrashed()->where('goods_id', 'in', $goods_id)->column('store_id'));
        $store_id = implode(',', $store_id);
        $where = [
            ['is_putaway', 'eq', 1],
            ['review_status', 'eq', 1],
            ['store_id', 'in', $store_id],
        ];
        $gn = GoodsModel::withTrashed()->where($where)->field('store_id , count(goods_id) as goods_num')->having("isNull(store_id) = 0")->select();

        if (!$gn->isEmpty()) {
            $this->allowField(true)->isUpdate(true)->saveAll($gn->toArray());
        }
    }

    /**
     * 商家关联品牌分类
     * @param $store_id
     * @param $brand_classify_id
     * @return mixed
     */
    public function editBrandClassify($store_id, $brand_classify_id)
    {
        if (!empty($store_id)) {
            (new Store())
                ->where([
                    ['store_id', 'in', $store_id],
                ])
                ->update(['brand_classify_id' => $brand_classify_id]);
        } else {
            (new Store())
                ->where([
                    ['brand_classify_id', 'eq', $brand_classify_id],
                ])
                ->update(['brand_classify_id' => NUll]);
        }
    }

    public function getBackImageAttr($value)
    {
        if ($value) {
            $config = config('oss.');
            $value = $config['prefix'] . $value;
        }
        return is_null($value) ? '' : $value;
    }
    
    public function getPcBackImageAttr($value)
    {
        if ($value) {
            $config = config('oss.');
            $value = $config['prefix'] . $value;
        }
        return is_null($value) ? '' : $value;
    }

    public function getGoodImageAttr($value)
    {
        if ($value) {
            $config = config('oss.');
            $value = $config['prefix'] . $value;
        }
        return is_null($value) ? '' : $value;
    }

    public function getBrandImageAttr($value)
    {
        if ($value) {
            $config = config('oss.');
            $value = $config['prefix'] . $value;
        }
        return is_null($value) ? '' : $value;
    }

    public function getPcHeadBackImageAttr($value)
    {
        if ($value) {
            $config = config('oss.');
            $value = $config['prefix'] . $value;
        }
        return is_null($value) ? '' : $value;
    }


    /**
     * 会员账号
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getMemberNameAttr($value, $data)
    {
        return (new MemberModel)
            ->where([
                ['member_id', '=', $data['member_id']],
            ])
            ->value('phone');
    }

    /**
     * 店铺类型
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getShopNameAttr($value, $data)
    {
        $status = [0 => '自营店铺', 1 => '个人店铺', 2 => '公司店铺'];
        return $status[$data['shop']];
    }

    /**
     * 主营分类
     * @param $value
     * @param $data
     * @return string
     */
    public function getCategoryNameAttr($value, $data)
    {
        $categoty = (new StoreClassify())
            ->where([
                ['store_classify_id', 'in', $data['category']],
            ])
            ->column('title');
        return implode(',', $categoty);
    }

    /**
     * 审核状态
     * @param $value
     * @param $data
     * @return string
     */
    public function getStatusNameAttr($value, $data)
    {
        $status = [0 => '待审核', 1 => '通过', 2 => '未通过', 3 => '待审核', 4 => '通过', 5 => '未通过'];
        return $status[$data['status']];
    }

    public function getProvinceIdAttr($value, $data)
    {
        return (new Area())
            ->where([
                ['area_name', '=', $data['province']],
            ])
            ->value('area_id');
    }

    public function getCityIdAttr($value, $data)
    {
        return (new Area())
            ->where([
                ['area_name', '=', $data['city']],
            ])
            ->value('area_id');
    }

    public function getAreaIdAttr($value, $data)
    {
        return (new Area())
            ->where([
                ['area_name', '=', $data['area']],
            ])
            ->value('area_id');
    }

    public function getStreetIdAttr($value, $data)
    {
        return (new Area())
            ->where([
                ['area_name', '=', $data['street']],
            ])
            ->value('area_id');
    }

    /**
     * 定义认证信息关联
     * @return \think\model\relation\HasOne
     */
    public function auth()
    {
        return $this->hasOne('StoreAuth');
    }

    /**
     * 海报商品
     * @return \think\model\relation\HasOne
     */
    public function posterGoods()
    {
        return $this->hasOne('Goods', 'store_id', 'store_id')
            ->where([
                ['is_putaway', '=', 1],
                ['store_poster', '=', 1]
            ])
            ->field('file,store_id');
    }

    /**
     * 关联店铺的费用设置
     */
    public function costs()
    {
        return $this->hasMany('StoreCosts','store_classify_id','category');
    }

    public function GoodsAllPrice()
    {
        return $this->hasMany('OrderAttach', 'store_id', 'store_id');
    }
    /**
     * 店铺推荐商品
     * @param $value
     * @param $data
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getShopGoodsAttr($value, $data)
    {
        // 功能状态条件
        Env::load(Env::get('app_path') . 'common/ini/.config');
        $function_status = [];
        // 拼团关闭
        if (Env::get('is_group') == 0) {
            $function_status[] = ['is_group', 'eq', '0'];
        }
        // 砍价关闭
        if (Env::get('is_cut') == 0) {
            $function_status[] = ['is_bargain', 'eq', '0'];
        }
        // 限时抢购关闭
        if (Env::get('is_limit') == 0) {
            $function_status[] = ['is_limit', 'eq', '0'];
        }
        $goods_list = (new Goods())
            ->where([
                ['store_id', '=', $data['store_id']],
                ['is_putaway', '=', 1],
                ['review_status', '=', 1],
            ])
            ->where($function_status)
            ->field('file,shop_price,goods_id,goods_name,store_id,market_price,goods_id,is_limit,
            is_bargain,is_group,is_vip,group_price,cut_price,time_limit_price')
            ->order(['store_particularly_recommend' => 'desc', 'store_recommend' => 'desc'])
            ->limit(3)
            ->select();
        return $goods_list;
    }


    /**
     * 统计各种种类店铺数量
     * @return array
     */
    public function getCount()
    {
        $arr['Self'] = $this->where(['shop' => 0])->count();
        $arr['OtherSelf'] = $this->where([['shop', 'in', '1,2']])->count();
        return $arr;
    }

    /**
     * 店铺会员关联
     * @return \think\model\relation\HasOne
     */
    public function Member()
    {
        return $this->hasOne('Member', 'member_id', 'member_id');
    }


    /**
     * 更新发票有运费
     * @param $id
     */
    public function changeIsInvoicedFreight($id)
    {
        //查询当前状态
        $curStatus = $this
            ->where([
                [$this->pk, '=', $id],
            ])
            ->value('invoiced_freight');
        //更改当前状态
        $this->isUpdate(true)
            ->where([
                [$this->pk, '=', $id],
            ])
            ->update(['invoiced_freight' => $curStatus ? 0 : 1]);
    }

    public function changeIsSecondOrder($id)
    {
        //查询当前状态
        $curStatus = $this
            ->where([
                [$this->pk, '=', $id],
            ])
            ->value('invoiced_second_order');
        //更改当前状态
        $this
            ->isUpdate(true)
            ->where([
                [$this->pk, '=', $id],
            ])
            ->update(['invoiced_second_order' => $curStatus ? 0 : 1]);
    }

}