<?php
declare(strict_types = 1);

namespace app\interfaces\controller\goods;

use app\common\model\Article;
use app\common\model\GroupActivityAttach;
use app\common\model\GroupClassify;
use app\common\model\GroupGoods;
use app\common\model\Goods;
use app\common\model\Member;
use app\common\service\OSS;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Request;

/**
 * 拼团 - Joy
 * Class Search
 * @package app\interfaces\controller\goods
 */
class Group extends BaseController
{
    
    /**
     * 分类列表
     * @param RSACrypt $crypt
     * @param GroupClassify $groupClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function class_index(RSACrypt $crypt,
                                GroupClassify $groupClassify)
    {
        $result = $groupClassify
            ->with('subset')
            ->where([
                ['parent_id', '=', 0],
            ])
            ->field('group_classify_id,title')
            ->order('create_time', 'asc')
            ->select();
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 列表
     * @param RSACrypt $crypt
     * @param GroupGoods $groupGoods
     * @param GroupClassify $groupClassify
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          GroupGoods $groupGoods,
                          GroupClassify $groupClassify)
    {
        $param = $crypt->request();
        $condition = [
            ['gg.up_shelf_time', '<=', date('Y-m-d H:i:s')],
            ['gg.down_shelf_time', '>=', date('Y-m-d H:i:s')],
            ['gg.status', '=', 1],
            ['g.is_putaway', '=', 1],
            ['g.review_status', '=', 1],
            ['g.is_group', '=', 1],
        ];
        // 精选状态
        if ($param['is_best']) {
            array_push($condition, ['gg.is_best', '=', 1]);
        }
        $parent_id = $groupClassify
            ->where([
                ['parent_id', '=', $param['group_classify_id']],
            ])
            ->column('group_classify_id');
        // 分类
        if ($param['group_classify_id']) {
            array_push($condition, ['gg.group_classify_id', 'in', $parent_id ? implode(',', $parent_id) . ',' : '' . $param['group_classify_id']]);
        }
        $result = $groupGoods
            ->alias('gg')
            ->join('goods g', 'g.goods_id = gg.goods_id')
            ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
            ->where($condition)
            ->field('g.goods_id,gg.group_num,g.goods_name,g.group_price,g.shop_price,g.file')
            ->order('gg.create_time', 'desc')
            ->paginate(20, false, $param);
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 我的拼团列表
     * @param RSACrypt $crypt
     * @param GroupActivityAttach $groupActivityAttach
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function my_index(RSACrypt $crypt,
                             GroupActivityAttach $groupActivityAttach)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        $condition[] = ['group_activity_attach.member_id', '=', $param['member_id']];
        // 拼团状态
        if ($param['status'] <> NULL) {
            $condition[] = ['group_activity.status', '=', $param['status']];
        }
        $result = $groupActivityAttach
            ->alias('group_activity_attach')
            ->join('group_activity group_activity', 'group_activity.group_activity_id = group_activity_attach.group_activity_id')
            ->join('group_goods group_goods', 'group_goods.group_goods_id = group_activity.group_goods_id')
            ->join('order_attach order_attach', 'order_attach.group_activity_attach_id = group_activity_attach.group_activity_attach_id')
            ->join('order_goods order_goods', 'order_goods.order_attach_id = order_attach.order_attach_id')
            ->where($condition)
            ->field('group_activity_attach.group_activity_attach_id,
                    group_activity_attach.member_id,group_activity.status,attr,goods_name,
                    file,single_price,group_num,order_attach.order_attach_id')
            ->order('group_activity_attach.create_time', 'desc')
            ->paginate(20, false, $param);
        return $crypt->response(['code' => 0, 'result' => $result]);
    }
    
    /**
     * 拼购详情
     * @param RSACrypt $crypt
     * @param GroupActivityAttach $groupActivityAttach
     * @param Goods $goodsModel
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function view(RSACrypt $crypt,
                         GroupActivityAttach $groupActivityAttach,
                         Goods $goodsModel,
                         Member $member)
    {
        $param = $crypt->request();
        $param['member_id'] = request(true)->mid ?? '';
        $groupActivityAttach->valid($param, 'view');
        $result = $groupActivityAttach
            ->alias('gaa')
            ->join('group_activity ga', 'ga.group_activity_id = gaa.group_activity_id')
            ->join('group_goods gg', 'gg.group_goods_id = ga.group_goods_id')
            ->join('goods g', 'g.goods_id = gg.goods_id')
            ->join('products p', 'p.products_id = ga.products_id', 'left')
            ->join('order_attach oa', 'oa.group_activity_attach_id = gaa.group_activity_attach_id')
            ->join('order_goods og', 'og.order_attach_id = oa.order_attach_id')
            ->where([
                ['gaa.group_activity_attach_id', '=', $param['group_activity_attach_id']],
            ])
            ->field('gaa.group_activity_attach_id,ga.group_activity_id,ga.status,og.attr,og.goods_name,og.file,
            g.shop_price,p.attr_shop_price,og.single_price,gg.group_num,oa.order_attach_id,ga.end_time,gg.goods_id,
            og.store_id,ga.owner')
            ->append(['continue_time', 'state', 'take', 'original_price'])
            ->hidden(['shop_price', 'attr_shop_price'])
            ->find();
        $result['member_id'] = $param['member_id'];
        $result['participant'] = $member
            ->where([
                ['member_id', 'in', implode(',', $result['take'])],
            ])
            ->field('member_id,nickname,avatar')
            ->orderRaw('find_in_set(member_id,"' . implode(',', $result['take']) . '")')
            ->select();
        if (!$result) {
            return $crypt->response([
                'code' => -1,
                'message' => '拼团信息不存在',
            ], true);
        }
        // 团购列表
        $group_list = $goodsModel
            ->where([
                ['goods_number', '>', 0],
                ['review_status', '=', 1],
                ['is_putaway', '=', 1],
                ['is_group', '=', 1]
            ])
            ->field('goods_id,goods_name,file,group_num,group_success_num,group_price,shop_price')
            ->order('sort', 'desc')
            ->limit(4)
            ->select();
        $encrypt = [
            'group_activity_attach_id' => urlencode($crypt->singleEnc($param['group_activity_attach_id'])),
            'member_id' => $param['member_id'] ? urlencode($crypt->singleEnc($param['member_id'])) : '',
        ];
        return $crypt->response([
            'code' => 0,
            'result' => $result,
            'group_list' => $group_list,
            'encrypt' => $encrypt,
        ], true);
    }
    
    /**
     * 拼购详情 - web
     * @param RSACrypt $crypt
     * @param GroupActivityAttach $groupActivityAttach
     * @param Goods $goodsModel
     * @param Member $member
     * @return array|mixed|\think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function view_web(RSACrypt $crypt,
                             GroupActivityAttach $groupActivityAttach,
                             Goods $goodsModel,
                             Member $member)
    {
        $param = Request::get();
        $group_activity_attach_id = $crypt->singleDec($param['group_activity_attach_id']);
        $result = $groupActivityAttach
            ->alias('gaa')
            ->join('group_activity ga', 'ga.group_activity_id = gaa.group_activity_id')
            ->join('group_goods gg', 'gg.group_goods_id = ga.group_goods_id')
            ->join('goods g', 'g.goods_id = gg.goods_id')
            ->join('order_attach oa', 'oa.group_activity_attach_id = gaa.group_activity_attach_id')
            ->join('order_goods og', 'og.order_attach_id = oa.order_attach_id')
            ->where([
                ['gaa.group_activity_attach_id', '=', $group_activity_attach_id],
            ])
            ->field('gaa.group_activity_attach_id,ga.group_activity_id,gaa.member_id,ga.status,attr,
            og.goods_name,og.file,g.shop_price as original_price,single_price,gg.group_num,
            oa.order_attach_id,end_time,gg.goods_id,og.store_id,ga.owner')
            ->append(['continue_time', 'state', 'take', 'regimental'])
            ->find();
        if (is_null($result)) {
            return $crypt->response([
                'code' => -1,
                'message' => '拼团信息不存在',
            ], true);
        }
        $result['participant'] = $member
            ->where([
                ['member_id', 'in', implode(',', $result['take'])],
                ['member_id', '<>', $result['owner']]
            ])
            ->field('member_id,nickname,avatar')
            ->select();
        // 团购列表
        $group_list = $goodsModel
            ->where([
                ['goods_number', '>', 0],
                ['review_status', '=', 1],
                ['is_putaway', '=', 1],
                ['is_group', '=', 1],
            ])
            ->field('goods_id,goods_name,file,group_num,group_success_num,group_price,shop_price')
            ->order('sort', 'desc')
            ->limit(4)
            ->select();
        return view('view_web', [
            'result' => $result,
            'group_list' => $group_list,
        ]);
    }
    
    /**
     * 拼购详情 - web
     * @param Article $article
     * @return array|mixed|\think\response\View
     */
    public function rule(Article $article)
    {
        $wc = $article
            ->where([
                ['article_id', '=', 20],
            ])
            ->value('web_content');
        return view('rule', [
            'content' => $wc,
        ]);
    }
    
}