<?php

namespace think;

header("Content-Type: application/json");

// 载入Loader类
require __DIR__ . '/../../thinkphp/library/think/Loader.php';

// 注册自动加载
Loader::register();

$_result = NULL;

define('BASE_PATH', dirname(dirname(dirname(__FILE__))));

\think\facade\Env::set('runtime_path', BASE_PATH . '/runtime/');

switch (strtoupper($_SERVER['REQUEST_METHOD'])) {
    case 'GET':
        $_result = GET();
        break;
    case 'POST':
        $_result = POST();
        break;
}

if ($_result === NULL) {
    json(
        [
            'code'    => '9999',
            'message' => '请求出错',
        ]
    );
}

function GET()
{
    $_user = require_once '../../config/user.php';
    $_oss = require_once '../../config/oss.php';
    $_redis = require_once '../../config/cache.php';
    $_beanstalk = require_once '../../config/beanstalk.php';
    $_database = require_once '../../config/database.php';
    $_wechat = require_once '../../config/wechat.php';
    $_link = require_once '../../config/link.php';
    $_daemon = require_once '../../config/daemon.php';
    $_smsTemplate = require_once '../../config/sms_template.php';
    $_sms = parse_ini_file('../../application/common/ini/.sms', TRUE);
    $_config = parse_ini_file('../../application/common/ini/.config', TRUE);
    $_rongyun = require_once '../../config/rongyun.php';
    $_appPush = require_once '../../config/push.php';

    $_data = [
        'base'      => [
            'share_friend_switch' => $_user['share_friend_switch'],
            'is_show_two_code'    => $_user['is_show_two_code'],
            'sms_verify'          => $_user['sms_verify'],
            'one_pay'             => $_user['one_pay'],
            'use_union_id'        => $_user['common']['wx']['use_unionId'] === 1,
            'one_more'            => $_user['one_more'],
            'type'                => [],
            'modules'             => [],
            'link'                => $_link['master_suffix'],
            'client_url'          => $_user['common']['client_url'],
            'mobile_domain'       => $_user['mobile']['mobile_domain'],
            'rong_cloud_switch'   => $_user['rong_cloud_switch'],
            'one_store_id'        => $_user['one_store_id']
        ],
        'express'   => [
            'sign'     => $_user['common']['express']['sign'],
            'customer' => $_user['common']['express']['customer'],
        ],
        'gao_de'    => [
            'jsapi_key'  => $_user['common']['gao_de']['jsapi_key'],
            'webapi_key' => $_user['common']['gao_de']['webapi_key'],
            'securityJsCode' => $_user['common']['gao_de']['securityJsCode']
        ],
        'oss'       => [
            'access_key_id'     => $_oss['AccessKeyId'],
            'access_key_secret' => $_oss['AccessKeySecret'],
            'bucket'            => $_oss['bucket'],
            'region'            => explode('.', $_oss['normalEndPoint'])[0] ?? '',
        ],
        'sms'       => [
            'sign'              => $_sms['aLi']['sign'],
            'access_key_id'     => $_sms['aLi']['appId'],
            'access_key_secret' => $_sms['aLi']['appKey'],
            'template'          => $_smsTemplate,
        ],
        'redis'     => [
            'host'     => $_redis['default']['host'],
            'port'     => $_redis['default']['port'],
            'password' => $_redis['default']['password'],
            'prefix'   => $_redis['default']['prefix'],
        ],
        'beanstalk' => [
            'host' => $_beanstalk['host'],
            'port' => $_beanstalk['port'],
            'tube' => $_beanstalk['tube'],
        ],
        'database'  => [
            'host'     => $_database['hostname'],
            'port'     => $_database['hostport'],
            'database' => $_database['database'],
            'username' => $_database['username'],
            'password' => $_database['password'],
        ],
        'applet'    => [
            'app_id'  => $_wechat['applet']['app_id'],
            'secret'  => $_wechat['applet']['secret'],
            'mch_id'  => $_wechat['applet']['mch_id'],
            'key'     => $_wechat['applet']['key'],
            'is_push' => $_user['applet']['is_push']
        ],
        'mobile'    => [
            'app_id'  => $_wechat['mobile']['app_id'],
            'secret'  => $_wechat['mobile']['secret'],
            'mch_id'  => $_wechat['mobile']['mch_id'],
            'key'     => $_wechat['mobile']['key'],
            'is_push' => $_user['mobile']['is_push']
        ],
        'app'       => [
            'app_id'  => $_wechat['app']['app_id'],
            'secret'  => $_wechat['app']['secret'],
            'mch_id'  => $_wechat['app']['mch_id'],
            'key'     => $_wechat['app']['key'],
            'is_push' => $_user['app']['is_push']
        ],
        'pc_login'  => [
            'app_id'      => $_wechat['pc_login']['app_id'],
            'secret'      => $_wechat['pc_login']['secret'],
            'mch_id'      => $_wechat['pc_login']['mch_id'],
            'key'         => $_wechat['pc_login']['key'],
            'is_push'     => $_user['pc']['is_push'],
            'is_wx_login' => $_user['pc']['is_wx_login'],
        ],
        'notify'    => [
            'host'                     => $_daemon['notify']['host'],
            'port'                     => $_daemon['notify']['port'],
            'worker_num'               => $_daemon['notify']['worker_num'],
            'daemonize'                => $_daemon['notify']['daemonize'],
            'heartbeat_check_interval' => $_daemon['notify']['heartbeat_check_interval'],
        ],
        'other'     => [
            'base_path' => BASE_PATH,
            'host_name' => "{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['SERVER_NAME']}",
        ],
        'rongyun'   => [
            'app_key'        => $_rongyun['app_key'],
            'app_secret'     => $_rongyun['app_secret'],
            'default_avatar' => $_rongyun['default_avatar']
        ],
        'app_push'  => [
            'accessKeyId'      => $_appPush['ali']['accessKeyId'],
            'accessKeySecret'  => $_appPush['ali']['accessKeySecret'],
            'regionId'         => $_appPush['ali']['regionId'],
            'androidAppKey'    => $_appPush['ali']['androidAppKey'],
            'androidAppSecret' => $_appPush['ali']['androidAppSecret'],
            'iosAppKey'        => $_appPush['ali']['iosAppKey'],
            'iosEnv'           => $_appPush['ali']['iosEnv'],
            'iosAppSecret'     => $_appPush['ali']['iosAppSecret'],
        ]
    ];

    if ($_user['pc']['is_include']) {
        $_data['base']['type'][] = "PC";
    }
    if ($_user['app']['is_include']) {
        $_data['base']['type'][] = "APP";
    }
    if ($_user['mobile']['is_include']) {
        $_data['base']['type'][] = "手机站";
    }
    if ($_user['applet']['is_include']) {
        $_data['base']['type'][] = "小程序";
    }


    if ($_config['is_coupon'] == 1) {
        $_data['base']['modules'][] = '优惠券';
    }
    if ($_config['is_red_packet'] == 1) {
        $_data['base']['modules'][] = '红包';
    }
    if ($_config['is_group'] == 1) {
        $_data['base']['modules'][] = '拼团';
    }
    if ($_config['is_cut'] == 1) {
        $_data['base']['modules'][] = '砍价';
    }
    if ($_config['is_limit'] == 1) {
        $_data['base']['modules'][] = '限时抢购';
    }
    if ($_config['is_sign_in'] == 1) {
        $_data['base']['modules'][] = '签到';
    }
    if ($_config['is_recharge'] == 1) {
        $_data['base']['modules'][] = '充值';
    }
    if ($_config['is_ranking'] == 1) {
        $_data['base']['modules'][] = '排行榜';
    }
    if ($_config['is_brand'] == 1) {
        $_data['base']['modules'][] = '品牌';
    }
    if ($_config['is_balance'] == 1) {
        $_data['base']['modules'][] = '余额';
    }
    if ($_config['is_integral_mall'] == 1) {
        $_data['base']['modules'][] = '积分商城';
    }
    if ($_config['is_shop'] == 1) {
        $_data['base']['modules'][] = '到店自提';
    }
    if ($_config['is_city'] == 1) {
        $_data['base']['modules'][] = '同城速递';
    }
    if ($_config['is_pay_delivery'] == 1) {
        $_data['base']['modules'][] = '货到付款';
    }
    if ($_config['is_under_order'] == 1) {
        $_data['base']['modules'][] = '线下订单';
    }
    if ($_config['is_distribution'] == 1) {
        $_data['base']['modules'][] = '分销';
    }
    if ($_config['is_added-value_tax'] == 1) {
        $_data['base']['modules'][] = '增值税';
    }
    if ($_config['is_live'] == 1) {
        $_data['base']['modules'][] = '直播间';
    }


    json(
        [
            'code' => '0000',
            'data' => $_data,
        ]
    );
}

