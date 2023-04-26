<?php
declare(strict_types = 1);

namespace app\common\push;

use think\facade\Env;

/**
 * 公众号模板
 * Class Tpl
 * @package app\common\push
 */
class Tpl
{
    // 客服电话
    protected $kf;
    
    public function __construct()
    {
        Env::load(Env::get('app_path') . 'common/ini/.config');
        $this->kf = Env::get('phone', '0451-12345678');
    }
    
    /**
     * 获取配置
     * @param $arg
     * @param $type 0 公众号 1 小程序
     * @return array|mixed
     */
    public function getSet($arg, $type = 0)
    {
        $tplList = [
            // 入驻申请提醒
            'reside_apply_state' => [
                'mobile_id' => 'mZzVMGlZ5roUaEX-IvCz4kDWQutlDUgwGKzPdAM5sSc',  // 公众号模板库###OPENTM407435154
                'applet_id' => '5mZRXaDFjfZ9Hb4kMonKy-lH1zURVYQhoYUSKLbbP-Y',  // 小程序
                'mobile_url' => ['Message/' . $arg['inside_data']['type']][0],
                'applet_url' => ['my/message/message?tab=' . $arg['inside_data']['type']][0],
                'PcAppKey' => ['userMsgList#' . $arg['inside_data']['type']][0],
            ],
            // 店铺状态提醒
            'shop_state' => [
                'mobile_id' => 'Zpr58Ooaj5x8XtasJIjLQZTmLdlr3aKsIOkihSB-Soo', //公众号模板库###OPENTM405904684
                'applet_id' => '5mZRXaDFjfZ9Hb4kMonKy-lH1zURVYQhoYUSKLbbP-Y',
                'mobile_url' => ['Message/' . $arg['inside_data']['type']][0],
                'applet_url' => ['my/message/message?tab=' . $arg['inside_data']['type']][0],
                'PcAppKey' => ['userMsgList#' . $arg['inside_data']['type']][0],
            ],
            // 活动商品提醒
            'active_goods_state' => [
                'mobile_id' => 'mZzVMGlZ5roUaEX-IvCz4kDWQutlDUgwGKzPdAM5sSc',
                'applet_id' => '5mZRXaDFjfZ9Hb4kMonKy-lH1zURVYQhoYUSKLbbP-Y',
                'mobile_url' => ['Message/' . $arg['inside_data']['type']][0],
                'applet_url' => ['my/message/message?tab=' . $arg['inside_data']['type']][0],
                'PcAppKey' => ['userMsgList#' . $arg['inside_data']['type']][0],
            ],
            // 用户状态提醒
            'member_state' => [
                'mobile_id' => 'mZzVMGlZ5roUaEX-IvCz4kDWQutlDUgwGKzPdAM5sSc',
                'applet_id' => '5mZRXaDFjfZ9Hb4kMonKy-lH1zURVYQhoYUSKLbbP-Y',
                'mobile_url' => ['Message/' . $arg['inside_data']['type']][0],
                'applet_url' => ['my/message/message?tab=' . $arg['inside_data']['type']][0],
                'PcAppKey' => ['userMsgList#' . $arg['inside_data']['type']][0],
            ],
            // 订单状态提醒
            'order_state' => [
                'mobile_id' => 'mZzVMGlZ5roUaEX-IvCz4kDWQutlDUgwGKzPdAM5sSc',
                'applet_id' => '5mZRXaDFjfZ9Hb4kMonKy-lH1zURVYQhoYUSKLbbP-Y',
                'mobile_url' => ['Message/' . $arg['inside_data']['type']][0],
                'applet_url' => ['my/message/message?tab=' . $arg['inside_data']['type']][0],
                'PcAppKey' => ['userMsgList#' . $arg['inside_data']['type']][0],
            ],
            // 商品状态提醒
            'goods_state' => [
                'mobile_id' => 'mZzVMGlZ5roUaEX-IvCz4kDWQutlDUgwGKzPdAM5sSc',
                'applet_id' => '5mZRXaDFjfZ9Hb4kMonKy-lH1zURVYQhoYUSKLbbP-Y',
                'mobile_url' => ['Message/' . $arg['inside_data']['type']][0],
                'applet_url' => ['my/message/message?tab=' . $arg['inside_data']['type']][0],
                'PcAppKey' => ['userMsgList#' . $arg['inside_data']['type']][0],
            ],
            // 分销商状态提醒[暂时未定推送]
            'distribution_state' => [
                'mobile_id' => 'mZzVMGlZ5roUaEX-IvCz4kDWQutlDUgwGKzPdAM5sSc',
                'applet_id' => '5mZRXaDFjfZ9Hb4kMonKy-lH1zURVYQhoYUSKLbbP-Y',
                'mobile_url' => ['Message/' . $arg['inside_data']['type']][0],
                'applet_url' => ['my/message/message?tab=' . $arg['inside_data']['type']][0],
                'PcAppKey' => ['userMsgList#' . $arg['inside_data']['type']][0],
            ],
            // 文章[站内]
            'article' => [
                'mobile_id' => 'mZzVMGlZ5roUaEX-IvCz4kDWQutlDUgwGKzPdAM5sSc',
                'applet_id' => '5mZRXaDFjfZ9Hb4kMonKy-lH1zURVYQhoYUSKLbbP-Y',
                'mobile_url' => ['Message/' . $arg['inside_data']['type']][0],
                'applet_url' => ['my/message/message?tab=' . $arg['inside_data']['type']][0],
                'PcAppKey' => ['userMsgList#' . $arg['inside_data']['type']][0],
            ],
            // 积分提醒
            'integral_remind' => [
                'mobile_id' => 'mZzVMGlZ5roUaEX-IvCz4kDWQutlDUgwGKzPdAM5sSc',
                'applet_id' => '5mZRXaDFjfZ9Hb4kMonKy-lH1zURVYQhoYUSKLbbP-Y',
                'mobile_url' => ['Message/' . $arg['inside_data']['type']][0],
                'applet_url' => ['my/message/message?tab=' . $arg['inside_data']['type']][0],
                'PcAppKey' => ['userMsgList#' . $arg['inside_data']['type']][0],
            ],
            // 红包提醒
            'packet_remind' => [
                'mobile_id' => 'mZzVMGlZ5roUaEX-IvCz4kDWQutlDUgwGKzPdAM5sSc',
                'applet_id' => '5mZRXaDFjfZ9Hb4kMonKy-lH1zURVYQhoYUSKLbbP-Y',
                'mobile_url' => ['Message/' . $arg['inside_data']['type']][0],
                'applet_url' => ['my/message/message?tab=' . $arg['inside_data']['type']][0],
                'PcAppKey' => ['userMsgList#' . $arg['inside_data']['type']][0],
            ],
            // 优惠券提醒
            'coupon_remind' => [
                'mobile_id' => 'mZzVMGlZ5roUaEX-IvCz4kDWQutlDUgwGKzPdAM5sSc',
                'applet_id' => '5mZRXaDFjfZ9Hb4kMonKy-lH1zURVYQhoYUSKLbbP-Y',
                'mobile_url' => ['Message/' . $arg['inside_data']['type']][0],
                'applet_url' => ['my/message/message?tab=' . $arg['inside_data']['type']][0],
                'PcAppKey' => ['userMsgList#' . $arg['inside_data']['type']][0],
            ],
        ];
        $ret = [];
        if (isset($tplList[$key = $arg['tplKey']])) {
            $ret = $tplList[$key];
            $ret['data'] = self::$key($arg['data'], $type);
        }
        return $ret;
    }
    
