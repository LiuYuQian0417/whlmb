<?php

namespace app\master\controller;

use app\common\model\FreightExpressClassify;
use app\common\model\Goods;
use app\common\model\WechatConf;
use EasyWeChat\Kernel\Messages\Image;
use phpDocumentor\Reflection\FqsenTest;
use think\Cache;
use think\Controller;
use EasyWeChat\Factory;
use think\Exception;
use think\facade\Env;
use think\Request;

class WeChat extends Controller
{
    private $_WeChatConf;
    private $_Applet;
    private $_AppletTemplate;
    private $_app;

    private $ERROR = [
        '-1' => '系统繁忙，此时请开发者稍候再试',
        '0' => '请求成功',
        '40001' => '获取 access_token 时 AppSecret 错误，或者 access_token 无效。请开发者认真比对 AppSecret 的正确性，或查看是否正在为恰当的公众号调用接口',
        '40002' => '不合法的凭证类型',
        '40003' => '不合法的 OpenID ，请开发者确认 OpenID （该用户）是否已关注公众号，或是否是其他公众号的 OpenID',
        '40004' => '不合法的媒体文件类型',
        '40005' => '不合法的文件类型',
        '40006' => '不合法的文件大小',
        '40007' => '不合法的媒体文件 id',
        '40008' => '不合法的消息类型',
        '40009' => '不合法的图片文件大小',
        '40010' => '不合法的语音文件大小',
        '40011' => '不合法的视频文件大小',
        '40012' => '不合法的缩略图文件大小',
        '40013' => '不合法的 AppID ，请开发者检查 AppID 的正确性，避免异常字符，注意大小写',
        '40014' => '不合法的 access_token ，请开发者认真比对 access_token 的有效性（如是否过期），或查看是否正在为恰当的公众号调用接口',
        '40015' => '不合法的菜单类型',
        '40016' => '不合法的按钮个数',
        '40017' => '不合法的按钮个数',
        '40018' => '不合法的按钮名字长度',
        '40019' => '不合法的按钮 KEY 长度',
        '40020' => '不合法的按钮 URL 长度',
        '40021' => '不合法的菜单版本号',
        '40022' => '不合法的子菜单级数',
        '40023' => '不合法的子菜单按钮个数',
        '40024' => '不合法的子菜单按钮类型',
        '40025' => '不合法的子菜单按钮名字长度',
        '40026' => '不合法的子菜单按钮 KEY 长度',
        '40027' => '不合法的子菜单按钮 URL 长度',
        '40028' => '不合法的自定义菜单使用用户',
        '40029' => '不合法的 oauth_code',
        '40030' => '不合法的 refresh_token',
        '40031' => '不合法的 openid 列表',
        '40032' => '不合法的 openid 列表长度',
        '40033' => '不合法的请求字符，不能包含 \uxxxx 格式的字符',
        '40035' => '不合法的参数',
        '40038' => '不合法的请求格式',
        '40039' => '不合法的 URL 长度',
        '40050' => '不合法的分组 id',
        '40051' => '分组名字不合法',
        '40060' => '删除单篇图文时，指定的 article_idx 不合法',
        '40117' => '分组名字不合法',
        '40118' => 'media_id 大小不合法',
        '40119' => 'button 类型错误',
        '40120' => 'button 类型错误',
        '40121' => '不合法的 media_id 类型',
        '40132' => '微信号不合法',
        '40137' => '不支持的图片格式',
        '40155' => '请勿添加其他公众号的主页链接',
        '40166' => '小程序 APPID 错误',
        '41001' => '缺少 access_token 参数',
        '41002' => '缺少 appid 参数',
        '41003' => '缺少 refresh_token 参数',
        '41004' => '缺少 secret 参数',
        '41005' => '缺少多媒体文件数据',
        '41006' => '缺少 media_id 参数',
        '41007' => '缺少子菜单数据',
        '41008' => '缺少 oauth code',
        '41009' => '缺少 openid',
        '42001' => 'access_token 超时，请检查 access_token 的有效期，请参考基础支持 - 获取 access_token 中，对 access_token 的详细机制说明',
        '42002' => 'refresh_token 超时',
        '42003' => 'oauth_code 超时',
        '42007' => '用户修改微信密码， accesstoken 和 refreshtoken 失效，需要重新授权',
        '43001' => '需要 GET 请求',
        '43002' => '需要 POST 请求',
        '43003' => '需要 HTTPS 请求',
        '43004' => '需要接收者关注',
        '43005' => '需要好友关系',
        '43019' => '需要将接收者从黑名单中移除',
        '44001' => '多媒体文件为空',
        '44002' => 'POST 的数据包为空',
        '44003' => '图文消息内容为空',
        '44004' => '文本消息内容为空',
        '45001' => '多媒体文件大小超过限制',
        '45002' => '消息内容超过限制',
        '45003' => '标题字段超过限制',
        '45004' => '描述字段超过限制',
        '45005' => '链接字段超过限制',
        '45006' => '图片链接字段超过限制',
        '45007' => '语音播放时间超过限制',
        '45008' => '图文消息超过限制',
        '45009' => '接口调用超过限制',
        '45010' => '创建菜单个数超过限制',
        '45011' => 'API 调用太频繁，请稍候再试',
        '45015' => '回复时间超过限制',
        '45016' => '系统分组，不允许修改',
        '45017' => '分组名字过长',
        '45018' => '分组数量超过上限',
        '45047' => '客服接口下行条数超过上限',
        '40054' => '子菜单链接不合法',
        '46001' => '不存在媒体数据',
        '46002' => '不存在的菜单版本',
        '46003' => '不存在的菜单数据',
        '46004' => '不存在的用户',
        '47001' => '解析 JSON/XML 内容错误',
        '48001' => 'api 功能未授权，请确认公众号已获得该接口，可以在公众平台官网 - 开发者中心页中查看接口权限',
        '48002' => '粉丝拒收消息（粉丝在公众号选项中，关闭了 “ 接收消息 ” ）',
        '48004' => 'api 接口被封禁，请登录 mp.weixin.qq.com 查看详情',
        '48005' => 'api 禁止删除被自动回复和自定义菜单引用的素材',
        '48006' => 'api 禁止清零调用次数，因为清零次数达到上限',
        '48008' => '没有该类型消息的发送权限',
        '50001' => '用户未授权该 api',
        '50002' => '用户受限，可能是违规后接口被封禁',
        '50005' => '用户未关注公众号',
        '61451' => '参数错误 (invalid parameter)',
        '61452' => '无效客服账号 (invalid kf_account)',
        '61453' => '客服帐号已存在 (kf_account exsited)',
        '61454' => '客服帐号名长度超过限制 ( 仅允许 10 个英文字符，不包括 @ 及 @ 后的公众号的微信号 )(invalid kf_acount length)',
        '61455' => '客服帐号名包含非法字符 ( 仅允许英文 + 数字 )(illegal character in kf_account)',
        '61456' => '客服帐号个数超过限制 (10 个客服账号 )(kf_account count exceeded)',
        '61457' => '无效头像文件类型 (invalid file type)',
        '61450' => '系统错误 (system error)',
        '61500' => '日期格式错误',
        '65301' => '不存在此 menuid 对应的个性化菜单',
        '65302' => '没有相应的用户',
        '65303' => '没有默认菜单，不能创建个性化菜单',
        '65304' => 'MatchRule 信息为空',
        '65305' => '个性化菜单数量受限',
        '65306' => '不支持个性化菜单的帐号',
        '65307' => '个性化菜单信息为空',
        '65308' => '包含没有响应类型的 button',
        '65309' => '个性化菜单开关处于关闭状态',
        '65310' => '填写了省份或城市信息，国家信息不能为空',
        '65311' => '填写了城市信息，省份信息不能为空',
        '65312' => '不合法的国家信息',
        '65313' => '不合法的省份信息',
        '65314' => '不合法的城市信息',
        '65316' => '该公众号的菜单设置了过多的域名外跳（最多跳转到 3 个域名的链接）',
        '65317' => '不合法的 URL',
        '9001001' => 'POST 数据参数不合法',
        '9001002' => '远端服务不可用',
        '9001003' => 'Ticket 不合法',
        '9001004' => '获取摇周边用户信息失败',
        '9001005' => '获取商户信息失败',
        '9001006' => '获取 OpenID 失败',
        '9001007' => '上传文件缺失',
        '9001008' => '上传素材的文件类型不合法',
        '9001009' => '上传素材的文件尺寸不合法',
        '9001010' => '上传失败',
        '9001020' => '帐号不合法',
        '9001021' => '已有设备激活率低于 50% ，不能新增设备',
        '9001022' => '设备申请数不合法，必须为大于 0 的数字',
        '9001023' => '已存在审核中的设备 ID 申请',
        '9001024' => '一次查询设备 ID 数量不能超过 50',
        '9001025' => '设备 ID 不合法',
        '9001026' => '页面 ID 不合法',
        '9001027' => '页面参数不合法',
        '9001028' => '一次删除页面 ID 数量不能超过 10',
        '9001029' => '页面已应用在设备中，请先解除应用关系再删除',
        '9001030' => '一次查询页面 ID 数量不能超过 50',
        '9001031' => '时间区间不合法',
        '9001032' => '保存设备与页面的绑定关系参数错误',
        '9001033' => '门店 ID 不合法',
        '9001034' => '设备备注信息过长',
        '9001035' => '设备申请参数不合法',
        '9001036' => '查询起始值 begin 不合法',
    ];


