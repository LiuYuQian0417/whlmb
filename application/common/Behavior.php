<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/13
 * Time: 15:32
 */

namespace app\common;

use app\common\model\WechatConf;
use think\facade\Config;
use think\facade\Env;

/**
 * Class Behavior
 * 定义系统的钩子
 *
 * @package app\common
 */
class Behavior
{
    function appInit()
    {

        // 获取平台配置
        Env::load(Env::get('app_path') . 'common/ini/.config');
        Env::load(Env::get('app_path') . 'common/ini/.distribution');

        //如果未开启分销模块  设置平台分销功能为关闭
        if (Env::get('IS_DISTRIBUTION') == 0) {
            ini_file(NULL, 'distribution_status', 0, Env::get('APP_PATH') . 'common/ini/.distribution');
        }

        $_configUser = config('user.');
        $_ENV = Env::get();
        // 允许 iniConfig 的配置
        $_iniConfigAllowed = [
            'IS_COUPON',                // 优惠券
            'IS_RED_PACKET',            // 红包
            'IS_GROUP',                 // 拼团
            'IS_CUT',                   // 砍价
            'IS_LIMIT',                 // 限时抢购
            'IS_SIGN_IN',               // 签到
            'IS_RECHARGE',              // 充值
            'IS_RANKING',               // 排行榜
            'IS_BRAND',                 // 品牌
            'IS_BALANCE',               // 余额
            'IS_GOODS_RECOMMEND',       // 商品推荐
            'IS_CLASSIFY_RECOMMEND',    // 品类推荐
            'IS_CUSTOMER',              // 客服
            'IS_INTEGRAL_MALL',         // 积分商城
            'IS_SHOP_STYLE',            // 店铺风格
            'IS_SHOP',                  // 到店自提
            'IS_CITY',                  // 同城速递
            'IS_PAY_DELIVERY',          // 货到付款
            'IS_UNDER_ORDER',           // 线下订单
            'IS_DISTRIBUTION',          // 分销
            'IS_ADDED-VALUE_TAX',       // 增值税
            'TITLE'                     // 商城标题
        ];
        // 允许 $_iniDistribution 的配置
        $_iniDistributionAllowed = [
            'DISTRIBUTION_STATUS',              //
            'DISTRIBUTION_PROPORTION',          //
            'DISTRIBUTION_LEVEL',               //
            'DISTRIBUTION_MANUAL',              //
            'DISTRIBUTION_REGISTER',            //
            'DISTRIBUTION_BUY',                 //
            'DISTRIBUTION_ACCUMULATIVE_PRICE',  //
            'DISTRIBUTION_ACCUMULATIVE',        //
            'DISTRIBUTION_COMMISSION',          //
            'DISTRIBUTION_HIERARCHY',           //
            'DISTRIBUTION_ONE',                 //
            'DISTRIBUTION_TWO',                 //
            'DISTRIBUTION_THREE',               //
            'DISTRIBUTION_WITHDRAW_TYPE',       //
            'DISTRIBUTION_WITHDRAW_DAY',        //
            'DISTRIBUTION_WITHDRAW_TIME',       //
            'DISTRIBUTION_WITHDRAW_PRICE',      //
            'DISTRIBUTION_WITHDRAW_COST',       //
            'DISTRIBUTION_WITHDRAW_COST_TYPE',  //
            'DISTRIBUTION_NAME_SHOW',           //
            'DISTRIBUTION_NAME_REQUIRED',       //
            'DISTRIBUTION_NUMBER_SHOW',         //
            'DISTRIBUTION_NUMBER_REQUIRED',     //
            'DISTRIBUTION_WECHAT_SHOW',         //
            'DISTRIBUTION_WECHAT_REQUIRED',     //
            'DISTRIBUTION_PHONE_SHOW',          //
            'DISTRIBUTION_PHONE_REQUIRED',      //
            'DISTRIBUTION_SEX_SHOW',            //
            'DISTRIBUTION_SEX_REQUIRED',        //
            'DISTRIBUTION_ADDRESS_SHOW',        //
            'DISTRIBUTION_ADDRESS_REQUIRED',    //
            'DISTRIBUTION_CARD',                //
            'DISTRIBUTION_NOTIFY_EXPLAIN',      //
            'UPGRADE_CONTENT',                  //
            'DOWN_CONTENT',                     //
            'RATIO_SET',                        //
            'DISTRIBUTION_GOODS_PROFIT',        //
            'INCOME_EXPLAIN',                   //
            'ABOUT_INCOME',                     //
            'NOUN_EXPLAIN',                     //
            'INCOME_STRATEGY',                  //
        ];
        $_iniConfig = [];
        $_iniDistribution = [];

        foreach ($_ENV as $_k => $_v) {
            // 开关配置
            if (in_array($_k, $_iniConfigAllowed)) {
                $_iniConfig[$_k] = $_v;
                continue;
            }
            // 分销
            if (in_array($_k, $_iniDistributionAllowed)) {
                $_iniDistribution[$_k] = $_v;
                continue;
            }
        }

        // 单店|多店
        $_iniConfig['SINGLE_STORE'] = $_configUser['one_more'] == 0 ? TRUE : FALSE;
        $_iniConfig['ONE_STORE_ID'] = $_configUser['one_store_id'];
        // 朋友圈开关
        $_iniConfig['SHARE_FRIEND_SWITCH'] = $_configUser['share_friend_switch'];

        if (!defined('INI_CONFIG')) {
            // 设置开关配置
            define("INI_CONFIG", $_iniConfig);
        }
        if (!defined('INI_DISTRIBUTION')) {
            // 分销配置
            define("INI_DISTRIBUTION", $_iniDistribution);
        }
    }

    function appDispatch()
    {
    }

    function appBegin()
    {
    }

    function moduleInit()
    {
    }

    function actionBegin()
    {
    }

    function viewFilter()
    {
    }

    function appEnd()
    {
    }

    function logWrite()
    {
    }

    function logLevel()
    {
    }

    function responseSend()
    {
    }

    function responseEnd()
    {
    }
}