function POST()
{
    $_user = require_once '../../config/user.php';
    $_oss = require_once '../../config/oss.php';
    $_redis = require_once '../../config/cache.php';
    $_beanstalk = require_once '../../config/beanstalk.php';
    $_database = require_once '../../config/database.php';
    $_wechat = require_once '../../config/wechat.php';
    $_link = require_once '../../config/link.php';
    $_daemon = require_once '../../config/daemon.php';
    $_sms = parse_ini_file('../../application/common/ini/.sms', TRUE);
    $_config = parse_ini_file('../../application/common/ini/.config', TRUE);
    $_rongyun = require_once '../../config/rongyun.php';
    $_appPush = require_once '../../config/push.php';

    $_data = json_decode(file_get_contents("php://input"), TRUE);

    $_user['share_friend_switch'] = $_data['base']['share_friend_switch'];
    $_user['is_show_two_code'] = $_data['base']['is_show_two_code'];
    $_user['sms_verify'] = $_data['base']['sms_verify'];
    $_user['one_pay'] = $_data['base']['one_pay'];
    $_user['one_more'] = $_data['base']['one_more'];
    $_user['common']['wx']['use_unionId'] = $_data['base']['use_union_id'] ? 1 : 0;
    $_user['common']['express']['sign'] = $_data['express']['sign'];
    $_user['common']['express']['customer'] = $_data['express']['customer'];
    $_user['common']['gao_de']['jsapi_key'] = $_data['gao_de']['jsapi_key'];
    $_user['common']['gao_de']['webapi_key'] = $_data['gao_de']['webapi_key'];
    $_user['common']['gao_de']['securityJsCode'] = $_data['gao_de']['securityJsCode'];
    $_user['pc']['is_include'] = in_array('PC', $_data['base']['type']);
    $_user['app']['is_include'] = in_array('APP', $_data['base']['type']);
    $_user['mobile']['is_include'] = in_array('手机站', $_data['base']['type']);
    $_user['applet']['is_include'] = in_array('小程序', $_data['base']['type']);
    $_user['common']['client_url'] = $_data['base']['client_url'];
    $_user['mobile']['mobile_domain'] = $_data['base']['mobile_domain'];
    $_user['rong_cloud_switch'] = $_data['base']['rong_cloud_switch'];
    $_user['one_store_id'] = $_data['base']['one_store_id'];
    $_user['mobile']['is_push'] = $_data['mobile']['is_push'];
    $_user['applet']['is_push'] = $_data['applet']['is_push'];
    $_user['app']['is_push'] = $_data['app']['is_push'];
    $_user['pc']['is_push'] = $_data['pc_login']['is_push'];
    $_user['pc']['is_wx_login'] = $_data['pc_login']['is_wx_login'];

    $_oss['AccessKeyId'] = $_data['oss']['access_key_id'];
    $_oss['AccessKeySecret'] = $_data['oss']['access_key_secret'];
    $_oss['bucket'] = $_data['oss']['bucket'];
    $_oss['normalEndPoint'] = "{$_data['oss']['region']}.aliyuncs.com";
    $_oss['ECSEndPoint'] = "{$_data['oss']['region']}-internal.aliyuncs.com";
    $_oss['ECSVPCEndPoint'] = "{$_data['oss']['region']}.aliyuncs.com";
    $_oss['prefix'] = "https://{$_data['oss']['bucket']}.{$_data['oss']['region']}.aliyuncs.com/";

    $_link['master_suffix'] = $_data['base']['link'];

    $_daemon['notify']['host'] = $_data['notify']['host'];
    $_daemon['notify']['port'] = $_data['notify']['port'];
    $_daemon['notify']['worker_num'] = $_data['notify']['worker_num'];
    $_daemon['notify']['daemonize'] = $_data['notify']['daemonize'];
    $_daemon['notify']['heartbeat_check_interval'] = $_data['notify']['heartbeat_check_interval'];

    $_sms['aLi']['sign'] = $_data['sms']['sign'];
    $_sms['aLi']['appId'] = $_data['sms']['access_key_id'];
    $_sms['aLi']['appKey'] = $_data['sms']['access_key_secret'];

    $_smsTemplate = $_data['sms']['template'];

    $_redis['default']['host'] = $_data['redis']['host'];
    $_redis['default']['port'] = $_data['redis']['port'];
    $_redis['default']['password'] = $_data['redis']['password'];
    $_redis['default']['prefix'] = $_data['redis']['prefix'];

    $_beanstalk['host'] = $_data['beanstalk']['host'];
    $_beanstalk['port'] = $_data['beanstalk']['port'];
    $_beanstalk['tube'] = $_data['beanstalk']['tube'];

    $_database['hostname'] = $_data['database']['host'];
    $_database['hostport'] = $_data['database']['port'];
    $_database['database'] = $_data['database']['database'];
    $_database['username'] = $_data['database']['username'];
    $_database['password'] = $_data['database']['password'];

    $_config['is_coupon'] = in_array('优惠券', $_data['base']['modules']) ? 1 : 0;
    $_config['is_red_packet'] = in_array('红包', $_data['base']['modules']) ? 1 : 0;
    $_config['is_group'] = in_array('拼团', $_data['base']['modules']) ? 1 : 0;
    $_config['is_cut'] = in_array('砍价', $_data['base']['modules']) ? 1 : 0;
    $_config['is_limit'] = in_array('限时抢购', $_data['base']['modules']) ? 1 : 0;
    $_config['is_sign_in'] = in_array('签到', $_data['base']['modules']) ? 1 : 0;
    $_config['is_recharge'] = in_array('充值', $_data['base']['modules']) ? 1 : 0;
    $_config['is_ranking'] = in_array('排行榜', $_data['base']['modules']) ? 1 : 0;
    $_config['is_brand'] = in_array('品牌', $_data['base']['modules']) ? 1 : 0;
    $_config['is_balance'] = in_array('余额', $_data['base']['modules']) ? 1 : 0;
    $_config['is_integral_mall'] = in_array('积分商城', $_data['base']['modules']) ? 1 : 0;
    $_config['is_shop'] = in_array('到店自提', $_data['base']['modules']) ? 1 : 0;
    $_config['is_city'] = in_array('同城速递', $_data['base']['modules']) ? 1 : 0;
    $_config['is_pay_delivery'] = in_array('货到付款', $_data['base']['modules']) ? 1 : 0;
    $_config['is_under_order'] = in_array('线下订单', $_data['base']['modules']) ? 1 : 0;
    $_config['is_distribution'] = in_array('分销', $_data['base']['modules']) ? 1 : 0;
    $_config['is_added-value_tax'] = in_array('增值税', $_data['base']['modules']) ? 1 : 0;
    $_config['is_live'] = in_array('直播间', $_data['base']['modules']) ? 1 : 0;

    $_wechat['applet']['app_id'] = $_data['applet']['app_id'];
    $_wechat['applet']['secret'] = $_data['applet']['secret'];
    $_wechat['applet']['mch_id'] = $_data['applet']['mch_id'];
    $_wechat['applet']['key'] = $_data['applet']['key'];

    $_wechat['mobile']['app_id'] = $_data['mobile']['app_id'];
    $_wechat['mobile']['secret'] = $_data['mobile']['secret'];
    $_wechat['mobile']['mch_id'] = $_data['mobile']['mch_id'];
    $_wechat['mobile']['key'] = $_data['mobile']['key'];

    $_wechat['app']['app_id'] = $_data['app']['app_id'];
    $_wechat['app']['secret'] = $_data['app']['secret'];
    $_wechat['app']['mch_id'] = $_data['app']['mch_id'];
    $_wechat['app']['key'] = $_data['app']['key'];

    $_wechat['pc_login']['app_id'] = $_data['pc_login']['app_id'];
    $_wechat['pc_login']['secret'] = $_data['pc_login']['secret'];
    $_wechat['pc_login']['mch_id'] = $_data['pc_login']['mch_id'];
    $_wechat['pc_login']['key'] = $_data['pc_login']['key'];

    $_rongyun['app_key'] = $_data['rongyun']['app_key'];
    $_rongyun['app_secret'] = $_data['rongyun']['app_secret'];
    $_rongyun['default_avatar'] = $_data['rongyun']['default_avatar'];

    $_appPush['ali']['accessKeyId'] = $_data['app_push']['accessKeyId'];
    $_appPush['ali']['accessKeySecret'] = $_data['app_push']['accessKeySecret'];
    $_appPush['ali']['androidAppKey'] = $_data['app_push']['androidAppKey'];
    $_appPush['ali']['androidAppSecret'] = $_data['app_push']['androidAppSecret'];
    $_appPush['ali']['iosAppKey'] = $_data['app_push']['iosAppKey'];
    $_appPush['ali']['iosEnv'] = $_data['app_push']['iosEnv'];
    $_appPush['ali']['iosAppSecret'] = $_data['app_push']['iosAppSecret'];

    $_userText = var_export($_user, TRUE);
    $_ossText = var_export($_oss, TRUE);
    $_redisText = var_export($_redis, TRUE);
    $_beanstalkText = var_export($_beanstalk, TRUE);
    $_databaseText = var_export($_database, TRUE);
    $_wechatText = var_export($_wechat, TRUE);
    $_linkText = var_export($_link, TRUE);
    $_daemonText = var_export($_daemon, TRUE);
    $_smsTemplateText = var_export($_smsTemplate, TRUE);
    $_rongyunText = var_export($_rongyun, TRUE);
    $_appPushTex = var_export($_appPush, TRUE);

    try {
        file_put_contents('../../config/user.php', "<?php \nreturn {$_userText};");
        file_put_contents('../../config/oss.php', "<?php \nreturn {$_ossText};");
        file_put_contents('../../config/cache.php', "<?php \nreturn {$_redisText};");
        file_put_contents('../../config/beanstalk.php', "<?php \nreturn {$_beanstalkText};");
        file_put_contents('../../config/database.php', "<?php \nreturn {$_databaseText};");
        file_put_contents('../../config/wechat.php', "<?php \nreturn {$_wechatText};");
        file_put_contents('../../config/sms_template.php', "<?php \nreturn {$_smsTemplateText};");
        file_put_contents('../../config/link.php', "<?php \nreturn {$_linkText};");
        file_put_contents('../../config/daemon.php', "<?php \nreturn {$_daemonText};");
        file_put_contents('../../config/rongyun.php', "<?php \nreturn {$_rongyunText};");
        file_put_contents('../../config/push.php', "<?php \nreturn {$_appPushTex};");
        write_ini_file($_sms, '../../application/common/ini/.sms');
        write_ini_file($_config, '../../application/common/ini/.config');

        json(
            [
                'code'    => '0000',
                'message' => '保存成功',
            ]
        );

    } catch (\Exception $e) {
        json(
            [
                'code'    => '8888',
                'message' => '保存出错请稍后再试',
            ]
        );
    }

    return TRUE;
}

function json($data)
{
    exit(json_encode($data, JSON_UNESCAPED_UNICODE));
}

function write_ini_file($assoc_arr, $path)
{
    $_content = "";

    foreach ($assoc_arr as $key => $elem) {
        if (is_array($elem)) {
            $_content .= "[{$key}]\n";

            foreach ($elem as $key2 => $elem2) {
                $_content .= "{$key2}={$elem2}\n";
            }
            continue;
        }

        $_content .= "{$key}={$elem}\n";
    }

    if (!$handle = fopen($path, 'w')) {
        return FALSE;
    }
    if (!fwrite($handle, $_content)) {
        return FALSE;
    }
    fclose($handle);
    return TRUE;

}