    /**
     * 初始化
     */
    public function initialize()
    {
        $_conf = WechatConf::getConf('wechat');
        $_applet = WechatConf::getConf('applet');
        if (!is_array($_conf)) {
            $this->error($_conf);
        }

        if (!is_array($_applet)) {
            $this->error($_applet);
        }
        $this->_WeChatConf = $_conf;

        $_easyWechatConf = [
            'app_id' => $_conf['wechat_app_id']['value']??'',
            'secret' => $_conf['wechat_app_secret']['value']??'',
            'token' => $_conf['wechat_token']['value']??'',
            'aes_key' => $_conf['wechat_encoding_aes_key']['value']??'',
            'response_type' => 'array',
            'log' => [
                'default' => 'dev', // 默认使用的 channel，生产环境可以改为下面的 prod
                'channels' => [
                    // 测试环境
                    'dev' => [
                        'driver' => 'single',
                        'path' => '/tmp/easywechat.log',
                        'level' => 'debug',
                    ],
                    // 生产环境
                    'prod' => [
                        'driver' => 'daily',
                        'path' => '/tmp/easywechat.log',
                        'level' => 'info',
                    ],
                ],
            ],
        ];


        $this->_Applet = $_applet;
        $this->_app = Factory::officialAccount($_easyWechatConf);

    }

