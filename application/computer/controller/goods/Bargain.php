<?php
declare(strict_types=1);

namespace app\computer\controller\goods;

use app\computer\model\Adv;
use app\computer\model\Article;
use app\computer\model\CutActivity;
use app\computer\model\CutActivityAttach;
use app\computer\model\CutGoods;
use app\computer\model\Goods;
use app\computer\model\Member;
use app\computer\model\Products;
use app\common\service\Beanstalk;
use app\common\service\Lock;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use common\lib\phpcode\QrCode;
use think\Db;
use think\facade\Request;
use think\facade\Env;
use think\facade\Session;
use EasyWeChat\Factory;

/**
 * 砍价
 * Class Search
 * @package app\computer\controller\goods
 */
class Bargain extends BaseController
{

    protected $set = [];

    public function __construct()
    {
        parent::__construct();

        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $this->set = Env::get();
    }

    protected $beforeActionList = [
        //检查是否登录
        'is_login'    => ['except' => 'index'],
    ];


    /**
     * 列表页面
     * @param CutGoods $cutGoods
     * @param Adv $adv
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(CutGoods $cutGoods, Adv $adv)
    {
        $where = [
            ['cg.up_shelf_time', '<=', date('Y-m-d H:i:s')],
            ['cg.down_shelf_time', '>=', date('Y-m-d H:i:s')],
            ['cg.status', '=', 1],
            ['g.is_putaway', '=', 1],
        ];
        // 数据
        $result = $cutGoods
            ->alias('cg')
            ->with('goods')
            ->join('goods g', 'g.goods_id = cg.goods_id')
            ->join('store store','g.store_id = store.store_id and '.self::store_auth_sql('store'))
            ->where(self::goods_where($where,'g'))
            ->field('cg.goods_id,cg.up_shelf_time,cg.goods_id,cg.down_shelf_time')
            ->order(['cg.update_time' => 'desc', 'cg.cut_goods_id' => 'desc'])
            ->paginate(10);

        // 广告
        $adv_info = $adv
            ->where([
                        ['adv_position_id', '=', config('pc_config.bargain_list_id')],
                        ['status', '=', 1],
                        ['start_time', ['<=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
                        ['end_time', ['>=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
                    ])
            ->field('type,file,content')
            ->find();

        return $this->fetch('', ['code' => 0, 'header_title' => '砍价', 'result' => $result, 'adv_info' => $adv_info]);
    }


    /**
     * 获取随机金额
     * @param $cut_activity_id
     * @param $member_id
     * @param $min_price
     * @param $max_price
     * @param CutActivity $cutActivity
     * @param CutActivityAttach $activityAttach
     * @param RSACrypt $crypt
     * @return array|mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getRandomMoney($cut_activity_id, $member_id, $min_price, $max_price, CutActivity $cutActivity, CutActivityAttach $activityAttach, RSACrypt $crypt)
    {
        // 随机金额
        $random_price = randomFloat($min_price, $max_price);

        // 读取数据
        $activity = $cutActivity
            ->where('cut_activity_id', $cut_activity_id)
            ->field('present_price,cut_price,end_time,status')
            ->find();

        // 判断时间是否超过或是否结束
        if ($activity['end_time'] < date('Y-m-d') || $activity['status'] <> 1)
        {
            return ['code' => -1, 'message' => config('message.')[-10][-7]];
        }

        // 判断砍价价格是否超过底价金额
        if (($activity['present_price'] - $random_price) < $activity['cut_price'])
        {
            $random_price = $activity['present_price'] - $activity['cut_price'];
        }

        // 如果为0
        if ($random_price == 0.00)
        {
            return ['code' => -1, 'message' => config('message.')[-10][-4]];
        }

        // 是否已经帮砍过了
        $state = $activityAttach
            ->where(
                [
                    ['helper', '=', $member_id],
                    ['cut_activity_id', '=', $cut_activity_id],
                ]
            )
            ->value('cut_activity_attach_id');

        if ($state)
        {
            return ['code' => -1, 'message' => config('message.')[-10][-5]];
        }

        // 砍价价格更新
        $cutActivity->where('cut_activity_id', $cut_activity_id)->setDec('present_price', $random_price);

        // 新增砍价记录
        $activityAttach->allowField(TRUE)->save(
            [
                'cut_activity_id' => $cut_activity_id,
                'helper'          => $member_id,
                'cut_price'       => $random_price,
                'create_time'     => date('Y-m-d H:i:s'),
            ]
        );

        return ['code' => 0, 'message' => config('message.')[0][0], 'random_price' => round($random_price, 2)];
    }



    /**
     * 我的砍价
     * @param Request $request
     * @param RSACrypt $crypt
     * @param CutActivity $cutActivity
     * @param Goods $goods
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function my_cut(Request $request, RSACrypt $crypt, CutActivity $cutActivity, Goods $goods)
    {
        $param = $request::instance()->param();
        $param['member_id'] = Session::get('member_info')['member_id'] ?? 0;
        $member_id = Session::get('member_info')['member_id'] ?? exception('会员id不能为空');
        $condition = [];

        //查找指定时间内订单
        switch ($param['share_time'] ?? '')
        {
            //近三个月
            case 1:
                $share_time = date('Y-m-d 00:00:00', strtotime('-3 month'));
                break;
            //今年
            case 2:
                $share_time = date('Y-01-01 00:00:00');
                break;
            //去年
            case 3:
                $share_time = date('Y-01-01 00:00:00', strtotime('-1 year'));
                $end_time = date('Y-01-01 00:00:00');
                break;
            //前年
            case 4:
                $share_time = date('Y-01-01 00:00:00', strtotime('-2 year'));
                $end_time = date('Y-01-01 00:00:00', strtotime('-1 year'));
                break;
            //前前年
            case 5:
                $share_time = date('Y-01-01 00:00:00', strtotime('-3 year'));
                $end_time = date('Y-01-01 00:00:00', strtotime('-2 year'));
                break;
        }
        if ($share_time ?? NULL)
        {
            array_push($condition, ['cut_activity.create_time', '>=', $share_time]);
        }
        if ($end_time ?? NULL)
        {
            array_push($condition, ['cut_activity.create_time', '<=', $end_time]);
        }

        // 会员
        $condition[] = ['owner', '=', $param['member_id']];
        // 状态
        if (!empty($param['status']))
        {
            $condition[] = ['cut_activity.status', '=', $param['status']];
        }
        if (!empty($param['keyword']))
        {
            $condition[] = ['goods.goods_name|order_attach.order_number', 'like', '%' . $param['keyword'] . '%'];
        }

        // 数据
        $result = $cutActivity
            ->alias('cut_activity')
            ->join('cut_goods cut_goods', 'cut_goods.cut_goods_id = cut_activity.cut_goods_id')
            ->join('goods goods', 'goods.goods_id = cut_goods.goods_id')
            ->join('order_attach order_attach', 'cut_activity.order_attach_id = order_attach.order_attach_id', 'left')
            ->join('store s', 's.store_id = goods.store_id and '.self::store_auth_sql('s'))
            ->where(self::goods_where($condition,'goods'))
            ->field(
                'cut_activity.cut_activity_id,cut_activity.order_attach_id,goods.goods_id,goods_name,file,original_price,
            cut_activity.present_price,cut_activity.goods_attr,cut_activity.products_id,cut_activity.cut_price,
            cut_activity.status,cut_activity.end_time,s.store_id,s.store_name,cut_activity.attr,goods.file as file_img,order_attach.order_id,order_attach.order_number,order_attach.pay_type,order_attach.status as order_status'
            )
            ->append(['expiration_time', 'order_data'])
            ->order(['cut_activity.cut_activity_id' => 'desc'])
            ->paginate(5);


        //猜你喜欢
        $recommend_list = recommend_list($goods, 8, $member_id, 1);


//        halt($result);

        return $this->fetch('', ['code' => 0, 'result' => $result, 'recommend_list' => $recommend_list]);


    }

    /**
     * 我的砍价详情
     * @param Request $request
     * @param RSACrypt $crypt
     * @param CutActivity $cutActivity
     * @param Member $member
     * @param Goods $goods
     * @param CutActivityAttach $activityAttach
     * @param Article $article
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function my_cut_view(Request $request, RSACrypt $crypt, CutActivity $cutActivity, Member $member, Goods $goods, CutActivityAttach $activityAttach, Article $article)
    {
        $param = $request::instance()->param();
        $param['member_id'] = Session::get('member_info')['member_id'];

        // 验证
        $check = $cutActivity->valid($param, 'my_cut_view');
        if ($check['code'])
        {
            return $crypt->response($check);
        }

        // 数据
        $result = $cutActivity
            ->alias('cut_activity')
            ->join('cut_goods cut_goods', 'cut_goods.cut_goods_id = cut_activity.cut_goods_id')
            ->join('goods goods', 'goods.goods_id = cut_goods.goods_id')
            ->join('store s', 's.store_id = goods.store_id and '.self::store_auth_sql('s'))
            ->where(self::goods_where([['cut_activity.cut_activity_id','=',$param['cut_activity_id']]],'goods'))
            ->field(
                'cut_activity_id,goods.goods_id,goods_attr,original_price,products_id,
            s.store_id,s.store_name,cut_activity.attr,goods.file as file_img,goods.file,
            present_price,cut_activity.cut_price,owner,cut_activity.status,cut_activity.end_time'
            )
            ->append(['expiration_time'])
            ->find();
        if (!$result)
        {
            return $crypt->response(['code' => -8, 'message' => config('message.')[-10][-8]], TRUE);
        }
        // 如果一帮忙砍价
        $cut_activity_attach_id = $activityAttach
            ->where(
                [
                    ['cut_activity_id', '=', $result['cut_activity_id']],
                    ['helper', '=', $param['member_id']],
                ]
            )
            ->value('cut_activity_attach_id');

        $result['state'] = $cut_activity_attach_id ? 2 : 3;

        // 如果是本人
        if ($param['member_id'] == (string)$result['owner'])
        {
            $result['state'] = 1;
        }

        $result['member'] = $member
            ->where('member_id', $result['owner'])
            ->field('avatar,nickname')
            ->find();

        $result['goods'] = $goods->alias('goods')
            ->join('store s', 's.store_id = goods.store_id and '.self::store_auth_sql('s'))
            ->where(self::goods_where([['goods.goods_id','=',$result['goods_id']]],'goods'))
            ->field('goods.goods_name,goods.file,goods.cut_success_num')
            ->find();

        $result['attach_list'] = $activityAttach
            ->with(['member'])
            ->where('cut_activity_id', $result['cut_activity_id'])
            ->field('helper,cut_price')
            ->order('create_time', 'desc')
            ->select();

        // 为你推荐 小随机 后期需要改成 用户习惯模式
        $recommend_list = $goods->alias('goods')
            ->join('store store', 'store.store_id = goods.store_id and '.self::store_auth_sql('store'))
            ->where(self::goods_where([
                                          ['goods_number', '>', 0],
                                          ['review_status', '=', 1],
                                          ['is_putaway', '=', 1],
                                      ],'goods')
            )
            ->field('goods.goods_id,goods_name,shop_price,goods.sales_volume,freight_status,shop,store_name,file')
            ->order('goods.sort', 'desc')
            ->limit(4)
            ->select();

        // 折扣
        $discount = discount($param['member_id']);

        $encrypt = [
            'cut_activity_id' => urlencode($crypt->singleEnc($param['cut_activity_id'])),
            'member_id'       => urlencode($crypt->singleEnc($param['member_id'] ?: '无数据')),
        ];

        $info = $article
            ->where('article_id', 21)
            ->field('title,content')
            ->find();

        $result['qr_code'] = self::qr_code($param['cut_activity_id']);
        // Log::record(urlencode($crypt->singleEnc($param['cut_activity_id'])));

//                return $crypt->response(['code' => 0, 'result' => $result, 'recommend_list' => $recommend_list, 'discount' => $discount, 'encrypt' => $encrypt,'info' => $info]);
        return $this->fetch(
            '',
            [
                'code'     => 0,
                'result'   => $result,
                'discount' => $discount,
                'encrypt'  => $encrypt,
                'info'     => $info,
            ]
        );
    }


    public function qr_code($cut_activity_id)
    {

        $publicPath = Env::get('root_path') . 'public';
        $file_dir = '/static/img/computer/qr_code/bargain/';
        $file_name = 'group' . $cut_activity_id;
        $data['domain'] = request()->domain();
        $data['qr_code'] =  '';
        $config = config('user.');

        //判断当前项目包含什么web端
        if($config['mobile']['is_include']){
            //手机
            $mobileDomain = config('user.mobile.mobile_domain');
            if (!file_exists($publicPath . ($data['qr_code'] = $file_dir . 'mobile/' . $file_name . '.png')))
            {
                //如果文件夹不存在则新建
//                $this->mkdirs($publicPath . $file_dir . 'mobile/');
                // 文件不存在,重新生成二维码
                require(Env::get('root_path') . 'extend/phpcode/QrCode.php');
                QrCode::getQrCode(
                    $file_name,
                    $mobileDomain . '/BargainDetail?id=' . $cut_activity_id.'&type=0',
                    $publicPath . $file_dir . 'mobile/',
                    6
                );
            }
        }else {
            if($config['applet']['is_include']){
                //小程序
                if (!file_exists($publicPath . ($data['qr_code'] = $file_dir . 'applet/' . $file_name . '.png')))
                {
                    $app = Factory::miniProgram(config('wechat.')['applet']);
                    $response = $app->app_code->getUnlimit(
                        (string)$cut_activity_id,
                        [
                            'width' => 200,
                            'page'  => 'pages/bargain/bargain',
                        ]
                    );
                    if ($response instanceof \EasyWeChat\Kernel\Http\StreamResponse)
                    {
                        $response->saveAs($publicPath . $file_dir . 'applet/', $file_name . '.png');
                    }
                }

            }else{
                if($config['app']['is_include']){
                    //app
                    $mobileDomain = config('user.mobile.mobile_domain');
                    if (!file_exists($publicPath . ($data['qr_code'] = $file_dir . 'app/' . $file_name . '.png')))
                    {
                        // 文件不存在,重新生成二维码
                        require(Env::get('root_path') . 'extend/phpcode/QrCode.php');
                        QrCode::getQrCode(
                            $file_name,
                            $mobileDomain . '/BargainDetail?id=' . $cut_activity_id.'&type=0',
                            $publicPath . $file_dir . 'app/',
                            6
                        );
                    }
                }
            }
        }


        return $data;
    }

    /**********废弃**************/

//    /**
//     * 立即砍价
//     * @param Request $request
//     * @param RSACrypt $crypt
//     * @param CutGoods $cutGoods
//     * @param CutActivity $cutActivity
//     * @param Lock $lock
//     * @param Goods $goodsModel
//     * @param Products $products
//     * @param CutActivityAttach $activityAttach
//     * @return mixed
//     */
//    public function immediately(Request $request, RSACrypt $crypt, CutGoods $cutGoods, CutActivity $cutActivity, Lock $lock, Goods $goodsModel, Products $products, CutActivityAttach $activityAttach)
//    {
//        if ($request::isPost())
//        {
//            try
//            {
//
//                $param = $crypt->request();
//                $param['owner'] = request(0)->mid;
//                // 验证
//                $check = $cutGoods->valid($param, 'immediately');
//                if ($check['code'])
//                {
//                    return $crypt->response($check);
//                }
//
//                // 事务处理
//                Db::startTrans();
//
//                // 查询结束时间和持续时间
//                $cut = $cutGoods
//                    ->where(
//                        [
//                            ['goods_id', '=', $param['goods_id']],
//                            ['up_shelf_time', '<=', date('Y-m-d')],
//                            ['down_shelf_time', '>=', date('Y-m-d')],
//                            ['status', '=', 1],
//                        ]
//                    )
//                    ->field('cut_goods_id,continue_time,down_shelf_time')
//                    ->find();
//
//                if (!$cut)
//                {
//                    return $crypt->response(['code' => -1, 'message' => config('message.')[-10][-3]]);
//                }
//
//                // 砍价限制
//                $cut_goods_id = $cutActivity->alias('cut_activity')
//                    ->join('cut_goods cut_goods', 'cut_goods.cut_goods_id = cut_activity.cut_goods_id')
//                    ->where(
//                        [
//                            ['owner', '=', $param['owner']],
//                            ['cut_goods.goods_id', '=', $param['goods_id']],
//                            ['up_shelf_time', '<=', date('Y-m-d')],
//                            ['down_shelf_time', '>=', date('Y-m-d')],
//                            ['cut_activity.status', '<', '3'],
//                        ]
//                    )
//                    ->value('cut_activity_id');
//
//                if ($cut_goods_id)
//                {
//                    return $crypt->response(['code' => -1, 'message' => config('message.')[-10][-6]]);
//                }
//
//                // redis锁设置
//                $lockKey = ['goods_' . $param['goods_id']];
//                if ($param['products_id'])
//                {
//                    array_push($lockKey, 'products_' . $param['products_id']);
//                }
//                $getLock = $lock->lock($lockKey, 10000);
//
//                // 成功执行
//                if ($getLock)
//                {
//
//                    if ($param['goods_attr'])
//                    {
//
//                        // 读取属性价格
//                        $goods = $products
//                            ->where(
//                                [
//                                    ['goods_id', '=', $param['goods_id']],
//                                    ['goods_attr', '=', $param['goods_attr']],
//                                ]
//                            )
//                            ->field(
//                                'attr_goods_number as goods_number,attr_single_cut_min as single_cut_min,attr_single_cut_max as single_cut_max,attr_cut_price as cut_price,attr_shop_price as price'
//                            )
//                            ->find();
//                    } else
//                    {
//                        $goods = $goodsModel->alias('goods')
//                            ->join('store store','goods.store_id = store.store_id and '.self::store_auth_sql('store'))
//                            ->join('cut_goods cut_goods', 'cut_goods.goods_id = goods.goods_id')
//                            ->where(self::goods_where([['goods.goods_id','=',$param['goods_id']]],'goods'))
//                            ->field('goods_number,single_cut_min,single_cut_max,cut_price,shop_price as price')
//                            ->find();
//                    }
//
//                    if ($goods['cut_price'] <> $param['price'])
//                    {
//
//                        // 解锁
//                        $lock->unlock($getLock);
//
//                        return $crypt->response(['code' => -1, 'message' => config('message.')[-10][-2]]);
//
//                    }
//
//                    // 判断库存成功执行
//                    if ($goods['goods_number'] > 0)
//                    {
//
//                        // 商品原价/商品现价/商品底价/结束时间
//                        $param['cut_goods_id'] = $cut['cut_goods_id'];
//                        $param['original_price'] = $goods['price'];
//                        $param['present_price'] = $goods['price'];
//                        $param['cut_price'] = $goods['cut_price'];
//                        $param['end_time'] = ($cut['down_shelf_time'] . ' 23:59:59' > date(
//                                'Y-m-d H:i:s',
//                                strtotime(
//                                    '+' . $cut['continue_time'] . ' hour'
//                                )
//                            )) ? date(
//                            'Y-m-d H:i:s',
//                            strtotime('+' . $cut['continue_time'] . ' hour')
//                        ) : $cut['down_shelf_time'] . ' 23:59:59';
//
//                        // 新增砍价活动
//                        $cutActivity->allowField(TRUE)->save($param);
//
//                        // 减库存
//                        if ($param['goods_attr'])
//                        {
//                            $products
//                                ->where(
//                                    [
//                                        ['goods_id', '=', $param['goods_id']],
//                                        ['goods_attr', '=', $param['goods_attr']],
//                                    ]
//                                )
//                                ->setDec('attr_goods_number', 1);
//                        }
//
//                        // 减主表库存
//                        $goodsModel
//                            ->where('goods_id', $param['goods_id'])
//                            ->setDec('goods_number', 1);
//
//                        // 新增砍价记录
//                        $return_state = $this->getRandomMoney(
//                            $cutActivity->cut_activity_id,
//                            $param['owner'],
//                            $goods['single_cut_min'],
//                            $goods['single_cut_max'],
//                            $cutActivity,
//                            $activityAttach,
//                            $crypt
//                        );
//
//                        if ($return_state['code'] == -1)
//                        {
//                            return $crypt->response(
//                                ['code' => $return_state['code'], 'message' => $return_state['message']]
//                            );
//                        }
//                        // 发送消息队列[砍价到期未成功更改状态]
//                        (new Beanstalk())->put(
//                            json_encode(
//                                [
//                                    'queue' => 'cutExpireChangeStatus',
//                                    'id'    => $cutActivity->cut_activity_id,
//                                    'time'  => date('Y-m-d H:i:s'),
//                                ]
//                            ),
//                            $cut['continue_time'] * 3600
//                        );
//                    }
//
//                    // 解锁
//                    $lock->unlock($getLock);
//
//                    Db::commit();
//
//                    return $crypt->response(
//                        [
//                            'code'            => 0,
//                            'message'         => config('message.')[0][0],
//                            'cut_activity_id' => $cutActivity->cut_activity_id,
//                            'random_price'    => $return_state['random_price'],
//                        ]
//                    );
//
//                }
//
//                return $crypt->response(['code' => -1, 'message' => config('message.')[-10][-1]]);
//
//            } catch (\Exception $e)
//            {
//                Db::rollback();
//                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
//            }
//        }
//    }
//
//    /**
//     * 砍价二维码
//     * @param RSACrypt $crypt
//     * @return mixed
//     */
//    public function qr_code(RSACrypt $crypt)
//    {
//        try
//        {
//            $param = $crypt->request();
//            if (!array_key_exists('cut_activity_id', $param) || !$param['cut_activity_id'])
//            {
//                return $crypt->response(['code' => -1, 'message' => '请输入砍价活动id'], TRUE);
//            }
//            $config = config('user.');
//            $data['mobile_bargain_qr_code'] = '';
//            $file_dir = Env::get('root_path') . 'public/bargain/invite/';
//            if ($config['mobile']['is_include'])
//            {
//                // 手机站
//                $file_name = 'bargain_mobile_' . $param['cut_activity_id'];
//                $mobileDomain = $config['mobile']['mobile_domain'];
//                if (!file_exists($file_dir . $file_name))
//                {
//                    // 文件不存在,重新生成二维码
//                    require_once(Env::get('root_path') . 'extend/phpcode/QrCode.php');
//                    QrCode::getQrCode(
//                        $file_name,
//                        $mobileDomain . '/BargainDetail/' . $param['cut_activity_id'],
//                        FALSE,
//                        6
//                    );
//                }
//                $data['mobile_bargain_qr_code'] = '/bargain/invite/' . $file_name . '.png';
//            }
//            return $crypt->response(['code' => 0, 'message' => config('message.')[0][0], 'data' => $data], TRUE);
//        } catch (\Exception $e)
//        {
//            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
//        }
//    }
}