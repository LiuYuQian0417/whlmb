<?php
declare(strict_types=1);

namespace app\interfaces\controller\distribution;


use app\common\model\Distribution;
use app\common\model\Member;
use app\interfaces\controller\BaseController;
use common\lib\phpcode\QrCode;
use EasyWeChat\Factory;
use mrmiao\encryption\RSACrypt;
use think\facade\Env;
use think\facade\Hook;
use think\facade\Request;
use think\facade\Response;
use think\facade\View;

/**
 * 分销商分享
 * Class Share
 * @package app\interfaces\controller\distribution
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
     * @param RSACrypt $crypt
     * @param Distribution $distribution
     * @return mixed
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function toInvite(RSACrypt $crypt, Distribution $distribution)
    {
        $param = $crypt->request();
        $param['member_id'] = request()->mid ?? '';
        if (!array_key_exists('distribution_id', $param) || !$param['distribution_id']) {
            return $crypt->response([
                'code' => -1,
                'message' => '分销商不存在',
            ], true);
        }
        $data['is_self'] = 0;
        if ($param['member_id']) {
            // 查询分销商信息
            $arr = $distribution
                ->alias('d')
                ->where([
                    ['distribution_id', '=', $param['distribution_id']],
                ])
                ->field('member_id,distribution_id')
                ->find();
            if (!is_null($arr) && $arr['member_id'] == $param['member_id']) {
                $data['is_self'] = 1;
            }
        }
        $publicPath = Env::get('root_path') . 'public';
        $file_dir = '/static/img/interfaces/qr_code/distribution/';
        $file_name = 'distribution_' . $param['distribution_id'];
        $data['domain'] = request()->domain();
        $data['qr_code'] = $data['combination'] = $data['bg_img'] = '';
        $combination_file_name = $this->set['DISTRIBUTION_CARD'] . '_' . $param['type'] . '_' . $param['distribution_id'];
        if ($param['type']) {
            if ($param['type'] == 1 && config('user.app.is_include')) {
                // app 1
                if (!file_exists($publicPath . ($data['qr_code'] = $file_dir . 'app/' . $file_name . '.png'))) {
                    // 文件不存在,重新生成二维码
                    require(Env::get('root_path') . 'extend/phpcode/QrCode.php');
                    QrCode::getQrCode($file_name,
                        request()->domain() . '/v2.0/distribution_share/to_invite_web?type=dist&sid=' . $param['distribution_id'],
                        $publicPath . $file_dir . 'app/',
                        6);
                }
                $combination_file_name = 'personal/' . $combination_file_name;
            }
            if ($param['type'] == 2 && config('user.mobile.is_include')) {
                // 手机站 2
                $mobileDomain = config('user.mobile.mobile_domain');
                if (!file_exists($publicPath . ($data['qr_code'] = $file_dir . 'mobile/' . $file_name . '.png'))) {
                    // 文件不存在,重新生成二维码
                    require(Env::get('root_path') . 'extend/phpcode/QrCode.php');
                    QrCode::getQrCode($file_name,
                        $mobileDomain . '/InviteRepresent?distribution_id=' . $param['distribution_id'],
                        $publicPath . $file_dir . 'mobile/',
                        6);
                }
                $combination_file_name = 'mobile/' . $combination_file_name;
            }
        } else {
            // 小程序 0
            if (config('user.applet.is_include')) {
                if (!file_exists($publicPath . ($data['qr_code'] = $file_dir . 'applet/' . $file_name . '.png'))) {
                    $app = Factory::miniProgram(config('wechat.')['applet']);
                    $response = $app->app_code->getUnlimit('scene,1-sup_id,' . $param['distribution_id'], [
                        'width' => 200,
                        'page' => 'my/fx_invitation/fx_invitation'
                    ]);
                    if ($response instanceof \EasyWeChat\Kernel\Http\StreamResponse) {
                        $response->saveAs($publicPath . $file_dir . 'applet/', $file_name . '.png');
                    }
                }
                $combination_file_name = 'applet/' . $combination_file_name;
            }
        }
        if ($data['qr_code']) {
            $data['bg_img'] = dirname($file_dir) . '/card/' . $this->set['DISTRIBUTION_CARD'] . '.png';
            // 图片合成
            $data['combination'] = self::combination(request()->domain() . $data['qr_code'], $combination_file_name);
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $data,
        ], true);
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
        $bg_img = Env::get('ROOT_PATH').'public' . dirname($combination) . '/card/' . $this->set['DISTRIBUTION_CARD'] . '.png';
        $qr_code_resource = imagecreatefromstring(file_get_contents($qr_code));
        $bg_img_resource = imagecreatefromstring(file_get_contents($bg_img));
        list($qCodeWidth, $qCodeHeight, $qCodeType) = getimagesize($qr_code);
        imagecopymerge($bg_img_resource, $qr_code_resource, 240, 700, 0, 0, $qCodeWidth, $qCodeHeight, 100);
        imagejpeg($bg_img_resource, $publicPath . $combination . $combination_file_name . '.jpg');
        imagedestroy($qr_code_resource);
        imagedestroy($bg_img_resource);
        return $combination . $combination_file_name . '.jpg';
    }

    /**
     * 分享出去的web页
     * @param Distribution $distribution
     * @param RSACrypt $crypt
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function toInviteWeb(Distribution $distribution, RSACrypt $crypt)
    {
        $param = Request::get();
        $arr = ['dist', 'goods'];
        if (!array_key_exists('type', $param) || !in_array($param['type'], $arr)) {
            $response = Response::create('您访问的页面不存在', 'html', 404);
            $response->send();
        }
        $info = [];
        if (array_key_exists('sid', $param) && $param['sid']) {
            // 查询用户分销商信息
            $info = $distribution
                ->alias('d')
                ->where([
                    ['distribution_id', '=', $param['sid']],
                ])
                ->join('member m', 'm.member_id = d.member_id')
                ->field('m.avatar,m.nickname')
                ->find();
        }
        $publicPath = Env::get('root_path') . 'public';
        $file_dir = '/static/img/interfaces/qr_code/distribution/';
        if (array_key_exists('sid', $param) && !array_key_exists('goods_id', $param)) {
            if ($param['sid']) {
                $file_name = 'distribution_' . $param['sid'];
                // 个人邀请
                if (!file_exists($publicPath . ($data['qr_code'] = $file_dir . 'app/' . $file_name . '.png'))) {
                    // 文件不存在,重新生成二维码
                    require(Env::get('root_path') . 'extend/phpcode/QrCode.php');
                    QrCode::getQrCode($file_name,
                        request()->domain() . '/v2.0/distribution_share/to_invite_web?type=dist&sid=' . $param['sid'],
                        $publicPath . $file_dir . 'app/',
                        6);
                }
                if (empty($info) || is_null($info)) {
                    $data['qr_code'] = $file_dir . 'card/code.png';
                }
                $data['avatar'] = !empty($info) ? $info['avatar'] : null;
                $data['nickname'] = !empty($info) ? $info['nickname'] : 'iShop';
                $distribution_file_name = 'personal/' . $this->set['DISTRIBUTION_CARD'] . '_1_' . $param['sid'];
            } else {
                // 平台邀请
                $data['qr_code'] = $file_dir . 'card/code.png';
                $data['avatar'] = null;
                $data['nickname'] = 'iShop';
                $distribution_file_name = 'plat/' . $this->set['DISTRIBUTION_CARD'] . '_plat';
            }
            $title = '邀请代言';
        } elseif (array_key_exists('goods_id', $param) && !array_key_exists('sid', $param)) {
            // 不含分销
            $distribution_file_name = 'goods/goods_' . $this->set['DISTRIBUTION_CARD'] . '_' . $param['goods_id'];
            $file_dir = $file_dir . '/goods/distribution_native/';
            $file_name = 'goods_' . $param['goods_id'];
            if (!file_exists($publicPath . ($data['qr_code'] = $file_dir . $file_name . '.png'))) {
                // 文件不存在,重新生成二维码
                require(Env::get('root_path') . 'extend/phpcode/QrCode.php');
                QrCode::getQrCode($file_name,
                    request()->domain() . '/v2.0/distribution_share/to_invite_web?type=goods&goods_id=' . $param['goods_id'],
                    $publicPath . $file_dir,
                    6);
            }
            $title = '分享商品';
        } else {
            // 分销商品
            $distribution_file_name = 'distribution_goods/goods_dis_' . $this->set['DISTRIBUTION_CARD'] . '_' . $param['sid'];
            $file_dir = $file_dir . '/goods/distribution/';
            $file_name = 'distribution_' . $param['goods_id'] . '_' . $param['sid'];
            if (!file_exists($publicPath . ($data['qr_code'] = $file_dir . $file_name . '.png'))) {
                // 文件不存在,重新生成二维码
                require(Env::get('root_path') . 'extend/phpcode/QrCode.php');
                QrCode::getQrCode($file_name,
                    request()->domain() . '/v2.0/distribution_share/to_invite_web?type=dist&sid=' . $param['sid'] . '&goods_id=' . $param['goods_id'],
                    $publicPath . $file_dir,
                    6);
            }
            if (empty($info) || is_null($info)) {
                $data['qr_code'] = dirname(dirname($file_dir)) . '/card/code.png';
            }
            $data['avatar'] = !empty($info) ? $info['avatar'] : null;
            $data['nickname'] = !empty($info) ? $info['nickname'] : '';
            $title = '邀请代言';
        }
        // 图片合成
        $data['combination'] = self::combination(Env::get('ROOT_PATH').'public'. $data['qr_code'], $distribution_file_name);
        $shareData = [
            'from' => '',
            'type' => '',
            'distribution_id' => '',
            'cid' => '',
            'scene' => 1,               // 来源场景 1个人 2店铺 3平台
            'avatar' => '',
            'nickname' => '',
        ];
        if (array_key_exists('goods_id', $param) && $param['goods_id']) {
            $shareData['from'] = 'goods';
            $shareData['cid'] = $param['goods_id'];
            $shareData['type'] = 'goods';
        }
        if (array_key_exists('sid', $param) && $param['sid']) {
            if ($shareData['from'] === '') {
                $shareData['from'] = 'invite';
            }
            $shareData['distribution_id'] = $param['sid'];
            $shareData['type'] = 'dist';
        }
        if (!empty($info)) {
            $shareData['avatar'] = $info['avatar'];
            $shareData['nickname'] = $info['nickname'];
        }
        $prefix = 'iShop:';
        $shareDataEnc = $prefix . $crypt->responseEnc($shareData, true);
        return View::fetch('distribution/share/toInviteWeb', [
            'data' => $data,
            'shareDataEnc' => $shareDataEnc,
            'title' => $title,
        ]);
    }

    /**
     * 获取分销商信息
     * @param RSACrypt $crypt
     * @param Distribution $distribution
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getInfo(RSACrypt $crypt,
                            Distribution $distribution,
                            Member $member)
    {
        $param = $crypt->request();
        $param['member_id'] = request(true)->mid ?? '';
        $data['dist'] = $distribution
            ->alias('d')
            ->where([
                ['distribution_id', '=', $param['distribution_id']],
                ['audit_status', '=', 1],
            ])
            ->join('member m', 'm.member_id = d.member_id')
            ->field('d.distribution_id,m.nickname,m.avatar')
            ->find();
        // 查询当前会员是否为分销商
        $data['cur'] = $distribution
            ->alias('d')
            ->where([
                ['m.member_id', '=', $param['member_id']],
            ])
            ->join('member m', 'm.member_id = d.member_id')
            ->field('d.distribution_id,d.audit_status')
            ->find();
        $appRulePath = [
            'form',                 //表单
            'normalSpeaker',        //普通专区
            'underReview',          //审核中
            'appointSpeaker',       //指定专区
        ];
        $data['click'] = '';
        if ($data['is_open'] = Env::get('distribution_status')) {
            // 开启模块
            if ($param['member_id']) {
                $info = $member
                    ->where([['member_id', '=', $param['member_id']]])
                    ->field('member_id')
                    ->with(['distributionConvert'])
                    ->find();
                if ($info && $info['distribution_convert']) {
                    // 已登录并且已有分销商记录
                    if ($info['distribution_convert']['audit_status'] == 1) {
                        // 已经审核为分销商[专区]
                        $data['click'] = $appRulePath[1];
                    } elseif ($info['distribution_convert']['audit_status'] == 0) {
                        // 分销商记录未审核[审核中]
                        // 判断当前是否为表单提交,否为专区
                        $flag = Env::get('distribution_manual', 0) ? 2 : 3;
                        $data['click'] = $appRulePath[$flag];
                    }
                } elseif (!$info['distribution_convert']) {
                    // 用户已登录无分销审核记录
                    $flag = Env::get('distribution_manual', 0) ? 0 : (Env::get('distribution_buy', 0) ? 3 : 1);
                    $data['click'] = $appRulePath[$flag];
                }
            } else {
                // 用户未登录
                $data['click'] = $appRulePath[1];
            }
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $data,
        ], true);
    }

    /**
     * 绑定会员和分销商关系
     * @param RSACrypt $crypt
     * @param Member $member
     * @param Distribution $distribution
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function bindDistribution(RSACrypt $crypt,
                                     Member $member,
                                     Distribution $distribution)
    {
        $param = $crypt->request();
        $param['member_id'] = request(true)->mid ?? '';
        $info = $distribution
            ->alias('d')
            ->where([
                ['d.member_id', '=', $param['member_id']],
            ])
            ->join('member m', 'm.member_id = d.member_id')
            ->field('d.distribution_id,d.audit_status,d.referrer_id,m.nickname')
            ->find();
        if (!is_null($info) && $info['audit_status'] == 1) {
            if ($info->referrer_id) {
                return $crypt->response([
                    'code' => 0,
                    'message' => '该会员已绑定分销商',
                ], true);
            } else {
                $bindData = [
                    'distribution_id' => $info['distribution_id'],
                    'distribution_superior' => $param['superior'],
                    'nickname' => $info['nickname'],
                ];
                Hook::exec(['app\\interfaces\\behavior\\Distribution', 'bindExisted'], $bindData);
            }
        }
        $member
            ->allowField(true)
            ->isUpdate(true)
            ->save([
                'member_id' => $param['member_id'],
                'distribution_superior' => $param['superior'],
            ]);
        return $crypt->response([
            'code' => 0,
            'message' => '绑定成功',
        ], true);
    }
    
    
}