    /**
     * 基础设置
     */
    public function baseConf()
    {
        if ($this->request->isPost()) {

            if(!\think\facade\Config::get('user.can_change_primary_config',true)){
                return ['code' => -1, 'message' => '请勿修改此项目关键配置'];
            };

            $_post = $this->request->post();

            $_create = WechatConf::setConf($_post, 'wechat');

            if ($_create !== TRUE) {
                return ['code' => -1, 'message' => $_create];
            }
            return ['code' => 0, 'message' => config('message.')[0], 'url' => '/we_chat/base_conf'];
        }

        $this->_WeChatConf['wechat_api_location'] = $this->request->domain() . '/we_chat/api';

        $this->assign('data', $this->_WeChatConf);
        return $this->fetch();
    }

    /**
     * 支付配置
     * @author Malson
     */
    public function payConf()
    {
        if ($this->request->isPost()) {
            if(!\think\facade\Config::get('user.can_change_primary_config',TRUE)){
                return ['code' => -1, 'message' => '请勿修改此项目关键配置'];
            };

            $_post = $this->request->post();

            $_file = $this->request->file();

            $_create = WechatConf::setConf($_post, 'wechat-pay');

            if ($_create !== TRUE) {
                return ['code' => -1, 'message' => $_create];
            }
            // 证书文件
            $_apiclientCert = $_file['apiclient_cert'] ?? FALSE;
            $_apiclientKey = $_file['apiclient_key'] ?? FALSE;

            // 支付证书存放文件夹
            $_filePath = Env::get('root_path') . 'cert/pay/wechat';

            // 尝试上传证书文件
            try {
                // 如果上传了 cert.pem 文件则保存
                if ($_apiclientCert !== FALSE) {
                    $_apiclientCert->move($_filePath, 'cert.pem');
                }

                // 如果上传了 key.pem 文件则保存
                if ($_apiclientKey !== FALSE) {
                    $_apiclientKey->move($_filePath, 'key.pem');
                }
            } catch (\Exception $e) {
                return ['code' => -1, 'message' => '证书文件上传失败'];
            }
            return ['code' => 0, 'message' => config('message.')[0], 'url' => '/we_chat/pay_conf'];
        }

        // 获取配置
        $_payConf = WechatConf::getConf('wechat-pay');

        // 判断返回信息是否正确
        if (!is_array($_payConf)) {
            $this->error($_payConf);
        }

        // 支付证书存放文件夹
        $_filePath = Env::get('root_path') . 'cert/pay/wechat';

        $_payConf['wechat-pay_cert_uploaded'] = file_exists($_filePath . '/cert.pem');
        $_payConf['wechat-pay_key_uploaded'] = file_exists($_filePath . '/key.pem');

        $this->assign('data', $_payConf);
        return $this->fetch();
    }

