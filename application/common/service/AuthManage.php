<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\model\AuthGroup;
use app\common\model\Store;
use think\Db;
use think\Exception;
use think\facade\Cache;
use think\facade\Env;
use think\facade\Request;

/**
 * 总权限与用户权限整理类
 * Class AuthManage
 * @package app\common\service
 */
class AuthManage
{
    //管理后台登录用户id
    private $manage_id;
    //管理后台登录用户组id
    private $user_group_id;
    //导航配置文件地址
    private static $filename;
    //导航配置文件只读长度
    const LENGTH = 1024 * 1024;
    //当前版本递归成树终止层级
    const CUT = 4;
    //后台地址  商家还是平台
    private static $type = '';

    // 平台后台的需要过滤掉的xml地址
    private static $_masterRul = [];
    // 商家后台的需要过滤掉的xml地址
    private static $_clientRul = [];

    public function __construct($manage_id = NULL, $user_group_id = NULL, $type = NULL, $filename = '')
    {

        if (empty(self::$type) || empty(self::$filename)) {
            if ($type == 'master') {
                self::$type = 'master';
                $filename = env('config_path') . 'master/navigation.xml';
            } else {
                self::$type = 'client';
                $filename = env('config_path') . 'client/navigation.xml';
            }
            self::$filename = $filename;
            $this->manage_id = $manage_id;
            $this->user_group_id = $user_group_id;
        }
    }

