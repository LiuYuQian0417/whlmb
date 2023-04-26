<?php
declare(strict_types=1);

namespace app\interfaces\controller\common;

use app\common\model\Member;
use app\common\model\PaymentConfig;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Env;

/**
 * 支付方式管理
 * Class Area
 * @package app\interfaces\controller\common
 */
class Pay extends BaseController
{

    /**
     * 充值/收银台支付方式 - Joy
     * @param RSACrypt $crypt
     * @param PaymentConfig $paymentConfig
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function recharge(RSACrypt $crypt,
                             PaymentConfig $paymentConfig,
                             Member $member)
    {
        $args = $crypt->request();
        $args['member_id'] = request(true)->mid ?? '';
        $where = [
            ['status', '=', 1],         // 1显示 0不显示
        ];
        Env::load(Env::get('app_path') . 'common/ini/.config');
        $result['is_balance'] = Env::get('is_balance', "1");
        // 充值支付方式剔除余额支付
        if ($args['type'] || !$result['is_balance']) {
            array_push($where, ['payment_config_id', '<>', 3]);
        }
        $result['pay_list'] = $paymentConfig
            ->where($where)
            ->field('type,name,file')
            ->order('sort', 'desc')
            ->select();
        // 检测用户是否设置过支付密码
        $result['has_pay_password'] = 0;
        if ($args['member_id']) {
            $result['has_pay_password'] = $member
                ->where([
                    ['member_id', '=', $args['member_id']],
                ])
                ->value('pay_password') ? 1 : 0;
        }
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
        ], true);
    }
}