    /**
     * 支付配置
     * @author Malson
     */
    public function MicroAppPayConf()
    {
        if ($this->request->isPost()) {

            if(!\think\facade\Config::get('user.can_change_primary_config',TRUE)){
                return ['code' => -1, 'message' => '请勿修改此项目关键配置'];
            };

            $_post = $this->request->post();

            $_file = $this->request->file();

            $_create = WechatConf::setConf($_post, 'micro-pay');

            if ($_create !== TRUE) {
                return ['code' => -1, 'message' => $_create];
            }
            // 证书文件
            $_apiclientCert = $_file['apiclient_cert'] ?? FALSE;
            $_apiclientKey = $_file['apiclient_key'] ?? FALSE;

            // 支付证书存放文件夹
            $_filePath = Env::get('root_path') . 'cert/pay/micro-app';

            // 尝试上传证书文件
            try {
                // 如果上传了 cert.pem 文件则保存
                if ($_apiclientCert !== FALSE) {
                    $_apiclientCert->move($_filePath, 'cert.pem');
                }

                // 如果上传了 key.pem 文件则保存
                if ($_apiclientKey !== FALSE) {
                    $_apiclientKey->move($_filePath, 'key.pem');
                }
            } catch (\Exception $e) {
                return ['code' => -1, 'message' => '证书文件上传失败'];
            }
            return ['code' => 0, 'message' => config('message.')[0], 'url' => '/we_chat/micro_app_pay_conf'];
        }

        // 获取配置
        $_payConf = WechatConf::getConf('micro-pay');

        // 判断返回信息是否正确
        if (!is_array($_payConf)) {
            $this->error($_payConf);
        }

        // 支付证书存放文件夹
        $_filePath = Env::get('root_path') . 'cert/pay/micro-app';

        $_payConf['micro-pay_cert_uploaded'] = file_exists($_filePath . '/cert.pem');
        $_payConf['micro-pay_key_uploaded'] = file_exists($_filePath . '/key.pem');

        $this->assign('data', $_payConf);
        return $this->fetch();
    }

    /*
     * 小程序模板消息
     * */
    public function applet()
    {
        if ($this->request->isPost()) {

            if(!\think\facade\Config::get('user.can_change_primary_config',TRUE)){
                return ['code' => -1, 'message' => '请勿修改此项目关键配置'];
            };

            $_post = $this->request->post();

            $_create = WechatConf::setConf($_post, 'applet');

            if ($_create !== TRUE) {
                return ['code' => -1, 'message' => $_create];
            }
            return ['code' => 0, 'message' => config('message.')[0], 'url' => '/we_chat/applet'];
        }

        $this->assign('data', $this->_Applet);
        return $this->fetch();
    }

