<?php
/**
 * 关于pc端接口路由.
 * User: Heng
 * Date: 2019/2/21
 * Time: 14:23
 */

use think\facade\Route;

$version = 'pc2.0';
//pc默认访问路由
Route::rule('/pc', '/pc2.0/index/index');

Route::group($version, function () {
    /***************************************个人信息*************************************************/
    //    Route::group('applet_my', function () {
    //        // 判断微信是否关联
    //        Route::rule('judge_binding_wechat', 'judgeBindingWechat', 'POST');
    //        // 判断QQ是否关联
    //        Route::rule('judge_binding_qq', 'judgeBindingQq', 'POST');
    //        // 绑定微信账号
    //        Route::rule('binding_wechat', 'bindingWechat', 'POST');
    //        // 解除绑定微信账号
    //        Route::rule('relieve_binding_wechat', 'relieveBindingWechat', 'POST');
    //        // 绑定QQ账号
    //        Route::rule('binding_qq', 'bindingQq', 'POST');
    //        // 解除绑定QQ账号
    //        Route::rule('relieve_binding_qq', 'relieveBindingQq', 'POST');
    //    })->prefix('computer/applet.my/');
    /***************************************账户设置************************************************/
    Route::group('setting', function () {
        // 首页
        Route::rule('index', 'index', 'GET|POST');
        // 账户与安全
        Route::rule('safety', 'safety', 'GET|POST');
        // 修改个人信息
        Route::rule('edit', 'editInformation', 'POST');
        // 设置登录密码
        Route::rule('set_password', 'setPassword', 'GET|POST');
        // 修改登录密码
        Route::rule('update_password', 'updatePassword', 'GET|POST');
        // 修改支付密码展示
        Route::rule('edit_password', 'edit_password', 'GET|POST');
        // 设置支付密码
        Route::rule('set_pay_password', 'setPayPassword', 'GET|POST');
        // 修改支付密码
        Route::rule('update_pay_password', 'updatePayPassword', 'GET|POST');
        // 修改手机号
        Route::rule('update_phone', 'updatePhone', 'GET|POST');
        // 忘记支付密码
        Route::rule('forget_pay_password', 'forgetPayPassword', 'GET|POST');
        // 忘记支付密码
        Route::rule('relevance', 'relevance', 'GET|POST');
        // 绑定微信
        Route::rule('bind_wx', 'bind_wx', 'GET');
        // 帮助中心
        Route::rule('help_center', 'help_center', 'GET|POST');
        // 问题反馈
        Route::rule('feedback', 'feedback', 'POST');
        // 验证手机号
        //        Route::rule('check', 'check', 'POST');
        // 忘记支付密码
        //        Route::rule('forget_password', 'forgetPassword', 'POST');
        // 更新头像
        //        Route::rule('avatar', 'avatar', 'POST');
        //        // 帮助中心
        //        Route::rule('help_center', 'help_center', 'POST');
        // 客服热线
        //        Route::rule('hotline', 'hotline', 'POST');
    })->prefix('computer/auth.setting/');
    /***************************************公共图片、视频方法************************************************/
    Route::group('image', function () {
        // APP上传图片
        Route::rule('app_upload', 'app_upload', 'POST');
        // 上传视频
        Route::rule('upload_video', 'upload_video', 'POST');
    })->prefix('computer/common.image/');
    /***************************************公共物流（快递）方法************************************************/
    Route::group('express', function () {
        // 物流（快递）详情
        Route::rule('view', 'view', 'POST');
        // 物流（快递）列表
        //        Route::rule('expressList', 'expressList', 'POST');
        // dada详情
        //        Route::rule('dadaExpress', 'dadaExpress', 'POST');
    })->prefix('computer/common.express/');
    /***************************************生成订单号方法************************************************/
    //    Route::group('common_order', function () {
    //        // 订单号
    //        Route::rule('number', 'number', 'POST');
    //    })->prefix('computer/common.order/');
    /***************************************用户收货地址************************************************/
    Route::group('address', function () {
        // 收货地址列表
        Route::rule('index', 'index', 'GET');
        // 新增收货地址
        Route::rule('create', 'create', 'POST');
        // 编辑收货地址
        Route::rule('update', 'update', 'POST');
        // 设置默认收货地址
        Route::rule('default_address', 'default_address', 'POST');
        // 读取收货地址
        Route::rule('find', 'find', 'POST');
        // 删除收货地址
        Route::rule('destroy', 'destroy', 'POST');
        // 省市区街道 - 地区联动
        Route::rule('linkage', 'linkage', 'POST');
    })->prefix('computer/auth.address/');
    Route::group('login', function () {
        // 手机号登录
        Route::rule('index', 'index', 'GET|POST');
        // 短信验证码登录
        Route::rule('sms', 'sms', 'POST');
        // 微信扫码登录
        Route::rule('we_chat_login', 'we_chat_login', 'GET');
        //微信登录綁定手机号
        Route::rule('info', 'info', 'POST');
    })->prefix('computer/auth.login/');
    /***************************************商品信息************************************************/
    Route::group('goods', function () {
        // 商品列表
        Route::rule('index', 'index', 'GET|POST');
        //查询商品状态
        Route::rule('goods_status', 'goods_status', 'GET|POST');
        // 商品详情
        Route::rule('view', 'view', 'GET');
        // 商品属性获取价格和图片
        Route::rule('attr_find', 'attr_find', 'POST');
        // 收藏商品
        Route::rule('collect_goods', 'collect_goods', 'POST');
        // 收藏商品列表
        Route::rule('collect_goods_list', 'collect_goods_list', 'GET|POST');
        // 收藏商品删除
        Route::rule('collect_goods_delete', 'collect_goods_delete', 'POST');
        // 降价通知
        Route::rule('depreciate_goods', 'depreciate_goods', 'POST');
        //商品评价
        Route::rule('evaluate_list', 'evaluate_list', 'POST');
        // 好物推荐 - 精选
        Route::rule('choiceness_list', 'choiceness_list', 'GET|POST');
        // 好物推荐 - 分类推荐
        Route::rule('good_recommend_list', 'good_recommend_list', 'POST');
        // 推荐商品列表
        //        Route::rule('recommend_goods_list', 'recommend_goods_list', 'POST');
        // 门店自提
        //        Route::rule('take_list', 'take_list', 'POST');
        // 商品配送说明
        //        Route::rule('shipping_instructions', 'shipping_instructions', 'POST');
        // 商品优惠券列表
        //        Route::rule('coupon_list', 'coupon_list', 'POST');
        // 详情收藏商品删除
        //        Route::rule('view_collect_goods_delete', 'view_collect_goods_delete', 'POST');
        //商品详情看了又看
        //        Route::rule('lock_goods_list', 'lock_goods_list', 'POST');
    })->prefix('computer/goods.goods/');
    /** 评价 **/
    Route::group('evaluate', function () {
        // 发表评价
        Route::rule('report', 'report', 'GET|POST');
        // 我的评价列表
        Route::rule('myEvaluateList', 'myEvaluateList', 'GET');
        // 评价详情
        Route::rule('myEvaluateExamine', 'myEvaluateExamine', 'GET');
    })->prefix('computer/order.evaluate/');
    /***************************************搜索************************************************/
    //    Route::group('search', function () {
    //        // 发送短信通用
    //        Route::rule('hot', 'hot', 'POST');
    //    })->prefix('computer/goods.search/');
    /***************************************店铺************************************************/
    Route::group('store', function () {
        // 收藏店铺
        Route::rule('collect_store', 'collect_store', 'POST');
        // 收藏店铺列表
        Route::rule('collect_store_list', 'collect_store_list', 'GET|POST');
        // 收藏店铺删除
        Route::rule('collect_store_delete', 'collect_store_delete', 'POST');
        // 附近门店
        Route::rule('nearby_list', 'nearby_list', 'GET|POST');
        // 发现好店--头部
        Route::rule('find_store', 'find_store', 'GET|POST');
        // 店铺商品列表
        Route::rule('goods_list', 'goods_list', 'GET|POST');
        // 店铺分类列表
        Route::rule('search_list', 'search_list', 'GET|POST');
        // 首页
        Route::rule('index', 'index', 'GET|POST');
        // 店铺详情
        Route::rule('view', 'view', 'GET');
        // 详情收藏店铺删除
        //        Route::rule('view_collect_store_delete', 'view_collect_store_delete', 'POST');
        //店铺分类列表
        //        Route::rule('nearby_list_classify', 'nearby_list_classify', 'POST');
        //店铺头部
        //        Route::rule('head', 'head', 'POST');
        //店铺商品分类左侧
        //        Route::rule('classify_list', 'classify_list', 'POST');
        // 平台店铺主营分类
        //        Route::rule('platform_classify', 'platform_classify', 'POST');
        // 发现好店列表
        //        Route::rule('good_list', 'good_list', 'POST');
    })->prefix('computer/store.index/');
    /***************************************商品浏览记录************************************************/
    Route::group('record_goods', function () {
        // 商品浏览记录 - 列表
        Route::rule('index', 'index', 'GET|POST');
        // 商品浏览记录 - 清空
        Route::rule('delete', 'delete', 'POST');
    })->prefix('computer/auth.record_goods/');
    /***************************************首页附属************************************************/
    Route::group('home', function () {
        // 排行榜
        Route::rule('ranking', 'ranking', 'GET');
        // ajax商品
        Route::rule('ajax_goods_ranking', 'ajax_goods_ranking', 'POST');
        // ajax店铺
        Route::rule('ajax_shop_ranking', 'ajax_shop_ranking', 'POST');
        // 商品排行榜
        Route::rule('goods_ranking', 'goods_ranking', 'GET|POST');
        // 店铺排行榜
        Route::rule('store_ranking', 'store_ranking', 'GET|POST');
        // 热点文章列表
        Route::rule('hot_list', 'hot_list', 'GET|POST');
        // 热点文章详情
        Route::rule('hot_view', 'hot_view', 'GET|POST');
        // 收藏文章
        Route::rule('collect_article', 'collect_article', 'POST');
        // 收藏文章删除
        Route::rule('collect_article_delete', 'collect_article_delete', 'POST');
        // 收藏文章列表
        Route::rule('article_list', 'article_list', 'GET|POST');
        // 文章详情 - 收藏文章删除
        //        Route::rule('view_collect_article_delete', 'view_collect_article_delete', 'POST');
        // 品牌甄选分类列表
        //        Route::rule('brand_class_list', 'brand_class_list', 'POST');
        // 品牌甄选列表
        Route::rule('brand_list', 'brand_list', 'GET|POST');
    })->prefix('computer/home.home/');
    /** 订单建立 **/
    Route::group('order', function () {
        // 确认订单
        Route::rule('confirm', 'confirm', 'POST');
        // 选择订单支付方式
        Route::rule('pay_type', 'pay_type', 'GET');
        // 检测待支付订单有效性
        Route::rule('check_order_valid', 'check_order_valid', 'POST');
        // 微信支付
        Route::rule('we_chat_pay', 'we_chat_pay', 'GET|POST');
        // 余额支付
        Route::rule('balance_pay', 'balance_pay', 'GET|POST');
        // 微信查询订单状态
        Route::rule('check_order_status', 'check_order_status', 'GET|POST');
        // 订单支付成功展示页面
        Route::rule('pay_success', 'pay_success', 'GET');
    })->prefix('computer/order.establish/');
    /** 订单说明 **/
    Route::group('order', function () {
        // 订单列表
        Route::rule('order_list', 'orderList', 'GET');
        //申请退款页面展示
        Route::rule('apply_for_after_sale', 'apply_for_after_sale', 'GET');
        // 订单列表(售后)
        Route::rule('orderAfterSaleList', 'orderAfterSaleList', 'GET');
        // 线下订单列表
        Route::rule('orderUnderLineList', 'orderUnderLineList', 'GET|POST');
        // 订单详情[非线下]
        Route::rule('order_details', 'orderDetails', 'GET|POST');
        // 订单详情[自提订单获得二维码]
        Route::rule('get_qrCode', 'getQrCode', 'GET');
        // 订单详情[自提订单获得条形码]
        Route::rule('get_barCode', 'getBarCode', 'GET');
        // 线下订单详情
        Route::rule('orderUnderLineDetails', 'orderUnderLineDetails', 'GET|POST');
        // 退货/退款详情
        Route::rule('refundDetails', 'refundDetails', 'GET');
        // 待评价列表
        Route::rule('orderEvaluateList', 'orderEvaluateList', 'GET');
        // 订单搜索记录
        //        Route::rule('searchHistoryList', 'searchHistoryList', 'POST');
        // 删除/清空订单搜索记录
        //        Route::rule('destroySearchHistory', 'destroySearchHistory', 'POST');
        // 各商品拼团信息列表
        //        Route::rule('groupMsgList', 'groupMsgList', 'POST');
    })->prefix('computer/order.explain/');
    /*************************************** 支付管理 ************************************************/
    /** 余额支付 **/
    Route::group('balance', function () {
        // 执行支付
        Route::rule('exec', 'exec', 'POST');
    })->prefix('computer/pay.balance/');
    /** 订单操作 **/
    Route::group('order', function () {
        // 撤销退款/退货
        Route::rule('revokeApply', 'revokeApply', 'POST');
        // 订单退款(退货第一步)
        Route::rule('refundAndReturn', 'refundAndReturn', 'GET|POST');
        // 取消订单
        //        Route::rule('cancel', 'cancel', 'POST');
        // 删除订单
        //        Route::rule('destroyOrder', 'destroyOrder', 'POST');
        // 订单退货
        //        Route::rule('returnConfirmed', 'returnConfirmed', 'POST');
        // 确定收货
        //        Route::rule('confirmCollect', 'confirmCollect', 'POST');
    })->prefix('computer/order.operate/');
    /***************************************我的红包************************************************/
    Route::group('member_packet', function () {
        // 我的红包 - 列表
        Route::rule('index', 'index', 'GET|POST');
        // 我的红包 - 使用说明
        //        Route::rule('instructions', 'instructions', 'GET');
    })->prefix('computer/auth.member_packet/');
    /***************************************我的银行卡************************************************/
    Route::group('member_card', function () {
        // 我的银行卡 - 列表
        Route::rule('index', 'index', 'GET|POST');
        // 我的银行卡 - 添加
        Route::rule('add', 'add', 'GET|POST');
        
        // 我的银行卡 - 删除
        Route::rule('destroy', 'destroy', 'POST');
    })->prefix('computer/auth.member_card/');
    
    /***************************************我的优惠券************************************************/
    Route::group('member_coupon', function () {
        // 我的优惠券 - 领券
        Route::rule('get', 'get', 'POST');
        // 我的优惠券 - 换券
        Route::rule('exchange', 'exchange', 'POST');
        // 我的优惠券 - 列表
        Route::rule('index', 'index', 'GET|POST');
        // 我的优惠券 - 使用说明
        //        Route::rule('instructions', 'instructions', 'GET');
    })->prefix('computer/auth.member_coupon/');
    
    /***************************************我的************************************************/
    Route::group('my', function () {
        // 我的- 猜你喜欢
        Route::rule('recommend_list', 'recommend_list', 'GET|POST');
        // 我的- 首页
        Route::rule('index', 'index', 'GET|POST');
        // 退出登录
        Route::rule('login_out', 'login_out', 'GET|POST');
        // 获得token
        Route::rule('get_token', 'get_token', 'POST');
        // 成长值
        Route::rule('task', 'task', 'GET|POST');
        // 创建店铺
        Route::rule('create_store', 'create_store', 'GET|POST');
    })->prefix('computer/auth.my/');
    /***************************************购物车************************************************/
    Route::group('cart', function () {
        // 购物车 - 新增
        Route::rule('create', 'create', 'POST');
        // 购物车 - 列表
        Route::rule('index', 'index', 'GET');
        // 购物车 - 数量修改
        Route::rule('edit_number', 'edit_number', 'POST');
        // 购物车 - 删除
        Route::rule('delete', 'delete', 'POST');
        // 购物车 - 商品收藏
        Route::rule('collect', 'collect', 'POST');
        // 下订单
        Route::rule('confirm_order', 'confirm_order', 'POST');
        // 选择地址更新配送方式
        Route::rule('get_freight_data', 'get_freight_data', 'POST');
        // 购物车 - 编辑
        //        Route::rule('update', 'update', 'POST');
        // 购物车 - 优惠券列表
        //        Route::rule('coupon_list', 'coupon_list', 'POST');
        
        // 购物车 - 商品规格
        //        Route::rule('attr', 'attr', 'POST');
        // 购物车 - 会员购物车商品数量
        //        Route::rule('number', 'number', 'POST');
        // 立即购买 - 确认订单
        //        Route::rule('common_confirm_order', 'common_confirm_order', 'POST');
        // 非默认地址列表
        //        Route::rule('address', 'address', 'POST');
    })->prefix('computer/shopping.cart/');
    /***************************************未登录购物车************************************************/
    //    Route::group('login_cart', function () {
    //        // 未登录购物车 - 新增
    ////        Route::rule('create', 'create', 'POST');
    //        // 未登录购物车 - 列表
    ////        Route::rule('index', 'index', 'POST');
    //        // 未登录购物车 - 数量增加
    //        Route::rule('add_number', 'add_number', 'POST');
    //        // 未登录购物车 - 数量减少
    //        Route::rule('reduce_number', 'reduce_number', 'POST');
    //        // 未登录购物车 - 删除
    //        Route::rule('delete', 'delete', 'POST');
    //        // 未登录购物车 - 编辑
    //        Route::rule('update', 'update', 'POST');
    //        // 未登录购物车 - 优惠券列表
    //        Route::rule('coupon_list', 'coupon_list', 'POST');
    //        // 未登录购物车 - 商品收藏
    //        Route::rule('collect', 'collect', 'POST');
    //        // 未登录购物车 - 商品规格
    //        Route::rule('attr', 'attr', 'POST');
    //        // 未登录购物车 - 会员购物车商品数量
    //        Route::rule('number', 'number', 'POST');
    //    })->prefix('computer/shopping.login_cart/');
    /***************************************合并购物车************************************************/
    Route::group('merge_cart', function () {
        // 合并购物车
        Route::rule('combine', 'combine', 'POST');
    })->prefix('computer/shopping.merge_cart/');
    /***************************************商品分类************************************************/
    //    Route::group('goods_classify', function () {
    //        // 发送短信通用
    //        Route::rule('parent', 'parent', 'POST');
    //        Route::rule('subordinate', 'subordinate', 'POST');
    //    })->prefix('computer/goods.classify/');
    /***************************************消息方式************************************************/
    Route::group('message', function () {
        // 消息列表
        Route::rule('index', 'index', 'GET');
        // 优惠信息查看
        Route::rule('discounts_examine', 'discounts_examine', 'GET');
    })->prefix('computer/home.message/');
    /***************************************积分商城************************************************/
    Route::group('integral', function () {
        // 详情
        Route::rule('view', 'view', 'GET|POST');
        // 签到
        Route::rule('sign', 'sign', 'POST');
        // 首页
        Route::rule('index', 'index', 'GET|POST');
        // 兑换展示
        Route::rule('conversion', 'conversion', 'GET');
        // 兑换商品
        Route::rule('redemption', 'redemption', 'POST');
        // 兑换记录
        Route::rule('conversion_record', 'conversion_record', 'GET');
        // 兑换记录详情
        Route::rule('conversion_view', 'conversion_view', 'GET');
        // 确认收货
        Route::rule('confirm_receipt', 'confirm_receipt', 'POST');
        // 我的积分
        Route::rule('my', 'my', 'GET|POST');
        // 明细
        Route::rule('detail', 'detail', 'GET|POST');
        // 任务
        //        Route::rule('task', 'task', 'POST');
        // 我的积分-底部商品
        //        Route::rule('underneath_goods', 'underneath_goods', 'POST');
        // 分类列表
        //        Route::rule('classify', 'classify', 'POST');
        // 我的积分-换购
        //        Route::rule('exchange', 'exchange', 'POST');
        // 积分兑换订单详情
        //        Route::rule('integral_order_details', 'integral_order_details', 'GET');
        // 删除兑换记录
        //        Route::rule('conversion_record_delete', 'conversion_record_delete', 'POST');
        // 兑换商品+金额
        //        Route::rule('redemption_money', 'redemption_money', 'POST');
        // 商品列表
        //        Route::rule('goods', 'goods', 'POST');
    })->prefix('computer/auth.integral/');
    /***************************************用户认证************************************************/
    Route::group('rank', function () {
        // 列表
        Route::rule('index', 'index', 'GET|POST');
    })->prefix('computer/auth.rank/');
    /*************************************** 分销 ************************************************/
    /** 分销商品 **/
    Route::group('distribution_goods', function () {
        // 分销商品列表
        Route::rule('goods_list', 'goods_list', 'post|GET');
    })->prefix('computer/distribution.goods/');
    /** 成为分销商 **/
    Route::group('distribution_become', function () {
        // 提交分销商申请
        Route::rule('apply', 'apply', 'post');
        // 成为代言规则
        Route::rule('tobe_distributor_rule', 'tobe_distributor_rule', 'GET');
        // 成为分销商表单设置
        Route::rule('distribution_form_set', 'distribution_form_set', 'GET');
        // 检测用户此次支付是否含有指定成为分销商商品
        //        Route::rule('query_point', 'queryPoint', 'post');
        // 会员转分销商
        //        Route::rule('vipTurnDist', 'vipTurnDist', 'post');
        // 申请成功跳页
        Route::rule('apply_success', 'apply_success', 'get');
    })->prefix('computer/distribution.become/');
    /** 提现 **/
    Route::group('distribution_withdrawal', function () {
        // 提现主页
        Route::rule('index', 'index', 'get|post');
        // 提现申请
        Route::rule('to_apply', 'to_apply', 'post');
        // 提现成功
        Route::rule('success1', 'success1', 'get|post');
        // 提现记录
        Route::rule('record', 'record', 'get|post');
    })->prefix('computer/distribution.withdrawal/');
    /** 等级说明 **/
    Route::group('distribution_level', function () {
        // 我的等级
        Route::rule('my_level', 'my_level', 'get|post');
        // 升降级记录
        Route::rule('change_record', 'change_record', 'get|post');
    })->prefix('computer/distribution.level/');
    Route::group('distribution_share', function () {
        // 分享
        Route::rule('to_invite', 'toInvite', 'get|post');
        // 邀请你代言web
        //        Route::rule('to_invite_web', 'toInviteWeb', 'POST');
        // 获取分销商信息
        //        Route::rule('get_info', 'getInfo', 'post');
        // 绑定会员和分销商关系
        //        Route::rule('bindDistribution', 'bindDistribution', 'post');
    })->prefix('computer/distribution.share/');
    /** 我的 **/
    Route::group('distribution_my', function () {
        // 分销商粉丝列表
        Route::rule('fans', 'fans', 'get|post');
        // 分销商收益详情//累积/今日收益记录
        Route::rule('earnings_details', 'earnings_details', 'get|post');
        // 粉丝收益详情//累积/今日收益记录
        Route::rule('fans_earnings_details', 'fans_earnings_details', 'get|post');
        // 收益主页
        Route::rule('earnings_view', 'earnings_view', 'GET');
        // ajax
        Route::rule('ajax_earnings', 'ajax_earnings', 'post');
        // 获取最近七天收益数据
        Route::rule('seven_data', 'seven_data', 'post');
        // 代言说明
        Route::rule('explain', 'explain', 'get|post');
    })->prefix('computer/distribution.my/');
    /***************************************优惠券************************************************/
    Route::group('coupon', function () {
        // 优惠券 - 领券
        Route::rule('get', 'get', 'get|POST');
        // 优惠券 - 换券share
        Route::rule('exchange', 'exchange', 'GET|POST');
        // 领券中心 - 换券 - 立即兑换详情
        //        Route::rule('exchange_view', 'exchange_view', 'POST');
        // 领券中心 - 换券 - 促销列表
        //        Route::rule('goods_list', 'goods_list', 'POST');
    })->prefix('computer/auth.coupon/');
    /***************************************公共图片、视频方法************************************************/
    Route::group('customer', function () {
        // APP上传图片
        Route::rule('customer_index', 'customer_index', 'GET');
        // 获得店铺信息
        Route::rule('get_store_info', 'get_store_info', 'GET|POST');
        // 获得店铺订单信息
        Route::rule('get_store_order_list', 'get_store_order_list', 'GET|POST');
        // 获得聊天记录
        Route::rule('get_chat_log', 'get_chat_log', 'POST');
        // 获得商品信息
        Route::rule('get_goods_info', 'get_goods_info', 'POST');
        // 获得历史浏览记录商品信息
        Route::rule('goods_browse_log', 'goods_browse_log', 'POST');
        // 获得店铺推荐商品信息
        Route::rule('store_recommend_goods', 'store_recommend_goods', 'POST');
    })->prefix('computer/store.customer/');
    /*************************************** 分销 ************************************************/
    //    /** 分销商品 **/
    //    Route::group('distribution_goods', function () {
    //        // 分销商品列表
    //        Route::rule('goods_list', 'goods_list', 'post');
    //    })->prefix('computer/distribution.goods/');
    //    /** 成为分销商 **/
    //    Route::group('distribution_become', function () {
    //        // 提交分销商申请
    //        Route::rule('apply', 'apply', 'post');
    //        // 成为代言规则
    //        Route::rule('tobe_distributor_rule', 'tobe_distributor_rule', 'get');
    //        // 成为分销商表单设置
    //        Route::rule('distribution_form_set', 'distribution_form_set', 'get');
    //        // 检测用户此次支付是否含有指定成为分销商商品
    ////        Route::rule('query_point', 'queryPoint', 'post');
    //        // 会员转分销商
    ////        Route::rule('vipTurnDist', 'vipTurnDist', 'post');
    //    })->prefix('computer/distribution.become/');
    //    /** 等级说明 **/
    //    Route::group('distribution_level', function () {
    //        // 我的等级
    //        Route::rule('my_level', 'my_level', 'post');
    //        // 升降级记录
    //        Route::rule('change_record', 'change_record', 'post');
    //    })->prefix('computer/distribution.level/');
    //    Route::group('distribution_share', function () {
    //        // 分享
    //        Route::rule('to_invite', 'toInvite', 'post');
    //        // 邀请你代言web
    ////        Route::rule('to_invite_web', 'toInviteWeb', 'POST');
    //        // 获取分销商信息
    ////        Route::rule('get_info', 'getInfo', 'post');
    //        // 绑定会员和分销商关系
    ////        Route::rule('bindDistribution', 'bindDistribution', 'post');
    //    })->prefix('computer/distribution.share/');
    //    /** 我的 **/
    //    Route::group('distribution_my', function () {
    //        // 分销商粉丝列表
    //        Route::rule('fans', 'fans', 'post');
    //        // 分销商收益详情//累积/今日收益记录
    //        Route::rule('earnings_details', 'earnings_details', 'post');
    //        // 粉丝收益详情//累积/今日收益记录
    //        Route::rule('fans_earnings_details', 'fans_earnings_details', 'get|post');
    //        // 收益主页
    //        Route::rule('earnings_view', 'earnings_view', 'post');
    //        // ajax
    //        Route::rule('ajax_earnings', 'ajax_earnings', 'post');
    //        // 获取最近七天收益数据
    //        Route::rule('seven_data', 'seven_data', 'post');
    //        // 代言说明
    //        Route::rule('explain', 'explain', 'get');
    //    })->prefix('computer/distribution.my/');
    /***************************************分享************************************************/
    Route::group('share', function () {
        // logo
        //        Route::rule('logo', 'logo', 'GET');
        // 平台执照信息
        //        Route::rule('license', 'license', 'POST');
    })->prefix('computer/common.share/');
    /*************************************** web页面管理 ************************************************/
    Route::group('html', function () {
        // 商品详情web
        //        Route::rule('goods_view', 'goods_view', 'GET');
        // 其他文章详情web
        Route::rule('article_view', 'article_view', 'GET|POST');
        //协议
        Route::rule('ajax_article_view', 'ajax_article_view', 'POST');
        // 抽奖活动规则
        //        Route::rule('draw_activity_view', 'draw_activity_view', 'GET');
        // 积分文章详情web
        //        Route::rule('integral_view', 'integral_view', 'GET');
    })->prefix('computer/common.html/');
    /*************************************** 抽奖活动管理 ************************************************/
    Route::group('lottery_activity', function () {
        //抽奖首页
        Route::rule('activity_goods_list', 'activity_goods_list', 'GET');
        //抽奖
        Route::rule('draw', 'draw', 'POST');
        //设置收货地址
        Route::rule('set_address', 'set_address', 'POST');
        //订单列表
        Route::rule('order_list', 'order_list', 'GET|POST');
        //订单详情
        //        Route::rule('order_info', 'order_info', 'POST');
        //确认收货
        Route::rule('confirm_take', 'confirm_take', 'POST');
        
    })->prefix('computer/lottery.lottery_activity/');
    /***************************************充值************************************************/
    Route::group('recharge', function () {
        // 充值 - 列表
        Route::rule('index', 'index', 'GET|POST');
        // 微信支付
        //        Route::rule('underway', 'underway', 'GET|POST');
        // 支付宝生成数据
        Route::rule('generate_order', 'generate_order', 'POST');
        // 充值 - 成功
        Route::rule('successful', 'successful', 'GET|POST');
        // 账户余额记录 - 列表
        Route::rule('balance_record', 'balance_record', 'GET|POST');
    })->prefix('computer/auth.recharge/');
    /***************************************砍价列表************************************************/
    Route::group('bargain', function () {
        // 砍价 - 列表
        Route::rule('index', 'index', 'GET|POST');
        // 砍价 - 立即砍价
        //        Route::rule('immediately', 'immediately', 'POST');
        // 砍价 - 二维码
        //        Route::rule('qr_code', 'qr_code', 'POST');
        // 砍价 - 我的砍价
        Route::rule('my_cut', 'my_cut', 'GET|POST');
        // 砍价 - 我的砍价详情
        Route::rule('my_cut_view', 'my_cut_view', 'GET|POST');
    })->prefix('computer/goods.bargain/');
    /***************************************短信************************************************/
    Route::group('sms', function () {
        // 发送短信通用
        Route::rule('send', 'send', 'POST');
        // 验证码合法性检测(单接口)
        Route::rule('checkCodeInvalid', 'checkCodeInvalid', 'POST');
    })->prefix('computer/auth.sms/');
    /***************************************拼团列表************************************************/
    Route::group('group', function () {
        // 拼团 - 二维码
        //        Route::rule('qr_code', 'qr_code', 'POST');
        // 拼团 - 列表
        Route::rule('index', 'index', 'GET|POST');
        // 拼团 - 详情
        Route::rule('view', 'view', 'GET|POST');
        // 拼团 - 我的拼团
        Route::rule('my_index', 'my_index', 'GET|POST');
    })->prefix('computer/goods.group/');
    /***************************************微信支付************************************************/
    Route::group('WeChat', function () {
        // 二维码
        Route::rule('payment', 'payment', 'GET|POST');
    })->prefix('computer/pay.WeChat/');
    /*************************************** 注册************************************************/
    Route::group('register', function () {
        //手机号注册1
        Route::rule('one', 'one', 'GET');
        //手机号注册
        Route::rule('tel', 'tel', 'GET|POST');
        //手机号注册3
        Route::rule('three', 'three', 'GET');
        // 大礼包列表
        Route::rule('coupon_list', 'coupon_list', 'GET|POST');
        // 领取大礼包
        Route::rule('get_coupon', 'get_coupon', 'POST');
    })->prefix('computer/auth.register/');
    
    /*************************************** 忘记密码************************************************/
    Route::group('forget', function () {
        //忘记密码1
        Route::rule('one', 'one', 'GET');
        //忘记密码
        Route::rule('two', 'two', 'GET');
        //忘记密码3
        Route::rule('three', 'three', 'GET|POST');
        //检查手机号
        Route::rule('check', 'check', 'POST');
        // 验证码合法性检测(单接口)
        Route::rule('checkCodeInvalid', 'checkCodeInvalid', 'POST');
    })->prefix('computer/auth.forget/');
    /***************************************支付宝支付************************************************/
    Route::group('ali_pay', function () {
        // 二维码
        Route::rule('pay', 'pay', 'GET|POST');
    })->prefix('computer/pay.AliPay/');
    /***************************************首页************************************************/
    Route::group('index', function () {
        // 首页
        Route::rule('index', 'index', 'GET');
        // 当前限时抢购列表(用于局部刷新接口)
        Route::rule('curLimitList', 'curLimitList', 'POST');
        // 排行榜列表(用于局部刷新接口)
        Route::rule('AjaxRankingList', 'AjaxRankingList', 'POST');
        // 拼团列表(用于局部刷新接口)
        Route::rule('AjaxGetGroupGoods', 'AjaxGetGroupGoods', 'POST');
        // 优惠券列表(用于局部刷新接口)
        Route::rule('AjaxGetCoupon', 'AjaxGetCoupon', 'POST');
        // 測試頁面
        //        Route::rule('test', 'test', 'GET|POST');
    })->prefix('computer/home.index/');
    /*************************************** 发票展示 ************************************************/
    Route::group('invoice_explain', function () {
        // 发票详情
        Route::rule('detail', 'detail', 'post');
        // 发票可开具类型
        Route::rule('getRiseHistory', 'getRiseHistory', 'post');
        // 编辑未支付订单发票信息
        Route::rule('editInvoice', 'editInvoice', 'post');
    })->prefix('computer/invoice.Explain/');
    /***************************************限时抢购************************************************/
    Route::group('time_limit', function () {
        // 限时抢购 - 列表
        Route::rule('index', 'index', 'GET');
    })->prefix('computer/goods.time_limit/');
    //如果未命中路由则跳转到首页
    Route::miss('/pc2.0/index/index');
})->middleware(function ($request, \Closure $next) {
    //如果当前请求页面不是首页  检测功能开关对应屏蔽
    if ($request->url() != '/pc2.0/index/index') {
        //开关关闭禁止访问页面
        $_rount_detection_where = [
            //单店开关
            'ONE_OR_MORE' => [
                '/pc2.0/my/create_store',//店铺入驻
                '/pc2.0/store/nearby_list',//附近商家
                '/pc2.0/store/find_store',//发现好店
                '/pc2.0/home/store_ranking',//排行榜
            ],
            //限时抢购
            'IS_LIMIT' => [
                '/pc2.0/time_limit/index'//限时抢购
            ],
            //优惠券
            'IS_COUPON' => [
                '/pc2.0/coupon/get',//领券中心
                '/pc2.0/coupon/exchange',//换券中心
                '/pc2.0/member_coupon/index',//我的优惠券
            ],
            //红包
            'IS_RED_PACKET' => [
                '/pc2.0/member_packet/index',//我的红包
            ],
            //拼团
            'IS_GROUP' => [
                '/pc2.0/group/index',//拼团商品列表
                '/pc2.0/group/my_index',//我的拼团
            ],
            //砍价
            'IS_CUT' => [
                '/pc2.0/bargain/index',//砍价商品列表
                '/pc2.0/bargain/my_cut',//我的砍价
            ],
            //充值
            'IS_RECHARGE' => [
                '/pc2.0/recharge/index',//充值
            ],
            //排行榜
            'IS_RANKING' => [
                '/pc2.0/home/ranking',//排行榜
                '/pc2.0/home/goods_ranking',//商品排行榜
                '/pc2.0/home/store_ranking',//
            ],
            //品牌甄选
            'IS_BRAND' => [
                '/pc2.0/home/brand_list',//品牌甄选
            ],
            //好物推荐
            'IS_GOODS_RECOMMEND' => [
                '/pc2.0/goods/choiceness_list',//好物推荐
            ],
            //分销
            'DISTRIBUTION_STATUS' => [
                '/pc2.0/distribution_my/earnings_view',//
                '/pc2.0/distribution_my/earnings_details',//
                '/pc2.0/distribution_my/fans',//
                '/pc2.0/distribution_my/fans_earnings_details',//
                '/pc2.0/distribution_my/explain',//
                '/pc2.0/distribution_share/to_invite',//
                '/pc2.0/distribution_level/my_level',//
                '/pc2.0/distribution_level/change_record',//
                '/pc2.0/distribution_goods/goods_list',//
                '/pc2.0/distribution_become/distribution_form_set',//
                '/pc2.0/distribution_become/tobe_distributor_rule',//
            ],
        ];
        //如果当前请求url存在检测条件中
        preg_match('/.*\/\w*/', $request->baseUrl(), $_this_request_url);
        foreach ($_rount_detection_where as $_route_key => $_route_value) {
            if (array_key_exists(($_this_request_url[0] ?? ''), array_flip($_route_value))) {
                //INI_CONFIG  INI_DISTRIBUTION  开关条件  在  app\\common\\Behavior  中赋值
                $route_function_status = array_merge(INI_CONFIG, INI_DISTRIBUTION);
                //单店多店开关
                $route_function_status['ONE_OR_MORE'] = config('user.one_more') === 0 ? 0 : 1;
                if ($route_function_status[$_route_key] == 0) {
                    if ($request->isAjax()) {
                        return response(['code' => -403, 'message' => '该功能暂未开放', 'url' => '/pc2.0/index/index'], '200', [], 'json');
                    } else {
                        return redirect('/pc2.0/index/index');
                    }
                }
            }
        }
    }
    return $next($request);
});