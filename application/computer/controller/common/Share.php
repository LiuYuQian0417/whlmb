<?php
declare(strict_types=1);

namespace app\computer\controller\common;

use app\computer\model\IntegralRecord;
use app\computer\model\Member;
use app\computer\model\MemberGrowthRecord;
use app\computer\model\OrderGoods;
use app\common\service\PlanTask;
use app\computer\behavior\PtPacket;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Env;
use think\facade\Hook;
use think\facade\Request;

/**
 * 分享管理
 * Class Area
 * @package app\computer\controller\common
 */
class Share extends BaseController
{

    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }

    /**废弃**/
    /**
     * LOGO
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function logo(RSACrypt $crypt)
    {
        return $crypt->response(['code' => 0, 'result' => fileOss(Env::get('logo'))]);
    }

    /**
     * 版本号
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function version_number(RSACrypt $crypt)
    {
        return $crypt->response(['code' => 0, 'result' => ['version_number' => Env::get('version_number'), 'show_version_number' => Env::get('show_version_number')]]);
    }

    /**
     * 测试文字 - 小程序
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function test(RSACrypt $crypt)
    {
        return $crypt->response(['code' => 0, 'result' => '商城所有商品为虚拟商品，都为测试数据']);
    }

    public function testtt()
    {
//        halt(strtotime('2019-02-23') - time());
//        (new PtPacket())->firstConsumption(['member_id' => 277]);
        try {
            halt(openssl_get_cipher_methods());
//            openssl_encrypt();
//            Db::startTrans();
//            $plantask = new PlanTask(['id' => 3802], './plantaskceshi.text');
////            $plantask = new PlanTask(['id' => 134,'order' => 1,'brokerage' => 1,], './plantaskceshi.text');
//            $a = $plantask->autoCloseSaleAfter();
////            $a = $plantask->distributionDowngradeCheck();
//            halt($a);
        } catch (\Exception $exception) {
//            Db::rollback();
//            halt($exception->getLine() . $exception->getMessage());
        }

    }


    /**
     * 分享文字 - 小程序
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function text(RSACrypt $crypt)
    {
        return $crypt->response(['code' => 0, 'result' => ['分享到好友', '朋友圈']]);
//         return $crypt->response(['code' => 0, 'result' => ['分享到好友']]);
    }

    /**
     * 分享回调 - Joy
     * @param Request $request
     * @param RSACrypt $crypt
     * @param IntegralRecord $integralRecord
     * @param MemberGrowthRecord $memberGrowthRecord
     * @param Member $member
     * @return array
     */
    public function notify(Request $request, RSACrypt $crypt, IntegralRecord $integralRecord, MemberGrowthRecord $memberGrowthRecord, Member $member)
    {

        if ($request::isPost()) {
            try {

                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;

                // 积分分享记录查询
                $integral_count = $integralRecord
                    ->where([
                        ['member_id', '=', $param['member_id']],
                        ['type', '=', 0],
                        ['origin_point', '=', 7]
                    ])
                    ->whereTime('create_time', 'd')
                    ->count();

                // 如果小于次数 新增积分
                if ($integral_count < Env::get('integral_share_number')) {

                    // 插入积分记录
                    $integralRecord->save([
                        'member_id' => $param['member_id'],
                        'type' => 0,
                        'origin_point' => 7,
                        'integral' => Env::get('integral_share'),
                        'describe' => '分享商品或活动',
                        'create_time' => date('Y-m-d H:i:s')
                    ]);

                    // 增加积分
                    $member->where('member_id', $param['member_id'])->setInc('pay_points', (int)Env::get('integral_share'));

                }

                // 成长值分享记录查询
                $member_count = $memberGrowthRecord
                    ->where([
                        ['member_id', '=', $param['member_id']],
                        ['type', '=', 0],
                        ['describe', '=', '分享商品或活动']
                    ])
                    ->whereTime('create_time', 'd')
                    ->count();

                // 如果小于次数 新增成长值
                if ($member_count < Env::get('growth_share_number')) {

                    // 插入成长值记录
                    $memberGrowthRecord->save([
                        'type' => 0,
                        'member_id' => $param['member_id'],
                        'growth_value' => Env::get('growth_share'),
                        'describe' => '分享商品或活动',
                        'create_time' => date('Y-m-d H:i:s')
                    ]);

                }

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
            }
        }
    }

    /**
     * 平台执照信息
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function license(RSACrypt $crypt)
    {
        try {
            $data = [
                // 营业执照
                'business_license' => Env::get('business_license'),
                // 经营许可证
                'other_licence' => Env::get('licence', ''),
            ];
            if ($data['other_licence']) {
                $data['other_licence'] = array_map(function ($v) {
                    $arr = explode(',-,', $v);
                    return [
                        'name' => reset($arr),
                        'path' => end($arr),
                    ];
                }, explode('-,-', $data['other_licence']));
            } else {
                $data['other_licence'] = [];
            }
            $ossManage = app('app\\common\\service\\OSS');
            array_walk($data, function (&$v) use ($ossManage) {
                if (!is_array($v)) {
                    if ($v !== '') {
                        $res = $ossManage->getSignUrlForGet($v . config('oss.')['style'][5]);
                        $v = ($res['code'] === 0) ? $res['url'] : '';
                    }
                } else {
                    if (!empty($v)) {
                        array_walk($v, function (&$vv) use ($ossManage) {
                            if ($vv['path'] !== '') {
                                $res = $ossManage->getSignUrlForGet($vv['path'] . config('oss.')['style'][5]);
                                $vv['path'] = ($res['code'] === 0) ? $res['url'] : '';
                            }
                        });
                    } else {
                        $v = [];
                    }
                }
            });
            return $crypt->response(['code' => 0, 'message' => config('message.')[0][0], 'data' => $data], true);
        } catch (\Exception $e) {
            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
        }
    }

    /**
     * app跳转标签
     * @param RSACrypt $crypt
     * @param Member $member
     * @return mixed
     */
    public function jumpSign(RSACrypt $crypt, Member $member)
    {
        try {
            $param = $crypt->request();
            $param['member_id'] = request()->mid ?? '';
            Env::load(Env::get('app_path') . 'common/ini/.distribution');
            $distributionSet = Env::get();
            $inc['app_click'] = $inc['wap_click'] = $inc['path'] = '';
            // 查询平台开启的规则
            $disRulePath = [
                '/my/fx_apply_dy/fx_apply_dy',          // 提交表单
                '/my/fx_goods_list/fx_goods_list',      // 专区
                '/my/fx_apply_sh/fx_apply_sh'           // 审核中
            ];
            $wapRulePath = [
                'Application',  //表单
                'Represent',    //专区
                'ApplyWait',    //审核中
            ];
            $appRulePath = [
                'form',         //表单
                'speaker',      //专区
                'UnderReview',  //审核中
            ];
            if ($inc['is_open'] = $distributionSet['DISTRIBUTION_STATUS']) {
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
                            $inc['path'] = $disRulePath[1];
                            $inc['wap_click'] = $wapRulePath[1];
                            $inc['app_click'] = $appRulePath[1];
                        } elseif ($info['distribution_convert']['audit_status'] == 0) {
                            // 分销商记录未审核[审核中]
                            $inc['path'] = $disRulePath[2];
                            $inc['wap_click'] = $wapRulePath[2];
                            $inc['app_click'] = $appRulePath[2];
                        }
                    } elseif (!$info['distribution_convert']) {
                        // 用户已登录无分销审核记录
                        $inc['path'] = $disRulePath[$distributionSet['DISTRIBUTION_MANUAL'] ? 0 : 1];
                        $inc['wap_click'] = $wapRulePath[$distributionSet['DISTRIBUTION_MANUAL'] ? 0 : 1];
                        $inc['app_click'] = $appRulePath[$distributionSet['DISTRIBUTION_MANUAL'] ? 0 : 1];
                    }
                } else {
                    // 用户未登录
                    $inc['path'] = $disRulePath[1];
                    $inc['wap_click'] = $wapRulePath[1];
                    $inc['app_click'] = $appRulePath[1];
                }
            }
            return $crypt->response(['code' => 0, 'message' => config('message.')[0][0], 'data' => $inc], true);
        } catch (\Exception $e) {
            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
        }
    }
}