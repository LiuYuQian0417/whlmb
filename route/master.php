<?php

use think\facade\Route;

// 获取 user.php 的配置
$_config = require dirname(__FILE__) . "/../config/link.php";

Route::rule('/' . $_config['master_suffix'], "master/login/index");
/* 首页 */
Route::group(
    'home',
    function () {
        // 首页
        Route::rule('index', 'index', 'POST|GET');
        // 店铺支付排行
        Route::rule('storePay', 'storePay', 'POST|GET');
        // 商品支付排行
        Route::rule('goodsPay', 'goodsPay', 'POST|GET');
        // 商品数量排行
        Route::rule('goodsCountPay', 'goodsCountPay', 'POST|GET');
        // 业务流程
        Route::rule('business_process', 'business_process', 'POST|GET');
        // 新手引导
        Route::rule('novice_guide', 'novice_guide', 'POST|GET');
    }
)->prefix('master/Home/');
/* 平台 */
Route::group(
    'desk',
    function () {
        // 平台概况
        Route::rule('index', 'index', 'POST|GET');
    }
)->prefix('master/Desk/');
/* 登录 */
Route::group(
    'login',
    function () {
        // 登录页
        Route::rule('index', 'index', 'POST|GET');
        // 退出登录
        Route::rule('outLogin', 'outLogin', "POST");
    }
)->prefix('master/Login/');
/* 主页 */
Route::group(
    'index',
    function () {
        // 父页面框
        Route::rule('index', 'index', 'GET');
    }
)->prefix('master/Index/');
/* 权限 */
Route::group(
    'manage',
    function () {
        // 管理员列表
        Route::rule('index', 'index', "GET|POST");
        // 添加管理员
        Route::rule('create', 'create', 'GET|POST');
        // 编辑管理员
        Route::rule('edit', 'edit', 'GET|POST');
        // 删除管理员
        Route::rule('destroy', 'destroy', 'POST');
        // 改变管理员状态
        Route::rule('changeStatus', 'changeStatus', 'POST');
    }
)->prefix('master/Manage/');
/* 权限组 */
Route::group(
    'auth_group',
    function () {
        // 权限组列表
        Route::rule('index', 'index', 'GET|POST');
        // 添加管理员
        Route::rule('create', 'create', 'GET|POST');
        // 编辑管理员
        Route::rule('edit', 'edit', 'GET|POST');
        // 删除权限组
        Route::rule('destroy', 'destroy', 'POST');
        // 改变权限组状态
        Route::rule('changeStatus', 'changeStatus', 'POST');
        // 查看&编辑用户组权限
        Route::rule('authEdit', 'authEdit', 'GET|POST');
        // 获取权限数据
        Route::rule('getAuthData', 'getAuthData', 'POST');
        // 保存权限数据
        Route::rule('saveAuthData', 'saveAuthData', 'POST');
    }
)->prefix('master/AuthGroup/');
/* 管理员日志 */
Route::group(
    'manage_log',
    function () {
        // 日志列表
        Route::rule('index', 'index', 'GET');
        // 删除日志
        Route::rule('destroy', 'destroy', 'POST');
    }
)->prefix('master/ManageLog/');
/***************************************平台设置管理************************************************/
// 系统设置
Route::group(
    'config',
    function () {
        // 列表
        Route::rule('index', 'index', "GET");
        // 保存设置
        Route::rule('saveConfig', 'saveConfig', "POST");
        // 保存设置
        Route::rule('function_status', 'function_status', "POST");
        // 证照信息
        Route::rule('license', 'license', "GET|POST");
        // 展示内容
        Route::rule('function_index', 'function_index', "GET|POST");
        // 版本设置
        Route::rule('versions', 'versions', "GET|POST");
        // 热搜管理
        Route::rule('hot_search', 'hot_search', "GET|POST");
    }
)->prefix('master/Config/');
//首页图标设置
Route::group(
    'icon',
    function () {
        Route::rule('index_img', 'index_img', "GET");
        //添加图标
        Route::rule('img_create', 'img_create', "GET|POST");
        //编辑图标
        Route::rule('img_edit', 'img_edit', "GET|POST");
        //图标显示状态
        Route::rule('img_is_show', 'img_is_show', "POST");
        //删除图标
        Route::rule('img_delete', 'img_delete', "POST");
        //更新排序
        Route::rule('store_update', 'store_update', "POST");

    }
)->prefix('master/Icon/');
// 支付设置
Route::group(
    'payment',
    function () {
        // 支付列表
        Route::rule('index', 'index', "GET");
        // 安装
        Route::rule('create', 'create', "GET|POST");
        // 编辑
        Route::rule('edit', 'edit', "GET|POST");
        // 卸载
        Route::rule('unload', 'unload', "GET|POST");
        // 文本修改
        Route::rule('text_update', 'text_update', "POST");
    }
)->prefix('master/Payment/');
// 友情链接
Route::group(
    'friendship',
    function () {
        // 友情链接列表
        Route::rule('index', 'index', "GET");
        // 添加友情链接
        Route::rule('create', 'create', "GET|POST");
        // 编辑友情链接
        Route::rule('edit', 'edit', "GET|POST");
        // 删除友情链接
        Route::rule('destroy', 'destroy', "GET|POST");
    }
)->prefix('master/Friendship/');
// 充值提现申请
Route::group(
    'consumption',
    function () {
        // 充值提现申请列表
        Route::rule('index', 'index', "GET|POST");
        // 添加充值提现申请
        Route::rule('create', 'create', "GET|POST");
        // 审核提现申请
        Route::rule('edit', 'edit', "GET|POST");
    }
)->prefix('master/Consumption/');
// 打印
Route::group(
    'print_settings',
    function () {
        Route::rule('index', 'index', "GET");
    }
)->prefix('master/PrintSettings/');
// 网站地图
Route::group(
    'site_map',
    function () {
        Route::rule('index', 'index', "GET");
        Route::rule('create', 'create', "GET");
    }
)->prefix('master/SiteMap/');
// 消息
Route::group(
    'message',
    function () {
        // 列表
        Route::rule('index', 'index', "GET|POST");
        // 添加
        Route::rule('create', 'create', "GET|POST");
        // 编辑
        Route::rule('edit', 'edit', "GET|POST");
        // 删除
        Route::rule('destroy', 'destroy', "GET|POST");
    }
)->prefix('master/Message/');
/***************************************电子书管理************************************************/
Route::group(
    'book',
    function () {
        // 电子书列表
        Route::rule('index', 'index', "GET|POST");
        // 添加
        Route::rule('create', 'create', "GET|POST");
        // 编辑
        Route::rule('edit', 'edit', "GET|POST");
        // 删除
        Route::rule('destroy', 'destroy', "GET|POST");
        // 上下架
        Route::rule('auditing', 'auditing', "GET|POST");
        //章节列表
        Route::rule('viewlist', 'viewlist', "GET|POST");
        //章节添加
        Route::rule('viewcreate', 'viewcreate', "GET|POST");
        //章节编辑
        Route::rule('viewedit', 'viewedit', "GET|POST");
        //章节删除
        Route::rule('viewdestroy', 'viewdestroy', "GET|POST");
        //章节状态更新
        Route::rule('viewauditing', 'viewauditing', "GET|POST");
        // 富文本编辑器展示页
        Route::rule('uEditor', 'uEditor', 'GET');
    }
)->prefix('master/Book/');
/***************************************商品分类管理************************************************/
Route::group('goods_classify', function () {
    // 商品分类列表
    Route::rule('index', 'index', "GET|POST");
    // 商品分类创建
    Route::rule('create', 'create', "GET|POST");
    // 商品分类编辑
    Route::rule('edit', 'edit', "GET|POST");
    // 商品分类删除
    Route::rule('destroy', 'destroy', "GET|POST");
    // 商品分类状态
    Route::rule('auditing', 'auditing', "POST");
    // 商品文本修改
    Route::rule('text_update', 'text_update', "POST");
    // 商品首页广告搜索
    Route::rule('get_adv', 'get_adv', "POST");
    // 商品分类首页广告搜索
    Route::rule('get_classify_adv', 'get_classify_adv', "POST");
    // 添加广告
    Route::rule('adv_create', 'adv_create', "GET|POST");
    // 快捷添加分类
    Route::rule('fast_create', 'fastCreate', "GET|POST");
    // 快捷添加分类-商品
    Route::rule('fast_create_goods', 'fastCreateGoods', "GET|POST");
    // 添加二三级分类
    Route::rule('create_child', 'create_child', "GET|POST");
    // 编辑二三级分类
    Route::rule('edit_child', 'edit_child', "GET|POST");
})->prefix('master/GoodsClassify/');
/***************************************用户评论管理************************************************/
Route::group(
    'goods_evaluate',
    function () {
        // 用户评论列表
        Route::rule('index', 'index', "GET|POST");
        // 用户评论查看
        Route::rule('edit', 'edit', "GET|POST");
        // 用户评论删除
        Route::rule('destroy', 'destroy', "GET|POST");
        // 改变评论显示
        Route::rule('auditing', 'auditing', "GET|POST");
    }
)->prefix('master/GoodsEvaluate/');
/***************************************品牌分类管理************************************************/
Route::group(
    'brand_classify',
    function () {
        // 品牌分类列表
        Route::rule('index', 'index', "GET|POST");
        // 创建品牌分类
        Route::rule('create', 'create', "GET|POST");
        // 编辑品牌分类
        Route::rule('edit', 'edit', "GET|POST");
        // 删除品牌分类
        Route::rule('destroy', 'destroy', "GET|POST");
        // 品牌分类更改审核
        Route::rule('auditing', 'auditing', "GET|POST");
        // 品牌分类排序
        Route::rule('text_update', 'text_update', "GET|POST");
    }
)->prefix('master/BrandClassify/');
/***************************************品牌管理************************************************/
Route::group(
    'brand',
    function () {
        // 品牌列表
        Route::rule('index', 'index', "GET|POST");
        // 创建品牌
        Route::rule('create', 'create', "GET|POST");
        // 设置品牌状态
        Route::rule('auditing', 'auditing', "GET|POST");
        // 编辑品牌
        Route::rule('edit', 'edit', "GET|POST");
        // 删除品牌
        Route::rule('destroy', 'destroy', "GET|POST");
        // 更新排序
        Route::rule('text_update', 'text_update', "GET|POST");
    }
)->prefix('master/Brand/');
/***************************************商品类型管理************************************************/
Route::group(
    'attr_type',
    function () {
        // 类型列表
        Route::rule('index', 'index', "GET|POST");
        // 删除类型
        Route::rule('destroy', 'destroy', "GET|POST");
        // 品牌分类更改审核
        Route::rule('auditing', 'auditing', "GET|POST");
        // 编辑类型
        Route::rule('edit', 'edit', "GET|POST");
        // 添加类型
        Route::rule('create', 'create', 'GET|POST');
    }
)->prefix('master/AttrType/');
/***************************************商品属性管理************************************************/
Route::group(
    'attr',
    function () {
        // 类型列表
        Route::rule('index', 'index', "GET|POST");
        // 删除类型
        Route::rule('destroy', 'destroy', "GET|POST");
        // 品牌分类更改审核
        Route::rule('auditing', 'auditing', "GET|POST");
        // 编辑类型
        Route::rule('edit', 'edit', "GET|POST");
        // 添加类型
        Route::rule('create', 'create', 'GET|POST');
    }
)->prefix('master/Attr/');
/***************************************降价通知管理************************************************/
Route::group(
    'goods_reduction_notic',
    function () {
        // 通知列表
        Route::rule('index', 'index', "GET|POST");
        //通知日志
        Route::rule('log', 'log', 'GET|POST');
        // 删除通知
        Route::rule('destroy', 'destroy', "GET|POST");
        // 删除通知日志
        Route::rule('logdel', 'logdel', "GET|POST");
        // 发送记录
        Route::rule('details', 'details', "GET|POST");
    }
)->prefix('master/GoodsReductionNotic/');

