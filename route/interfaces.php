<?php

use think\facade\Route;

$version = 'v2.0';
Route::group($version, function () {
    /***************************************手机端用户操作************************************************/
    Route::group('mobile_my', function () {
        // 配置
        Route::rule('set', 'set', 'post');
    })->prefix('interfaces/mobile.my/');
    /***************************************小程序用户操作************************************************/
    Route::group('applet_my', function () {
        // 小程序注册
        Route::rule('login', 'login', 'post');
        // 保存小程序form_id
        Route::rule('saveFormId', 'saveFormId', 'post');
        // APP微信注册
        Route::rule('app_login', 'app_login', 'post');
        // 手机站微信注册
        Route::rule('mobile_login', 'mobile_login', 'GET');
        // 绑定手机号和密码
        Route::rule('info', 'info', 'post');
        // 支付
        Route::rule('pay', 'pay', 'post');
        // 绑定微信
        Route::rule('binding_wechat', 'binding_wechat', 'post');
        // 解除绑定微信
        Route::rule('relieve_binding_wechat', 'relieve_binding_wechat', 'post');
        // 是否绑定微信
        Route::rule('judge_binding_wechat', 'judge_binding_wechat', 'post');
        // 面对面扫码
        Route::rule('face_code', 'face_code', 'post');
        // 小程序一级菜单
        Route::rule('navigation', 'navigation', 'post');
    })->prefix('interfaces/applet.my/');
    /***************************************小程序支付操作************************************************/
    Route::group('applet_pay', function () {
        // 支付
        Route::rule('payment', 'payment', 'post');
        // 充值
        Route::rule('recharge', 'recharge', 'post');
        
        
    })->prefix('interfaces/applet.pay/');
    /***************************************店铺************************************************/
    Route::group('store', function () {
        // 头部
        Route::rule('head', 'head', 'post');
        // 首页
        Route::rule('index', 'index', 'post');
        // 分类列表
        Route::rule('classify_list', 'classify_list', 'post');
        // 热门分类列表
        Route::rule('hot_classify_list', 'hot_classify_list', 'post');
        // 店铺详情
        Route::rule('info', 'info', 'post');
        // 店铺全部商品
        Route::rule('goods_list', 'goods_list', 'post');
        // 店铺新品
        Route::rule('new_product_list', 'new_product_list', 'post');
        // 收藏商品
        Route::rule('collect_store', 'collect_store', 'post');
        // 收藏商品列表
        Route::rule('collect_store_list', 'collect_store_list', 'post');
        // 收藏商品删除
        Route::rule('collect_store_delete', 'collect_store_delete', 'post');
        // 详情收藏商品删除
        Route::rule('view_collect_store_delete', 'view_collect_store_delete', 'post');
        // 动态列表
        Route::rule('article_list', 'article_list', 'post');
        // 动态详情
        Route::rule('article_view', 'article_view', 'post');
        // 附近门店
        Route::rule('nearby_list', 'nearby_list', 'post');
        // 发现好店列表
        Route::rule('good_list', 'good_list', 'post');
        // 搜索店铺列表
        Route::rule('search_list', 'search_list', 'post');
        // 动态详情 - web
        Route::rule('web_article_view', 'web_article_view', 'GET');
        // 平台店铺主营分类
        Route::rule('platform_classify', 'platform_classify', 'post');
    })->prefix('interfaces/store.index/');
    /***************************************用户认证************************************************/
    Route::group('register', function () {
        // 手机号注册
        Route::rule('tel', 'tel', 'post');
        // 邀请好友 - 邀请好友注册页面
        Route::rule('invite', 'invite', 'GET|post');
    })->prefix('interfaces/auth.register/');
    Route::group('login', function () {
        // 手机号登录
        Route::rule('index', 'index', 'post');
        // 短信验证码登录
        Route::rule('sms', 'sms', 'post');
        Route::rule('test', 'test', 'GET|post');
    })->prefix('interfaces/auth.login/');
    // 我的
    Route::group('my', function () {
        // 我的
        Route::rule('index', 'index', 'post');
        // 获取用户入驻状态
        Route::rule('getInState', 'getInState', 'post');
        // 我的钱包
        Route::rule('myWallet', 'myWallet', 'post');
        // 个人资料
        Route::rule('info', 'info', 'post');
        // 更新头像
        Route::rule('avatar', 'avatar', 'post');
        // 更新其他字段
        Route::rule('other', 'other', 'post');
        // 成长值
        Route::rule('task', 'task', 'post');
        // 创建店铺
        Route::rule('create_store', 'create_store', 'post');
        // 我的会员卡生成码
        Route::rule('payment_code', 'payment_code', 'post');
        // 会员卡获取解密会员ID
        Route::rule('index_number', 'index_number', 'post');
        // 会员卡web页
        Route::rule('index_web', 'index_web', 'get');
        // 客服手机号
        Route::rule('customer_phone', 'customer_phone', 'post|get');
    })->prefix('interfaces/auth.my/');
    // 会员等级
    Route::group('rank', function () {
        // 列表
        Route::rule('index', 'index', 'post');
        // 会员专享价 - web
        Route::rule('premium_price', 'premium_price', 'GET');
        // 会员卡
        Route::rule('card', 'card', 'post');
    })->prefix('interfaces/auth.rank/');
    /***************************************短信************************************************/
    Route::group('sms', function () {
        // 发送短信通用
        Route::rule('send', 'send', 'post');
        // 验证码合法性检测(单接口)
        Route::rule('checkCodeInvalid', 'checkCodeInvalid', 'post');
    })->prefix('interfaces/auth.sms/');
    /***************************************电子书************************************************/
    Route::group('book', function () {
        // 列表
        Route::rule('index', 'index', 'post');
    })->prefix('interfaces/book.book/');
    /***************************************账户设置************************************************/
    Route::group('setting', function () {
        // 首页
        Route::rule('index', 'index', 'post');
        // 账户与安全
        Route::rule('safety', 'safety', 'post');
        // 设置密码
        Route::rule('set_password', 'set_password', 'post');
        // 修改密码
        Route::rule('update_password', 'update_password', 'post');
        // 忘记密码
        Route::rule('forget_password', 'forget_password', 'post');
        // 修改该手机号
        Route::rule('update_phone', 'update_phone', 'post');
        // 设置支付密码
        Route::rule('set_pay_password', 'set_pay_password', 'post');
        // 修改支付密码
        Route::rule('update_pay_password', 'update_pay_password', 'post');
        // 忘记支付密码
        Route::rule('forget_pay_password', 'forget_pay_password', 'post');
        // 问题反馈
        Route::rule('feedback', 'feedback', 'post');
        // 帮助中心
        Route::rule('help_center', 'help_center', 'post');
        // 客服热线
        Route::rule('hotline', 'hotline', 'post');
    })->prefix('interfaces/auth.setting/');
    /***************************************收货地址************************************************/
    Route::group('address', function () {
        // 首页
        Route::rule('index', 'index', 'post');
        // 新增
        Route::rule('create', 'create', 'post');
        // 编辑
        Route::rule('update', 'update', 'post');
        // 读取
        Route::rule('find', 'find', 'post');
        // 省市区街道 - 地区联动
        Route::rule('linkage', 'linkage', 'post');
        // 读取
        Route::rule('destroy', 'destroy', 'post');
    })->prefix('interfaces/auth.address/');
    /***************************************银行卡************************************************/
    Route::group('card', function () {
        // 首页
        Route::rule('index', 'index', 'post');
        // 新增
        Route::rule('create', 'create', 'post');
        // 编辑
        Route::rule('details', 'details', 'post');
        // 删除
        Route::rule('destroy', 'destroy', 'post');
    })->prefix('interfaces/auth.card/');
    /***************************************积分商城************************************************/
    Route::group('integral', function () {
        // 分类列表
        Route::rule('classify', 'classify', 'post');
        // 首页
        Route::rule('index', 'index', 'post');
        // 商品列表
        Route::rule('goods', 'goods', 'post');
        // 详情
        Route::rule('view', 'view', 'post');
        // 签到
        Route::rule('sign', 'sign', 'post');
        // 明细
        Route::rule('detail', 'detail', 'post');
        // 任务
        Route::rule('task', 'task', 'post');
        // 兑换展示
        Route::rule('conversion', 'conversion', 'post');
        // 兑换商品
        Route::rule('redemption', 'redemption', 'post');
        // 兑换商品+金额
        Route::rule('redemption_money', 'redemption_money', 'post');
        // 兑换记录
        Route::rule('conversion_record', 'conversion_record', 'post');
        // 删除兑换记录
        Route::rule('conversion_record_delete', 'conversion_record_delete', 'post');
        // 兑换记录详情
        Route::rule('conversion_view', 'conversion_view', 'post');
        // 确认收货
        Route::rule('confirm_receipt', 'confirm_receipt', 'post');
        // 积分说明
        Route::rule('help', 'help', 'GET');
        // 积分换购预下单
        Route::rule('preOrder', 'preOrder', 'post');
        // 积分支付回调
        Route::rule('notify', 'notify', 'post');
    })->prefix('interfaces/auth.integral/');
    /***************************************商品分类************************************************/
    Route::group('goods_classify', function () {
        // 发送短信通用
        Route::rule('parent', 'parent', 'post');
        Route::rule('subordinate', 'subordinate', 'post');
    })->prefix('interfaces/goods.classify/');
    /***************************************商品************************************************/
    Route::group('goods', function () {
        // 商品列表
        Route::rule('index', 'index', 'post');
        // 商品详情
        Route::rule('view', 'view', 'post');
        // 商品优惠券列表
        Route::rule('coupon_list', 'coupon_list', 'post');
        // 商品属性获取价格和图片
        Route::rule('attr_find', 'attr_find', 'post');
        // 降价通知
        Route::rule('depreciate_goods', 'depreciate_goods', 'post');
        // 收藏商品
        Route::rule('collect_goods', 'collect_goods', 'post');
        // 收藏商品列表
        Route::rule('collect_goods_list', 'collect_goods_list', 'post');
        // 收藏商品删除
        Route::rule('collect_goods_delete', 'collect_goods_delete', 'post');
        // 详情收藏商品删除
        Route::rule('view_collect_goods_delete', 'view_collect_goods_delete', 'post');
        // 好物推荐 - 精选
        Route::rule('choiceness_list', 'choiceness_list', 'post');
        // 好物推荐 - 分类推荐
        Route::rule('good_recommend_list', 'good_recommend_list', 'post');
        // 商品评价列表
        Route::rule('evaluate_list', 'evaluate_list', 'post');
        // 商品配送说明
        Route::rule('shipping_instructions', 'shipping_instructions', 'post');
        // 门店自提
        Route::rule('take_list', 'take_list', 'post');
    })->prefix('interfaces/goods.goods/');
    /***************************************限时抢购************************************************/
    Route::group('time_limit', function () {
        // 限时抢购 - 分类
        Route::rule('classify', 'classify', 'post');
        // 限时抢购 - 列表
        Route::rule('index', 'index', 'post');
    })->prefix('interfaces/goods.time_limit/');
    /***************************************砍价列表************************************************/
    Route::group('bargain', function () {
        // 砍价 - 列表
        Route::rule('index', 'index', 'post');
        // 砍价 - 立即砍价
        Route::rule('immediately', 'immediately', 'post');
        // 砍价 - 我的砍价
        Route::rule('my_cut', 'my_cut', 'post');
        // 砍价 - 我的砍价详情
        Route::rule('my_cut_view', 'my_cut_view', 'post');
        // 砍价 - 帮助砍价
        Route::rule('my_cut_help', 'my_cut_help', 'post');
        // 砍价 - 砍价详情 - web
        Route::rule('view_web', 'view_web', 'GET');
    })->prefix('interfaces/goods.bargain/');
    /***************************************拼团列表************************************************/
    Route::group('group', function () {
        // 拼团 - 列表
        Route::rule('index', 'index', 'post');
        // 拼团 - 分类列表
        Route::rule('class_index', 'class_index', 'post');
        // 拼团 - 我的拼团
        Route::rule('my_index', 'my_index', 'post');
        // 拼团 - 拼团详情
        Route::rule('view', 'view', 'post');
        // 拼团 - 拼团详情 - web
        Route::rule('view_web', 'view_web', 'GET');
        // 拼团 - 拼团规则 - web
        Route::rule('rule', 'rule', 'GET');
    })->prefix('interfaces/goods.Group/');
    /***************************************充值************************************************/
    Route::group('recharge', function () {
        // 充值 - 列表
        Route::rule('index', 'index', 'post');
        // 账户余额记录 - 列表
        Route::rule('balance_record', 'balance_record', 'post');
        // （微信）异步充值通知
        Route::rule('notify', 'notify', 'post');
    })->prefix('interfaces/auth.recharge/');
    /***************************************充值(支付宝回调)************************************************/
    Route::group('ali_recharge', function () {
        // （支付宝）异步充值通知
        Route::rule('notify', 'notify', 'post');
    })->prefix('interfaces/auth.AliRecharge/');
    /***************************************购物车************************************************/
    Route::group('cart', function () {
        // 购物车 - 新增
        Route::rule('create', 'create', 'post');
        // 购物车 - 列表
        Route::rule('index', 'index', 'post');
        // 购物车 - 数量增加
        Route::rule('add_number', 'add_number', 'post');
        // 购物车 - 数量减少
        Route::rule('reduce_number', 'reduce_number', 'post');
        // 购物车 - 删除
        Route::rule('delete', 'delete', 'post');
        // 购物车 - 编辑
        Route::rule('update', 'update', 'post');
        // 购物车 - 商品收藏
        Route::rule('collect', 'collect', 'post');
        // 购物车 - 商品规格
        Route::rule('attr', 'attr', 'post');
        // 购物车 - 确认订单
        Route::rule('confirm_order', 'confirm_order', 'post');
        // 购物车 - 会员购物车商品数量
        Route::rule('number', 'number', 'post');
        // 立即购买 - 确认订单
        Route::rule('common_confirm_order', 'common_confirm_order', 'post');
    })->prefix('interfaces/shopping.cart/');
    /***************************************未登录购物车************************************************/
    Route::group('login_cart', function () {
        // 未登录购物车 - 新增
        Route::rule('create', 'create', 'post');
        // 未登录购物车 - 列表
        Route::rule('index', 'index', 'post');
        // 未登录购物车 - 数量增加
        Route::rule('add_number', 'add_number', 'post');
        // 未登录购物车 - 数量减少
        Route::rule('reduce_number', 'reduce_number', 'post');
        // 未登录购物车 - 删除
        Route::rule('delete', 'delete', 'post');
        // 未登录购物车 - 编辑
        Route::rule('update', 'update', 'post');
        // 未登录购物车 - 商品收藏
        Route::rule('collect', 'collect', 'post');
        // 未登录购物车 - 商品规格
        Route::rule('attr', 'attr', 'post');
        // 未登录购物车 - 会员购物车商品数量
        Route::rule('number', 'number', 'post');
    })->prefix('interfaces/shopping.login_cart/');
    /***************************************合并购物车************************************************/
    Route::group('merge_cart', function () {
        // 合并购物车
        Route::rule('combine', 'combine', 'post');
    })->prefix('interfaces/shopping.merge_cart/');
    /***************************************城市************************************************/
    Route::group('area', function () {
        // 列表
        Route::rule('index', 'index', 'post');
        // 根据ID 获取列表
        Route::rule('get_list_by_id', 'getListById', 'get');
    })->prefix('interfaces/common.area/');
    /***************************************分享************************************************/
    Route::group('share', function () {
        // 版本号
        Route::rule('version_number', 'version_number', 'GET');
        // 文字
        Route::rule('text', 'text', 'GET');
        // 回调
        Route::rule('notify', 'notify', 'post');
        // 平台执照信息
        Route::rule('license', 'license', 'post');
        Route::rule('test11', 'test11', 'get');
        // app跳转标签
        Route::rule('jumpSign', 'jumpSign', 'post');
    })->prefix('interfaces/common.share/');
    /***************************************搜索************************************************/
    Route::group('search', function () {
        // 发送短信通用
        Route::rule('hot', 'hot', 'post');
    })->prefix('interfaces/goods.search/');
    /***************************************优惠券************************************************/
    Route::group('coupon', function () {
        // 优惠券 - 领券
        Route::rule('get', 'get', 'post');
        // 优惠券 - 换券share
        Route::rule('exchange', 'exchange', 'post');
        // 领券中心 - 换券 - 立即兑换详情
        Route::rule('exchange_view', 'exchange_view', 'post');
        // 领券中心 - 换券 - 促销列表
        Route::rule('goods_list', 'goods_list', 'post');
    })->prefix('interfaces/auth.coupon/');
    /***************************************我的优惠券************************************************/
    Route::group('member_coupon', function () {
        // 我的优惠券 - 领券
        Route::rule('get', 'get', 'post');
        // 我的优惠券 - 换券
        Route::rule('exchange', 'exchange', 'post');
        // 我的优惠券 - 列表
        Route::rule('index', 'index', 'post');
        // 我的优惠券 - 使用说明
        Route::rule('instructions', 'instructions', 'GET');
    })->prefix('interfaces/auth.member_coupon/');
    /***************************************我的红包************************************************/
    Route::group('member_packet', function () {
        // 我的红包 - 列表
        Route::rule('index', 'index', 'post');
        // 我的红包 - 使用说明
        Route::rule('instructions', 'instructions', 'GET');
    })->prefix('interfaces/auth.member_packet/');
    /***************************************邀请好友************************************************/
    Route::group('packet', function () {
        // 邀请好友 - 统计数据
        Route::rule('index', 'index', 'post');
    })->prefix('interfaces/auth.packet/');
    /***************************************商品浏览记录************************************************/
    Route::group('record_goods', function () {
        // 商品浏览记录 - 列表
        Route::rule('index', 'index', 'post');
        // 商品浏览记录 - 清空
        Route::rule('delete', 'delete', 'post');
    })->prefix('interfaces/auth.record_goods/');
    /***************************************首页附属************************************************/
    Route::group('home', function () {
        // 商品排行榜
        Route::rule('goods_ranking', 'goods_ranking', 'post');
        // 店铺排行榜
        Route::rule('store_ranking', 'store_ranking', 'post');
        // 品牌甄选分类列表
        Route::rule('brand_class_list', 'brand_class_list', 'post');
        // 品牌甄选列表
        Route::rule('brand_list', 'brand_list', 'post');
        // 热点文章列表
        Route::rule('hot_list', 'hot_list', 'post');
        // 热点文章详情
        Route::rule('hot_view', 'hot_view', 'post');
        // 收藏文章
        Route::rule('collect_article', 'collect_article', 'post');
        // 收藏文章删除
        Route::rule('collect_article_delete', 'collect_article_delete', 'post');
        // 收藏文章列表
        Route::rule('article_list', 'article_list', 'post');
        // 文章详情 - 收藏文章删除
        Route::rule('view_collect_article_delete', 'view_collect_article_delete', 'post');
    })->prefix('interfaces/home.home/');
    /***************************************首页************************************************/
    Route::group('index', function () {
        // 首页
        Route::rule('index', 'index', 'post');
        // 增加广告点击数
        Route::rule('adBrowseInc', 'adBrowseInc', 'post');
        // 当前限时抢购列表(用于局部刷新接口)
        Route::rule('curLimitList', 'curLimitList', 'post');
        // 大礼包列表
        Route::rule('coupon_list', 'coupon_list', 'post');
        // 领取大礼包
        Route::rule('get_coupon', 'get_coupon', 'post');
        // 关闭新人礼包
        Route::rule('setGiftClose', 'setGiftClose', 'post');
    })->prefix('interfaces/home.index/');
    /***************************************消息方式************************************************/
    Route::group('message', function () {
        // 消息列表
        Route::rule('index', 'index', 'post');
        // 消息统计
        Route::rule('statistics', 'statistics', 'post');
    })->prefix('interfaces/home.message/');
    /***************************************支付方式************************************************/
    Route::group('pay', function () {
        // 充值
        Route::rule('recharge', 'recharge', 'post');
    })->prefix('interfaces/common.pay/');
    Route::group('payInfo', function () {
        // 获取支付后的活动信息
        Route::rule('getPayInfo', 'getPayInfo', 'post');
    })->prefix('interfaces/pay.info/');
    /***************************************公共图片、视频方法************************************************/
    Route::group('image', function () {
        // 小程序上传图片
        Route::rule('upload', 'upload', 'post');
        // APP上传图片
        Route::rule('app_upload', 'app_upload', 'post');
        // 上传视频
        Route::rule('upload_video', 'upload_video', 'post');
    })->prefix('interfaces/common.image/');
    /***************************************公共物流（快递）方法************************************************/
    Route::group('express', function () {
        // 物流（快递）详情
        Route::rule('view', 'view', 'post');
        // 物流（快递）列表
        Route::rule('expressList', 'expressList', 'post');
        // dada详情
        Route::rule('dadaExpress', 'dadaExpress', 'post');
    })->prefix('interfaces/common.express/');
    /***************************************生成订单号方法************************************************/
    Route::group('common_order', function () {
        // 订单号
        Route::rule('number', 'number', 'post');
    })->prefix('interfaces/common.order/');
    /*************************************** 订单管理 ************************************************/
    /** 订单建立 **/
    Route::group('order', function () {
        // 确认订单
        Route::rule('confirm', 'confirm', 'post');
        // 确认订单ios
        Route::rule('confirmIos', 'confirmIos', 'post');
    })->prefix('interfaces/order.establish/');
    /** 订单说明 **/
    Route::group('order', function () {
        // 订单列表
        Route::rule('orderList', 'orderList', 'post');
        // 订单列表(售后)
        Route::rule('orderAfterSaleList', 'orderAfterSaleList', 'post');
        // 线下订单列表
        Route::rule('orderUnderLineList', 'orderUnderLineList', 'post');
        // 订单详情[非线下]
        Route::rule('orderDetails', 'orderDetails', 'post');
        // 线下订单详情
        Route::rule('orderUnderLineDetails', 'orderUnderLineDetails', 'post');
        // 退货/退款详情
        Route::rule('refundDetails', 'refundDetails', 'post');
        // 退货/退款金额
        Route::rule('refundMoney', 'refundMoney', 'get|post');
        // 订单搜索记录
        Route::rule('searchHistoryList', 'searchHistoryList', 'post');
        // 待评价列表
        Route::rule('orderEvaluateList', 'orderEvaluateList', 'post');
        // 删除/清空订单搜索记录
        Route::rule('destroySearchHistory', 'destroySearchHistory', 'post');
        // 各商品拼团信息列表
        Route::rule('groupMsgList', 'groupMsgList', 'post');
        // 获取订单状态
        Route::rule('getOrderState', 'getOrderState', 'post');
    })->prefix('interfaces/order.explain/');
    /** 订单操作 **/
    Route::group('order', function () {
        // 取消订单
        Route::rule('cancel', 'cancel', 'post');
        // 删除订单
        Route::rule('destroyOrder', 'destroyOrder', 'post');
        // 撤销退款/退货
        Route::rule('revokeApply', 'revokeApply', 'post');
        // 订单退款(退货第一步)
        Route::rule('refundAndReturn', 'refundAndReturn', 'post');
        // 订单退货
        Route::rule('returnConfirmed', 'returnConfirmed', 'post');
        // 确定收货
        Route::rule('confirmCollect', 'confirmCollect', 'post');
    })->prefix('interfaces/order.operate/');
    /** 组件 **/
    Route::group('assembly', function () {
        // 店铺自提点列表
        Route::rule('takeList', 'takeList', 'post');
    })->prefix('interfaces/order.assembly/');
    /** 评价 **/
    Route::group('evaluate', function () {
        // 发表评价
        Route::rule('report', 'report', 'post');
        Route::rule('reportIos', 'reportIos', 'post');
        // 我的评价列表
        Route::rule('myEvaluateList', 'myEvaluateList', 'post');
    })->prefix('interfaces/order.evaluate/');
    /*************************************** 支付管理 ************************************************/
    /** 余额支付 **/
    Route::group('balance', function () {
        // 执行支付
        Route::rule('exec', 'exec', 'post');
    })->prefix('interfaces/pay.balance/');
    // 微信支付
    Route::group('wx', function () {
        // 支付回调
        Route::rule('paidNotify', 'paidNotify', 'post');
    })->prefix('interfaces/pay.wxPay/');
    // 支付宝支付
    Route::group('ali', function () {
        // 支付回调
        Route::rule('notifyurl', 'notifyurl', 'post');
    })->prefix('interfaces/pay.AliPay/');
    /*************************************** web页面管理 ************************************************/
    Route::group('html', function () {
        // 商品详情web
        Route::rule('goods_view', 'goods_view', 'GET');
        // 其他文章详情web
        Route::rule('article_view', 'article_view', 'GET');
        // 抽奖活动规则
        Route::rule('draw_activity_view', 'draw_activity_view', 'GET');
        // 积分文章详情web
        Route::rule('integral_view', 'integral_view', 'GET');
        // 其他文章详情小程序
        Route::rule('applet_article_view', 'applet_article_view', 'GET');
    })->prefix('interfaces/common.html/');
    /*************************************** 扫码生成管理 ************************************************/
    Route::group('code', function () {
        // 条形码
        Route::rule('bar_code', 'bar_code', 'GET');
        // 二维码
        Route::rule('qr_code', 'qr_code', 'GET');
    })->prefix('interfaces/common.code/');
    /*************************************** 抽奖活动管理 ************************************************/
    Route::group('lottery_activity', function () {
        //抽奖首页
        Route::rule('activity_goods_list', 'activity_goods_list', 'post');
        //抽奖
        Route::rule('draw', 'draw', 'post');
        //订单列表
        Route::rule('order_list', 'order_list', 'post');
        //订单详情
        Route::rule('order_info', 'order_info', 'post');
        //设置收货地址
        Route::rule('set_addres', 'set_addres', 'post');
        //确认收货
        Route::rule('confirm_take', 'confirm_take', 'post');
        //分享活动
        Route::rule('share_activity', 'share_activity', 'post');
    })->prefix('interfaces/lottery.lottery_activity/');
    /*************************************** 全店风格管理 ************************************************/
    Route::group('shop_style', function () {
        // 获取当前店铺风格
        Route::rule('get', 'get', 'get');
    })->prefix('interfaces/common.shop_style/');
    /*************************************** 分销 ************************************************/
    /** 我的 **/
    Route::group('distribution_my', function () {
        // 分销商粉丝列表
        Route::rule('fans', 'fans', 'post');
        // 分销商收益详情//累积/今日收益记录
        Route::rule('earnings_details', 'earnings_details', 'post');
        // 收益主页
        Route::rule('earnings_view', 'earnings_view', 'post');
        // 代言说明
        Route::rule('explain', 'explain', 'get');
    })->prefix('interfaces/distribution.my/');
    /** 提现 **/
    Route::group('distribution_withdrawal', function () {
        // 提现主页
        Route::rule('index', 'index', 'post');
        // 提现申请
        Route::rule('to_apply', 'to_apply', 'post');
        // 提现记录
        Route::rule('record', 'record', 'post');
    })->prefix('interfaces/distribution.withdrawal/');
    /** 等级说明 **/
    Route::group('distribution_level', function () {
        // 我的等级
        Route::rule('my_level', 'my_level', 'post');
        // 升降级记录
        Route::rule('change_record', 'change_record', 'post');
    })->prefix('interfaces/distribution.level/');
    /** 成为分销商 **/
    Route::group('distribution_become', function () {
        // 提交分销商申请
        Route::rule('apply', 'apply', 'post');
        // 成为代言规则
        Route::rule('tobe_distributor_rule', 'tobe_distributor_rule', 'post|get');
        // 成为分销商表单设置
        Route::rule('distribution_form_set', 'distribution_form_set', 'get');
        // 检测用户此次支付是否含有指定成为分销商商品
        Route::rule('query_point', 'queryPoint', 'post');
        // 会员转分销商
        Route::rule('vipTurnDist', 'vipTurnDist', 'post');
    })->prefix('interfaces/distribution.become/');
    Route::group('distribution_share', function () {
        // 分享
        Route::rule('to_invite', 'toInvite', 'post');
        // 邀请你代言web
        Route::rule('to_invite_web', 'toInviteWeb', 'get');
        // 获取分销商信息
        Route::rule('get_info', 'getInfo', 'post');
        // 绑定会员和分销商关系
        Route::rule('bindDistribution', 'bindDistribution', 'post');
    })->prefix('interfaces/distribution.share/');
    /** 分销商品 **/
    Route::group('distribution_goods', function () {
        // 分销商品列表
        Route::rule('goods_list', 'goods_list', 'post');
    })->prefix('interfaces/distribution.goods/');
    /*************************************** 客服 ************************************************/
    Route::group('customer', function () {
        // 登录
        Route::rule('login', 'login', 'post');
        // 获取用户信息
        Route::rule('getUserInfo', 'getUserInfo', 'post');
        // 上传图片
        Route::rule('uploadFile', 'uploadFile', 'post');
        //拉取消息列表
        Route::rule('getChatLog', 'getChatLog', 'post');
        //拉取聊天列表
        Route::rule('getCustomerList', 'getCustomerList', 'post');
        //获得商品详情index3
        Route::rule('getGoodsInfo', 'getGoodsInfo', 'post');
        //获得店铺商品
        Route::rule('getStoreGoodsList', 'getStoreGoodsList', 'post');
        //获得店铺信息
        Route::rule('getStoreInfo', 'getStoreInfo', 'post');
        //获得商品列表
        Route::rule('getGoodsList', 'getGoodsList', 'post');
        //获得商品列表
        Route::rule('getOrderInfo', 'getOrderInfo', 'post');
        //获得店铺订单列表
        Route::rule('getStoreOrderList', 'getStoreOrderList', 'post');
        //修改订单金额
        Route::rule('updateOrderPrice', 'updateOrderPrice', 'post');
        // 获取融云token
        Route::rule('getToken', 'getToken', 'post');
        // 获取融云用户信息
        Route::rule('rongInfo', 'rongInfo', 'post');
    })->prefix('interfaces/customer.customer/');
    /*************************************** 客服 ************************************************/
    Route::group('customer_goods', function () {
        // 商品详情
        Route::rule('info', 'info', 'GET');
    })->prefix('interfaces/customer.goods/');
    /*************************************** 手机站-微信支付 ************************************************/
    Route::group('wx_mobile', function () {
        // 获取openid
        Route::rule('open_id', 'open_id', 'get');
        // 充值
        Route::rule('recharge', 'recharge', 'post');
    })->prefix('interfaces/recharge.mobile/');
    /*************************************** 发票展示 ************************************************/
    Route::group('invoice_explain', function () {
        // 发票详情
        Route::rule('detail', 'detail', 'post');
        // 发票可开具类型
        Route::rule('getRiseHistory', 'getRiseHistory', 'post');
        // 编辑未支付订单发票信息
        Route::rule('editInvoice', 'editInvoice', 'post');
    })->prefix('interfaces/invoice.Explain/');
    /*************************************** 微信公众号 ************************************************/
    Route::group('weChatPub', function () {
        Route::rule('receive', 'receive', 'get|post');
    })->prefix('interfaces/auth.WxPlat/');
    /*************************************** 授权中心 ************************************************/
    Route::group('test', function () {
        Route::rule('auth', 'auth');
        Route::rule('auth_req', 'authReq');
    })->prefix('interfaces/test.Index/');
    /*************************************** 直播 ************************************************/
    Route::group('live', function () {
        // 获取小程序直播间列表
        Route::rule('list', 'list', 'get');
    })->prefix('interfaces/live.index/');
});