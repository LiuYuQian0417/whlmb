<?php
declare(strict_types=1);

namespace app\computer\controller\goods;

use app\computer\model\Adv;
use app\computer\model\GoodsClassify;
use app\computer\model\Brand;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Env;
use think\facade\Request;

/**
 * 商品分类
 * Class Classify
 * @package app\computer\controller\goods
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
     * @param Request $request
     * @param RSACrypt $crypt
     * @param GoodsClassify $goodsClassify
     * @return mixed
     */
    public function parent(Request $request, RSACrypt $crypt, GoodsClassify $goodsClassify)
    {
        if ($request::isPost()) {
            try {
                $result = $goodsClassify
                    ->where(['parent_id' => 0, 'status' => 1])
                    ->field('classify_adv_id,goods_classify_id,title,web_file')
                    ->order(['sort' => 'desc', 'goods_classify_id' => 'desc'])
                    ->select();
                return $crypt->response(['code' => 0, 'result' => $result, 'level' => Env::get('navigation_status')]);
            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }

    /**
     * 二级分类
     * @param Request $request
     * @param RSACrypt $crypt
     * @param GoodsClassify $goodsClassify
     * @param Brand $brand
     * @param Adv $adv
     * @return mixed
     */
    public function subordinate(Request $request, RSACrypt $crypt, GoodsClassify $goodsClassify, Brand $brand, Adv $adv)
    {
        if ($request::isPost()) {
            try {

                $param = $crypt->request();

                // 验证
                $check = $goodsClassify->valid($param, 'subordinate');
                if ($check['code']) return $crypt->response($check);

                //  判断二级或三级
                $parameter = Env::get('navigation_status') == 2 ? 'subset' : '';

                // 分类
                $result = $goodsClassify
                    ->where(['parent_id' => $param['parent_id'], 'status' => 1])
                    ->with($parameter)
                    ->field('goods_classify_id,title,web_file')
                    ->order(['sort' => 'desc', 'goods_classify_id' => 'desc'])
                    ->select();

                // 品牌
//                $brand_list = $brand
//                    ->where('goods_classify_id in (' . $param['parent_id'] . ')')
//                    ->field('brand_id,brand_name,brand_logo')
//                    ->order(['is_recommend' => 'desc', 'sort' => 'desc'])
//                    ->limit(12)
//                    ->select();

                // 广告
//                $adv_info = $adv->where('adv_id', $param['classify_adv_id'])->field('type,file,content')->find();

//                return $crypt->response(['code' => 0, 'result' => $result, 'brand_list' => $brand_list, 'adv_info' => $adv_info]);
                return $crypt->response(['code' => 0, 'result' => $result]);
            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }
}