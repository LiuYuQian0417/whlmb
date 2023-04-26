<?php
declare(strict_types=1);

namespace app\computer\controller\goods;

use app\computer\model\Search as SearchModel;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Request;

/**
 * 搜索 - Joy
 * Class Search
 * @package app\computer\controller\goods
 */
class Search extends BaseController
{

//    /**
//     * 热门搜索 - 废弃
//     * @param Request $request
//     * @param RSACrypt $crypt
//     * @param SearchModel $searchModel
//     * @return mixed
//     */
//    public function hot(Request $request, RSACrypt $crypt, SearchModel $searchModel)
//    {
//        if ($request::isPost()) {
//            try {
//                // 获取参数
//                $param = $crypt->request();
//
//                // 默认值
//                $type = !empty($param['type']) ? $param['type'] : '1';
//
//                // 数据
//                $result = $searchModel->where('type', $type)->order('number', 'desc')->limit(8)->column('keyword');
//
//                return $crypt->response(['code' => 0, 'result' => $result]);
//
//            } catch (\Exception $e) {
//                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
//            }
//        }
//    }
}