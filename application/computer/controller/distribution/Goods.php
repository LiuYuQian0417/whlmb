<?php
declare(strict_types=1);

namespace app\computer\controller\distribution;

use app\computer\model\Distribution;
use app\computer\model\Goods as GoodsModel;
use app\computer\controller\BaseController;
use think\facade\Session;
use think\facade\Request;
use app\interfaces\behavior\Distribution as DistributionBehavior;
use app\computer\model\Member;
use think\facade\Env;


class Goods extends BaseController
{
    /**
     * 分销商品列表
     * @param GoodsModel $goods
     * @param Distribution $distribution
     * @param DistributionBehavior $distributionBehavior
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function goods_list(GoodsModel $goods, Distribution $distribution ,DistributionBehavior $distributionBehavior)
    {
        $param = Request::instance()->param();
        $param['member_id'] = Session::get('member_info')['member_id'];

        $distribution_info = $distribution
            ->where([['member_id', '=', $param['member_id']]])->count();

        //排除活动
        $condition = [
            ['is_limit', '=', 0],
            ['is_group', '=', 0],
            ['is_bargain', '=', 0],
        ];

        if (empty($distribution_info)){
            //开启注册后用户自动成为分销商自动会员成为分销商
            if (Env::get('distribution_register', 0) == 1)
            {
                //自动成为分销商
                $distributionBehavior->toBeDistributor(
                    array_merge(
                        Member::get($param['member_id'])->toArray(),
                        [
                            'distribution_superior' => 0,
                            'bType'                 => 2,
                            //成为分销商途径注册自动成为分销商
                            'text'                  => 2,//注册即成为分销商
                        ]
                    )
                );
            }

            //开启购买指定商品成为分销商
            if(Env::get('distribution_buy', 0) == 1){
                // 分销商指定商品
                $condition[] = [
                    ['goods.is_distributor', '=', 1],  //是否分销
                    ['goods.review_status', '=', 1],   //是否审核
                    ['goods.is_putaway', '=', 1],      //是否上架
                ];
            }else{
                //分销商商品
                $condition[] = [
                    ['goods.is_distribution', '=', 1],  //是否分销商商品
                    ['goods.review_status', '=', 1],   //是否审核
                    ['goods.is_putaway', '=', 1],      //是否上架
                ];
            }
        }else{
            // 分销商可分销商品
            $condition[] = [
                ['goods.is_distribution', '=', 1],  //是否分销商商品
                ['goods.review_status', '=', 1],   //是否审核
                ['goods.is_putaway', '=', 1],      //是否上架
            ];
        }

        if (array_key_exists('goods_classify_id', $param))
        {
            $condition[] = ['goods.goods_classify_id', '=', $param['goods_classify_id'] ?: 0];
        }
        $data = $goods
            ->alias('goods')
            ->join('store store','goods.store_id = store.store_id and '.self::store_auth_sql('store'))
            ->where(self::goods_where($condition,'goods'))
            ->field(
                'goods.goods_id,goods_name,shop_price,
                    goods.sales_volume,shop,store_name,goods.file'
            )
            ->orderRand()
            ->order(['goods.sort' => 'desc', 'goods.goods_id' => 'desc'])
            ->append(['attribute_list', 'limit_state'])
            ->paginate(20, FALSE, $param);

        // 折扣
        $discount = discount($param['member_id']);

        return $this->fetch(
            '',
            [
                'result'       => $data,
                'discount'     => $discount,
                'header_title' => '代言专区',
            ]
        );

    }
}