<?php
declare(strict_types=1);

namespace app\common\model;

use think\facade\Cache;
use think\facade\Config;

/**
 * 店铺收藏模型
 * Class CollectStore
 * @package app\common\model
 */
class CollectStore extends BaseModel
{
    protected $pk = 'collect_store_id';

    public static function init()
    {
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
        });
        self::afterInsert(function ($e) {
            (new Store())
                ->where([
                    ['store_id', '=', $e->store_id],
                ])
                ->setInc('collect');
            // 写入数量
            $prefix = Config::get('cache.default')['prefix'];
            Cache::handler()->zIncrBy($prefix . 'collect_store', 1, $e->member_id);
        });
    }

    /**
     * 收藏店铺推荐商品
     * @return \think\model\relation\HasMany
     */
    public function shopGoods()
    {
        return $this
            ->hasMany('Goods', 'store_id', 'store_id')
            ->where([
                ['is_putaway', '=', 1],
            ])
            ->field('file,shop_price,goods_id,goods_name,store_id')
            ->order(['store_particularly_recommend' => 'desc', 'store_recommend' => 'desc'])
            ->limit(4);
    }

    /**
     * 获取有效状态
     * @param $value
     * @param $data
     * @return int
     */
    public function getIsInvalidAttr($value, $data)
    {
        $ret = 0;
        if ($data['status'] != 4 || (!is_null($data['end_time']) && $data['end_time'] < date('Y-m-d'))) {
            $ret = 1;
        }
        return $ret;
    }
}