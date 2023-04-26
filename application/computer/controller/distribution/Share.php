<?php
declare(strict_types = 1);

namespace app\computer\controller\distribution;


use app\computer\model\Distribution;
use app\computer\model\Member;
use app\computer\controller\BaseController;
use common\lib\phpcode\QrCode;
use EasyWeChat\Factory;
use mrmiao\encryption\RSACrypt;
use think\facade\Env;
use think\facade\Request;
use think\facade\Response;
use think\facade\View;

/**
 * 分销商分享
 * Class Share
 * @package app\computer\controller\distribution
 */
class Share extends BaseController
{
    protected $set = [];

    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $this->set = Env::get();
    }

    /**
     * 分享页面
     * @param Request $request
     * @param Distribution $distribution
     * @return mixed
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function toInvite(Request $request, Distribution $distribution)
    {

        $param = $request::instance()->param();
        $config = config('user.');
        if ($config['applet']['is_include']) {

            //小程序
            $param['type'] = 2;
        } elseif ($config['app']['is_include']) {
            //app
            $param['type'] = 1;
        } else {
            //手机
            $param['type'] = 0;
        }

        //获取分销商id
        $param['distribution_id'] = $distribution->get_distribution_id();
        $publicPath = Env::get('root_path') . 'public';
        $file_dir = '/static/img/interfaces/qr_code/distribution/';
        $file_name = 'distribution_' . $param['distribution_id'];
        $data['domain'] = request()->domain();
        $data['qr_code'] = $data['combination'] = $data['bg_img'] = '';
        $combination_file_name = $this->set['DISTRIBUTION_CARD'] . '_' . $param['type'] . '_' . $param['distribution_id'];
        if ($param['type'] == 1 && $config['app']['is_include']) {
            // app 1
            if (!file_exists($publicPath . ($data['qr_code'] = $file_dir . 'app/' . $file_name . '.png'))) {
                // 文件不存在,重新生成二维码
                require(Env::get('root_path') . 'extend/phpcode/QrCode.php');
                QrCode::getQrCode(
                    $file_name,
                    request()->domain() . '/v2.0/distribution_share/to_invite_web?type=dist&sid=' . $param['distribution_id'],
                    $publicPath . $file_dir . 'app/',
                    6
                );
            }
            $combination_file_name = 'personal/' . $combination_file_name;
        }
        // 小程序
        if ($param['type'] == 2 && $config['applet']['is_include']) {
            if (!file_exists($publicPath . ($data['qr_code'] = $file_dir . 'applet/' . $file_name . '.png'))) {
                $app = Factory::miniProgram(config('wechat.')['applet']);
                $response = $app->app_code->getUnlimit(
                    'scene,1-sup_id,' . $param['distribution_id'],
                    [
                        'width' => 200,
                        'page' => 'my/fx_invitation/fx_invitation',
                    ]
                );
                if ($response instanceof \EasyWeChat\Kernel\Http\StreamResponse) {
                    $response->saveAs($publicPath . $file_dir . 'applet/', $file_name . '.png');
                }
            }
            $combination_file_name = 'applet/' . $combination_file_name;
        }
        //手机站
        if ($param['type'] == 0) {

            $mobileDomain = config('user.mobile.mobile_domain');
            if (!file_exists($publicPath . ($data['qr_code'] = $file_dir . 'mobile/' . $file_name . '.png'))) {
                //如果文件夹不存在则新建
                $this->mkdirs($publicPath . $file_dir . 'mobile/');
                // 文件不存在,重新生成二维码
                require(Env::get('root_path') . 'extend/phpcode/QrCode.php');
                QrCode::getQrCode(
                    $file_name,
                    $mobileDomain . '/InviteRepresent?distribution_id=' . $param['distribution_id'],
                    $publicPath . $file_dir . 'mobile/',
                    6
                );
            }
            $combination_file_name = 'mobile/' . $combination_file_name;
        }
        if ($data['qr_code']) {
            $data['bg_img'] = dirname($file_dir) . '/card/' . $this->set['DISTRIBUTION_CARD'] . '.png';
            // 图片合成
            //            $data['combination'] = self::combination(request()->domain() . $data['qr_code'], $combination_file_name);
        }
        $data['combination'] = request()->domain() . '/template/master/resource/image/card/' . $this->set['DISTRIBUTION_CARD'] . '.png';
        return $this->fetch('', ['code' => 0, 'message' => config('message.')[0][0], 'data' => $data]);

    }

    /**
     * 合成图片
     * @param $qr_code
     * @param $combination_file_name
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function combination($qr_code, $combination_file_name)
    {
        $publicPath = Env::get('root_path') . 'public';
        $combination = '/static/img/interfaces/qr_code/distribution/combination/';
        if (file_exists($publicPath . $combination . $combination_file_name . '.jpg')) {
            return $combination . $combination_file_name . '.jpg';
        }
        if (!is_dir($combinationDir = pathinfo($publicPath . $combination . $combination_file_name . '.jpg')['dirname'])) {
            mkdir($combinationDir, 0755, true);
        }
        $bg_img = request()->domain() . '/template/master/resource/image/card/' . $this->set['DISTRIBUTION_CARD'] . '.png';
        $qr_code_resource = imagecreatefromstring(file_get_contents($qr_code));
        $bg_img_resource = imagecreatefromstring(file_get_contents($bg_img));
        list($qCodeWidth, $qCodeHeight, $qCodeType) = getimagesize($qr_code);
        imagecopymerge($bg_img_resource, $qr_code_resource, 240, 700, 0, 0, $qCodeWidth, $qCodeHeight, 100);
        imagejpeg($bg_img_resource, $publicPath . $combination . $combination_file_name . '.jpg');
        imagedestroy($qr_code_resource);
        imagedestroy($bg_img_resource);
        return $combination . $combination_file_name . '.jpg';
    }


    //如果文件夹不存在则创建
    public function mkdirs($dir, $mode = 0766)
    {
        if (is_dir($dir) || @mkdir($dir, $mode)) return true;
        if (!$this->mkdirs(dirname($dir), $mode)) return false;
        return @mkdir($dir, $mode);
    }



    /********废弃***********/
    //    /**
    //     * 分享出去的web页
    //     * @param Request $request
    //     * @param Distribution $distribution
    //     * @param RSACrypt $crypt
    //     * @return string
    //     * @throws \think\db\exception\DataNotFoundException
    //     * @throws \think\db\exception\ModelNotFoundException
    //     * @throws \think\exception\DbException
    //     */
    //    public function toInviteWeb(Request $request, Distribution $distribution, RSACrypt $crypt)
    //    {
    //        $param = $request::get();
    //        $arr = ['dist', 'goods'];
    //        if (!array_key_exists('type', $param) || !in_array($param['type'], $arr))
    //        {
    //            $response = Response::create('您访问的页面不存在', 'html', 404);
    //            $response->send();
    //        }
    //        $info = [];
    //        if (array_key_exists('sid', $param) && $param['sid'])
    //        {
    //            // 查询用户分销商信息
    //            $info = $distribution
    //                ->alias('d')
    //                ->where([['distribution_id', '=', $param['sid']]])
    //                ->join('member m', 'm.member_id = d.member_id')
    //                ->field('m.avatar,m.nickname')
    //                ->find();
    //        }
    //        if (array_key_exists('sid', $param) && !array_key_exists('goods_id', $param))
    //        {
    //            if ($param['sid'])
    //            {
    //                // 个人邀请
    //                $file_dir = Env::get('root_path') . 'public/advocacy/invite/';
    //                $file_name = 'distribution_' . $param['sid'];
    //                if (!file_exists($file_dir . $file_name . '.png'))
    //                {
    //                    // 文件不存在,重新生成二维码
    //                    require(Env::get('root_path') . 'extend/phpcode/QrCode.php');
    //                    QrCode::getQrCode(
    //                        $file_name,
    //                        request()->domain() . '/v2.0/distribution_share/to_invite_web?type=dist&sid=' . $param['sid'],
    //                        $file_dir,
    //                        6
    //                    );
    //                }
    //                $data['qr_code'] = '/advocacy/invite/' . $file_name . '.png';
    //                if (empty($info) || is_null($info))
    //                {
    //                    $data['qr_code'] = '/advocacy/assets/code.png';
    //                }
    //                $data['avatar'] = $info['avatar'];
    //                $data['nickname'] = $info['nickname'];
    //                $distribution_file_name = $this->set['DISTRIBUTION_CARD'] . '_' . $param['sid'];
    //            } else
    //            {
    //                // 平台邀请
    //                $data['qr_code'] = '/advocacy/assets/code.png';
    //                $data['avatar'] = NULL;
    //                $data['nickname'] = 'iShop';
    //                $distribution_file_name = $this->set['DISTRIBUTION_CARD'] . '_plat';
    //            }
    //            $title = '邀请代言';
    //        } else
    //        {
    //            if (array_key_exists('goods_id', $param) && !array_key_exists('sid', $param))
    //            {
    //                // 不含分销
    //                $distribution_file_name = 'goods_' . $this->set['DISTRIBUTION_CARD'] . '_' . $param['goods_id'];
    //                $file_dir = Env::get('root_path') . 'public/qr_code/app_goods/';
    //                $file_name = 'goods_' . $param['goods_id'];
    //                if (!file_exists($file_dir . $file_name . '.png'))
    //                {
    //                    // 文件不存在,重新生成二维码
    //                    require(Env::get('root_path') . 'extend/phpcode/QrCode.php');
    //                    QrCode::getQrCode(
    //                        $file_name,
    //                        request()->domain(
    //                        ) . '/v2.0/distribution_share/to_invite_web?type=goods&goods_id=' . $param['goods_id'],
    //                        $file_dir,
    //                        6
    //                    );
    //                }
    //                $data['qr_code'] = '/qr_code/app_goods/' . $file_name . '.png';
    //                $title = '分享商品';
    //            } else
    //            {
    //                // 分销商品
    //                $distribution_file_name = 'goods_dis_' . $this->set['DISTRIBUTION_CARD'] . '_' . $param['sid'];
    //                $file_dir = Env::get('root_path') . 'public/qr_code/app_distribution/';
    //                $file_name = 'dis_' . $param['goods_id'] . '_' . $param['sid'];
    //                if (!file_exists($file_dir . $file_name . '.png'))
    //                {
    //                    // 文件不存在,重新生成二维码
    //                    require(Env::get('root_path') . 'extend/phpcode/QrCode.php');
    //                    QrCode::getQrCode(
    //                        $file_name,
    //                        request()->domain(
    //                        ) . '/v2.0/distribution_share/to_invite_web?type=dist&sid=' . $param['sid'] . '&goods_id=' . $param['goods_id'],
    //                        $file_dir,
    //                        6
    //                    );
    //                }
    //                $data['qr_code'] = '/qr_code/app_distribution/' . $file_name . '.png';
    //                if (empty($info) || is_null($info))
    //                {
    //                    $data['qr_code'] = '/advocacy/assets/code.png';
    //                }
    //                $data['avatar'] = $info['avatar'];
    //                $data['nickname'] = $info['nickname'];
    //                $title = '邀请代言';
    //            }
    //        }
    //        // 图片合成
    //        $data['combination'] = self::combination(request()->domain() . $data['qr_code'], $distribution_file_name);
    //        $shareData = [
    //            'from'            => '',
    //            'type'            => '',
    //            'distribution_id' => '',
    //            'cid'             => '',
    //            'scene'           => 1,               // 来源场景 1个人 2店铺 3平台
    //            'avatar'          => '',
    //            'nickname'        => '',
    //        ];
    //        if (array_key_exists('goods_id', $param) && $param['goods_id'])
    //        {
    //            $shareData['from'] = 'goods';
    //            $shareData['cid'] = $param['goods_id'];
    //            $shareData['type'] = 'goods';
    //        }
    //        if (array_key_exists('sid', $param) && $param['sid'])
    //        {
    //            if ($shareData['from'] === '')
    //            {
    //                $shareData['from'] = 'invite';
    //            }
    //            $shareData['distribution_id'] = $param['sid'];
    //            $shareData['type'] = 'dist';
    //        }
    //        if (!empty($info))
    //        {
    //            $shareData['avatar'] = $info['avatar'];
    //            $shareData['nickname'] = $info['nickname'];
    //        }
    //        $prefix = 'iShop:';
    //        $shareDataEnc = $prefix . $crypt->responseEnc($shareData, TRUE);
    //        return View::fetch(
    //            'distribution/share/toInviteWeb',
    //            [
    //                'data'         => $data,
    //                'shareDataEnc' => $shareDataEnc,
    //                'title'        => $title,
    //            ]
    //        );
    //    }
    //
    //    /**
    //     * 获取分销商信息
    //     * @param RSACrypt $crypt
    //     * @param Distribution $distribution
    //     * @return mixed
    //     */
    //    public function getInfo(RSACrypt $crypt, Distribution $distribution)
    //    {
    //        try
    //        {
    //            $param = $crypt->request();
    //            $param['member_id'] = request(TRUE)->mid ?? '';
    //            $data['dist'] = $distribution
    //                ->alias('d')
    //                ->where(
    //                    [
    //                        ['distribution_id', '=', $param['distribution_id']],
    //                        ['audit_status', '=', 1],
    //                    ]
    //                )
    //                ->join('member m', 'm.member_id = d.member_id')
    //                ->field('d.distribution_id,m.nickname,m.avatar')
    //                ->find();
    //            // 查询当前会员是否为分销商
    //            $data['cur'] = $distribution
    //                ->alias('d')
    //                ->where(
    //                    [
    //                        ['m.member_id', '=', $param['member_id']],
    //                    ]
    //                )
    //                ->join('member m', 'm.member_id = d.member_id')
    //                ->field('d.distribution_id,d.audit_status')
    //                ->find();
    //            return $crypt->response(['code' => 0, 'message' => config('message.')[0][0], 'data' => $data], TRUE);
    //        } catch (\Exception $e)
    //        {
    //            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
    //        }
    //    }
    //
    //    /**
    //     * 绑定会员和分销商关系
    //     * @param RSACrypt $crypt
    //     * @param Member $member
    //     * @param Distribution $distribution
    //     * @return mixed
    //     */
    //    public function bindDistribution(RSACrypt $crypt, Member $member, Distribution $distribution)
    //    {
    //        try
    //        {
    //            $param = $crypt->request();
    //            $param['member_id'] = request(TRUE)->mid ?? '';
    //            $info = $distribution
    //                ->where([['member_id', '=', $param['member_id']]])
    //                ->field('distribution_id,audit_status')
    //                ->find();
    //            if (!is_null($info) && $info['audit_status'] == 1)
    //            {
    //                return $crypt->response(['code' => -6, 'message' => config('message.')[-18][-6]], TRUE);
    //            }
    //            $member->allowField(TRUE)->isUpdate(TRUE)->save(
    //                [
    //                    'member_id'             => $param['member_id'],
    //                    'distribution_superior' => $param['superior'],
    //                ]
    //            );
    //            return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]], TRUE);
    //        } catch (\Exception $e)
    //        {
    //            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], TRUE);
    //        }
    //    }


}