$(function () {
    // 顶部各个模块切换
    $('.ecsc-nav,.admin-logo').find('a').click(function () {

        // 更改顶部导航
        $('.ecsc-nav-ul').children('li').removeClass("current");
        $(this).parent('li').addClass("current");
        $('.ecsc-nav-ul').children('li').children('.arrow').css('display', 'none');
        $(this).next('.arrow').css('display', 'block');

        //侧边导航展开状态
        $('.seller_center_left_menu').hide();
        var _modules = $(this).parent().addClass('active').attr('data-param');
        $('div[id^="navTabs_"]').hide();
        $('#navTabs_' + _modules).show().find('li').removeClass('current').first().addClass('current').children('a').click();
    });


    // 侧边导航三级级菜单点击
    $('.seller_center_left_menu li a').click(function () {
        openItem($(this).attr('data-param'));
        $(this).parents("li").addClass("current").siblings().removeClass("current")
    });

    if ($.cookie('client_workspace') == null) {
        // 默认选择第一个菜单
        $('.ecsc-nav-ul').find('li:first > a').click();
    } else {
        reashItem($.cookie('client_workspace'));
    }
});

// 点击菜单，iframe页面跳转
function openItem(param) {
    var data_str = param.split('|');
    var url = data_str[2];
    $('#client_workspace').attr('src', '/' + url);
    $.cookie('client_workspace', param, {expires: 1, path: "/"});
}

// 刷新菜单改变
function reashItem(param) {
    var data_str = param.split('|');
    var eqs = data_str[0] - 1;
    // 顶部导航全部样式初始化并打开对应顶部导航样式
    $('.ecsc-nav-ul').children('li').removeClass("current").end().children('li').eq(eqs).addClass("current");
    // 左侧边导航样式初始化并打开对应左侧边导航样式
    $('ul[id^="navTabs_"]').eq(data_str[0]).find('li').removeClass('current');
    $('ul[id^="navTabs_"]').hide().eq(data_str[0]).show().find('li').eq(data_str[1]).addClass('current');
    $('#client_workspace').attr('src', '/' + data_str[2]);
    $.cookie('client_workspace', param, {expires: 1, path: "/"});
}