/***************************************商品批量修改管理************************************************/
Route::group(
    'goods_batch_operation',
    function () {
        // 商品批量操作列表
        Route::rule('index', 'index', "GET|POST");
        //商品批量修改获取商品
        Route::rule('get_goods', 'get_goods', "GET|POST");
        //选择商品后的选择
        Route::rule('goods_list', 'goods_list', "GET|POST");
    }
)->prefix('master/GoodsBatchOperation/');
/***************************************商品批量导入管理************************************************/
Route::group(
    'goods_import',
    function () {
        // 商品批量导入操作列表
        Route::rule('index', 'index', "GET|POST");
        Route::rule('createcsv', 'createcsv', "GET|POST");
        Route::rule('create', 'create', "GET|POST");
    }
)->prefix('master/goodsImport/');
/***************************************商品批量导出管理************************************************/
Route::group(
    'goods_export',
    function () {
        // 商品批量导入操作列表
        Route::rule('index', 'index', "GET|POST");
        Route::rule('create', 'create', "GET|POST");
    }
)->prefix('master/goodsExport/');
/***************************************店铺等级管理************************************************/
Route::group(
    'store_grade',
    function () {
        // 店铺等级列表
        Route::rule('index', 'index', "GET|POST");
        // 店铺等级创建
        Route::rule('create', 'create', "GET|POST");
        // 店铺等级编辑
        Route::rule('edit', 'edit', "GET|POST");
        // 店铺等级删除
        Route::rule('destroy', 'destroy', "GET|POST");
        // 店铺等级状态
        Route::rule('auditing', 'auditing', "POST");
        // 店铺等级文本修改
        Route::rule('text_update', 'text_update', "POST");
        // 店铺分配等级
        Route::rule('jurisdiction', 'jurisdiction', "GET|POST");
    }
)->prefix('master/storeGrade/');
/***************************************店铺管理************************************************/
Route::group(
    'store',
    function () {
        // 店铺概况
        Route::rule('general', 'general', "GET|POST");
        // 店铺审核
        Route::rule('checked', 'checked', "GET|POST");
        // 费用设置
        Route::rule('costs_set', 'costs_set', "GET|POST");
        // 店铺列表
        Route::rule('index', 'index', "GET|POST");
        // 店铺创建
        Route::rule('create', 'create', "GET|POST");
        // 店铺创建选择会员信息
        Route::rule('member_search', 'member_search', "GET");
        // 店铺编辑
        Route::rule('edit', 'edit', "GET|POST");
        // 店铺删除
        Route::rule('destroy', 'destroy', "GET|POST");
        // 店铺状态
        Route::rule('auditing', 'auditing', "POST");
        // 店铺文本修改
        Route::rule('text_update', 'text_update', "POST");
        // 联系我们
        Route::rule('contact', 'contact', "GET|POST");
        // 图片信息
        Route::rule('images', 'images', "GET|POST");
        // 认证信息
        Route::rule('storeAuth', 'storeAuth', "GET|POST");
        // 店铺设置
        Route::rule('setting', 'setting', "GET|POST");
        // 店铺装修
        Route::rule('fitment', 'fitment', "GET|POST");
        // 启用店铺
        Route::rule('open_store', 'open_store', "GET|POST");
        // 审核入驻
        Route::rule('is_checked', 'is_checked', "GET|POST");
        // 入驻信息
        Route::rule('checked_info', 'checked_info', "GET|POST");
        // 添加费用信息
        Route::rule('add_costs', 'add_costs', "GET|POST");
        // 删除费用信息
        Route::rule('subtract_costs', 'subtract_costs', "POST");
        // 编辑费用信息
        Route::rule('edit_costs', 'edit_costs', "POST");
        // 店铺信息
        Route::rule('audit_store_info', 'auditStoreInfo', "GET");
        // 店铺认证信息
        Route::rule('audit_auth_info', 'auditAuthInfo', "GET|POST");
        // 店铺类型转换
        Route::rule('type_change', 'type_change', "GET|POST");
    }
)->prefix('master/Store/');
/***************************************店铺活动分类************************************************/
Route::group(
    'store_article',
    function () {
        // 店铺动态类表
        Route::rule('index', 'index', "GET|POST");
        // 添加店铺动态
        Route::rule('create', 'create', "GET|POST");
        // 创建店铺动态OLD
        Route::rule('create_old', 'createOld', "GET|POST");
        //编辑店铺动态
        Route::rule('edit', 'edit', "GET|POST");
        // 删除店铺动态
        Route::rule('destroy', 'destroy', "GET|POST");
        // 筛选商品
        Route::rule('select_goods', 'selectGoods', "GET|POST");
    }
)->prefix('master/StoreArticle/');
/***************************************店铺商品分类************************************************/
Route::group(
    'store_goods_classify',
    function () {
        // 店铺商品分类
        Route::rule('index', 'index', "GET|POST");
        // 创建店铺商品分类
        Route::rule('create', 'create', "GET|POST");
        // 编辑店铺商品分类
        Route::rule('edit', 'edit', "GET|POST");
        // 店铺商品分类状态
        Route::rule('auditing', 'auditing', "GET|POST");
        // 删除店铺商品分类
        Route::rule('destroy', 'destroy', "GET|POST");
    }
)->prefix('master/StoreGoodsClassify/');
/***************************************店铺运费模板************************************************/
Route::group(
    'store_business_express',
    function () {
        // 运费模板列表
        Route::rule('index', 'index', "GET|POST");
        // 创建运费模板
        Route::rule('create', 'create', "GET|POST");
        // 编辑运费模板
        Route::rule('edit', 'edit', "GET|POST");
        // 获取当前需要的运费模板列表
        Route::rule('get_area', 'getArea', "GET|POST");
        Route::rule('get_area', 'getArea', "GET|POST");
        // 删除运费模板
        Route::rule('destroy', 'destroy', "GET|POST");
        // 保存
        Route::rule('save', 'save', "GET|POST");
    }
)->prefix('master/StoreBusinessExpress/');
/***************************************店铺自提核销************************************************/
Route::group(
    'to_store',
    function () {
        // 自提核销
        Route::rule('write_off', 'writeOff', "GET|POST");
        // 自提列表
        Route::rule('index', 'index', "GET|POST");
        // 查看自提订单
        Route::rule('edit', 'edit', "GET|POST");
        // 审核自提订单
        Route::rule('cancellation', 'cancellation', "GET|POST");

    }
)->prefix('master/ToStore/');
/***************************************店铺分类************************************************/
Route::group(
    'store_classify',
    function () {
        // 店铺分类列表
        Route::rule('index', 'index', "GET|POST");
        // 查看自提订单
        Route::rule('edit', 'edit', "GET|POST");
        // 编辑店铺分类
        Route::rule('create', 'create', "GET|POST");
        // 店铺商品状态
        Route::rule('auditing', 'auditing', "GET|POST");
        // 删除店铺分类
        Route::rule('destroy', 'destroy', "GET|POST");
        // 文本编辑
        Route::rule('text_update', 'text_update', "POST");

    }
)->prefix('master/StoreClassify/');
/***************************************店铺账户************************************************/
Route::group(
    'store_account',
    function () {
        // 店铺账户首页
        Route::rule('index', 'index', "GET|POST");
    }
)->prefix('master/storeAccount/');
/***************************************会员管理************************************************/
Route::group(
    'member',
    function () {
        // 会员概况
        Route::rule('general', 'general', "GET|POST");
        // 会员列表
        Route::rule('index', 'index', "GET|POST");
        // 会员创建
        Route::rule('create', 'create', "GET|POST");
        // 会员编辑
        Route::rule('view', 'view', "GET|POST");
        // 会员删除
        Route::rule('destroy', 'destroy', "GET|POST");
        // 会员状态
        Route::rule('auditing', 'auditing', "GET|POST");
        // 成长值设置
        Route::rule('member_growth', 'member_growth', "GET|POST");
        // 移除黑名单
        Route::rule('open_member', 'open_member', "POST");
        // 积分设置
        Route::rule('integral', 'integral', 'GET|POST');
    }
)->prefix('master/Member/');
/***************************************会员等级管理************************************************/
Route::group(
    'member_rank',
    function () {
        // 会员等级列表
        Route::rule('index', 'index', "GET|POST");
        // 会员等级创建
        Route::rule('create', 'create', "GET|POST");
        // 会员等级编辑
        Route::rule('edit', 'edit', "GET|POST");
        // 会员等级删除
        Route::rule('destroy', 'destroy', "GET|POST");
        // 会员等级状态
        Route::rule('auditing', 'auditing', "POST");
        // 会员等级文本编辑
        Route::rule('text_update', 'text_update', "POST");
    }
)->prefix('master/MemberRank/');
/***************************************会员收货地址管理************************************************/
Route::group(
    'member_address',
    function () {
        // 会员收货地址列表
        Route::rule('index', 'index', "GET|POST");
        // 会员收货地址编辑
        Route::rule('edit', 'edit', "GET|POST");
        // 会员收货地址删除
        Route::rule('destroy', 'destroy', "GET|POST");
        // 会员收货地址三级联动
        Route::rule('area', 'area', "GET|POST");
    }
)->prefix('master/MemberAddress/');
/***************************************会员意见反馈************************************************/
Route::group(
    'feedback',
    function () {
        // 会员意见反馈类表
        Route::rule('index', 'index', "GET|POST");
        // 会员意见反馈查看
        Route::rule('edit', 'edit', "GET|POST");
        // 会员意见反馈删除
        Route::rule('destroy', 'destroy', "GET|POST");
    }
)->prefix('master/Feedback/');
/***************************************广告位置管理************************************************/
Route::group(
    'adv_position',
    function () {
        // 广告位置列表
        Route::rule('index', 'index', "GET|POST");
        // 广告位置子页列表
        Route::rule('ad_list', 'ad_list', "GET|POST");
        // 广告位置创建
        Route::rule('create', 'create', "GET|POST");
        // 广告位置编辑
        Route::rule('edit', 'edit', "GET|POST");
    }
)->prefix('master/AdvPosition/');
/***************************************广告列表管理************************************************/
Route::group(
    'adv',
    function () {
        // 手机端广告位置列表列表
        Route::rule('web_index', 'web_index', "GET|POST");
        // 手机端广告位置列表创建
        Route::rule('web_create', 'web_create', "GET|POST");
        // 手机端广告位置列表编辑
        Route::rule('web_edit', 'web_edit', "GET|POST");
        // 电脑端广告位置列表列表
        Route::rule('pc_index', 'pc_index', "GET|POST");
        // 电脑端广告位置列表创建
        Route::rule('pc_create', 'pc_create', "GET|POST");
        // 电脑端广告位置列表编辑
        Route::rule('pc_edit', 'pc_edit', "GET|POST");
        // 电脑端广告位置列表删除
        Route::rule('destroy', 'destroy', "GET|POST");
        // 电脑端广告位置列表状态
        Route::rule('auditing', 'auditing', "POST");
        // 电脑端广告位置列表修改
        Route::rule('text_update', 'text_update', "POST");
        // 电脑端广告位置店铺搜索
        Route::rule('store_search', 'store_search', "GET|POST");
        // 电脑端广告位置商品搜索
        Route::rule('goods_search', 'goods_search', "GET|POST");
    }
)->prefix('master/Adv/');
/***************************************文章分类管理************************************************/
Route::group(
    'article_classify',
    function () {
        // 文章分类列表
        Route::rule('index', 'index', "GET|POST");
        // 文章分类创建
        Route::rule('create', 'create', "GET|POST");
        // 文章分类编辑
        Route::rule('edit', 'edit', "GET|POST");
        // 文章分类删除
        Route::rule('destroy', 'destroy', "GET|POST");
        // 文章分类状态
        Route::rule('auditing', 'auditing', "POST");
        // 文章文本修改
        Route::rule('text_update', 'text_update', "POST");
    }
)->prefix('master/ArticleClassify/');
/***************************************文章列表管理************************************************/
Route::group(
    'article',
    function () {
        // 文章列表
        Route::rule('index', 'index', "GET|POST");
        // 文章创建
        Route::rule('create', 'create', "GET|POST");
        // 文章编辑
        Route::rule('edit', 'edit', "GET|POST");
        // 文章删除
        Route::rule('destroy', 'destroy', "GET|POST");
        // 文章状态
        Route::rule('auditing', 'auditing', "POST");
        // 文章文本修改
        Route::rule('text_update', 'text_update', "POST");
        // 文章选择关联商品
        Route::rule('selectGoods', 'selectGoods', "GET|POST");
        // 文章选择关联商品右侧
        Route::rule('getGoods', 'getGoods', "GET|POST");
        // 系统说明
        Route::rule('rule', 'rule', "GET|POST");
        // 系统说明 - 编辑
        Route::rule('rule_edit', 'rule_edit', "GET|POST");
        // 帮助中心列表
        Route::rule('help_index', 'help_index', "GET|POST");
        // 帮助中心创建
        Route::rule('help_create', 'help_create', "GET|POST");
        // 帮助中心编辑
        Route::rule('help_edit', 'help_edit', "GET|POST");
        // 帮助中心删除
        Route::rule('help_destroy', 'help_destroy', "GET|POST");
        // 协议说明
        Route::rule('agreement', 'agreement', "GET|POST");
        // 编辑协议说明
        Route::rule('agreement_edit', 'agreement_edit', "GET|POST");
    }
)->prefix('master/Article/');
/***************************************数据管理************************************************/
Route::group(
    'data_base',
    function () {
        // 数据库列表
        Route::rule('index', 'index', "GET|POST");
        // 数据开始备份
        Route::rule('backup', 'backup', "GET|POST");
        // 数据开始备份
        Route::rule('backup_index', 'backup_index', "GET|POST");
        // 数据备份删除
        Route::rule('destroy', 'destroy', "POST");
        // 数据备份下载
        Route::rule('download', 'download', "POST");
        // 清除缓存
        Route::rule('clear_cache', 'clear_cache', "GET|POST");
    }
)->prefix('master/DataBase/');
/***************************************短信************************************************/
Route::group(
    'sms',
    function () {
        // 短信统计
        Route::rule('statistics', 'statistics', 'GET|POST');
        // 短信设置VIEW
        Route::rule('index', 'index', 'GET');
        // 保存短信设置
        Route::rule('saveConfig', 'saveConfig', 'POST');
        // 腾讯云短信
        Route::rule('indexTX', 'indexTX', 'GET');
        //创建腾讯云模板
        Route::rule('createTX', 'createTX', 'GET|POST');
        //编辑腾讯云模板
        Route::rule('editTX', 'editTX', 'GET|POST');
        //删除腾讯云模板
        Route::rule('destroyTX', 'destroyTX', 'POST');
        //短信统计查看
        Route::rule('countTX', 'countTX', 'GET|POST');
        // 阿里云短信
        Route::rule('indexAL', 'indexAL', 'GET');
        //创建阿里云模板
        Route::rule('createAL', 'createAL', 'GET|POST');
        //编辑阿里云模板
        Route::rule('editAL', 'editAL', 'GET|POST');
        //删除阿里云模板
        Route::rule('destroyAL', 'destroyAL', 'POST');
        //阿里云短信统计查看
        Route::rule('countAL', 'countAL', 'GET|POST');
    }
)->prefix('master/Sms/');
/***************************************商品************************************************/
Route::group(
    'goods',
    function () {
        // 商品概况
        Route::rule('general', 'general', 'GET');
        // 商品列表
        Route::rule('index', 'index', 'GET');
        // 删除商品
        Route::rule('destroy', 'destroy', 'POST');
        // 永久删除商品
        Route::rule('foreverDestroy', 'foreverDestroy', 'POST');
        // 恢复商品数据
        Route::rule('recover', 'recover', 'POST');
        // 添加商品
        Route::rule('create', 'create', 'GET');
        // 编辑商品
        Route::rule('getActive', 'getActive', 'GET|POST');
        // 获取商品子类分类
        Route::rule('getSonCate', 'getSonCate', 'POST');
        // 获取商品规格
        Route::rule('attrList', 'attrList', 'POST');
        // 上传商品相册
        Route::rule('uploadSpAlbum', 'uploadSpAlbum', 'POST');
        // 富文本编辑器展示页
        Route::rule('uEditor', 'uEditor', 'GET');
        // 第四步获取分类
        Route::rule('getCate', 'getCate', 'POST');
        // 城市frame
        Route::rule('city', 'city', 'GET|POST');
        // 搜索商品frame
        Route::rule('searchGoods', 'searchGoods', 'GET');
        // 检测货号唯一性
        Route::rule('checkGoodsSn', 'checkGoodsSn', 'POST');
        // 商品上传处理
        Route::rule('createAct', 'createAct', 'POST');
        // 商品属性手工录入
        Route::rule('specAdd', 'specAdd', 'POST');
        // 规格库存展示
        Route::rule('productShow', 'productShow', 'GET');
        // 获取商品属性规格
        Route::rule('getProducts', 'getProducts', 'POST');
        // 获取商品规格属性
        Route::rule('getAttrType', 'getAttrType', 'POST');
        // 获取商家分类
        Route::rule('getStoreClassify', 'getStoreClassify', 'POST');
        // 商品智能权重
        Route::rule('weight', 'weight', 'GET');
        // 获取运费
        Route::rule('getFreight', 'getFreight', 'POST');
        // 商品智能权重
        Route::rule('status', 'status', 'GET');
        // 批量操作上架下架
        Route::rule('shelves', 'shelves', 'POST|GET');
        // 单修改参数
        Route::rule('editVal', 'editVal', 'POST');
        // 关闭店铺其他轮播
        Route::rule('editValStoreBanner', 'editValStoreBanner', 'POST');
        // 审核商品
        Route::rule('reviewStatus', 'reviewStatus', 'POST');
        // 单修改属性参数
        Route::rule('editAttr', 'editAttr', 'POST');
        // 获取个人分类历史输入
        Route::rule('getCateHistory', 'getCateHistory', 'POST');
        // 商品分销设置
        Route::rule('distribution', 'distribution', 'GET|POST');
        // 商品批量分销设置
        Route::rule('distributionAll', 'distributionAll', 'GET|POST');
        // 推荐设置
        Route::rule('recommend', 'recommend', 'GET|POST');
        // 活动设置-限时抢购
        Route::rule('activity_limit', 'activity_limit', 'GET|POST');
        // 活动设置-拼团
        Route::rule('activity_group', 'activity_group', 'GET|POST');
        // 活动设置-砍价
        Route::rule('activity_cut', 'activity_cut', 'GET|POST');
        // 活动设置-积分商城
        Route::rule('activity_integral', 'activity_integral', 'GET|POST');
        // 批量审核
        Route::rule('batch_review', 'batchReview', 'GET|POST');
        // 查看评论
        Route::rule('evaluate', 'evaluate', 'GET');
        // 审核全部
        Route::rule('review_all', 'reviewAll', 'GET|POST');
        // 上架/下架全部
        Route::rule('put_away_all', 'putAwayAll', 'POST');
        // 上架/下架单个商品
        Route::rule('put_away', 'putAway', 'POST');
        // 删除全部
        Route::rule('delete_all', 'deleteAll', 'POST');
        // 商品查看
        Route::rule('view', 'view', 'GET');
        // 查看收藏
        Route::rule('collect', 'collect', 'GET');
        // 删除商品
        Route::rule('delete', 'delete', 'POST');
        // 恢复商品
        Route::rule('restore', 'restore', 'POST');
        // 获取店铺可以使用的配送方式
        Route::rule('get_freight_type_list', 'getFreightList', 'POST');
        // 选择活动商品判断其活动状态
        Route::rule('selectActivity', 'selectActivity', 'POST');
    }
)->prefix('master/Goods/');
/***************************************商品品牌************************************************/
Route::group(
    'brand',
    function () {
        // 获取品牌列表
        Route::rule('getBrand', 'getBrand', 'POST');
    }
)->prefix('master/Brand/');
/***************************************地区************************************************/
Route::group(
    'area',
    function () {
        // 地区列表
        Route::rule('index', 'index', 'GET|POST');
        // 地区新增
        Route::rule('create', 'create', 'POST');
        // 地区删除
        Route::rule('destroy', 'destroy', 'POST');
        // 地区状态
        Route::rule('auditing', 'auditing', 'POST');
        // 地区文本修改
        Route::rule('text_update', 'text_update', 'POST');
        // 热门
        Route::rule('hot', 'hot', 'POST');
    }
)->prefix('master/Area/');
/***************************************门店自提管理************************************************/
Route::group(
    'take',
    function () {
        // 文章列表
        Route::rule('index', 'index', "GET|POST");
        // 文章创建
        Route::rule('create', 'create', "GET|POST");
        // 文章编辑
        Route::rule('edit', 'edit', "GET|POST");
        // 文章删除
        Route::rule('destroy', 'destroy', "GET|POST");
        // 文章状态
        Route::rule('auditing', 'auditing', "POST");
    }
)->prefix('master/Take/');
/***************************************营销首页管理************************************************/
Route::group(
    'marketing',
    function () {
        //优惠券列表
        Route::rule('index', 'index', "GET|POST");
    }
)->prefix('master/Marketing/');
/***************************************平台优惠券管理************************************************/
Route::group(
    'coupon',
    function () {
        //优惠券列表
        Route::rule('index', 'index', "GET|POST");
        // 添加优惠券
        Route::rule('create', 'create', "GET|POST");
        // 编辑优惠券
        Route::rule('edit', 'edit', "GET|POST");
        // 删除优惠券
        Route::rule('destroy', 'destroy', "GET|POST");
        // 推荐优惠券礼包
        Route::rule('auditing', 'auditing', "GET|POST");
    }
)->prefix('master/Coupon/');
/***************************************平台充值管理************************************************/
Route::group(
    'recharge',
    function () {
        //充值列表
        Route::rule('index', 'index', "GET|POST");
        // 添加充值
        Route::rule('create', 'create', "GET|POST");
        // 编辑优充值
        Route::rule('edit', 'edit', "GET|POST");
        // 删除充值
        Route::rule('destroy', 'destroy', "GET|POST");
        // 推荐充值
        Route::rule('auditing', 'auditing', "GET|POST");
    }
)->prefix('master/Recharge/');
/***************************************平台红包管理************************************************/
Route::group(
    'red_packet',
    function () {
        // 红包列表
        Route::rule('index', 'index', "GET|POST");
        // 添加红包
        Route::rule('create', 'create', "GET|POST");
        // 编辑红包
        Route::rule('edit', 'edit', "GET|POST");
        // 删除红包
        Route::rule('destroy', 'destroy', "GET|POST");
        // 红包使用情况
        Route::rule('memberPacket', 'memberPacket', "GET|POST");
    }
)->prefix('master/RedPacket/');
/***************************************积分商城管理************************************************/
Route::group(
    'integral',
    function () {
        // 积分商城商品列表
        Route::rule('index', 'index', "GET|POST");
        // 添加积分商城商品
        Route::rule('create', 'create', "GET|POST");
        // 编辑积分商城商品
        Route::rule('edit', 'edit', "GET|POST");
        // 删除积分商品
        Route::rule('destroy', 'destroy', "GET|POST");
        // 富文本编辑器
        Route::rule('uEditor', 'uEditor', 'GET');
    }
)->prefix('master/Integral/');
/***************************************积分商品分类************************************************/
Route::group(
    'integral_classify',
    function () {
        // 积分商品分类列表
        Route::rule('index', 'index', "GET|POST");
        // 创建积分商品分类
        Route::rule('create', 'create', "GET|POST");
        // 编辑积分商品分类
        Route::rule('edit', 'edit', "GET|POST");
        // 删除积分商品分类
        Route::rule('destroy', 'destroy', "GET|POST");
        // 更改积分分类显示状态
        Route::rule('auditing', 'auditing', "GET|POST");
    }
)->prefix('master/IntegralClassify/');
/***************************************积分订单管理************************************************/
Route::group(
    'integral_order',
    function () {
        // 积分订单列表
        Route::rule('index', 'index', "GET|POST");
        // 积分订单操作
        Route::rule('edit', 'edit', "GET|POST");
    }
)->prefix('master/IntegralOrder/');
/***************************************拼团商品分类************************************************/
Route::group(
    'group_classify',
    function () {
        // 拼团商品分类列表
        Route::rule('index', 'index', "GET|POST");
        // 创建拼团商品分类
        Route::rule('create', 'create', "GET|POST");
        // 编辑拼团商品分类
        Route::rule('edit', 'edit', "GET|POST");
        // 删除拼团商品分类
        Route::rule('destroy', 'destroy', "GET|POST");
    }
)->prefix('master/GroupClassify/');
/***************************************拼团商品管理************************************************/
Route::group(
    'group_goods',
    function () {
        // 拼团活动商品列表
        Route::rule('index', 'index', "GET|POST");
        // 创建拼团商品
        Route::rule('create', 'create', "GET|POST");
        // 编辑拼团商品
        Route::rule('edit', 'edit', "GET|POST");
        // 删除拼团活动
        Route::rule('destroy', 'destroy', "GET|POST");
        // 审核商家提交拼团商品
        Route::rule('inspect', 'inspect', "GET|POST");
        // 查看拼团商品
        Route::rule('view', 'view', "GET|POST");
    }
)->prefix('master/GroupGoods/');
/***************************************拼团活动管理************************************************/
Route::group(
    'group_activity',
    function () {
        // 拼团活动列表
        Route::rule('index', 'index', "GET|POST");
        // 查看拼团活动
        Route::rule('editAL', 'editAL', "GET|POST");
    }
)->prefix('master/GroupActivity/');
/***************************************砍价活动管理************************************************/
Route::group(
    'cut',
    function () {
        // 拼团商品列表
        Route::rule('index', 'index', "GET|POST");
        // 创建拼团商品列表
        Route::rule('create', 'create', "GET|POST");
        // 编辑拼团商品
        Route::rule('edit', 'edit', "GET|POST");
        // 删除砍价商品
        Route::rule('destroy', 'destroy', "GET|POST");
        // 查看砍价商品
        Route::rule('inspect', 'inspect', "GET|POST");
    }
)->prefix('master/Cut/');
/***************************************砍价活动管理************************************************/
Route::group(
    'cut_activity',
    function () {
        // 查看拼团活动
        Route::rule('editAL', 'editAL', "GET|POST");
    }
)->prefix('master/CutActivity/');
/***************************************限时抢购管理************************************************/
Route::group(
    'limit',
    function () {
        // 限时抢购列表
        Route::rule('index', 'index', "GET|POST");
        // 创建限时抢购
        Route::rule('create', 'create', "GET|POST");
        // 编辑限时抢购
        Route::rule('edit', 'edit', "GET|POST");
        // 删除限时抢购
        Route::rule('destroy', 'destroy', "GET|POST");
        // 抢购详情
        Route::rule('view', 'view', "GET|POST");
    }
)->prefix('master/Limit/');
/***************************************快递配送管理************************************************/
Route::group(
    'freight_express',
    function () {
        // 快递配送列表
        Route::rule('index', 'index', "GET|POST");
        // 创建快递配送模板
        Route::rule('create', 'create', "GET|POST");
        // 编辑快递配送模板
        Route::rule('edit', 'edit', "GET|POST");
        // 编辑快递配送模板
        Route::rule('save', 'save', "GET|POST");
        // 多删除快递配送模板
        Route::rule('multi_destroy', 'multiDestroy', "GET|POST");
        // 删除快递配送模板
        Route::rule('destroy', 'destroy', "GET|POST");

        Route::rule('get_row', 'getRow', "GET|POST");
        Route::rule('get_area', 'getArea', "GET|POST");
        // 获取店铺列表
        Route::rule('get_store_list', 'getStoreList', "GET|POST");
        // 保存
        Route::rule('save', 'save', "GET|POST");
    }
)->prefix('master/Express/');
/***************************************全店风格************************************************/
Route::group(
    'shop_style',
    function () {
        // 首页
        Route::rule('index', 'index', "GET|POST");
        // 创建风格
        Route::rule('save', 'saveStyle', "POST");
        // 删除风格
        Route::rule('delete', 'delete', "POST");
    }
)->prefix('master/ShopStyle/');
/***************************************同城速递管理************************************************/
Route::group(
    'distribution_city',
    function () {
        // 同城速递类表
        Route::rule('index', 'index', "GET|POST");
        // 创建同城速递
        Route::rule('create', 'create', "GET|POST");
        // 编辑同城速递
        Route::rule('edit', 'edit', "GET|POST");
        // 删除同城速递
        Route::rule('destroy', 'destroy', "GET|POST");
        // 第三方配送创建商户
        Route::rule('third', 'third', "GET|POST");
        // 第三方配送门店列表
        Route::rule('shopList', 'shopList', "GET|POST");
        // 第三方配送创建门店
        Route::rule('shopCreate', 'shopCreate', "GET|POST");
        // 获取下级城市
        Route::rule('getNextArea', 'getNextArea', "GET|POST");
        // 删除门店
        Route::rule('destroy', 'destroy', "POST");
    }
)->prefix('master/DistributionCity/');
/***************************************配送设置管理************************************************/
Route::group(
    'delivery_settings',
    function () {
        // 配送设置类表
        Route::rule('index', 'index', "GET|POST");
        // 创建配送设置
        Route::rule('create', 'create', "GET|POST");
        // 编辑配送设置
        Route::rule('edit', 'edit', "GET|POST");
        // 删除配送设置
        Route::rule('destroy', 'destroy', "GET|POST");
        // 设为默认配送设置
        Route::rule('set_default', 'setDefault', "GET|POST");
        // 批量删除配送设置
        Route::rule('multi_destroy', 'multiDestroy', "GET|POST");
    }
)->prefix('master/DeliverySettings/');
/***************************************订单管理************************************************/
Route::group(
    'order',
    function () {
        // 订单概况
        Route::rule('general', 'general', 'POST|GET');
        //打印订单
        Route::rule('get_examine', 'get_examine', 'POST|GET');
        // 订单类别
        Route::rule('index', 'index', "GET|POST");
        // 订单详情
        Route::rule('examine', 'examine', "GET|POST");
        // 关闭订单
        Route::rule('closeOrder', 'closeOrder', 'POST|GET');
        // 退款详情
        Route::rule('refunds_details', 'refunds_details', 'POST|GET');
        // 修改价格
        Route::rule('editPrice', 'editPrice', 'POST|GET');
        // 核销订单
        Route::rule('checkTakeCode', 'checkTakeCode', 'POST|GET');
        // 检查退货订单状态
        Route::rule('checkRefunds', 'checkRefunds', 'POST|GET');
        // 导出订单
        Route::rule('exportList', 'exportList', 'GET|POST');
        // 达达配送回调
        Route::rule('dada_callback', 'dadaCallback', 'GET|POST');
        // 线下付款订单列表
        Route::rule('offline_payment_list', 'offline_payment_list', 'POST|GET');
        // 发货
        Route::rule('deliver', 'deliver', 'POST|GET');
        // 退款
        Route::rule('refund', 'refund', 'POST|GET');
        // 订单参数设置
        Route::rule('settings', 'settings', 'POST|GET');
        // 货到付款确认收货
        Route::rule('confirmCollect', 'confirmCollect', 'POST|GET');
        // 线下付款
        Route::rule('offline_payment_info', 'offline_payment_info', 'POST|GET');
        // 线下付款信息详情
        Route::rule('get_offline_payment_info', 'get_offline_payment_info', 'POST');
        // 线下付款下订单
        Route::rule('offline_payment_pay', 'offline_payment_pay', 'POST|GET');
    }
)->prefix('master/Order/');
/***************************************文件处理************************************************/
Route::group(
    'file_act',
    function () {
        // 单图/多图上传
        Route::rule('upload', 'upload', 'POST');
    }
)->prefix('master/FileAct/');
/***************************************资产************************************************/
Route::group(
    'store_capital',
    function () {
        // 资产概况
        Route::rule('general', 'general', 'GET|POST');
        // 账户明细
        Route::rule('details', 'details', 'GET|POST');
        // 对账单
        Route::rule('property_list', 'property_list', 'GET|POST');
        // 对账单查看
        Route::rule('property_list_examine', 'property_list_examine', 'GET|POST');
        // 提现管理
        Route::rule('withdraw', 'withdraw', 'GET|POST');
        // 审核
        Route::rule('isChecking', 'isChecking', 'GET|POST');
        // 完成
        Route::rule('isComplete', 'isComplete', 'GET|POST');
        // 账户明细导出
        Route::rule('exportList', 'exportList', 'GET|POST');
        // 提现导出
        Route::rule('exportWithdraw', 'exportWithdraw', 'GET|POST');
        // 提现审核失败信息
        Route::rule('checkedFail', 'checkedFail', 'GET|POST');
        // 提现成功
        Route::rule('isOkay', 'isOkay', 'GET|POST');
    }
)->prefix('master/StoreCapital/');
/***************************************微信************************************************/
Route::group(
    'we_chat',
    function () {
        // 基础设置
        Route::rule('base_conf', 'baseConf', 'GET|POST');
        // 支付设置
        Route::rule('pay_conf', 'PayConf', 'GET|POST');
        // 小程序支付设置
        Route::rule('micro_app_pay_conf', 'MicroAppPayConf', 'GET|POST');
        // 消息群发
        Route::rule('message_group', 'messageGroup', 'GET|POST');
        // 关注回复
        Route::rule('automatic_response', 'automaticResponse', 'GET|POST');
        // 更新自定义菜单
        Route::rule('update_diy_menu', 'updateDiyMenu', 'GET|POST');
        // 保存自定义回复
        Route::rule('save_replay', 'saveReplay', 'POST');
        // 素材列表
        Route::rule('material_list', 'materialList', 'GET|POST');
        // DIY菜单
        Route::rule('diy_menu', 'diyMenu', 'GET|POST');
        // 微信事件推送API接口
        Route::rule('api', 'api', 'GET|POST');
        // 小程序基础设置
        Route::rule('applet', 'applet', 'GET|POST');
        // 小程序模板消息
        Route::rule('template_message', 'templateMessage', 'GET|POST');
        // 单修改参数
        Route::rule('editVal', 'editVal', 'POST');
    }
)->prefix('master/WeChat/');
/***************************************APP************************************************/
Route::group(
    'we_chat_app',
    function () {
        // 基础配置
        Route::rule('base_conf', 'baseConf', 'GET|POST');
        // 支付配置
        Route::rule('pay_conf', 'payConf', 'GET|POST');
    }
)->prefix('master/WeChatApp/');
/***************************************优惠信息************************************************/
Route::group(
    'preferential',
    function () {
        // 文章列表
        Route::rule('index', 'index', "GET|POST");
        // 文章创建
        Route::rule('create', 'create', "GET|POST");
        // 文章编辑
        Route::rule('edit', 'edit', "GET|POST");
        // 文章删除
        Route::rule('destroy', 'destroy', "GET|POST");
        // 文章状态
        Route::rule('auditing', 'auditing', "POST");
        // 文章文本修改
        Route::rule('text_update', 'text_update', "POST");
        // 文章选择关联商品
        Route::rule('selectGoods', 'selectGoods', "GET|POST");
        // 文章选择关联商品右侧
        Route::rule('getGoods', 'getGoods', "GET|POST");
    }
)->prefix('master/Preferential/');
/***************************************平台充值管理************************************************/
Route::group(
    'recharge',
    function () {
        //充值列表
        Route::rule('index', 'index', "GET|POST");
        // 添加充值
        Route::rule('create', 'create', "GET|POST");
        // 编辑优充值
        Route::rule('edit', 'edit', "GET|POST");
        // 删除充值
        Route::rule('destroy', 'destroy', "GET|POST");
        // 推荐充值
        Route::rule('auditing', 'auditing', "GET|POST");
    }
)->prefix('master/Recharge/');
/***************************************抽奖************************************************/
Route::group(
    'lottery',
    function () {
        //抽奖活动列表
        Route::rule('activity_list', 'activity_list', "GET|POST");
        //添加活动
        Route::rule('activity_create', 'activity_create', "GET|POST");
        //编辑抽奖活动
        Route::rule('activity_edit', 'activity_edit', "GET|POST");
        //删除抽奖活动
        Route::rule('activity_destroy', 'activity_destroy', "GET|POST");
        //抽奖优惠券列表
        Route::rule('coupon_list', 'coupon_list', "GET|POST");
        //创建抽奖优惠券
        Route::rule('coupon_create', 'coupon_create', "GET|POST");
        //编辑抽奖优惠券
        Route::rule('coupon_edit', 'coupon_edit', "GET|POST");
        //编辑抽奖活动商品
        Route::rule('edit_prize', 'edit_prize', "GET|POST");
        //抽奖订单列表
        Route::rule('order_list', 'order_list', "GET|POST");
        //抽奖订单详情
        Route::rule('order_examine', 'order_examine', "GET|POST");
        //改变活动状态
        Route::rule('activity_open', 'activity_open', "GET|POST");
    }
)->prefix('master/lottery/');
/***************************************分销开关************************************************/
Route::group(
    'distribution_switch',
    function () {
        // 分销开关功能
        Route::rule('index', 'index', "GET|POST");
    }
)->prefix('master/DistributionSwitch/');
/***************************************分销规则************************************************/
Route::group(
    'distribution_rule',
    function () {
        // 分销商规则
        Route::rule('index', 'index', "GET|POST");
        // 分销商手动申请表单
        Route::rule('distributor', 'distributor', "GET|POST");
        // 默认分佣规则
        Route::rule('ratio', 'ratio', "GET|POST");
        // 分销说明设置
        Route::rule('explainSet', 'explainSet', "GET|POST");
        // 分销规则开关功能
        Route::rule('editVal', 'editVal', "POST");
    }
)->prefix('master/DistributionRule/');
/***************************************分销商管理************************************************/
Route::group(
    'distribution_manage',
    function () {
        // 分销商列表
        Route::rule('manage_list', 'manageList', 'GET');
        // 分销商粉丝列表
        Route::rule('distribution_fans', 'fans', 'GET');
        // 分销商冻结/解冻
        Route::rule('distribution_frozen', 'distributionFrozen', 'POST');
        // 会员转分销商...会员列表
        Route::rule('member_convert_distribution', 'memberConvertDistribution', 'GET');
        // 会员转分销商...转化
        Route::rule('covert_to_distribution', 'covertToDistribution', 'POST');
        // 分销商审核...列表
        Route::rule('distribution_audit_list', 'distributionAuditList', 'GET');
        // 分销商审核
        Route::rule('distribution_audit', 'distributionAudit', 'POST');
        // 修改分销商等级
        Route::rule('edit_distribution_level', 'editDistributionLevel', 'POST');
        // 取消分销商资格
        Route::rule('cancel_dist', 'cancelDist', 'POST');
    }
)->prefix('master/DistributionManage/');
/***************************************分销商等级************************************************/
Route::group(
    'distribution_level',
    function () {
        // 分销商等级列表
        Route::rule('level_list', 'levelList', 'GET');
        // 添加分销商等级
        Route::rule('distribution_put', 'distributionPut', 'GET|POST');
        // 删除分销商等级
        Route::rule('distribution_del', 'distributionDel', 'POST');
    }
)->prefix('master/DistributionLevel/');
/***************************************分销提现管理************************************************/
Route::group(
    'distribution_withdraw',
    function () {
        // 分销提现管理信息
        Route::rule('rule', 'rule', 'GET');
        // 保存分销提现管理
        Route::rule('editVal', 'editVal', 'GET|POST');
        // 分销提现列表
        Route::rule('index', 'index', 'GET');
        // 确认转账
        Route::rule('checkTransfer', 'checkTransfer', 'GET|POST');
        // 暂停转账
        Route::rule('stopTransfer', 'stopTransfer', 'GET|POST');
        // 提现管理列表
        Route::rule('getList', 'getList', 'GET');
        // 提现管理详情
        Route::rule('details', 'details', 'GET');
    }
)->prefix('master/DistributionWithdraw/');
/***************************************分销商推广名片************************************************/
Route::group(
    'distribution_card',
    function () {
        // 分销商推广名片
        Route::rule('index', 'index', 'GET|POST');
        // 分销商推广名片局部编辑
        Route::rule('editVal', 'editVal', 'GET|POST');
        // 二维码生成
        Route::rule('qr_code', 'qr_code', 'GET');
    }
)->prefix('master/DistributionCard/');
/***************************************对账管理************************************************/
Route::group(
    'distribution_book',
    function () {
        // 结算对账
        Route::rule('store', 'store', 'GET');
        // 对账单
        Route::rule('statement', 'statement', 'GET');
        // 分销商对账
        Route::rule('distribution', 'distribution', 'GET');
        // 分销商对账详情
        Route::rule('distribution_details', 'distributionDetails', 'GET');
        // 订单对账
        Route::rule('order', 'order', 'GET');
    }
)->prefix('master/DistributionBook/');
/***************************************发票管理************************************************/
Route::group(
    'invoice',
    function () {
        // 发票列表 - 未开
        Route::rule('index', 'index', 'GET|POST');
        // 开票
        Route::rule('examine', 'examine', 'GET|POST');
        // 发票设置
        Route::rule('settings', 'settings', 'GET|POST');
        // 补开发票
        Route::rule('fill_open', 'fill_open', 'GET|POST');
        // 发票列表 - 已开
        Route::rule('open', 'open', 'GET|POST');
        // 冲红
        Route::rule('RCW', 'RCW', 'GET|POST');
        // 重开
        Route::rule('reopen', 'reopen', 'GET|POST');
    }
)->prefix('master/Invoice/');