    /**
     * 小程序 模板消息
     */
    public function templateMessage()
    {
        if ($this->request->isPost()) {

            if(!\think\facade\Config::get('user.can_change_primary_config',TRUE)){
                return ['code' => -1, 'message' => '请勿修改此项目关键配置'];
            };

            $_post = $this->request->post();
            $_create = WechatConf::setConf($_post, 'micro-template');

            if ($_create !== TRUE) {
                return ['code' => -1, 'message' => $_create];
            }
            return ['code' => 0, 'message' => config('message.')[0], 'url' => '/we_chat/template_message'];
        }

        $_applet_template = WechatConf::getConf('micro-template');

        if (!is_array($_applet_template)) {
            $this->error($_applet_template);
        }
        $this->assign('data', $_applet_template);
        return $this->fetch();
    }


    public function editVal(Request $request, WechatConf $conf)
    {
        if ($request->isPost()) {
            try {

                $param = $request->post();

                $res = $conf->where('name', $param['name'])->update(['status' => $param['checked']]);

                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 自动回复
     */
    public function automaticResponse()
    {
        if (!is_array($_replayData = WechatConf::getConf('diy-replay'))) {
            $this->error($_replayData);
        };

        $this->assign('data', $_replayData['diy-replay_content']['value']??'');

        return $this->fetch();
    }

    /**
     * 自定义菜单
     */
    public function diyMenu()
    {
        try {
            $_data = WechatConf::where('name', 'diy-menu_content')->find()['value'];
        } catch (\Exception $e) {
            $_data = '';
        }

        $this->assign('data', $_data);

        return $this->fetch();
    }


    /**
     * 素材列表
     */
    public function materialList()
    {
        // 当前页码
        $_page = $this->request->get('page', 1);
        // 每页元素数量
        $_pageRows = 15;
        // 偏移量
        $_offset = ($_page - 1) * $_pageRows;
        // 素材列表
        $_materialList = $this->_app->material->list('image', $_offset, $_pageRows);
        // 如果微信返回错误
        if (isset($_materialList['errcode'])) {
            $this->error($this->ERROR[$_materialList['errcode']]);
        }

//        $_mook = ['item' => [0 => ['media_id' => 'MKVTFJ3GDzHYWLFUl__qJrFmW5QKt1GasMEc76oP8-w', 'name' => 'UgY18DW.jpg', 'update_time' => 1542682014, 'url' => 'http://mmbiz.qpic.cn/mmbiz_jpg/kNr0D3nVH8p9Vpv5UH6s3Th87E1AWy8Bpp3l224omWyORUxg3EjiaRFj73a9ciaRGeTaoZhBQfHX5a56wDib4NrXg/0?wx_fmt=jpeg',], 1 => ['media_id' => 'MKVTFJ3GDzHYWLFUl__qJs8Ammn4GrzPX_9xktgKr9A', 'name' => 'rfwDB3L.png', 'update_time' => 1542682013, 'url' => 'http://mmbiz.qpic.cn/mmbiz_png/kNr0D3nVH8p9Vpv5UH6s3Th87E1AWy8BOgOH1x83HHKerKANp3PIsW6iclQoSh3Y24zBTpgXyn06icWJTicNlXqicQ/0?wx_fmt=png',], 2 => ['media_id' => 'MKVTFJ3GDzHYWLFUl__qJjc5Hfd_vywkSrhdXLoGpNM', 'name' => 'wallpaper.png', 'update_time' => 1542682013, 'url' => 'http://mmbiz.qpic.cn/mmbiz_png/kNr0D3nVH8p9Vpv5UH6s3Th87E1AWy8BcveKcWz0ImXFKSfWC1m3fyiaqvPYM94kyJXrgpV2KhFaflSCF4nCQgw/0?wx_fmt=png',], 3 => ['media_id' => 'MKVTFJ3GDzHYWLFUl__qJn82NcNkL8_UfmFvzkbGS54', 'name' => 'code-wallpaper-18.png', 'update_time' => 1542682012, 'url' => 'http://mmbiz.qpic.cn/mmbiz_png/kNr0D3nVH8p9Vpv5UH6s3Th87E1AWy8BKOqkLiaibhxe9u9zWUFH9j8af9ibEgezia9akFzXeyKd2oFdZLaHu4N4Zw/0?wx_fmt=png',], 4 => ['media_id' => 'MKVTFJ3GDzHYWLFUl__qJg62o0490ANobbEPPasDFIg', 'name' => 'life-code-typography-hd-wallpaper-1920x1080-7168.jpg', 'update_time' => 1542682012, 'url' => 'http://mmbiz.qpic.cn/mmbiz_jpg/kNr0D3nVH8p9Vpv5UH6s3Th87E1AWy8BUMibIpq7oDavq9F0z4TSdIBbrQWBqCc1SLefmttRWUElgTJ2s9qlm6A/0?wx_fmt=jpeg',], 5 => ['media_id' => 'MKVTFJ3GDzHYWLFUl__qJlXNJzhn2QeF_WY2GmhqmMQ', 'name' => '4781442-d6a8c2e5714b4c44.png', 'update_time' => 1542682012, 'url' => 'http://mmbiz.qpic.cn/mmbiz_png/kNr0D3nVH8p9Vpv5UH6s3Th87E1AWy8Bd8D0wGbhwuiacrBDP4GPnibHBJU78qZS1ugQOArDMDsEcoibldPiawUTmQ/0?wx_fmt=png',], 6 => ['media_id' => 'MKVTFJ3GDzHYWLFUl__qJiw7rEZtM3C-fnwBSlXnIVQ', 'name' => '6f8dad9c18b0c067f8868104179ac66b.jpg', 'update_time' => 1542682003, 'url' => 'http://mmbiz.qpic.cn/mmbiz_jpg/kNr0D3nVH8p9Vpv5UH6s3Th87E1AWy8Be4V707CFuHGZlRXMmH5Y5t8edBpMf48cSrv08zjfyWOPplJbicMLGNw/0?wx_fmt=jpeg',], 7 => ['media_id' => 'MKVTFJ3GDzHYWLFUl__qJh6wwmHnl-1ifFZn8Gh_6LE', 'name' => '666afff8ac6023fbaf2cfc63ebfa13ab.jpg', 'update_time' => 1542682003, 'url' => 'http://mmbiz.qpic.cn/mmbiz_jpg/kNr0D3nVH8p9Vpv5UH6s3Th87E1AWy8BNYmp4ewOuUyv4VCgB4kCP5OPuiagdqYQ4y6CMNEOJpDXzqUFysdaiaNQ/0?wx_fmt=jpeg',], 8 => ['media_id' => 'MKVTFJ3GDzHYWLFUl__qJkh_Kwz8i2VUqs7Ph8a4iWo', 'name' => 'a3fe385fe691a5e14582088f03b1e8e1.jpg', 'update_time' => 1542682003, 'url' => 'http://mmbiz.qpic.cn/mmbiz_jpg/kNr0D3nVH8p9Vpv5UH6s3Th87E1AWy8Bw5DP5DpPESQ34bOUiaGHo7fp4woicp7cSFmWicia8icyR2n3PVr3O9UAmWQ/0?wx_fmt=jpeg',], 9 => ['media_id' => 'MKVTFJ3GDzHYWLFUl__qJm6zi99cVGiGhiQm40uaDUk', 'name' => '3208932803b9bf62dd1cbeaaaee86802.jpg', 'update_time' => 1542682003, 'url' => 'http://mmbiz.qpic.cn/mmbiz_jpg/kNr0D3nVH8p9Vpv5UH6s3Th87E1AWy8BCAZvMT0ewicP5ibb19u1aSgIApDPYpSsuvfdBBaEwBenKO1hm4b2DbZA/0?wx_fmt=jpeg',], 10 => ['media_id' => 'MKVTFJ3GDzHYWLFUl__qJhRezlFg2jZQojkcHRRGnkw', 'name' => 'f7b3382f02db01a1252f0d908d18fdd1.jpg', 'update_time' => 1542682003, 'url' => 'http://mmbiz.qpic.cn/mmbiz_jpg/kNr0D3nVH8p9Vpv5UH6s3Th87E1AWy8BKiad9KGtSNVJeSvLiaxQFFH8kxNJ5yI2mnqWwwwmC7LVHku3hvlmXr7A/0?wx_fmt=jpeg',], 11 => ['media_id' => 'MKVTFJ3GDzHYWLFUl__qJo_RT1ZnN8oNOk5upfQ-HZ8', 'name' => 'fd042afa595edcdbc11bdc2ed6a3ac3d.jpg', 'update_time' => 1542682003, 'url' => 'http://mmbiz.qpic.cn/mmbiz_jpg/kNr0D3nVH8p9Vpv5UH6s3Th87E1AWy8BoEnbRLVPDV4luBL3EqCQeXAPf4wBUWdFJnWGvEh59AHxOKKZTmhwnQ/0?wx_fmt=jpeg',], 12 => ['media_id' => 'MKVTFJ3GDzHYWLFUl__qJmvBBKmIKYaD4xWmYWfHN0Q', 'name' => '1f57ac8806ab258f27e771d8ed148f5c.jpg', 'update_time' => 1542682002, 'url' => 'http://mmbiz.qpic.cn/mmbiz_jpg/kNr0D3nVH8p9Vpv5UH6s3Th87E1AWy8BytauLlUTuSIABMREQcdz49YUG6uKLyYCKoJXzcW8e1fRM0mAhXseOw/0?wx_fmt=jpeg',], 13 => ['media_id' => 'MKVTFJ3GDzHYWLFUl__qJqrD9PCL4SbR4QRMIb3rXTM', 'name' => '4fa0d352218ba4a082c951f46b361440.jpg', 'update_time' => 1542682002, 'url' => 'http://mmbiz.qpic.cn/mmbiz_jpg/kNr0D3nVH8p9Vpv5UH6s3Th87E1AWy8BRl94r6cpYgqYt3equzRwfAF3rzSukrRUA5xXcwIhSOJxIdBHEFKiaQQ/0?wx_fmt=jpeg',], 14 => ['media_id' => 'MKVTFJ3GDzHYWLFUl__qJrHhcjNZKqw0E79QEszcYX8', 'name' => '4c9edbff8877fd02c7b521f6f27fa4f4.jpg', 'update_time' => 1542682002, 'url' => 'http://mmbiz.qpic.cn/mmbiz_jpg/kNr0D3nVH8p9Vpv5UH6s3Th87E1AWy8By59NWDmnxe0456ibkKibsIrwSXNYeuvtREF9E3rQS0H7yic2CrO8Kp5KA/0?wx_fmt=jpeg',],], 'total_count' => 17, 'item_count' => 15,];

        $this->assign('data', $_materialList);
        $this->assign('page_rows', 15);
        $this->assign('page', $_page);
        return $this->fetch();
    }

    /**
     * 保存自动回复
     */
    public function saveReplay()
    {
        if ($this->request->isPost()) {

            if(!\think\facade\Config::get('user.can_change_primary_config',TRUE)){
                return ['code' => -1, 'message' => '请勿修改此项目关键配置'];
            };

            $_result = WechatConf::setConf([
                'diy-replay_content' => $this->request->post('data', ''),
            ], 'diy-replay');

            if ($_result === TRUE) {
                return ['code' => 0];
            }

            return ['code' => -1, 'message' => $_result];
        }
    }

    /**
     * 更新自定义菜单
     * @param WechatConf $wechatConf
     * @return array
     */
    public function updateDiyMenu(WechatConf $wechatConf)
    {
        $_post = $this->request->post();


        if(!\think\facade\Config::get('user.can_change_primary_config',TRUE)){
            return ['code' => -1, 'message' => '请勿修改此项目关键配置'];
        };

        // 验证
        $check = $wechatConf->valid($_post, 'updateDiyMenu');
        if ($check['code']) return $check;

        $_data = $_post['content'];

        $_menuData = [];

        function buildButtonContent($name, $type, $key, $data)
        {
            $_data = [
                'name' => $name,
                'type' => $type,
            ];

            switch ($type) {
                case 'click':           // 点击
                case 'location_select': // 发送地理位置
                case 'pic_sysphoto':    // 系统拍照发图
                case 'pic_photo_or_album':  // 拍照或者相册发图
                case 'pic_weixin':          // 微信相册发图
                    $_data['key'] = $key;
                    break;
                case 'view':
                    $_data['url'] = $data['link-page'];
                    break;
                case 'miniprogram':
                    $_data['appid'] = $data['micro-app-id'];
                    $_data['pagepath'] = $data['micro-app-page'];
                    $_data['url'] = $data['micro-app-spare-page'];
                    break;
            }

            return $_data;
        }


        foreach ($_data as $key => $val) {

            $_menu = [
                'name' => $val['name'],
            ];

            // 如果设置了子元素,那么他含有二级菜单
            if (
                isset($val['children']) &&
                !empty($val['children'])
            ) {
                $_menu['sub_button'] = [];
                foreach ($val['children'] as $key2 => $val2) {
                    $_menu['sub_button'][] = buildButtonContent($val2['name'], $val2['type'], "{$key}-{$key2}", $val2['data']);
                }

            } else {
                // 否则他是个button
                $_menu = buildButtonContent($val['name'], $val['type'], $key, $val['data']);

            }
            $_menuData[] = $_menu;
        }

        $_result = $this->_app->menu->create($_menuData);

        WechatConf::setConf([
            'diy-menu_content' => json_encode($_data),
        ], 'diy-menu');

        try {
            return ['code' => $_result['errcode'], 'message' => $this->ERROR[$_result['errcode']]];
        } catch (\Exception $e) {
            return ['code' => -1, 'message' => $this->ERROR[$_result['errcode']]];
        }
    }

    /**
     * 微信API
     */
    public function api()
    {
        try {
            $_conf = WechatConf::getConf('diy-menu');

            if (!is_array($_conf)) {
                throw new Exception('success');
            }

            $_confData = json_decode($_conf['diy-menu_content']['value'], TRUE);


            unset($_conf);

            $this->_app->server->push(function ($message) use ($_confData) {
                // 如果收到的消息不是事件的话不做处理
                if ($message['MsgType'] !== 'event') {
                    return 'success';
                }

                // 关注回复
                if ($message['Event'] === 'subscribe') {
                    if (!is_array($_replayData = WechatConf::getConf('diy-replay'))) {
                        return 'success';
                    };

                    return $_replayData['diy-replay_content']['value'];
                }

                // 以下是自定义菜单回复

                // 把 0-1 这种的消息key拆成数组
                $_messageKey = explode('-', $message['EventKey']);

                // 记录有消息key 有多少层级
                $_countMessageKey = count($_messageKey);

                // 如果消息key的个数既不是1个 也不是两个 说明微信发过来的消息key 是有问题的,宁可不发送也不能报错
                if ($_countMessageKey !== 1 && $_countMessageKey !== 2) {
                    return 'success';
                }

                // 删除变量,清理内存
                unset($_countMessageKey);

                // 发送消息的数据
                $_messageData = count($_messageKey) === 2 ? $_confData[$_messageKey[0]]['children'][$_messageKey[1]]['data'] : $_confData[$_messageKey[0]]['data'];

                // 判断消息类型,发送消息
                switch ($_messageData['data_type']) {
                    case 'text':
                        return $_messageData['text'];
                        break;
                    case 'image':
                        return new Image($_messageData['media_id']);
                        break;
                    default:
                        return NULL;
                }
            });

            $_response = $this->_app->server->serve();

            $_response->send();

        } catch (\Exception $e) {
            return 'success';
        }
    }
}