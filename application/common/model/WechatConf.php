<?php
declare(strict_types=1);

namespace app\common\model;


use think\facade\Cache;

class WechatConf extends BaseModel
{


    public static function init()
    {
        parent::init();
    }

    /**
     * 允许更新的字段
     */
    const ALLOW_SAVE_NAME = [
        'wechat_name',
        'wechat_original_id',
        'wechat_app_id',
        'wechat_app_secret',
        'wechat_token',
        'wechat_encoding_aes_key',
        'diy-menu_content',
        'applet_app_id',
        'applet_app_secret',
        'diy-replay_content',
        'micro-template_pay_done',
        'micro-template_request_enter_audit_result',
        'micro-template_cut_progress',
        'micro-template_cut_success',
        'micro-template_refund_success',
        'micro-template_take_away',
        'micro-template_group_failed',
        'micro-template_group_success',
        'micro-template_recharge-success',
        'micro-template_pending_payment',
        'micro-template_order_ship',
        'micro-template_order_pay_success',
        'wechat-pay_mch_id',
        'wechat-pay_key',
        'micro-pay_mch_id',
        'micro-pay_key',
        'wechat-app_app_id',
        'wechat-app_app_secret',
        'wechat-app-pay_mch_id',
        'wechat-app-pay_key',
        'micro-template_enter_apply',
        'micro-template_store_type',
        'micro-template_put_blacklist',
        'micro-template_remove_blacklist',
        'micro-template_member_upgrade',
        'micro-template_refund_defeated',
        'micro-template_price_off',
        'micro-template_confirm_receipt',
        'micro-template_subordinate_fans',
        'micro-template_distributor_promoted'
    ];


    /**
     * 获取配置
     * @param string $prefix 配置的前缀
     * @return array|string
     */
    public static function getConf($prefix = NULL)
    {
        $_where = [];
        $_data = [];

        if (!is_null($prefix)) {
            $_where[] = [
                'name',
                'like',
                $prefix . '_%',
            ];
        }

        $_cacheName = $prefix ?? 'all';

        if (Cache::tag('wechat-prefix')->has($_cacheName)) {
            return Cache::tag('wechat-prefix')->get($_cacheName);
        }
        try {

            $_result = self::where($_where)->select()->toArray();
            foreach ($_result as $value) {
                $_data[$value['name']] = $value;
                Cache::tag('wechat')->set($value['name'], $value);
            }

            Cache::tag('wechat-prefix')->set($_cacheName, $_data);
            return $_data;

        } catch (\Exception $e) {
            return $e->getMessage();
        }


    }

    /**
     * 更新微信配置
     *
     * @param array $data
     * @param null $prefix
     * @return bool|string
     */
    public static function setConf($data, $prefix = NULL)
    {
        $_keyList = array_keys($data);

        try {
            foreach ($_keyList as $value) {
                if (in_array($value, self::ALLOW_SAVE_NAME)) {
                    self::update([
                        'name' => $value,
                        'value' => $data[$value],
                    ], [
                        'name' => $value,
                    ]);
                }
            }
            Cache::clear('wechat');
            Cache::clear('wechat-prefix');
            return TRUE;
        } catch (\Exception $e) {
            return $e->getMessage();
        }

    }

    /**
     * 根据数组获取配置
     *
     * @param array $list 需要的数据的name名 一维数组
     *
     * @return array|bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author Malson
     */
    public static function getConfByList($list)
    {
        // 最终返回的数据
        $_data = [];

        $_results = self::where([
            'name' => $list,
        ])->select();

        foreach ($_results as $result) {
            $_data[$result['name']] = $result;
        }

        return $_data;
    }
}