<?php
declare(strict_types = 1);

namespace app\common\model;

use think\facade\Cache;
use think\facade\Env;

/**
 * 商品分类模型
 * Class Manage
 * @package app\common\model
 */
class GoodsClassify extends BaseModel
{
    protected $pk = 'goods_classify_id';

    public function setCount($parent_id, $id)
    {
        $count = $this
                ->where([
                    ['goods_classify_id', '=', $parent_id],
                ])
                ->value('count') + 1;
        $this
            ->where([
                ['goods_classify_id', '=', $id],
            ])
            ->update(['count' => $count]);
    }

    /**
     * 商品列表
     * 获取图片oss地址
     * @param $value
     * @return mixed
     */
    public function getWebFileAttr($value)
    {

        if ($this->isPrivateOss && $value) {
            $ossManage = app('app\\common\\service\\OSS');
            //            $res = $ossManage->getSignUrlForGet($value . config('oss.')['style'][0]);
            $res = $ossManage->getSignUrlForGet($value);
            $value = ($res['code'] === 0) ? $res['url'] : '';
        }
        return $value;
    }

    /**
     * @param $value
     * @param $data
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getGoodsListAttr($value, $data)
    {
        // 功能状态条件
        Env::load(Env::get('app_path') . 'common/ini/.config');
        $function_status = [];
        // 拼团关闭
        if (Env::get('is_group', 1) == 0) {
            $function_status[] = ['is_group', 'eq', '0'];
        }
        // 砍价关闭
        if (Env::get('is_cut', 1) == 0) {
            $function_status[] = ['is_bargain', 'eq', '0'];
        }
        // 限时抢购关闭
        if (Env::get('is_limit', 1) == 0) {
            $function_status[] = ['is_limit', 'eq', '0'];
        }
        $list = (new Goods())
            ->alias('g')
            ->where([
                ['goods_number', '>', 0],
                ['review_status', '=', 1],
                ['is_putaway', '=', 1],
                ['goods_classify_id', 'in', implode(',', array_column(getParCate($data['goods_classify_id'], (new GoodsClassify()), 0), 'goods_classify_id'))],
            ])
            ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
            ->where($function_status)
            ->field('g.goods_id,g.is_limit,g.is_bargain,g.is_group,g.goods_name,
            g.file,g.shop_price,g.is_vip,g.group_price,g.cut_price,g.time_limit_price')
            ->order([
                'g.is_best' => 'desc',
                'g.is_hot' => 'desc',
                'g.is_popularity' => 'desc',
                'g.sort' => 'desc',
            ])
            ->limit(isset($data['limit']) ? $data['limit'] : 7)
            ->select();
        return $list;
    }

    public static function init()
    {
        self::beforeWrite(
            function ($e) {
                $file = self::upload('image', 'goods_classify/file/' . date('Ymd') . '/');
                if ($file) {
                    $e->file = $file['ossUrl'];
                }
                $web_file = self::upload('image1', 'goods_classify/file/' . date('Ymd') . '/');
                if ($web_file) {
                    $e->web_file = $web_file['ossUrl'];
                }
                $e->update_time = date('Y-m-d H:i:s');
            }
        );
        self::beforeInsert(
            function ($e) {
                $e->create_time = date('Y-m-d H:i:s');

            }
        );
        //写入后更新分类缓存D
        self::afterWrite(function () {
            (new GoodsClassify)->getTree(2);
        });
        //删除后更新分类缓
        self::afterDelete(function () {
            (new GoodsClassify)->getTree(2);
        });
    }

    /**
     * 接口获取下级分类
     */
    public function subset()
    {
        return $this
            ->hasMany('GoodsClassify', 'parent_id', 'goods_classify_id')
            ->where([
                ['status', '=', 1],
            ])
            ->field('goods_classify_id,title,web_file,parent_id')
            ->order(['sort' => 'desc', 'goods_classify_id' => 'asc']);
    }

    /**
     * 获取首页广告名称
     * @return mixed
     */
    public function Adv()
    {
        return $this->hasOne('Adv', 'adv_id', 'adv_id')->where([['status', '=', 1]]);
    }

    /**
     * 获取电脑端首页广告名称
     * @return mixed
     */
    public function PcAdv()
    {
        return $this->hasOne('Adv', 'adv_id', 'pc_adv_id');
    }


    /**  pc添加
     * @param int $type 是否强制更新  1否 2是
     * @return array|mixed|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTree($type = 1)
    {
        //        if (!Cache::has('pc_Goods_classify') OR $type == 2) {
        $Goods_classify = self::with(
            [
                'TreeRelevance' => function ($query) {
                    $query->with('TreeRelevance');
                },
            ]
        )->where([['parent_id', '=', 0], ['status', '=', 1]])->field(
            'goods_classify_id,title,web_file,parent_id'
        )->order('sort desc')->select();
        Cache::set('pc_Goods_classify', $Goods_classify);
        //        }
        //        return $Goods_classify ?? Cache::get('pc_Goods_classify');
        return $Goods_classify;
    }

    //树形结构商品分类自关联
    public function TreeRelevance()
    {
        return $this->hasMany('GoodsClassify', 'parent_id', 'goods_classify_id')
            ->where([['status', '=', 1]])
            ->field('status,goods_classify_id,title,web_file,parent_id')
            ->order(['sort' => 'asc', 'goods_classify_id' => 'asc']);
    }

    /**
     * 获取分类首页广告名称
     * @return mixed
     */
    public function ClassifyAdv()
    {
        return $this->hasOne('Adv', 'adv_id', 'classify_adv_id');
    }

    public function childrenCount()
    {
        return $this->where('parent_id', $this->goods_classify_id)->count();
    }

}