    /**
     * 入驻申请提醒
     * @param $arg [审核状态,店铺名]
     * @param $type
     * @return array
     */
    public function reside_apply_state($arg, $type)
    {
        if (count($arg) < 4) {
            $arg = array_merge($arg, explode(' ', str_repeat(' ', 3 - count($arg))));
        }
        $data = [
            ['first', 'keyword1'][$type] => ['value' => ['入驻申请提交成功', '审核成功', '审核失败'][$arg[0]],],
            ['keyword1', 'keyword2'][$type] => ['value' => date('Y年m月d日 H时i分'),],
            ['keyword2', 'keyword3'][$type] => ['value' => [
                sprintf('您的【%s】店铺申请正在审核，请关注短信通知，查看审核结果。', $arg[1]),
                sprintf('恭喜您！您的【%s】店铺申请已通过审核，可登录商家后台填写认证信息；商家后台地址：%s，账号为您会员手机号，密码为%s。', $arg[1], $arg[2], $arg[3]),
                sprintf('您的【%s】店铺申请审核未通过，审核未通过原因：%s，您可以重新提交申请。', $arg[1], $arg[2]),
            ][$arg[0]],],
            ['remark', 'keyword4'][$type] => ['value' => '如有问题请咨询客服，' . $this->kf,],
        ];
        return $data;
    }
    
