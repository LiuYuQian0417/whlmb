<?php

use think\facade\Route;

$version = 'm2.0';
Route::group($version, function () {

    /***************************************用户认证************************************************/
    Route::group('login', function () {
        // 手机号登录
        Route::rule('index', 'index', 'GET|post');
        // 短信验证码登录
        Route::rule('sms', 'sms', 'post');
        // 忘记密码
        Route::rule('set_password', 'set_password', 'post');
    })->prefix('merchant/auth.login/');
    /***************************************短信************************************************/
    Route::group('sms', function () {
        // 发送短信通用
        Route::rule('send', 'send', 'post');
        // 验证码合法性检测(单接口)
        Route::rule('checkCodeInvalid', 'checkCodeInvalid', 'post');
    })->prefix('merchant/auth.sms/');

    /***************************************首页************************************************/
    Route::group('index', function () {
        // 首页
        Route::rule('index', 'index', 'GET|post');
        // 排行列表
        Route::rule('ranking_list', 'ranking_list', 'GET|post');
        // 搜索
        Route::rule('index_seek', 'index_seek', 'GET|post');
    })->prefix('merchant/home.index/');
    /***************************************平台************************************************/
    Route::group('platform', function () {
        // 首页
        Route::rule('index', 'index', 'GET|post');
        // 排行列表
        Route::rule('ranking_list', 'ranking_list', 'GET|post');
        // 店铺列表
        Route::rule('store_list', 'store_list', 'GET|post');
    })->prefix('merchant/platform.index/');
    /***************************************平台************************************************/
    Route::group('create_store', function () {
        // 用户列表
        Route::rule('member_list', 'member_list', 'GET|post');
        // 添加店铺
        Route::rule('create', 'create', 'GET|post');
        // 店铺列表
        Route::rule('store_list', 'store_list', 'GET|post');
        // 主营类目
        Route::rule('main_business', 'main_business', 'GET|post');
        // 编辑店铺
        Route::rule('edit', 'edit', 'GET|post');
        // 编辑权重
        Route::rule('weight', 'weight', 'GET|post');
        // 注销店铺
        Route::rule('destroy', 'destroy', 'GET|post');
        // 启用店铺
        Route::rule('open_store', 'open_store', 'GET|post');
        // 审核店铺
        Route::rule('store_audit', 'store_audit', 'GET|post');
    })->prefix('merchant/platform.create_store/');
});