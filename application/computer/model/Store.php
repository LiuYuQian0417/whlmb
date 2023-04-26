<?php
declare(strict_types=1);

namespace app\computer\model;

use \app\common\model\Store as StoreModel;
use app\common\service\OssImage;
use think\facade\Env;

/**
 * 店铺模型
 * Class Manage
 * @package app\common\model
 */
class Store extends StoreModel
{

    /**
     * 获取用户入驻状态
     * @param $member_id
     * @return int
     */
    public function inStore($member_id)
    {
        $val = NULL;
        if ($member_id)
        {
            $val = $this
                ->where('member_id', $member_id)
                ->value('member_id');
        }
        return is_null($val) ? 0 : 1;
    }

    /**
     * pc店铺推荐商品
     * @param $value
     * @param $data
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getStoreGoodsAttr($value, $data)
    {
        // 功能状态条件
        Env::load(Env::get('app_path') . 'common/ini/.config');
        $function_status = [];
        // 拼团关闭
        if (Env::get('is_group') == 0)
        {
            $function_status[] = ['is_group', 'eq', '0'];
        }
        // 砍价关闭
        if (Env::get('is_cut') == 0)
        {
            $function_status[] = ['is_bargain', 'eq', '0'];
        }
        // 限时抢购关闭
        if (Env::get('is_limit') == 0)
        {
            $function_status[] = ['is_limit', 'eq', '0'];
        }

        $goods_list = (new Goods())
            ->where(
                [
                    ['store_id', '=', $data['store_id']],
                    ['is_putaway', '=', 1],
                    ['review_status', '=', 1],
                ]
            )
            ->where($function_status)
            ->field(
                'file,shop_price,shop_price as goods_price,goods_id,goods_name,store_id,sales_volume,is_group,is_vip,is_bargain,is_limit,time_limit_price,group_num,group_price,cut_price'
            )
            ->order(['store_particularly_recommend' => 'desc', 'store_recommend' => 'desc'])
            ->limit(2)
            ->select();
        return $goods_list;
    }

    public function getPcHeadBackImageAttr($value)
    {
        if ($this->isPrivateOss && $value)
        {
            $ossManage = app('app\\common\\service\\OSS');
//            $res = $ossManage->getSignUrlForGet($value . config('oss.')['style'][0]);
            $res = $ossManage->getSignUrlForGet($value);
            $value = ($res['code'] === 0) ? $res['url'] : '';
        }
        return $value;
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
        if (Env::get('is_group') == 0)
        {
            $function_status[] = ['is_group', 'eq', '0'];
        }
        // 砍价关闭
        if (Env::get('is_cut') == 0)
        {
            $function_status[] = ['is_bargain', 'eq', '0'];
        }
        // 限时抢购关闭
        if (Env::get('is_limit') == 0)
        {
            $function_status[] = ['is_limit', 'eq', '0'];
        }

        $goods_list = (new Goods())
            ->where(
                [
                    ['store_id', '=', $data['store_id']],
                    ['is_putaway', '=', 1],
                    ['review_status', '=', 1],
                ]
            )
            ->where($function_status)
            ->field(
                'file,shop_price,shop_price as goods_price,is_group,is_bargain,is_limit,is_vip,cut_price,group_price,time_limit_price,goods_id,goods_name,store_id'
            )
            ->order(['store_particularly_recommend' => 'desc', 'store_recommend' => 'desc'])
            ->limit(3)
            ->select();
        return $goods_list;
    }


    /**
     * 评分
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getStorePercentAttr($value, $data)
    {
        return (new GoodsEvaluate)->storeEvaluate($data['store_id'] ?? 0);
    }

    /**
     * 获得店铺信息
     * @param array $field
     * @param int $store_id 店铺id 0则获取平台信息
     * @return array|\PDOStatement|string|\think\Model|void|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getStoreInfo($field = [], $store_id = 0)
    {

        if ($store_id)
        {
            $store_data = $this->where([['store_id', '=', $store_id]])->field($field)->append(
                    ['store_percent']
                )->find() ?? exception('店铺不存在');
            $store_data->append(['store_percent']);
        } else
        {
            //读取平台信息
            $store_data = [
                'store_id'      => 0,
                'store_name'    => env('title'),
                'logo'          => (new OssImage)->buildOssUrl(env('logo', ''))['url'] ?? '',
                'phone'         => env('phone'),
                'store_percent' => ['self_score' => 5, 'trend' => 0],
            ];
        }
        return $store_data;
    }
}