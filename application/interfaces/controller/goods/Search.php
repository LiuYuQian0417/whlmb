<?php
declare(strict_types=1);

namespace app\interfaces\controller\goods;

use app\common\model\Search as SearchModel;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;

/**
 * 搜索 - Joy
 * Class Search
 * @package app\interfaces\controller\goods
 */
class Search extends BaseController
{

    /**
     * 热门搜索
     * @param RSACrypt $crypt
     * @param SearchModel $searchModel
     * @return mixed
     */
    public function hot(RSACrypt $crypt,
                        SearchModel $searchModel)
    {
        $param = $crypt->request();
        $type = !empty($param['type']) ? $param['type'] : '1';
        $result = $searchModel
            ->where([
                ['type', '=', $type],
            ])
            ->order('number', 'desc')
            ->limit(8)
            ->column('keyword');
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
}