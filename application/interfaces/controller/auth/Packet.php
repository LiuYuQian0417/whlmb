<?php
declare(strict_types=1);

namespace app\interfaces\controller\auth;

use app\common\model\Invite;
use app\common\model\InvitePacketLog;
use app\common\service\QrCode;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Env;
use think\facade\Request;

/**
 * 红包 - Joy
 * Class Packet
 * @package app\interfaces\controller\auth
 */
class Packet extends BaseController
{

    /**
     * 红包 - 邀请好友列表
     * @param RSACrypt $crypt
     * @param Invite $invite
     * @param InvitePacketLog $invitePacketLog
     * @param QrCode $qrCode
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,
                          Invite $invite,
                          InvitePacketLog $invitePacketLog,
                          QrCode $qrCode)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        // 邀请好友记录
        $result = $invitePacketLog
            ->alias('il')
            ->join('member m', 'm.member_id = il.invite_member_id')
            ->where([
                ['il.member_id', '=', $param['member_id']],
            ])
            ->field('nickname,title,price,DATE_FORMAT(create_time,"%Y-%m-%d") as date_time')
            ->order(['create_time' => 'desc'])
            ->limit(3)
            ->select();
        $statistics = [
            // 已邀请人
            'inviter' => $invite
                ->where([
                    ['member_id', '=', $param['member_id']],
                ])
                ->count(),
            // 成功下单人
            'order' => $invite
                ->where([
                    ['member_id', '=', $param['member_id']],
                    ['is_order', '=', 1],
                ])
                ->count(),
            // 累计获得红包
            'packet' => $invitePacketLog
                ->where([
                    ['member_id', '=', $param['member_id']],
                ])
                ->count(),
        ];
        $parameter = urlencode($crypt->singleEnc(['member_id' => $param['member_id']]) ?: '');
        $invCode = $qrCode->member_qrCode($param['member_id']);
        Env::load(Env::get('app_path') . 'common/ini/.config');
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'result' => $result,
            'parameter' => $parameter,
            'statistics' => $statistics,
            'invite_code' => Request::instance()->domain() . $invCode,
        ], true);
    }
}