    /**
     * 用户所在有效组的权限
     * @param int $export 返回输出类型 0树形 1一维
     * @param int $isNav 是否是nav数据 0否 1是
     * @param AuthGroup $authGroup 权限组模型类
     * @return mixed
     */
    public function userAuthData($export = 0, $isNav = 0, AuthGroup $authGroup)
    {
        try {
            if ($isNav) {
                // 管理员检测权限
                $userAuth = Cache::get(
                    $export ? 'flatMaster_userAuth_' . $this->manage_id : 'flatMaster_userAuthTree_' . $this->manage_id
                );
            } else {
                // 导航展示权限
                $userAuth = Cache::get(
                    $export ? 'flatMaster_userNavAuth_' . $this->manage_id : 'flatMaster_userNavAuthTree_' . $this->manage_id
                );
            }
            if (!$userAuth) {
                //查询用户权限组所含规则
                $userIdArr = $authGroup->groupRules($this->user_group_id);
                //通过id转化为规则数组
                $allAuth = self::allAuthData(1);
                //转化完成的用户权限数组
                $userAuth = [];
                if ($allAuth) {
                    if (count($userIdArr) === 1 && (int)$userIdArr[0] == -1) {
                        //拥有全部权限
                        $userAuth = $allAuth;
                    } else {
                        //交换数组中的健值
                        $_userIdArr = array_flip($userIdArr);
                        $userAuth = array_filter(
                            $allAuth,
                            function ($value) use ($_userIdArr) {
                                //判断当前权限是否存在对应权限组里面  如果不在则过滤掉
                                return array_key_exists($value['id'], $_userIdArr);
                            }
                        );
                    }
                }
                if ($userAuth) {
                    $userAuth = $export ? $userAuth : self::toTree($userAuth);
                    $showNav = $isNav ? 'Nav' : '';
                    Cache::set(
                        $export ? 'flatMaster_user' . $showNav . 'Auth_' . $this->manage_id : 'flatMaster_user' . $showNav . 'AuthTree_' . $this->manage_id,
                        $userAuth
                    );
                }
            }
            return $userAuth;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 返回全部权限数组[默认返回缓存]
     * @param int $export 返回输出类型 0树形 1一维
     * @return array|mixed
     */
    public function allAuthData($export = 0)
    {
        try {
            $auth = Cache::get($export ? 'flatMaster_allAuth' : 'flatMaster_allAuthTree');
            if (!$auth) {
                $auth = $export ? self::xmlToArray() : self::toTree(self::xmlToArray());
                if ($auth) {
                    Cache::set($export ? 'flatMaster_allAuth' : 'flatMaster_allAuthTree', $auth);
                }
            }
            return $auth;
        } catch (\Exception $e) {
            return [];
        }
    }


    /**
     * 店铺后台使用
     * @param int $export
     * @return array|mixed
     */
    public function allClientData($export = 0)
    {
        try {
            $auth = Cache::get(
                $export ? 'flatClient_allAuth_' . $this->manage_id : 'flatClient_allAuthTree_' . $this->manage_id
            );
            if (!$auth) {
                $auth = $export ? self::xmlToArray('client') : self::toTree(self::xmlToArray('client'));
                if ($auth) {
                    Cache::set(
                        $export ? 'flatClient_allAuth_' . $this->manage_id : 'flatClient_allAuthTree_' . $this->manage_id,
                        $auth
                    );
                }
            }
            return $auth;
        } catch (\Exception $e) {
            return [];
        }
    }


    /**
     * 面包屑及网站标题数据整理
     * @param string $type
     * @return array|mixed
     */
    public function breadcrumb($type = 'flatMaster_breadcrumb')
    {
        try {
            $breadcrumb = Cache::get($type);

            if (!$breadcrumb) {
                $allData = $type == 'flatClient_breadcrumb' ? self::xmlToArray('client') : self::xmlToArray('master');
                $breadcrumb = [];
                if ($allData) {
                    foreach ($allData as $key => $item) {
                        $breadcrumb[$item['id']] = [
                            'url' => array_key_exists('url', $item) ? $item['url'] : '',
                            'title' => $item['title'],
                            'pid' => $item['pid'],
                        ];
                    }
                }
                if ($breadcrumb) {
                    krsort($breadcrumb, SORT_STRING);
                    Cache::set($type, $breadcrumb);
                }
            }
            return $breadcrumb;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 解析xml文件到数组
     * @param string $type 是否是店铺解析
     * @return array
     */
    public static function xmlToArray($type = '')
    {
        $xmlParse = xml_parser_create('utf-8');
        xml_parser_set_option($xmlParse, XML_OPTION_CASE_FOLDING, 1);
        xml_parser_set_option($xmlParse, XML_OPTION_SKIP_WHITE, 1);
        $fp = fopen(self::$filename, 'r');
        $xmlData = fread($fp, self::LENGTH);
        xml_parse_into_struct($xmlParse, $xmlData, $xmlArr);
        $xmlFinalArr = [];

        foreach ($xmlArr as $key => $item) {
            if (array_key_exists('attributes', $item)) {
                $xmlFinalArr[] = $item['attributes'];
            }
        }
        fclose($fp);

        // 平台后台过滤
        self::$_masterRul = self::master_rul();
        // 商家后台过滤
        self::$_clientRul = self::client_rul();

        if ($xmlFinalArr) {
            foreach ($xmlFinalArr as $key => &$item) {
                if (self::pattern($item)) {
                    unset($xmlFinalArr[$key]);
                };

                switch (self::$type) {
                    case 'master':  // 平台后台
                        // 替换地址
                        $item = self::oneReplace($item);
                        break;
                }

                if (is_array($item)) {
                    $item = array_change_key_case($item, CASE_LOWER);
                }
            }
        }
        return $xmlFinalArr;
    }

    /**
     * 递归成树
     * @param $auth array 需递归数组[一维]
     * @param int $pid 初始父ID
     * @return array
     */
    private function toTree($auth, $pid = 0)
    {
        try {
            $treeArr = [];
            foreach ($auth as $key => $item) {
                if ($item['pid'] == $pid) {
                    $level = substr_count($item['id'], '.') + 1;
                    $item['level'] = $level;
                    if ($level < self::CUT) {
                        $item['children'] = self::toTree($auth, $item['id']);
                    }
                    $treeArr[] = $item;
                }
            }
            return $treeArr;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 检测用户权限
     * @param $url string 当前路径 example : master/index/index
     * @return bool
     */
    public function checkAuth($url)
    {
        $authGroup = app('app\common\model\AuthGroup');
        $userArr = $this->userAuthData(1, 0, $authGroup);
        //提取配置中路径参数
        if ($userArr) {
            $userArr = array_column($userArr, 'url');
        }
        $waitCheck = ltrim($url, '/');
        if (stripos(strrev($waitCheck), 'lmth.') === 0) {
            $waitCheck = substr($waitCheck, 0, strlen($waitCheck) - 5);
        }
        return in_array($waitCheck, $userArr) ? TRUE : FALSE;
    }

    /**
     * 平台当前商城模式   过滤掉对应xml
     */
    private static function pattern($item)
    {
        try {
            switch (self::$type) {
                case 'client':
                    $_rul = self::$_clientRul;
                    break;
                case 'master':
                    $_rul = self::$_masterRul;
                    break;
                default :
                    $_rul = [];
            }
            //存储当前分类ID
            static $ID = [];
            //如果当前数据存在条件中则过滤掉
            if ((isset($item['URL']) && in_array($item['URL'], $_rul, TRUE)) || (isset($item['PID']) && in_array($item['PID'], $ID, TRUE))) {
                $ID[] = $item['ID'];
                //更新数据库中权限数据  删除掉当前关闭的功能的权限
                Db::name('auth_group')->where([['rules', '<>', 0]])->setField('rules', Db::raw('IF(substring_index(rules,",","-1")="' . $item['ID'] . '",SUBSTRING(rules,1,length(rules)-length("' . $item['ID'] . '")+1),REPLACE(rules,",' . $item['ID'] . ',",","))'));
                return TRUE;
            }
            return FALSE;
        } catch (\Exception $e) {
            halt($e->getMessage());
        }
    }

    /**
     * 单店替换对应的链接地址
     *
     * @param $item
     * @return bool|mixed 如果存在
     */
    private static function oneReplace($item)
    {
        // 如果不存在存在不
        if (!isset($item['URL'])) {
            return $item;
        }

        $_oneMore = config('user.one_more');

        $_replaceList = [];

        // 单店
        if ($_oneMore != 1) {
            $_onStoreId = config('user.one_store_id');
            $_replaceList['distribution_city/index'] = "distribution_city/edit?id={$_onStoreId}";
        }

        $item['URL'] = $_replaceList[$item['URL']] ?? $item['URL'];

        return $item;
    }


    /**
     * 平台当前商城模式   过滤掉对应xml
     */
    private static function master_rul()
    {
        //读取当前店铺模式
        $config = config('user.');
        $return_data = [];
        //基础配置导入
        //将数组下标转化为小写
        $env = array_change_key_case(Env::get(), CASE_LOWER);
        /*****************************************各个模式对应过滤功能数组************************************/
        //功能开关过滤
        $many_stores = [
            //优惠券
            ['status' => $env['is_coupon'], 'path' => []],
            //红包
            ['status' => $env['is_red_packet'], 'path' => []],
            //拼团
            ['status' => $env['is_group'], 'path' => [
                'group_activity/index',
            ]],
            //砍价
            ['status' => $env['is_cut'], 'path' => [
                'cut/index',
            ]],
            //限时抢购
            ['status' => $env['is_limit'], 'path' => [
                'limit/index',
            ]],
        ];
        foreach ($many_stores as $val) {
            if ($val['status'] == 0) {
                $return_data = array_merge($return_data, $val['path']);
            }
        }
        //是否包含pc
        if (!$config['pc']['is_include']) {
            $return_data[] = 'adv/pc_index';
        }

        //分销开关
        if (!INI_CONFIG['IS_DISTRIBUTION']) {
            $return_data[] = 'distribution_switch/index';
        }

        // 屏蔽全店风格
        if (!INI_CONFIG['IS_SHOP_STYLE']) {
            $return_data[] = 'shop_style/index';
        }

        // 关闭余额
        if (!INI_CONFIG['IS_BALANCE']) {
            $return_data[] = 'order/offline_payment_list';
        }

        // 屏蔽同城速递
        if (!INI_CONFIG['IS_CITY']) {
            // 订单-配送设置-同城速递
            $return_data[] = 'distribution_city/index';
        }

        // 屏蔽到店自提
        if (!INI_CONFIG['IS_SHOP']) {
            $return_data[] = 'take/index';
        }

        // 关闭优惠券
        if (!INI_CONFIG['IS_COUPON']) {
            $return_data[] = 'coupon/index';
        }
        // 关闭红包
        if (!INI_CONFIG['IS_RED_PACKET']) {
            $return_data[] = 'red_packet/index';
        }
        // 关闭积分商城
        if (!INI_CONFIG['IS_INTEGRAL_MALL']) {
            $return_data[] = 'integral_order/index';
        }

        // 关闭充值
        if (!INI_CONFIG['IS_RECHARGE']) {
            $return_data[] = 'recharge/index';
            $return_data[] = 'consumption/index';
        }

        // 限时抢购
        if (!INI_CONFIG['IS_LIMIT']) {
            $return_data[] = 'goods/activity_limit';
        }
        // 拼团
        if (!INI_CONFIG['IS_GROUP']) {
            $return_data[] = 'goods/activity_group';
        }
        // 砍价
        if (!INI_CONFIG['IS_CUT']) {
            $return_data[] = 'goods/activity_cut';
        }
        // 积分商城
        $return_data[] = 'goods/activity_integral';


        // 单店版
        if ($config['one_more'] != 1) {
            $return_data[] = 'store/general'; // 店铺
            $return_data[] = 'store_capital/property_list'; // 财务-店铺-店铺账单
            $return_data[] = 'store_capital/withdraw'; // 财务-店铺-店铺提现
        } else {
            // 多店
            // 多店无发票
            $return_data[] = 'invoice/settings';  // 发票
            $return_data[] = 'order/offline_payment_info'; //线下付款
        }

        return $return_data;
    }

    /**
     * 商家当前商城模式   过滤掉对应xml
     */
    private static function client_rul()
    {
        //读取当前店铺模式
        $config = config('user.');
        /*****************************************各个模式对应过滤功能数组************************************/
        //多店基础过滤
        $many_stores = [
            'client/group_goods/index',//拼团
            'client/cut/index',//砍价
            'member_rank/index',//会员等级
        ];

        $_map = [$many_stores];
        $return_data = $_map[$config['pattern']] ?? [];

        //分销开关
        if (!INI_CONFIG['IS_DISTRIBUTION']) {
            $return_data[] = 'distribution';
        }

        // 关闭优惠券
        if (!INI_CONFIG['IS_COUPON']) {
            $return_data[] = 'client/coupon/index';
        }

        // 拼团
        if (!INI_CONFIG['IS_GROUP']) {
            $return_data[] = 'client/group_activity/index';
        }

        // 砍价
        if (!INI_CONFIG['IS_CUT']) {
            $return_data[] = 'client/cut/index';
        }

        // 限时抢购
        if (!INI_CONFIG['IS_LIMIT']) {
            $return_data[] = 'client/limit/index';
        }

        // 关闭活动
        if (!INI_CONFIG['IS_GROUP'] && !INI_CONFIG['IS_CUT'] && !INI_CONFIG['IS_LIMIT']) {
            $return_data[] = 'client/coupon';
        }

        // 关闭同城速递
        if (!INI_CONFIG['IS_CITY']) {
            $return_data[] = 'client/distribution_city/index';
        }

        // 关闭到店自提
        if (!INI_CONFIG['IS_SHOP']) {
            $return_data[] = 'client/take/index';
        }

        // 关闭分销
        if (!INI_CONFIG['IS_DISTRIBUTION']) {
            $return_data[] = 'client/distribution_book';
        }


//        // 关闭红包
//        if (!INI_CONFIG['IS_RED_PACKET']) {
//            $return_data[] = 'red_packet/index';
//        }
//        // 关闭积分商城
//        if (!INI_CONFIG['IS_INTEGRAL_MALL']) {
//            $return_data[] = 'integral_order/index';
//        }
//
//        // 关闭充值
//        if (!INI_CONFIG['IS_RECHARGE']) {
//            $return_data[] = 'recharge/index';
//            $return_data[] = 'consumption/index';
//        }


        // 余额订单
        if (!INI_CONFIG['IS_BALANCE']) {
            $return_data[] = 'client/order/offline_payment_info';
            $return_data[] = 'client/order/offline_payment_list';
        }

        return $return_data;
    }
}