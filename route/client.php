<?php

use think\facade\Route;


Route::group('client', function () {
    Route::group('test', function () {
        // 登录
        Route::rule('index', 'index', 'POST|GET');
    })->prefix('client/Test/');
    /***************************************登录管理************************************************/

    Route::group('login', function () {
        // 登录
        Route::rule('index', 'index', 'POST|GET');
        // 注销登录
        Route::rule('out_login', 'outLogin', 'POST|GET');
    })->prefix('client/Login/');

    /***************************************主页管理************************************************/

    Route::group('index', function () {
        // 首页
        Route::rule('index', 'index', 'POST|GET');
    })->prefix('client/Index/');

    Route::group('desk', function () {
        // 首页
        Route::rule('index', 'index', 'POST|GET');
        // 商品支付排行
        Route::rule('goodsPay', 'goodsPay', 'POST|GET');
        // 商品数量排行
        Route::rule('goodsCountPay', 'goodsCountPay', 'POST|GET');
    })->prefix('client/Desk/');

    /***************************************店铺管理************************************************/

    Route::group('store', function () {
        // 店铺信息
        Route::rule('index', 'index', 'POST|GET');
        // 联系我们
        Route::rule('contact', 'contact', 'POST|GET');
        // 图片信息
        Route::rule('images', 'images', 'POST|GET');
        // 店铺认证
        Route::rule('storeAuth', 'storeAuth', 'POST|GET');
        // 店铺装修
        Route::rule('fitment', 'fitment', 'POST|GET');
        // 开关
        Route::rule('switch_enabled', 'switchEnabled', 'POST');
        // 店铺设置
        Route::rule('setting', 'setting', 'GET|POST');
        // 处理融云名字
        Route::rule('rongInfo', 'rongInfo', 'GET|POST');
    })->prefix('client/Store/');

    /***************************************店铺动态************************************************/

    Route::group('article', function () {
        // 店铺动态列表
        Route::rule('index', 'index', 'POST|GET');
        // 创建店铺动态-旧
        Route::rule('create_old', 'createOld', 'POST|GET');
        // 创建店铺动态
        Route::rule('create', 'create', 'POST|GET');
        // 编辑店铺动态
        Route::rule('edit', 'edit', 'POST|GET');
        // 删除店铺动态
        Route::rule('destroy', 'destroy', 'POST|GET');
        // 文章选择关联商品
        Route::rule('select_goods', 'selectGoods', "GET|POST");
        // 文章选择关联商品右侧
        Route::rule('getGoods', 'getGoods', "GET|POST");
        // 多删除
        Route::rule('multi_destroy', 'multiDestroy', "GET|POST");
    })->prefix('client/Article/');

    /***************************************配送************************************************/

    // 商家配送
    Route::group('business_express', function () {
        // 配送模板列表-新
        Route::rule('index', 'index', 'POST|GET');
        // 新建
        Route::rule('create', 'create', 'POST|GET');
        // 编辑
        Route::rule('edit', 'edit', 'POST|GET');
        // 删除运费模板
        Route::rule('destroy', 'destroy', 'POST|GET');
        // 获取模板配送信息-新
        Route::rule('get_row', 'getRow', 'POST|GET');
        // 获取模板列表
        Route::rule('get_list', 'getList', 'POST|GET');
        // 获取城市列表
        Route::rule('get_area', 'getArea', 'POST|GET');
        // 保存现有的信息
        Route::rule('save', 'save', 'POST');

    })->prefix('client/BusinessExpress/');

    // 商家配送分类
    Route::group('business_express_classify', function () {
        // 删除运费模板分类
        Route::rule('destroy', 'destroy', 'POST');
        // 设为默认
        Route::rule('set_default', 'setDefault', 'POST');
    })->prefix('client/BusinessExpressClassify/');

    // 区域切换
    Route::group('area', function () {
        Route::rule('search', 'search', 'POST|GET');

    })->prefix('client/Area/');

    // 同城速递
    Route::group('distribution_city', function () {
        Route::rule('index', 'index', 'POST|GET');

    })->prefix('client/DistributionCity/');

    /***************************************达达管理************************************************/

    // 达达管理
    Route::group('dada', function () {
        // 创建商户
        Route::rule('merchantAdd', 'merchantAdd', 'POST|GET');
        // 门店列表
        Route::rule('dadaShop', 'dadaShop', 'POST|GET');
        // 门店创建
        Route::rule('create', 'create', 'POST|GET');
        // 门店编辑
        Route::rule('edit', 'edit', 'POST|GET');
        // 获取市区
        Route::rule('area', 'area', 'POST|GET');
    })->prefix('client/Dada/');

    // 到店自提
    Route::group('take', function () {
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
    })->prefix('client/Take/');
    // 配送设置
    Route::group('delivery_settings', function () {
        // 配送列表
        Route::rule('index', 'index', "GET|POST");
        // 配送地址新建
        Route::rule('create', 'create', "GET|POST");
        // 配送地址编辑
        Route::rule('edit', 'edit', "GET|POST");
        // 配送删除
        Route::rule('destroy', 'destroy', "GET|POST");
    })->prefix('client/DeliverySettings/');

    /***************************************优惠券管理************************************************/

    Route::group('coupon', function () {
        // 优惠券列表
        Route::rule('index', 'index', 'POST|GET');
        // 优惠券添加
        Route::rule('create', 'create', 'POST|GET');
        // 优惠券编辑
        Route::rule('edit', 'edit', 'POST|GET');
        // 优惠券删除
        Route::rule('destroy', 'destroy', 'POST|GET');
        // 优惠券设置上架状态
        Route::rule('editVal', 'editVal', 'POST|GET');
    })->prefix('client/Coupon/');

    /***************************************商品管理************************************************/

    Route::group('goods', function () {
        // 商品列表
        Route::rule('index', 'index', 'POST|GET');
        // 添加商品
        Route::rule('create', 'create', 'POST|GET');
        // 删除商品
        Route::rule('destroy', 'destroy', 'POST|GET');
        // 恢复商品
        Route::rule('recover', 'recover', 'POST|GET');
        // 批量操作上架下架
        Route::rule('shelves', 'shelves', 'POST|GET');
        // 永久删除
        Route::rule('foreverDestroy', 'foreverDestroy', 'POST|GET');
        // 恢复商品
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
        // 搜索商品frame
        Route::rule('searchGoods', 'searchGoods', 'GET');
        // 检测商品是否在活动中
        Route::rule('getActive', 'getActive', 'GET|POST');
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
        // 商品智能权重
        Route::rule('weight', 'weight', 'GET');
        // 单修改参数
        Route::rule('editVal', 'editVal', 'POST');
        // 获取个人分类历史输入
        Route::rule('getCateHistory', 'getCateHistory', 'POST');
        // 文本修改
        Route::rule('text_update', 'text_update', "POST");
        // 修改单属性
        Route::rule('editAttr', 'editAttr', 'POST|GET');
        // 选择活动商品
        // 选择城市
        Route::rule('city', 'city', 'POST|GET');
        // 选择活动商品
        Route::rule('searchGoods', 'searchGoods', 'POST|GET');
        //  获取商品属性规格
        Route::rule('getProducts', 'getProducts', 'POST|GET');
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
        // 查看评论
        Route::rule('evaluate', 'evaluate', 'GET');
        // 单个商品上下架
        Route::rule('change_put_away','changePutAway','POST');
        // 删除商品|批量删除商品
        Route::rule('delete','delete','POST');
        // 上架全部|下架全部
        Route::rule('put_away_all','putAwayAll','POST');
        // 删除全部
        Route::rule('delete_all','deleteAll','POST');
        // 查看收藏
        Route::rule('collect', 'collect', 'GET');
        // 标签未选择商品frame
        Route::rule('tagGoods', 'tagGoods', 'GET');
        // 标签商品frame
        Route::rule('tagChooseGoods', 'tagChooseGoods', 'GET');
        // 标签设置
        Route::rule('setTag', 'setTag', 'GET|POST');
        // 商品列表展示
        Route::rule('is_show', 'is_show', 'GET|POST');
        // 选择标签
        Route::rule('chooseTag', 'chooseTag', 'GET|POST');
        // 移除标签
        Route::rule('removeTag', 'removeTag', 'GET|POST');
    })->prefix('client/Goods/');
    /***************************************商品类型管理************************************************/

    Route::group('attr_type', function () {
        // 类型列表
        Route::rule('index', 'index', 'POST|GET');
        // 类型添加
        Route::rule('create', 'create', 'POST|GET');
        // 类型编辑
        Route::rule('edit', 'edit', 'POST|GET');
        // 类型删除
        Route::rule('destroy', 'destroy', 'POST|GET');
        // 更改状态
        Route::rule('auditing', 'auditing', 'POST|GET');
    })->prefix('client/AttrType/');
    /***************************************用户评论管理************************************************/
    Route::group('goods_evaluate', function () {
        // 用户评论列表
        Route::rule('index', 'index', "GET|POST");
        // 用户评论查看
        Route::rule('edit', 'edit', "GET|POST");
        // 用户评论删除
        Route::rule('destroy', 'destroy', "GET|POST");
        // 改变评论显示
        Route::rule('auditing', 'auditing', "GET|POST");
    })->prefix('client/GoodsEvaluate/');
    /***************************************商品属性管理************************************************/
    Route::group('attr', function () {
        // 属性列表
        Route::rule('index', 'index', 'POST|GET');
        // 属性添加
        Route::rule('create', 'create', 'POST|GET');
        // 属性编辑
        Route::rule('edit', 'edit', 'POST|GET');
        // 属性删除
        Route::rule('destroy', 'destroy', 'POST|GET');
        // 文本修改
        Route::rule('text_update', 'text_update', "POST");

    })->prefix('client/Attr/');
    /***************************************商品品牌管理************************************************/
    Route::group('brand', function () {
        // 品牌列表
        Route::rule('index', 'index', 'POST|GET');
        // 品牌添加
        Route::rule('create', 'create', 'POST|GET');
        // 品牌编辑
        Route::rule('edit', 'edit', 'POST|GET');
        // 获取品牌
        Route::rule('getBrand', 'getBrand', 'POST');
        // 品牌删除
        Route::rule('destroy', 'destroy', 'POST|GET');

    })->prefix('client/Brand/');
    /***************************************商品分类管理************************************************/
    Route::group('store_goods_classify', function () {
        // 分类列表
        Route::rule('index', 'index', 'GET|POST');
        // 分类添加
        Route::rule('create', 'create', 'POST|GET');
        // 分类编辑
        Route::rule('edit', 'edit', 'POST|GET');
        // 分类删除
        Route::rule('destroy', 'destroy', 'POST|GET');
        // 更改状态
        Route::rule('auditing', 'auditing', 'POST|GET');
        // 文本修改
        Route::rule('text_update', 'text_update', "POST");
        // 快速添加
        Route::rule('fast_create', 'fastCreate', "GET|POST");
        // 快速添加-商品
        Route::rule('fast_create_goods', 'fastCreateGoods', "GET|POST");

    })->prefix('client/StoreGoodsClassify/');
    /***************************************商品批量修改管理************************************************/
    Route::group('goods_batch_operation', function () {
        // 商品批量操作列表
        Route::rule('index', 'index', "GET|POST");
        //商品批量修改获取商品
        Route::rule('get_goods', 'get_goods', "GET|POST");
        //选择商品后的选择
        Route::rule('goods_list', 'goods_list', "GET|POST");
    })->prefix('client/GoodsBatchOperation/');
    /***************************************商品批量导入管理************************************************/
    Route::group('goods_import', function () {
        // 商品批量导入操作列表
        Route::rule('index', 'index', "GET|POST");
        Route::rule('createcsv', 'createcsv', "GET|POST");
        Route::rule('create', 'create', "GET|POST");
    })->prefix('client/goodsImport/');
    /***************************************商品批量导出管理************************************************/
    Route::group('goods_export', function () {
        // 商品批量导入操作列表
        Route::rule('index', 'index', "GET|POST");
        Route::rule('create', 'create', "GET|POST");
    })->prefix('client/goodsExport/');

    /***************************************拼团商品管理************************************************/

    Route::group('group_goods', function () {
        // 拼团商品列表
        Route::rule('index', 'index', 'POST|GET');
        // 拼团商品添加
        Route::rule('create', 'create', 'POST|GET');
        // 拼团商品编辑
        Route::rule('edit', 'edit', 'POST|GET');
        // 砍价查看
        Route::rule('inspect', 'inspect', 'POST|GET');
        // 拼团商品删除
        Route::rule('destroy', 'destroy', 'POST|GET');
    })->prefix('client/GroupGoods/');
    /***************************************拼团活动管理************************************************/
    Route::group('group_activity', function () {
            // 拼团活动列表
            Route::rule('index', 'index', "GET|POST");
            // 查看拼团活动
            Route::rule('editAL', 'editAL', "GET|POST");
        }
    )->prefix('client/GroupActivity/');
    /***************************************砍价管理************************************************/
    Route::group('cut', function () {
        // 砍价列表
        Route::rule('index', 'index', 'POST|GET');
        // 砍价添加
        Route::rule('create', 'create', 'POST|GET');
        // 砍价查看
        Route::rule('inspect', 'inspect', 'POST|GET');
        // 砍价编辑
        Route::rule('edit', 'edit', 'POST|GET');
        // 砍价删除
        Route::rule('destroy', 'destroy', 'POST|GET');
    })->prefix('client/Cut/');
    /***************************************砍价活动管理************************************************/
    Route::group(
        'cut_activity',
        function () {
            // 查看拼团活动
            Route::rule('editAL', 'editAL', "GET|POST");
        }
    )->prefix('client/CutActivity/');
    /***************************************限时抢购管理************************************************/

    Route::group('limit', function () {
        // 限时抢购列表
        Route::rule('index', 'index', 'POST|GET');
        // 限时抢购添加
        Route::rule('create', 'create', 'POST|GET');
        // 限时抢购查看
        Route::rule('inspect', 'inspect', 'POST|GET');
        // 限时抢购编辑
        Route::rule('edit', 'edit', 'POST|GET');
        // 限时抢购删除
        Route::rule('destroy', 'destroy', 'POST|GET');
        // 明细
        Route::rule('view', 'view', 'POST|GET');
    })->prefix('client/Limit/');

    /***************************************订单管理************************************************/

    Route::group('order', function () {
        // 订单列表
        Route::rule('index', 'index', 'POST|GET');
        // 打印订单信息
        Route::rule('get_examine', 'get_examine', 'POST|GET');
        // 线下付款
        Route::rule('offline_payment', 'offline_payment', 'POST|GET');
        // 线下付款信息
        Route::rule('offline_payment_info', 'offline_payment_info', 'POST|GET');
        // 线下付款信息详情
        Route::rule('get_offline_payment_info', 'get_offline_payment_info', 'POST');
        // 线下付款下订单
        Route::rule('offline_payment_pay', 'offline_payment_pay', 'POST|GET');
        // 线下付款订单列表
        Route::rule('offline_payment_list', 'offline_payment_list', 'POST|GET');
        // 订单审核
        Route::rule('examine', 'examine', 'POST|GET');
        // 退款详情
        Route::rule('refunds_details', 'refunds_details', 'POST|GET');
        // 修改价格
        Route::rule('editPrice', 'editPrice', 'POST|GET');
        // 关闭订单
        Route::rule('closeOrder', 'closeOrder', 'POST|GET');
        // 核销订单
        Route::rule('checkTakeCode', 'checkTakeCode', 'POST|GET');
        // 检查退货订单状态
        Route::rule('checkRefunds', 'checkRefunds', 'POST|GET');
        Route::rule('dada_callback', 'dadaCallback', 'POST|GET');
    })->prefix('client/Order/');
    /***************************************文件处理************************************************/
    Route::group('file_act', function () {
        // 单图/多图上传
        Route::rule('upload', 'upload', 'POST');
    })->prefix('client/FileAct/');
    /***************************************数据管理************************************************/
    Route::group('data_base', function () {
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
    })->prefix('client/DataBase/');
    /***************************************客服************************************************/
    Route::group('customer', function () {
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
    })->prefix('client/Customer/');
    /***************************************客服************************************************/
    Route::group('customer_settings', function () {
        // 接待设置
        Route::rule('reception', 'reception', "GET|POST");
    })->prefix('client/CustomerSettings/');
    /***************************************客服组************************************************/
    Route::group('customer_group', function () {
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
    })->prefix('client/CustomerGroup/');
    /***************************************客服分流************************************************/
    Route::group('customer_diversion', function () {
        // 分流设置
        Route::rule('manage','manage','GET|POST');
    })->prefix('client/CustomerDiversion/');
    /***************************************资产************************************************/
    Route::group('store_capital', function () {
        // 资产概况
        Route::rule('index', 'index', 'GET|POST');
        // 账户明细
        Route::rule('details', 'details', 'GET|POST');
        // 对账单
        Route::rule('propertyList', 'propertyList', 'GET|POST');
        //对账单查看
        Route::rule('property_examine', 'property_examine', 'GET|POST');
        //定时对账
        Route::rule('check_order', 'check_order', 'GET|POST');
        // 提现申请
        Route::rule('withdraw', 'withdraw', 'GET|POST');
        // 账户明细导出
        Route::rule('exportList', 'exportList', 'GET|POST');
        // 提现导出
        Route::rule('exportWithdraw', 'exportWithdraw', 'GET|POST');
        // 提现记录
        Route::rule('withdraw_details', 'withdraw_details', 'GET|POST');
        // 提现明细
        Route::rule('withdraw_examine', 'withdraw_examine', 'GET|POST');
    })->prefix('client/StoreCapital/');
    /***************************************结算管理************************************************/
    Route::group('distribution_book', function () {
        // 分销商对账
        Route::rule('distribution', 'distribution', 'GET');
        // 分销商对账详情
        Route::rule('distribution_details', 'distributionDetails', 'GET');
        // 订单对账
        Route::rule('order', 'order', 'GET');
    })->prefix('client/DistributionBook/');
    /***************************************分销商推广名片************************************************/
    Route::group('distribution_card', function () {
        // 分销商对账
        Route::rule('index', 'index', 'GET');
        // 分销商推广名片局部编辑
        Route::rule('editVal', 'editVal', 'GET|POST');
        // 二维码生成
        Route::rule('qr_code', 'qr_code', 'GET');
    })->prefix('client/DistributionCard/');

    /***************************************发票管理************************************************/
    Route::group('invoice',function (){
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
    })->prefix('client/Invoice/');
    /***************************************标签设置************************************************/
    Route::group('tag',function(){
        //标签分类
        Route::rule('classify_index','classify_index','GET|POST');
        //查看标签分类
        Route::rule('classify_create','classify_create','GET|POST');
        //标签管理
        Route::rule('index','index','GET|POST');
        //查看标签
        Route::rule('create','create','GET|POST');
        //选中商品
        Route::rule('choose','choose','POST');
        //移除商品
        Route::rule('remove','remove','POST');
        //标签点击记录
        Route::rule('log_index','log_index','GET|POST');
    })->prefix('client/Tag/');
});