    /**
     * 店铺状态提醒
     * @param $arg [店铺状态,店铺名,注销原因]
     * @param $type
     * @return array
     */
    public function shop_state($arg, $type)
    {
        if (count($arg) < 3) {
            $arg = array_merge($arg, explode(' ', str_repeat(' ', 2 - count($arg))));
        }
        $data = [
            ['first', 'keyword1'][$type] => ['value' => ['店铺注销通知', '店铺启用通知'][$arg[0]],],
            ['keyword1', 'keyword2'][$type] => ['value' => date('Y-m-d H:i:s'),],
            ['keyword2', 'keyword3'][$type] => ['value' => [
                sprintf('您的【%s】店铺被管理员无情的注销，注销原因：%s，', $arg[1], $arg[2]),
                sprintf('恭喜您，您的【%s】店铺已经重新启用，快去商家后台打理店铺吧', $arg[1]),
            ][$arg[0]],],
            ['remark', 'keyword4'][$type] => ['value' => '如有问题请咨询客服，' . $this->kf,],
        ];
        return $data;
    }
    
    /**
     * 活动商品提醒
     * @param $arg [活动类型,店铺名,商品名]
     * @param $type
     * @return array
     */
    public function active_goods_state($arg, $type)
    {
        if (count($arg) < 4) {
            $arg = array_merge($arg, explode(' ', str_repeat(' ', 3 - count($arg))));
        }
        $data = [
            ['first', 'keyword1'][$type] => ['value' => ['砍价进度通知', '砍价成功通知', '拼团失败通知', '拼团成功通知'][$arg[0]],],
            ['keyword1', 'keyword2'][$type] => ['value' => date('Y年m月d日 H时i分'),],
            ['keyword2', 'keyword3'][$type] => ['value' => ($arg[1] ? "【$arg[1]】" : '') . [
                    sprintf('启禀%s大人，您参加【%s】宝贝的砍价活动就要砍到最低价了，快去邀请好友帮你砍一刀吧！', $arg[2], $arg[3]),
                    sprintf('恭喜%s大人，您参加【%s】宝贝的砍价已经砍到最低价了，快去下单吧，记得向帮您砍价的好友说声下次还要帮我砍价！', $arg[2], $arg[3]),
                    sprintf('很遗憾，%s大人，您参加【%s】宝贝的拼团活动，由于人数不足拼团失败了，不要气馁，再去看看其他拼团商品吧，会有惊喜哦！', $arg[2], $arg[3]),
                    sprintf('恭喜%s大人，您参加的【%s】宝贝的拼团活动，拼团成功啦！我们正在努力备货，请您喝会茶，坐等发货吧。', $arg[2], $arg[3]),
                ][$arg[0]],],
            ['remark', 'keyword4'][$type] => ['value' => '如有问题请咨询客服，' . $this->kf,],
        ];
        return $data;
    }
    
    /**
     * 用户状态提醒
     * @param $arg [用户状态]
     * @param $type
     * @return array
     */
    public function member_state($arg, $type)
    {
        if (count($arg) < 2) {
            $arg = array_merge($arg, explode(' ', str_repeat(' ', 1 - count($arg))));
        }
        $data = [
            ['first', 'keyword1'][$type] => ['value' => ['加入黑名单', '移除黑名单', '会员升级'][$arg[0]],],
            ['keyword1', 'keyword2'][$type] => ['value' => date('Y年m月d日 H时i分'),],
            ['keyword2', 'keyword3'][$type] => ['value' => [
                '很抱歉，您被平台管理员无情的加入黑名单了，您将无法登录商城。',
                '恭喜您，平台已经为您洗刷冤屈，您已恢复会员身份，可以登录商城开始您的购物之旅了！',
                sprintf('恭喜您已经晋升成为%s会员，您将享受更多的vip会员服务，快去商城体验吧。', $arg[1]),
            ][$arg[0]],],
            ['remark', 'keyword4'][$type] => ['value' => '如有问题请咨询客服，' . $this->kf,],
        ];
        return $data;
    }
    
