<?php
declare(strict_types=1);

namespace app\computer\controller\goods;

use app\computer\controller\BaseController;
use app\computer\model\Article;
use mrmiao\encryption\RSACrypt;
use common\lib\phpcode\QrCode;
use think\Db;
use think\facade\Env;
use app\computer\model\GroupClassify;
use app\computer\model\GroupGoods;
use app\common\service\OSS;
use think\facade\Request;
use app\computer\model\Adv;
use think\facade\Session;
use app\computer\model\GroupActivityAttach;
use app\computer\model\Goods;
use app\computer\model\Member;
use EasyWeChat\Factory;


/**
 * 拼团
 * Class Search
 * @package app\computer\controller\goods
 */
class Group extends BaseController
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
        'is_login'      => ['only' => 'my_index'],
    ];


//    /**
//     * 拼团二维码 废弃
//     * @param RSACrypt $crypt
//     * @return mixed
//     */
//    public function qr_code(RSACrypt $crypt)
//    {
//        try
//        {
//            $param = $crypt->request();
//            if (!array_key_exists('goods_id', $param) || !$param['goods_id'])
//            {
//                return $crypt->response(['code' => -1, 'message' => '请输入商品id'], TRUE);
//            }
//            $config = config('user.');
//            $data['mobile_group_qr_code'] = '';
//            $file_dir = Env::get('root_path') . 'public/group/invite/';
//            if ($config['mobile']['is_include'])
//            {
//                // 手机站
//                $file_name = 'group_mobile_' . $param['goods_id'];
//                $mobileDomain = $config['mobile']['mobile_domain'];
//                if (!file_exists($file_dir . $file_name))
//                {
//                    // 文件不存在,重新生成二维码
//                    require_once(Env::get('root_path') . 'extend/phpcode/QrCode.php');
//                    QrCode::getQrCode(
//                        $file_name,
//                        $mobileDomain . '/GoodDetail/' . $param['goods_id'],
//                        FALSE,
//                        6
//                    );
//                }
//                $data['mobile_group_qr_code'] = '/group/invite/' . $file_name . '.png';
//            }
//            return $crypt->response(['code' => 0, 'message' => config('message.')[0][0], 'data' => $data], TRUE);
//        } catch (\Exception $e)
//        {
//            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
//        }
//    }

    /**
     * 列表
     * @param Request $request
     * @param GroupGoods $groupGoods
     * @param GroupClassify $groupClassify
     * @param Adv $adv
     * @return mixed
     * @throws \OSS\Core\OssException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(Request $request, GroupGoods $groupGoods, GroupClassify $groupClassify, Adv $adv)
    {
        $group_classify_id = $request::instance()->param('group_classify_id');

        $last_id = $groupClassify->where('group_classify_id', '=', $group_classify_id)->value('parent_id');  //当前id的上一级分类

        $class = $groupClassify
            ->where('parent_id', '=', 0)
            ->field('group_classify_id,title')
            ->order('create_time', 'asc')
            ->select();


        $two_class = []; //二级分类展示
        if (!empty($group_classify_id))
        {
            $two_class = $groupClassify
                ->where('parent_id', '=', $group_classify_id)
                ->field('group_classify_id,title')
                ->order('create_time', 'asc')
                ->select();
            if (!empty($two_class) && $last_id !=0){
                //当前二级分类展示同级数据
                $two_class = $groupClassify
                    ->where('parent_id', '=', $last_id)
                    ->field('group_classify_id,title')
                    ->order('create_time', 'asc')
                    ->select();
            }
        }

        $condition = [
            ['gg.up_shelf_time', '<=', date('Y-m-d H:i:s')],
            ['gg.down_shelf_time', '>=', date('Y-m-d H:i:s')],
            ['gg.status', '=', 1],
            ['g.is_putaway', '=', 1],
            ['g.review_status', '=', 1],
        ];

        // 分类
        if (!empty($group_classify_id)){
            $parent_id = $groupClassify
                ->where('parent_id', $group_classify_id)
                ->column('group_classify_id');
            array_push(
                $condition,
                [
                    'gg.group_classify_id',
                    'in',
                    $parent_id ? implode(',', $parent_id) . ',' : '' . $group_classify_id,
                ]
            );
        }
        // else{
        //     array_push($condition, ['gg.is_best', '=', 1]);
        // }

        //判断当前功能开关状态从新生成查询条件
        $condition = self::goods_where($condition, 'g');
        $result = $groupGoods
            ->alias('gg')
            ->join('goods g', 'g.goods_id = gg.goods_id')
            ->where($condition)
            ->field('g.goods_id,gg.group_num,g.goods_name,g.group_price,g.shop_price,g.file')
            ->order('gg.create_time', 'desc')
            ->paginate(20, FALSE, ['query' =>['group_classify_id' => $group_classify_id]]);

        if ($result->count())
        {
            $oss = new OSS();
            foreach ($result as $_res)
            {
                $_res->file = $oss->getSignUrlForGet($_res->file)['url'];
            }
        }


        $adv_info = $adv
            ->where([
                        ['adv_position_id', '=', config('pc_config.group_list_id')],
                        ['status', '=', 1],
                        ['start_time', ['<=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
                        ['end_time', ['>=', date('Y-m-d H:i:s')], ['exp', Db::raw('is null')], 'or'],
                    ])
            ->field('type,file,content')
            ->select();


        return $this->fetch(
            '',
            [
                'code'         => 0,
                'header_title' => '团购',
                'last_id'      => $last_id,
                'adv_info'     => $adv_info,
                'result'       => $result,
                'class'        => $class,
                'two_class'    => $two_class,
            ]
        );
    }

    /**
     * 我的拼团列表
     * @param Request $request
     * @param GroupActivityAttach $groupActivityAttach
     * @param Goods $goods
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function my_index(Request $request, GroupActivityAttach $groupActivityAttach, Goods $goods)
    {
        // 获取参数
        $param = $request::instance()->param();
        $member_id = Session::get('member_info')['member_id'] ?? 0;

        // 条件
        $condition[] = ['group_activity_attach.member_id', '=', $member_id];

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
            array_push($condition, ['group_activity.create_time', '>=', $share_time]);
        }
        if ($end_time ?? NULL)
        {
            array_push($condition, ['group_activity.create_time', '<=', $end_time]);
        }
        // 拼团状态
        if (!empty($param['status']))
        {
            $condition[] = ['group_activity.status', '=', $param['status']];
        }

        if (!empty($param['keyword']))
        {
            $condition[] = ['goods_name|order_attach.order_number|order_attach.order_attach_number', 'like', '%' . $param['keyword'] . '%'];
        }

        $result = $groupActivityAttach->alias('group_activity_attach')
            ->join(
                'group_activity group_activity',
                'group_activity.group_activity_id = group_activity_attach.group_activity_id'
            )
            ->join('group_goods group_goods', 'group_goods.group_goods_id = group_activity.group_goods_id')
            ->join(
                'order_attach order_attach',
                'order_attach.group_activity_attach_id = group_activity_attach.group_activity_attach_id'
            )
            ->join('order_goods order_goods', 'order_goods.order_attach_id = order_attach.order_attach_id')
            ->join('store store', 'order_goods.store_id = store.store_id and '.self::store_auth_sql('store'))
            ->where($condition)
            ->field(
                'group_activity_attach.group_activity_attach_id,group_goods.goods_id,
            group_activity_attach.member_id,group_activity.status,attr,goods_name,
            file,single_price,group_num,order_attach.order_attach_id,store.store_name'
            )
            ->order('group_activity_attach.create_time', 'desc')
            ->paginate(20, FALSE, $param);

        foreach($result as &$value){
            $value['qr_code'] = self::qr_code($value['group_activity_attach_id']);
        }

        $recommend_list = recommend_list($goods, 8, $member_id, 1);

        return $this->fetch('', ['code' => 0, 'result' => $result, 'recommend_list' => $recommend_list]);
    }

    public function view(RSACrypt $crypt,Request $request,
                         GroupActivityAttach $groupActivityAttach,
                         Article $article,
                         Member $member)
    {
        $param = $request::instance()->param();
        $param['member_id'] = Session::get('member_info')['member_id'] ?? '';
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
//        halt($result);
        $result['member'] = $member->where([['member_id', '=', $result['member_id']]])->find();
//        if (!$result) {
//            return $crypt->response([
//                                        'code' => -1,
//                                        'message' => '拼团信息不存在',
//                                    ], true);
//        }

        $result['article'] = $article->where([['article_id','=','20']])->field('title,content')->find();

        $result['qr_code'] = self::qr_code($param['group_activity_attach_id']);

        return $this->fetch('',[
                    'result' => $result,
                ]);
    }

    public function qr_code($group_activity_attach_id)
    {

        $publicPath = Env::get('root_path') . 'public';
        $file_dir = '/static/img/computer/qr_code/group/';
        $file_name = 'group' . $group_activity_attach_id;
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
                    $mobileDomain . '/GroupDetail?id=' . $group_activity_attach_id.'&type=0',
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
                        (string)$group_activity_attach_id,
                        [
                            'width' => 200,
                            'page'  => 'pages/collage_detail/collage_detail',
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
                            $mobileDomain . '/GroupDetail?id=' . $group_activity_attach_id.'&type=0',
                            $publicPath . $file_dir . 'app/',
                            6
                        );
                    }
                }
            }
        }


        return $data;
    }

}