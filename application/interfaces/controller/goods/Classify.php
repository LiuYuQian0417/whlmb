<?php
declare(strict_types=1);

namespace app\interfaces\controller\goods;

use app\common\model\Adv;
use app\common\model\GoodsClassify;
use app\common\model\Brand;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Env;

/**
 * 商品分类 - Joy
 * Class Classify
 * @package app\interfaces\controller\goods
 */
class Classify extends BaseController
{

    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }

    /**
     * 一级分类
     * @param RSACrypt $crypt
     * @param GoodsClassify $goodsClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function parent(RSACrypt $crypt,
                           GoodsClassify $goodsClassify)
    {
        $result = $goodsClassify
            ->where([
                ['parent_id', '=', 0,],
                ['status', '=', 1],
            ])
            ->field('classify_adv_id,goods_classify_id,title,web_file')
            ->order(['sort' => 'desc', 'goods_classify_id' => 'desc'])
            ->select();
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
            'level' => Env::get('navigation_status', 0),
        ], true);
    }

    /**
     * 二级分类
     * @param RSACrypt $crypt
     * @param GoodsClassify $goodsClassify
     * @param Brand $brand
     * @param Adv $adv
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function subordinate(RSACrypt $crypt,
                                GoodsClassify $goodsClassify,
                                Brand $brand,
                                Adv $adv)
    {
        $param = $crypt->request();
        $goodsClassify->valid($param, 'subordinate');
        //  判断平台开启的是二级或三级分类导航
        $parameter = Env::get('navigation_status', 3) == 2 ? 'subset' : '';
        // 分类
        $result = $goodsClassify
            ->where([
                ['parent_id', '=', $param['parent_id']],
                ['status', '=', 1],
            ])
            ->with($parameter)
            ->field('goods_classify_id,title,web_file')
            ->order(['sort' => 'desc', 'goods_classify_id' => 'desc'])
            ->select();

        // 品牌
        $brand_list = $brand
            ->where('goods_classify_id in (' . $param['parent_id'] . ')')
            ->field('brand_id,brand_name,brand_logo')
            ->order(['is_recommend' => 'desc', 'sort' => 'desc'])
            ->limit(12)
            ->select();

        // 广告
        $adv_info = $adv
            ->where([
                ['adv_id', '=', $param['classify_adv_id']],
                ['status', '=', 1],
                ['start_time', ['exp', Db::raw('is null')], ['<=', date('Y-m-d H:i:s')], 'or'],
                ['end_time', ['exp', Db::raw('is null')], ['>=', date('Y-m-d H:i:s')], 'or'],
            ])
            ->field('adv_id,type,file,content')
            ->find();
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
            'brand_list' => $brand_list,
            'adv_info' => $adv_info ?: json([]),
        ], true);
    }
}