Route::group(
    'invoice_store',
    function () {
        // 自营店铺发票列表
        Route::rule('index', 'index', 'GET|POST');
        // 更新发票信息
        Route::rule('edit', 'edit', 'GET|POST');
    }
)->prefix('master/InvoiceStore/');
/***************************************测试************************************************/
// 测试
Route::group(
    'test',
    function () {
        // 管理员列表
        Route::rule('index', 'index', "GET|POST");
        Route::rule('test', 'test', "GET|POST");
        Route::rule('index2', 'index2', "GET|POST");
        Route::rule('attr_list', 'attr_list', "GET");
        Route::rule('upload', 'upload', "POST");
        Route::rule('create', 'create', "POST");
    }
)->prefix('index/Index/');

/***************************************客服************************************************/
Route::group(
    'customer',
    function () {
        // 客服管理
        Route::rule('index', 'index', "GET|POST");
        // 创建客服
        Route::rule('create', 'create', "GET|POST");
        // 编辑客服
        Route::rule('update', 'update', "GET|POST");
        // 删除客服
        Route::rule('destroy', 'destroy', "POST");
        // 消息列表
        Route::rule('index3', 'index3', "GET");
        // 启用
        Route::rule('enabled', 'enabled', "POST");
        // 禁用
        Route::rule('disabled', 'disabled', "POST");
        // 获得客服用户列表
        Route::rule('get_member_list', 'get_member_list', "POST");
        // 获得消息列表
        Route::rule('get_message_list', 'get_message_list', "POST");
    }
)->prefix('master/Customer/');
/***************************************客服************************************************/
Route::group(
    'customer_settings',
    function () {
        // 接待设置
        Route::rule('reception', 'reception', "GET|POST");
    }
)->prefix('master/CustomerSettings/');
/***************************************客服组************************************************/
Route::group(
    'customer_group',
    function () {
        // 客服管理
        Route::rule('index', 'index', "GET|POST");
        // 创建客服组
        Route::rule('create', 'create', "GET|POST");
        // 编辑客服组
        Route::rule('update', 'update', "GET|POST");
        // 删除客服组
        Route::rule('destroy', 'destroy', "POST");
        // 启用客服组
        Route::rule('enabled', 'enabled', "POST");
        // 禁用客服组
        Route::rule('disabled', 'disabled', "POST");
    }
)->prefix('master/CustomerGroup/');
/***************************************客服分流************************************************/
Route::group(
    'customer_diversion',
    function () {
        // 分流设置
        Route::rule('manage', 'manage', 'GET|POST');
    }
)->prefix('master/CustomerDiversion/');