    /**
     * 订单状态提醒
     * @param $arg [订单状态,店铺名,用户名,[商品名]]
     * @param $type
     * @return array
     */
    public function order_state($arg, $type)
    {
        if (count($arg) < 4) {
            $arg = array_merge($arg, explode(' ', str_repeat(' ', 3 - count($arg))));
        }
        $data = [
            ['first', 'keyword1'][$type] => ['value' => ['待付款', '付款成功', '已发货', '退款失败', '退款成功',
                '降价通知', '确认收货', '到店自提', '已发货【抽奖】', '已发货【积分】'][$arg[0]],],
            ['keyword1', 'keyword2'][$type] => ['value' => date('Y年m月d日 H时i分'),],
            ['keyword2', 'keyword3'][$type] => ['value' => ($arg[1] ? "【$arg[1]】" : '') . [
                    sprintf('启禀%s大人，您相中的宝贝已经准备就绪，只等大人付款，付款后我们立即为您发货，恭候大人佳音。', $arg[2]),
                    sprintf('恭喜%s大人，获得了您相中已久的宝贝，我们正在积极备货中，很快就会送到您的手中。', $arg[2]),
                    sprintf('启禀%s大人，您购买的宝贝已经发货，请查看物流信息跟踪宝贝动态，记得再来哦，亲！', $arg[2]),
                    sprintf('很抱歉，%s大人，您的【%s】宝贝退款申请没有通过。', $arg[2], $arg[3]),
                    sprintf('启禀%s大人，您的【%s】宝贝退款成功，我们会用心服务每一个顾客，闲暇时间多到本店逛逛，欢迎您再次光临。', $arg[2], $arg[3]),
                    sprintf('启禀%s大人，您关注的【%s】宝贝已经降价，为了不让您遗憾，请以光速去抢购，come on！', $arg[2], $arg[3]),
                    sprintf('恭喜%s大人，您购买的宝贝已经确认收货，记得去给个好评哦！期待您下次光临。', $arg[2]),
                    sprintf('恭喜%s大人，您购买的宝贝已经到店提走，记得给个好评哦！期待您下次光临。', $arg[2]),
                    sprintf('恭喜%s大人，您抽中的【%s】宝贝已经发货。', $arg[2], $arg[3]),
                    sprintf('恭喜%s大人，您购买的【%s】宝贝已经发货。', $arg[2], $arg[3]),
                ][$arg[0]],],
            ['remark', 'keyword4'][$type] => ['value' => '如有问题请咨询客服，' . $this->kf,],
        ];
        return $data;
    }
    
    /**
     * 分销提醒
     * @param $arg [分销类型,用户名,被邀请人|分销等级名称]
     * @param $type
     * @return array
     */
    public function distribution_state($arg, $type)
    {
        if (count($arg) < 3) {
            $arg = array_merge($arg, explode(' ', str_repeat(' ', 2 - count($arg))));
        }
        $data = [
            ['first', 'keyword1'][$type] => ['value' => ['成功获取下级粉丝', '分销商晋级', '您已失去分销商资格',
                '会员转化分销商成功', '恭喜您成为分销商', '分销商申请成功', '分销商申请失败'][$arg[0]],],
            ['keyword1', 'keyword2'][$type] => ['value' => date('Y年m月d日 H时i分'),],
            ['keyword2', 'keyword3'][$type] => ['value' => [
                sprintf('恭喜%s大人，由于您的独具魅力，您的好友%s在您的引导下已经成为代言人，还要继续努力哦！', $arg[1], $arg[2]),
                sprintf('恭喜%s大人，由于您的努力，已经晋级为%s级别代言人，更多收益等您拿！', $arg[1], $arg[2]),
                ['售后了全部成为分销商指定商品', '售后导致成为分销商资格的订单金额不足', '平台取消分销商资格'][is_numeric($arg[1]) ? $arg[1] : 0],
                '恭喜您转化分销商成功',
                ['购买指定商品成为分销商', '订单金额达标', '注册即成为分销商'][is_numeric($arg[1]) ? $arg[1] : 0],
                '恭喜您申请分销商成功',
                sprintf("您的分销商审核未通过,原因: %s", $arg[1]),
            ][$arg[0]],],
            ['remark', 'keyword4'][$type] => ['value' => '如有问题请咨询客服，' . $this->kf,],
        ];
        return $data;
    }
    
