<?php
declare(strict_types=1);

namespace app\common\service;

use think\facade\Config;
use think\facade\Env;
use think\facade\Request;
use mrmiao\encryption\RSACrypt;
use common\lib\phpcode\QrCode as CodeModel;

class QrCode
{
    private $publicPath;
    private $interfacePathPre;

    public function __construct()
    {
        require(Env::get('root_path') . 'extend/phpcode/QrCode.php');
        $this->publicPath = Env::get('root_path') . 'public';
        $this->interfacePathPre = '/static/img/interfaces/qr_code/';
    }

    /**
     * 会员邀请码生成
     * @param $member_id
     * @return bool|string
     * @throws \common\lib\phpcode\BadRequestHttpException
     */
    public function member_qrCode($member_id)
    {
        // 位置:/public/static/img/interfaces/qr_code/member_invite_code/invite_xxx.png
        $filename = 'invite_' . $member_id;
        if (!file_exists($this->publicPath . $this->interfacePathPre . 'member_invite_code/' . $filename . '.png')) {
            // 生成图片
            $param = Request::domain() . '/v2.0/register/invite?token=' . urlencode((new RSACrypt())->singleEnc(['member_id' => $member_id]));
            CodeModel::getQrCode($filename, $param, $this->publicPath . $this->interfacePathPre . 'member_invite_code/');
        }
        return $this->interfacePathPre . 'member_invite_code/' . $filename . '.png';
    }

    /**
     * 商品二维码生成
     * @param $goods_id
     * @param string $mobile_domain
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function goods_qrCode($goods_id, $mobile_domain = '')
    {
        // 本地路径
        $prefix = Env::get('root_path') . 'public/qr_code/app_goods/';
        if (!is_dir($prefix)) {
            mkdir($prefix, 0755, true);
        }
        $filename = $mobile_domain ? ('mobile_goods_' . $goods_id) : ('goods_' . $goods_id);
        if (!is_file($prefix . $filename . '.png')) {
            // 生成图片
            CodeModel::getQrCode($filename,
                $mobile_domain ? $mobile_domain . '/GoodDetail/' . $goods_id :
                    request()->domain() . '/v2.0/distribution_share/to_invite_web?type=goods&goods_id=' . $goods_id,
                $prefix, 6);
        }
        return Request::instance()->domain() . '/qr_code/app_goods/' . $filename . '.png';
    }

    /**
     * 商品分销商二维码生成
     * @param $goods_id
     * @param $distribution_id
     * @return string
     */
    public function goods_distribution_qrCode($goods_id, $distribution_id)
    {
        // 本地路径
        $prefix = Env::get('root_path') . 'public/qr_code/app_distribution/';
        if (!is_dir($prefix)) {
            mkdir($prefix, 0755, true);
        }
        if (!is_file($prefix . 'dis_' . $goods_id . '_' . $distribution_id . '.png')) {
            // 生成图片
            CodeModel::getQrCode('dis_' . $goods_id . '_' . $distribution_id,
                Request::domain() . '/v2.0/distribution_share/to_invite_web?type=dist&sid=' .
                $distribution_id . '&goods_id=' . $goods_id, $prefix, 6);
        }
        return Request::instance()->domain() . '/qr_code/app_distribution/dis_' . $goods_id . '_' . $distribution_id . '.png';
    }

    /**
     * 店铺二维码生成
     * @param $store_id
     * @return string
     * @throws \common\lib\phpcode\BadRequestHttpException
     */
    public function store_qrCode($store_id)
    {
        // 本地路径
        $prefix = Env::get('root_path') . 'public/static/img/interfaces/qr_code/store/app/';
        if (!file_exists($prefix . 'store_' . $store_id . '.png')) {
        	$local = config('user.mobile.mobile_domain') . '/ShopDetail/' . $store_id;
            $data = CodeModel::getQrCode('store_' . $store_id, $local, $prefix);    
        }
        return Request::domain() . '/static/img/interfaces/qr_code/store/app/store_' . $store_id . '.png';
    }


    /**
     * 下载二维码
     * @throws \common\lib\phpcode\BadRequestHttpException
     */
    public function download()
    {
        // 生成图片
        CodeModel::getQrCode('download', Request::instance()->domain() . '/download/index.html', Env::get('root_path') . 'public/');
    }
}