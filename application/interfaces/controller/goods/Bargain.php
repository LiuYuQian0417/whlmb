<?php
declare(strict_types = 1);

namespace app\interfaces\controller\goods;

use app\common\model\Adv;
use app\common\model\CutActivity;
use app\common\model\CutActivityAttach;
use app\common\model\CutGoods;
use app\common\model\Goods;
use app\common\model\Member;
use app\common\model\Products;
use app\common\service\Beanstalk;
use app\common\service\Lock;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Cache;
use think\facade\Env;
use think\facade\Request;

/**
 * 砍价 - Joy
 * Class Search
 * @package app\interfaces\controller\goods
 */
class Bargain extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('app_path') . 'common/ini/.config');
    }
    
    /**
     * 砍价列表
     * @param RSACrypt $crypt
     * @param CutGoods $cutGoods
     * @param Adv $adv
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          CutGoods $cutGoods,
                          Adv $adv)
    {
        $result = [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 10,
            'total' => 0,
            'data' => [],
        ];
        $adv_info = json([]);
        if (env('is_cut', 1)) {
            $result = $cutGoods
                ->alias('cg')
                ->where([
                    ['cg.up_shelf_time', '<=', date('Y-m-d H:i:s')],
                    ['cg.down_shelf_time', '>=', date('Y-m-d H:i:s')],
                    ['cg.status', '=', 1],
                    ['g.is_putaway', '=', 1],
                ])
                ->with('goods')
                ->join('goods g', 'g.goods_id = cg.goods_id')
                ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
                ->field('cg.goods_id,cg.up_shelf_time,cg.goods_id,cg.down_shelf_time')
                ->order(['cg.update_time' => 'desc', 'cg.cut_goods_id' => 'desc'])
                ->paginate(10, false);
            $adv_info = $adv
                ->where([
                    ['adv_position_id', '=', '11'],     //砍价列表banner
                    ['status', '=', 1],
                    ['start_time', ['<=', date('Y-m-d')], ['exp', Db::raw('is null')], 'or'],
                    ['end_time', ['>=', date('Y-m-d')], ['exp', Db::raw('is null')], 'or'],
                ])
                ->field('type,file,content')
                ->find();
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
            'adv_info' => $adv_info ?: json([]),
        ], true);
    }
    
    /**
     * 立即砍价
     * @param RSACrypt $crypt
     * @param CutGoods $cutGoods
     * @param CutActivity $cutActivity
     * @param Lock $lock
     * @param Goods $goodsModel
     * @param Products $products
     * @param CutActivityAttach $activityAttach
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function immediately(RSACrypt $crypt,
                                CutGoods $cutGoods,
                                CutActivity $cutActivity,
                                Lock $lock,
                                Goods $goodsModel,
                                Products $products,
                                CutActivityAttach $activityAttach)
    {
        if (env('is_cut', 1)) {
            $param = $crypt->request();
            $param['owner'] = request(0)->mid;
            $cutGoods->valid($param, 'immediately');
            // 查询砍价商品信息
            $cut = $cutGoods
                ->where([
                    ['goods_id', '=', $param['goods_id']],
                    ['up_shelf_time', '<=', date('Y-m-d')],
                    ['down_shelf_time', '>=', date('Y-m-d')],
                    ['status', '=', 1],
                ])
                ->field('cut_goods_id,continue_time,down_shelf_time')
                ->find();
            if (is_null($cut)) {
                return $crypt->response([
                    'code' => -1,
                    'message' => '该商品已下架或已过期',
                ], true);
            }
            // 砍价限制
            $cut_goods_id = $cutActivity
                ->alias('ca')
                ->join('cut_goods cg', 'cg.cut_goods_id = ca.cut_goods_id')
                ->where([
                    ['owner', '=', $param['owner']],
                    ['cg.goods_id', '=', $param['goods_id']],
                    ['up_shelf_time', '<=', date('Y-m-d')],
                    ['down_shelf_time', '>=', date('Y-m-d')],
                    ['ca.status', '=', '1'],            // 进行中活动
                ])
                ->value('cut_activity_id');
            if ($cut_goods_id) {
                return $crypt->response([
                    'code' => -2,
                    'message' => '您已经参与此商品砍价,请勿重复砍价',
                ], true);
            }
            $lockKey = ['goods_' . $param['goods_id']];
            if ($param['products_id']) {
                array_push($lockKey, 'products_' . $param['products_id']);
            }
            if ($param['goods_attr']) {
                // 读取属性价格
                $goods = $products
                    ->alias('p')
                    ->where([
                        ['p.goods_id', '=', $param['goods_id']],
                        ['p.goods_attr', '=', $param['goods_attr']]
                    ])
                    ->join('goods g', 'g.goods_id = p.goods_id')
                    ->join('store s', 'g.store_id = s.store_id')
                    ->field('p.attr_goods_number as goods_number,p.attr_single_cut_min as single_cut_min,
                        p.attr_single_cut_max as single_cut_max,p.attr_cut_price as cut_price,p.attr_shop_price as price,
                        s.store_name,g.goods_name,g.file')
                    ->find();
            } else {
                // 读取非属性价格
                $goods = $goodsModel
                    ->alias('g')
                    ->join('cut_goods cg', 'cg.goods_id = g.goods_id and cg.delete_time is null')
                    ->where([
                        ['g.goods_id', '=', $param['goods_id']],
                    ])
                    ->join('store s', 's.store_id = g.store_id')
                    ->field('goods_number,single_cut_min,single_cut_max,cut_price,shop_price as price,s.store_name,
                    g.goods_name,g.file')
                    ->find();
            }
            $getLock = $lock->lock($lockKey, 10000);
            if ($getLock && $goods['goods_number'] > 0) {
                // 商品原价/商品现价/商品底价/结束时间
                $param['cut_goods_id'] = $cut['cut_goods_id'];
                $param['original_price'] = $goods['price'];
                $param['present_price'] = $goods['price'];
                $param['cut_price'] = $goods['cut_price'];
                $param['end_time'] = ($cut['down_shelf_time'] . ' 23:59:59' > date('Y-m-d H:i:s', strtotime('+' . $cut['continue_time'] . ' hour'))) ?
                    date('Y-m-d H:i:s', strtotime('+' . $cut['continue_time'] . ' hour')) :
                    $cut['down_shelf_time'] . ' 23:59:59';
                Db::startTrans();
                // 新增砍价活动
                $cutActivity
                    ->allowField(true)
                    ->isupdate(false)
                    ->save($param);
                // 减库存
                if ($param['goods_attr']) {
                    $products
                        ->where([
                            ['goods_id', '=', $param['goods_id']],
                            ['goods_attr', '=', $param['goods_attr']]
                        ])
                        ->setDec('attr_goods_number', 1);
                }
                // 减主表库存
                $goodsModel
                    ->where([
                        ['goods_id', '=', $param['goods_id']],
                    ])
                    ->setDec('goods_number', 1);
                // 新增砍价记录
                $return_state = $this->getRandomMoney(
                    $cutActivity->cut_activity_id,
                    $param['owner'],
                    $goods,
                    $cutActivity,
                    $activityAttach
                );
                if ($return_state['code']) {
                    $lock->unlock($getLock);
                    return $crypt->response([
                        'code' => $return_state['code'],
                        'message' => $return_state['message'],
                    ], true);
                }
                Db::commit();
                // 发送消息队列[砍价到期未成功更改状态]
                (new Beanstalk())->put(json_encode([
                    'queue' => 'cutExpireChangeStatus',
                    'id' => $cutActivity->cut_activity_id,
                    'time' => date('Y-m-d H:i:s'),
                ]), $cut['continue_time'] * 3600);
                $lock->unlock($getLock);
                return $crypt->response([
                    'code' => 0,
                    'message' => '砍价成功',
                    'cut_activity_id' => $cutActivity->cut_activity_id,
                ], true);
            }
            return $crypt->response([
                'code' => -1,
                'message' => '网络繁忙,请重试',
            ], true);
        }
        return $crypt->response([
            'code' => -1,
            'message' => '当前模式不支持砍价',
        ], true);
    }
    
    /**
     * 我的砍价
     * @param RSACrypt $crypt
     * @param CutActivity $cutActivity
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function my_cut(RSACrypt $crypt,
                           CutActivity $cutActivity)
    {
        $result = [];
        if (env('is_cut', 1)) {
            $param = $crypt->request();
            $param['member_id'] = request(0)->mid;
            $condition[] = ['owner', '=', $param['member_id']];
            if ($param['status']) {
                $condition[] = ['ca.status', '=', $param['status']];
            }
            $result = $cutActivity
                ->alias('ca')
                ->join('cut_goods cg', 'cg.cut_goods_id = ca.cut_goods_id')
                ->join('goods goods', 'goods.goods_id = cg.goods_id')
                ->join('order_attach oa', 'ca.order_attach_id = oa.order_attach_id', 'left')
                ->join('store s', 's.store_id = goods.store_id')
                ->where($condition)
                ->field('ca.cut_activity_id,ca.order_attach_id,goods.goods_id,goods_name,
                file,original_price,ca.present_price,ca.goods_attr,ca.products_id,
                ca.cut_price,ca.status,ca.end_time,s.store_id,s.store_name,ca.attr,
                goods.file as file_img,oa.status as order_status')
                ->append(['expiration_time'])
                ->order(['ca.cut_activity_id' => 'desc'])
                ->paginate(10, false);
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
    
    /**
     * 我的砍价详情
     * @param RSACrypt $crypt
     * @param CutActivity $cutActivity
     * @param Member $member
     * @param Goods $goods
     * @param CutActivityAttach $activityAttach
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function my_cut_view(RSACrypt $crypt,
                                CutActivity $cutActivity,
                                Member $member,
                                Goods $goods,
                                CutActivityAttach $activityAttach)
    {
        $param = $crypt->request();
        $param['member_id'] = request(true)->mid ?? '';
        $result = [];
        if (env('is_cut', 1)) {
            $cutActivity->valid($param, 'my_cut_view');
            $result = $cutActivity
                ->alias('ca')
                ->join('cut_goods cg', 'cg.cut_goods_id = ca.cut_goods_id')
                ->join('goods g', 'g.goods_id = cg.goods_id')
                ->join('store s', 's.store_id = g.store_id')
                ->where([
                    ['ca.cut_activity_id', '=', $param['cut_activity_id']],
                ])
                ->field('cut_activity_id,g.goods_id,goods_attr,original_price,products_id,
                    s.store_id,s.store_name,ca.attr,g.file as file_img,g.file,
                    present_price,ca.cut_price,owner,ca.status,ca.end_time')
                ->append(['expiration_time'])
                ->find();
            if (is_null($result)) {
                return $crypt->response([
                    'code' => -1,
                    'message' => '砍价信息不存在',
                ], true);
            }
            // 如果一帮忙砍价
            $cut_activity_attach_id = $activityAttach
                ->where([
                    ['cut_activity_id', '=', $result['cut_activity_id']],
                    ['helper', '=', $param['member_id']],
                ])
                ->value('cut_activity_attach_id');
            // 1本人 2已帮忙砍过 3未砍过
            $result['state'] = $cut_activity_attach_id ? 2 : 3;
            if ($param['member_id'] == (string)$result['owner']) {
                $result['state'] = 1;
            }
            $result['member'] = $member
                ->where([
                    ['member_id', '=', $result['owner']],
                ])
                ->field('avatar,nickname')
                ->find();
            $result['goods'] = $goods
                ->where([
                    ['goods_id', '=', $result['goods_id']],
                ])
                ->field('goods_name,file,cut_success_num')
                ->find();
            $result['attach_list'] = $activityAttach
                ->with(['member'])
                ->where([
                    ['cut_activity_id', '=', $result['cut_activity_id']],
                ])
                ->field('helper,cut_price')
                ->order(['create_time' => 'desc'])
                ->select();
        }
        // 为你推荐 小随机 后期需要改成 用户习惯模式
        $recommend_list = $goods
            ->alias('g')
            ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
            ->whereRaw(self::$goodsAuthSql)
            ->field('g.goods_id,goods_name,shop_price,g.sales_volume,freight_status,
                shop,s.store_name,file')
            ->order(['g.sort' => 'desc'])
            ->limit(4)
            ->select();
        // 会员折扣
        $discount = discount($param['member_id']);
        $encrypt = [
            'cut_activity_id' => urlencode($param['cut_activity_id'] ? $crypt->singleEnc($param['cut_activity_id']) : ''),
            'member_id' => urlencode($param['member_id'] ? $crypt->singleEnc($param['member_id']) : ''),
        ];
        return $crypt->response([
            'code' => 0,
            'result' => $result ?: json([]),
            'recommend_list' => $recommend_list,
            'discount' => $discount,
            'encrypt' => $encrypt,
        ], true);
    }
    
    /**
     * 砍价详情 - web
     * @param RSACrypt $crypt
     * @param CutActivity $cutActivity
     * @param Member $member
     * @param Goods $goods
     * @param CutActivityAttach $activityAttach
     * @return array|mixed|\think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function view_web(RSACrypt $crypt,
                             CutActivity $cutActivity,
                             Member $member,
                             Goods $goods,
                             CutActivityAttach $activityAttach)
    {
        $param = Request::get();
        $cut_activity_id = $crypt->singleDec($param['cut_activity_id']);
        $member_id = $crypt->singleDec($param['member_id']);
        $result = [];
        if (env('is_cut', 1)) {
            $result = $cutActivity
                ->alias('ca')
                ->join('cut_goods cg', 'cg.cut_goods_id = ca.cut_goods_id')
                ->join('goods g', 'g.goods_id = cg.goods_id')
                ->join('store s', 's.store_id = g.store_id')
                ->where([
                    ['ca.cut_activity_id', '=', $cut_activity_id],
                ])
                ->field('cut_activity_id,g.goods_id,goods_attr,original_price,
            products_id,s.store_id,s.store_name,ca.attr,g.file as file_img,
            g.file,present_price,ca.cut_price,owner,ca.status,ca.end_time')
                ->append(['expiration_time'])
                ->find();
            // 如果一帮忙砍价
            $cut_activity_attach_id = $activityAttach
                ->where([
                    ['cut_activity_id', '=', $result['cut_activity_id']],
                    ['helper', '=', $member_id]
                ])
                ->value('cut_activity_attach_id');
            // 1本人 2已帮忙砍过 3未砍过
            $result['state'] = $cut_activity_attach_id ? 2 : 3;
            if ($param['member_id'] == (string)$result['owner']) $result['state'] = 1;
            $result['member'] = $member
                ->where([
                    ['member_id', '=', $result['owner']],
                ])
                ->field('avatar,nickname')
                ->find();
            $result['goods'] = $goods
                ->where([
                    ['goods_id', '=', $result['goods_id']],
                ])
                ->field('goods_name,file,cut_success_num')
                ->find();
            $result['attach_list'] = $activityAttach
                ->with(['member'])
                ->where([
                    ['cut_activity_id', '=', $result['cut_activity_id']],
                ])
                ->field('helper,cut_price')
                ->order(['create_time' => 'desc'])
                ->select();
            $result['percentage'] = ($result['original_price'] - $result['present_price']) / ($result['original_price'] - $result['cut_price']) * 100;
        }
        // 为你推荐 小随机 后期需要改成 用户习惯模式
        $recommend_list = $goods
            ->alias('g')
            ->join('store s', 's.store_id = g.store_id and ' . self::$storeAuthSql)
            ->whereRaw(self::$goodsAuthSql)
            ->field('g.goods_id,goods_name,shop_price,g.sales_volume,freight_status,
            shop,s.store_name,file')
            ->order(['g.sort', '=', 'desc'])
            ->limit(4)
            ->select();
        $discount = discount($param['member_id']);
        return view('view_web', [
            'result' => $result ?: json([]),
            'recommend_list' => $recommend_list,
            'discount' => $discount,
        ]);
    }
    
    /**
     * 帮助砍价
     * @param RSACrypt $crypt
     * @param CutActivity $cutActivity
     * @param Goods $goodsModel
     * @param Products $products
     * @param CutActivityAttach $activityAttach
     * @param Member $member
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function my_cut_help(RSACrypt $crypt,
                                CutActivity $cutActivity,
                                Goods $goodsModel,
                                Products $products,
                                CutActivityAttach $activityAttach,
                                Member $member)
    {
        if (env('is_cut', 1)) {
            $param = $crypt->request();
            $param['member_id'] = request(0)->mid;
            $cutActivity->valid($param, 'my_cut_help');
            if ($param['goods_attr']) {
                // 读取属性价格
                $goods = $products
                    ->alias('p')
                    ->where([
                        ['p.goods_id', '=', $param['goods_id']],
                        ['p.goods_attr', '=', $param['goods_attr']]
                    ])
                    ->join('goods g', 'g.goods_id = p.goods_id')
                    ->join('store s', 's.store_id = g.store_id')
                    ->field('p.attr_single_cut_min as single_cut_min,p.attr_single_cut_max as single_cut_max,
                    g.goods_name,s.store_name,g.file')
                    ->find();
            } else {
                $goods = $goodsModel
                    ->alias('goods')
                    ->join('cut_goods cut_goods', 'cut_goods.goods_id = goods.goods_id')
                    ->join('store s', 's.store_id = goods.store_id')
                    ->where('goods.goods_id', $param['goods_id'])
                    ->field('single_cut_min,single_cut_max,goods.goods_name,s.store_name,goods.file')
                    ->find();
            }
            // 新增砍价记录
            $return_state = $this->getRandomMoney(
                $param['cut_activity_id'],
                $param['member_id'],
                $goods,
                $cutActivity,
                $activityAttach
            );
            if ($return_state['code'] == -1) {
                return $crypt->response([
                    'code' => $return_state['code'],
                    'message' => $return_state['message'],
                ], true);
            }
            $info = $member
                ->where([
                    ['member_id', '=', $param['member_id']],
                ])
                ->field('avatar')
                ->find();
            return $crypt->response([
                'code' => 0,
                'message' => '帮砍成功',
                'random_price' => $return_state['random_price'],
                'member' => $info,
            ]);
        }
        return $crypt->response([
            'code' => -1,
            'message' => '不支持砍价'
        ], true);
    }
    
    /**
     * 获取随机金额
     * @param $cut_activity_id
     * @param $member_id
     * @param $goods
     * @param CutActivity $cutActivity
     * @param CutActivityAttach $activityAttach
     * @return array|mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getRandomMoney($cut_activity_id,
                                   $member_id,
                                   $goods,
                                   CutActivity $cutActivity,
                                   CutActivityAttach $activityAttach)
    {
        // 随机金额
        $random_price = randomFloat($goods['single_cut_min'], $goods['single_cut_max']);
        // 读取数据
        $activity = $cutActivity
            ->alias('ca')
            ->where([
                ['ca.cut_activity_id', '=', $cut_activity_id],
            ])
            ->join('member m', 'm.member_id = ca.owner')
            ->field('ca.present_price,ca.cut_price,ca.end_time,ca.status,ca.owner,
            m.nickname,m.web_open_id,ca.original_price,m.micro_open_id,m.subscribe_time,m.phone')
            ->find();
        // 判断时间是否超过或是否结束
        if ($activity['end_time'] < date('Y-m-d') || $activity['status'] <> 1) {
            return [
                'code' => -1,
                'message' => '砍价已结束',
            ];
        }
        if ($activity['present_price'] == $activity['cut_price']) {
            return [
                'code' => -2,
                'message' => '商品已经看到底价了',
            ];
        }
        $push_flag = -1;
        // 判断砍价价格是否超过底价金额
        if (($activity['present_price'] - $random_price) < $activity['cut_price']) {
            $random_price = $activity['present_price'] - $activity['cut_price'];
            $push_flag = 1;
        } else {
            $cur = $activity['present_price'] - $random_price - $activity['cut_price'];
            $diff = $activity['original_price'] - $activity['cut_price'];
            $cut_push_flag = Cache::store('file')->get('cut_push_' . $cut_activity_id);
            if ($diff > 0 && $cur > 0 && $cur / $diff <= 0.2 && !$cut_push_flag) {
                Cache::store('file')->set('cut_push_' . $cut_activity_id, 1, 86400 * 3);
                $push_flag = 0;
            }
        }
        if ($random_price == 0) {
            return [
                'code' => -1,
                'message' => '金额异常,砍价失败',
            ];
        }
        // 是否已经帮砍过了
        $state = $activityAttach
            ->where([
                ['helper', '=', $member_id],
                ['cut_activity_id', '=', $cut_activity_id],
            ])
            ->value('cut_activity_attach_id');
        if ($state) {
            return [
                'code' => -1,
                'message' => '您已经帮助好友砍价成功,请勿重复砍价',
            ];
        }
        // 砍价价格更新
        $cutActivity
            ->where([
                ['cut_activity_id', '=', $cut_activity_id],
            ])
            ->setDec('present_price', $random_price);
        // 新增砍价记录
        $activityAttach
            ->allowField(true)
            ->isUpdate(false)
            ->save([
                'cut_activity_id' => $cut_activity_id,
                'helper' => $member_id,
                'cut_price' => $random_price,
                'create_time' => date('Y-m-d H:i:s')
            ]);
        if ($push_flag >= 0) {
            // 推送消息[砍价80%|砍成功]
            $pushServer = app('app\\interfaces\\behavior\\Push');
            $pushServer->send([
                'tplKey' => 'active_goods_state',
                'openId' => $activity['web_open_id'],
                'subscribe_time' => $activity['subscribe_time'],
                'microId' => $activity['micro_open_id'],
                'phone' => $activity['phone'],
                'data' => [$push_flag, $goods['store_name'], $activity['nickname'], $goods['goods_name']],
                'inside_data' => [
                    'member_id' => $activity['owner'],
                    'type' => 0,
                    'jump_state' => '1',
                    'attach_id' => $cut_activity_id,
                    'file' => $goods->getData('file'),
                ],
                'sms_data' => [],
            ]);
        }
        return [
            'code' => 0,
            'message' => '查询成功',
            'random_price' => round($random_price, 2),
        ];
    }
}