/***************************************标签分类************************************************/
Route::group(
    'tag_classify',
    function () {
        // 获取标签分类列表
        Route::rule('index','index',"GET");
        // 获取标签分类列表
        Route::rule('create','create',"GET|POST");
        // 获取标签分类列表
        Route::rule('edit','edit',"GET|POST");
        // 获取标签分类列表
        Route::rule('delete','delete',"GET|POST");
    }
)->prefix('master/TagClassify/');
/***************************************标签管理************************************************/
Route::group(
    'tag',
    function () {
        // 获取标签分类列表
        Route::rule('index','index',"GET");
        // 获取标签分类列表
        Route::rule('create','create',"GET|POST");
        // 获取标签分类列表
        Route::rule('edit','edit',"GET|POST");
        // 获取标签分类列表
        Route::rule('delete','delete',"GET|POST");
        // 选择商品
        Route::rule('choose_goods','choose_goods',"GET");
    }
)->prefix('master/Tag/');

/***************************************标签点击记录************************************************/
Route::group(
    'tag_click',
    function () {
        // 获取标签点击记录列表
        Route::rule('index','index',"GET");
    }
)->prefix('master/TagClick/');
/***************************************直播间************************************************/
Route::group(
    'live',
    function () {
        // 获取标签点击记录列表
        Route::rule('clear_list','clear_list',"GET|POST");
        // 直播间列表
        Route::rule('index', 'index', "GET|POST");
    }
)->prefix('master/Live/');

/* 其他 */
Route::get('static', response()->code(404));
Route::miss('@master/Login/miss');
