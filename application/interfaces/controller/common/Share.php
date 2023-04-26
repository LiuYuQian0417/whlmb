<?php
declare(strict_types = 1);

namespace app\interfaces\controller\common;

use app\common\model\IntegralRecord;
use app\common\model\Member;
use app\common\model\MemberGrowthRecord;
use app\common\model\RedPacket;
use app\common\push\AliPush;
use app\common\service\PlanTask;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\Db;
use think\facade\Config;
use think\facade\Env;
use think\facade\Request;

/**
 * 分享管理
 * Class Area
 * @package app\interfaces\controller\common
 */
class Share extends BaseController
{
    
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }
    
    public function test11()
    {
        // $a = (new AliPush())->toSend([
        //     'account' => 18245132627,
        //     'title' => "测试12333334555",
        //     'body' => "测试内容567",
        //     'extra' => [
        //         'jumpKey' => 'userMsgList'
        //     ],
        // ]);
        // halt($a);
       $a = (new PlanTask(['id' => '136']))->autoCloseSaleAfter();
       halt($a);
    }
    
    /**
     * 版本号
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function version_number(RSACrypt $crypt)
    {
        return $crypt->response([
            'code' => 0,
            'result' => [
                'version_number' => Env::get('version_number', '2.0'),
                'show_version_number' => Env::get('show_version_number', '2.0'),
            ],
        ], true);
    }
    
    /**
     * 分享回调 - Joy
     * @param RSACrypt $crypt
     * @param IntegralRecord $integralRecord
     * @param MemberGrowthRecord $memberGrowthRecord
     * @param Member $member
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function notify(RSACrypt $crypt,
                           IntegralRecord $integralRecord,
                           MemberGrowthRecord $memberGrowthRecord,
                           Member $member)
    {
        $param = $crypt->request();
        $param['member_id'] = request(0)->mid;
        // 积分分享记录查询
        $integral_count = $integralRecord
            ->where([
                ['member_id', '=', $param['member_id']],
                ['type', '=', 0],                           // 0收入 1支出
                ['origin_point', '=', 7],                   // 分享商品
            ])
            ->whereTime('create_time', 'today')
            ->count();
        Db::startTrans();
        // 如果小于次数 新增积分
        if ($integral_count < Env::get('integral_share_number', 0)) {
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
            $member
                ->where([
                    ['member_id', '=', $param['member_id']],
                ])
                ->setInc('pay_points', (int)Env::get('integral_share', 0));
        }
        // 成长值分享记录查询
        $member_count = $memberGrowthRecord
            ->where([
                ['member_id', '=', $param['member_id']],
                ['type', '=', 0],
                ['describe', '=', '分享商品或活动']
            ])
            ->whereTime('create_time', 'today')
            ->count();
        // 如果小于次数 新增成长值
        if ($member_count < Env::get('growth_share_number', 0)) {
            // 插入成长值记录
            $memberGrowthRecord->save([
                'type' => 0,
                'member_id' => $param['member_id'],
                'growth_value' => Env::get('growth_share'),
                'describe' => '分享商品或活动',
                'create_time' => date('Y-m-d H:i:s')
            ]);
            // 检测会员成长值若升级则推送信息
            app('app\\interfaces\\behavior\\Growth')->checkCurGrowth([
                'member_id' => $param['member_id'],
                'web_open_id' => Request::param('web_open_id'),
                'subscribe_time' => Request::param('subscribe_time'),
                'phone' => Request::param('phone'),
            ]);
        }
        Db::commit();
        return $crypt->response([
            'code' => 0,
            'message' => '分享成功',
        ], true);
    }
    
    /**
     * 平台执照信息
     * @param RSACrypt $crypt
     * @return mixed
     */
    public function license(RSACrypt $crypt)
    {
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
        $ossConfig = Config::get('oss.');
        array_walk($data, function (&$v) use ($ossConfig) {
            if (!is_array($v)) {
                if ($v !== '') {
                    $v = $ossConfig['prefix'] . $v . $ossConfig['style'][0];
                }
            } else {
                if (!empty($v)) {
                    array_walk($v, function (&$vv) use ($ossConfig) {
                        if ($vv['path'] !== '') {
                            $vv['path'] = $ossConfig['prefix'] . $vv . $ossConfig['style'][0];
                        }
                    });
                } else {
                    $v = [];
                }
            }
        });
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $data,
        ], true);
    }
    
    /**
     * 分销导航跳转标签
     * @param RSACrypt $crypt
     * @param Member $member
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function jumpSign(RSACrypt $crypt,
                             Member $member)
    {
        $param = $crypt->request();
        $param['member_id'] = request()->mid ?? '';
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $distributionSet = Env::get();
        $inc['app_click'] = $inc['wap_click'] = $inc['path'] = '';
        // 查询平台开启的规则
        $disRulePath = [
            '/my/fx_apply_dy/fx_apply_dy',          // 提交表单
            '/my/fx_goods_list/fx_goods_list',      // 专区
            '/my/fx_apply_sh/fx_apply_sh',          // 审核中
            '/my/fx_goods_list/fx_goods_list',      // 指定专区
        ];
        $wapRulePath = [
            'Application',  //表单
            'Represent',    //专区
            'ApplyWait',    //审核中
            'EndorsementZone',  //指定专区
        ];
        $appRulePath = [
            'form',                 //表单
            'speaker',              //专区
            'UnderReview',          //审核中
            'appointSpeaker',       //指定专区
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
                        // 判断当前是否为表单提交,否为专区
                        $flag = $distributionSet['DISTRIBUTION_MANUAL'] ? 2 : 3;
                        $inc['path'] = $disRulePath[$flag];
                        $inc['wap_click'] = $wapRulePath[$flag];
                        $inc['app_click'] = $appRulePath[$flag];
                    }
                } elseif (!$info['distribution_convert']) {
                    // 用户已登录无分销审核记录
                    $flag = Env::get('distribution_manual', 0) ? 0 : (Env::get('distribution_buy', 0) ? 3 : 1);
                    // 用户已登录无分销审核记录
                    $inc['path'] = $disRulePath[$flag];
                    $inc['wap_click'] = $wapRulePath[$flag];
                    $inc['app_click'] = $appRulePath[$flag];
                }
            } else {
                // 用户未登录
                $inc['path'] = $disRulePath[1];
                $inc['wap_click'] = $wapRulePath[1];
                $inc['app_click'] = $appRulePath[1];
            }
        }
        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $inc,
        ], true);
    }
}