    /**
     * 商品状态通知
     * @param $arg
     * @param $type
     * @return array
     */
    public function goods_state($arg, $type)
    {
        if (count($arg) < 4) {
            $arg = array_merge($arg, explode(' ', str_repeat(' ', 3 - count($arg))));
        }
        $data = [
            ['first', 'keyword1'][$type] => ['value' => ['降价通知', '到货提醒'][$arg[0]],],
            ['keyword1', 'keyword2'][$type] => ['value' => date('Y年m月d日 H时i分'),],
            ['keyword2', 'keyword3'][$type] => ['value' => ($arg[1] ? "【$arg[1]】" : '') . [
                    sprintf('启禀%s大人，您关注的【%s】宝贝已经降价，为了不让您遗憾，请以光速去抢购，come on！', $arg[2], $arg[3]),
                    sprintf('您购物车/收藏夹中的商品【%s】到货啦，快去看看吧！go>>', $arg[2]),
                ][$arg[0]],],
            ['remark', 'keyword4'][$type] => ['value' => '如有问题请咨询客服，' . $this->kf,],
        ];
        return $data;
    }
    
    /**
     * 文章
     * @param $arg
     * @param $type
     * @return array
     */
    public function article($arg, $type)
    {
        if (count($arg) < 2) {
            $arg = array_merge($arg, explode(' ', str_repeat(' ', 3 - count($arg))));
        }
        $data = [
            ['first', 'keyword1'][$type] => ['value' => $arg[0],],
            ['keyword1', 'keyword2'][$type] => ['value' => date('Y年m月d日 H时i分'),],
            ['keyword2', 'keyword3'][$type] => ['value' => $arg[1],],
            ['remark', 'keyword4'][$type] => ['value' => '如有问题请咨询客服，' . $this->kf,],
        ];
        return $data;
    }
    
    /**
     * 积分提醒
     * @param $arg
     * @param $type
     * @return array
     */
    public function integral_remind($arg, $type)
    {
        if (count($arg) < 4) {
            $arg = array_merge($arg, explode(' ', str_repeat(' ', 3 - count($arg))));
        }
        $data = [
            ['first', 'keyword1'][$type] => ['value' => ['积分到账啦', '积分使用提醒', '积分过期提醒'][$arg[0]],],
            ['keyword1', 'keyword2'][$type] => ['value' => date('Y年m月d日 H时i分'),],
            ['keyword2', 'keyword3'][$type] => ['value' => [
                sprintf('%s积分到账啦！攒积分可享受更多特权！go>>', $arg[1]),
                sprintf('您刚使用%s积分！要赚更多积分？go>>', $arg[1]),
                sprintf('您的积分即将过期！快去换购吧！go>>'),
            ][$arg[0]],],
            ['remark', 'keyword4'][$type] => ['value' => '如有问题请咨询客服，' . $this->kf,],
        ];
        return $data;
    }
    
    /**
     * 红包提醒
     * @param $arg
     * @param $type
     * @return array
     */
    public function packet_remind($arg, $type)
    {
        if (count($arg) < 2) {
            $arg = array_merge($arg, explode(' ', str_repeat(' ', 3 - count($arg))));
        }
        $data = [
            ['first', 'keyword1'][$type] => ['value' => ['红包到账提醒', '红包到期提醒'][$arg[0]],],
            ['keyword1', 'keyword2'][$type] => ['value' => date('Y年m月d日 H时i分'),],
            ['keyword2', 'keyword3'][$type] => ['value' => [
                '恭喜您获得红包，快去看看吧！go>>',
                '您有超值红包今天将过期，用红包可享折上折，别浪费哦！go>>',
            ][$arg[0]],],
            ['remark', 'keyword4'][$type] => ['value' => '如有问题请咨询客服，' . $this->kf,],
        ];
        return $data;
    }
    
    /**
     * 优惠券提醒
     * @param $arg
     * @param $type
     * @return array
     */
    public function coupon_remind($arg, $type)
    {
        if (count($arg) < 2) {
            $arg = array_merge($arg, explode(' ', str_repeat(' ', 3 - count($arg))));
        }
        $data = [
            ['first', 'keyword1'][$type] => ['value' => ['优惠券到账提醒', '优惠券到期提醒'][$arg[0]],],
            ['keyword1', 'keyword2'][$type] => ['value' => date('Y年m月d日 H时i分'),],
            ['keyword2', 'keyword3'][$type] => ['value' => [
                '恭喜您获得优惠券，快去看看吧！go>>',
                '您有超值优惠券今天将过期，用券可享折上折，别浪费哦！go>>',
            ][$arg[0]],],
            ['remark', 'keyword4'][$type] => ['value' => '如有问题请咨询客服，' . $this->kf,],
        ];
        return $data;
    }
}