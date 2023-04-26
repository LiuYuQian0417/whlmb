//拓展
window.main = {
    //ajax调用
    ajax: function (param) {
        if ('confirm_text' in param) {
            var index = layer.confirm(param.confirm_text, {
                title: param.title || '提示框',
                offset: '30%',
                btnAlign: 'c',
                skin: 'demo-class',
                btn: ['确定', '取消'] //按钮
            }, function () {
                layer.close(index);
                main._ajax(param);
            });
        } else {
            main._ajax(param);
        }
    },
    _ajax: function (param) {
        var loadIndex = 0;
        $.ajax({
            type: param.type || 'post',
            url: param.url,
            data: param.data || '',
            dataType: 'json',
            async: ('async' in param) ? param.async : true,
            headers: {
                "token": get_token(),
            },
            beforeSend: function () {
                if ('return_url' in param) {
                    sessionStorage.return_url = param.return_url;
                }
                if ('before_send' in param && typeof param.before_send == 'function') {
                    if (!param.before_send()) {
                        return;
                    }
                }
                if (!('not_load' in param) || ('not_load' in param && param.not_load === false)) {
                    loadIndex = layer.load(1, {shade: [0.1, '#fff']});
                }
            },
            success: param.callback || main.callback,
            error: function (request, err) {
                layer.close(loadIndex);
                layer.msg(err, {time: 2000});
            },
            complete: function (resObj) {
                if (!('not_login_out' in param) && ('responseText' in resObj)) {
                    //如果是令牌无效则跳转到登录
                    var json = JSON.parse(resObj.responseText);
                    if (json.code === -201 || json.code === -200) {
                        main.loginOut(2);
                        return;
                    }
                }
                set_token(resObj, false);
                layer.close(loadIndex);
            },
        });
    },
    /**
     *
     * @param {Object} data{
     *     return_url:回跳地址
     *     confirm_text:如果有确认提示框则询问文字
     *     url:跳转地址
     *      }
     */
    jump: function (data) {
        if (data.constructor === Object) {
            if (!('is_open' in data)) {
                data.is_open = false;
            }
            if (!('url' in data)) {
                throw new Error('缺少跳转参数!');
            }
            if (data.url === '') {
                return true;
            }
            if ('return_url' in data) {
                sessionStorage.return_url = data.return_url;
            }
            if ('confirm_text' in data) {
                var index = layer.confirm(data.confirm_text, {
                    btn: ['是', '否'] //按钮
                }, function () {
                    layer.close(index);
                    data.is_open ? window.open(data.url) : window.location.href = data.url;
                });
                return;
            }
            //判断页面链接是否有http或是https
            // data.url = RegExp(/(https:\/\/|http:\/\/)/).test((data.url)) or  ? data.url : 'http://' + data.url;
            //判断如果是指定页面的话则替换历史记录
            if (window.location.href.indexOf('/cart/confirm_order') !== -1 || window.location.href.indexOf('/order/pay_type') !== -1) {
                window.location.replace(data.url);
                return;
            } else {
                data.is_open ? window.open(data.url, (data.open_name || '_blank'), (data.open_specs || ''), true) : window.location.href = data.url;
            }
        }
    },
    // ajax回调函数
    callback: function (result) {

        if ('message' in result && $.inArray(result.code, [-200, -200]) === -1) layer.msg(result.message || '操作完成', {
            offset: '400px',
            time: 2000
        });
        if (parseInt(result.code) === 0) {
            setTimeout(function () {
                if (result.url) {
                    location.href = result.url;
                } else if (sessionStorage.return_url && sessionStorage.return_url !== '') {
                    var json_url = ObjectOrJson(sessionStorage.return_url);
                    //如果回跳地址转化后是对象  则判断是否应用到当前页面
                    if (json_url.constructor === Object) {
                        //如果限制标识存在则判断是否是当前页面
                        if (('use_url' in json_url) && window.location.href.indexOf(json_url.use_url) != -1) {
                            sessionStorage.return_url = '';
                            window.location.href = json_url.return_url;
                            return;
                        }
                        main.reload();
                    } else {
                        var return_url = sessionStorage.return_url;
                        sessionStorage.return_url = '';
                        if (!RegExp(/\/.*?/).test((return_url)) && sessionStorage.return_parame) {
                            return_url = return_url + '?' + sessionStorage.return_parame;
                        }
                        window.location.href = return_url;
                    }
                } else {
                    main.reload();
                }
            }, 500);
        }
    },
    /**
     * 退出登录
     * @param type   1退出后刷新当前页  2退出后跳转登录
     */
    loginOut: function (type) {
        $.ajax({
            type: 'post',
            url: '/pc2.0/my/login_out',
            dataType: 'json',
            async: false,
            complete: function () {
                set_token('', true);
                parseInt(type) === 1 ? main.reload({'type': 2}) : window.location.href = '/pc2.0/login/index';
            }
        });
    },
    pitch_on_style: function (Selector, Class) {
        //判断当前页面选中标签
        $(Selector).removeClass(Class);
        var _self_url = window.location.href;
        if (_self_url.indexOf('.html') === -1) {
            _self_url = _self_url + '.html';
        }
        //截取当前页面链接的方法
        var self_url = /\/pc2\.0(\S*?)[\.|\?]/i.exec(_self_url)[1];
        $(Selector).each(function (k, v) {
            if ($(v).attr('href') !== '') {
                if ($(v).attr('href').indexOf(self_url) !== -1) {
                    $(v).addClass(Class);
                    return;
                }
                var tag_url = $(v).attr('href');
                if (tag_url.indexOf('javascript') != -1) {
                    tag_url = /url':'(\S*?)[']/i.exec(tag_url)[1] + '.';
                }
                var match_result = url_map[/\/pc2\.0(\S*?)[\.|\?]/i.exec(tag_url)[1]];
                if ((typeof match_result).toString() !== 'undefined' && match_result.constructor === Array && match_result.indexOf(self_url) !== -1) {
                    $(v).addClass(Class);
                }
            }
        });
    },
    //:TODO 修改表单验证可传入回调函数
    //valid表单验证提交
    valid: function (Selector) {
        var _Selector = Selector;
        // 禁用按钮防止重复提交
        $("#submit").attr("disabled", "disabled");
        $.getScript('/template/computer/resource/js/Validform_v5.3.2_ncr_min.js', function () {
            _valid(Selector);
        });

        function _valid(Selector) {
            if (Selector.constructor === Object) {
                if ('return_url' in Selector) {
                    sessionStorage.return_url = Selector.return_url;
                }
                if ('call_back' in Selector) {
                    call_back = Selector.call_back;
                }
                if ('btn_submit' in Selector) {
                    btn_submit = Selector.btn_submit;
                }
                if ('ignoreHidden' in Selector) {
                    ignoreHidden = Selector.btn_submit;
                }
                if ('select' in Selector) {
                    Selector = Selector.select;
                }
            }
            if (Selector.constructor === Array) {
                switch (Selector.length) {
                    case 3:
                        sessionStorage.return_url = Selector[2];
                    case 2:
                        var call_back = Selector[1];
                    case 1:
                        Selector = Selector[0];
                        break;
                }
            }
            $((typeof btn_submit != "undefined") ? btn_submit : "#submit").removeAttr("disabled");
            $.Tipmsg.r = null;
            $.Tipmsg.c = null;
            $.Tipmsg.p = null;
            $(Selector).Validform({
                //默认绑定提交按钮
                btnSubmit: (typeof btn_submit != "undefined") ? btn_submit : '#submit',
                tipSweep: true,
                tiptype: function (msg) {
                    layer.msg(msg, {offset: '400px', time: 2000});
                },
                datatype: {
                    alphaNum: /^[a-zA-Z0-9]{1,20}$/,
                    password: /^\S{6,20}$/
                },
                ajaxPost: true,
                //忽略验证隐藏表单元素
                ignoreHidden: (typeof ignoreHidden != "undefined") ? ignoreHidden : false,
                // beforeCheck: typeof call_back != "undefined" ? call_back : function (formObj) {
                //
                // },
                beforeSubmit: function (formObj) {
                    if (typeof call_back != "undefined") {
                        if (call_back() === false) {
                            return false;
                        }
                        ;
                    }
                    $(Selector).ajaxSubmit(get_valid_options(_Selector));
                    return false;
                },
                usePlugin: {}
            });
        }
    },
    //猜你喜欢
    new_like: function (Selector) {
        main.ajax({
            'url': '/pc2.0/my/recommend_list',
            data: {'limit': ('limit' in Selector) ? Selector.limit : 8},
            callback: function (res) {
                var str = '';
                $.each(res.result, function (k, v) {
                    var data = main.goods_label(v, res.discount);
                    //拼接字符串
                    str += '<div class="list" style="' + (('div_style' in Selector) ? Selector.div_style : +'width: 25%') + '">\n' +
                        '<a href="javascript:jump_goods(' + v.goods_id + ');">\n' +
                        '<img src="' + v.file + '" alt="">\n' +
                        '<p class="name">' + v.goods_name + '</p>\n' +
                        '<div class="money">\n' +
                        '<div class="pic primary-color">￥' + data.price + '</div>\n' +
                        '<div class="volume">\n' +
                        '销量 <span class="primary-color">' + v.sales_volume + '</span>\n' +
                        '</div></div>\n' +
                        '<div class="discounts">\n'
                        + data.label_str + data.group_str +
                        '</div></a></div>';
                });
                $(Selector.select).html(str);
            }
        });
    },
    /**
     *
     * @param type  广告类型 0 普通广告  1 商品 2 店铺  3 无操作广告
     * @param content  广告内容 链接 或 商品、店铺ID
     */
    adv_jump: function (type, content) {
        console.log(content);
        if (content !== '') {
            switch (type.toString()) {
                //链接广告
                case '0':
                    if (!RegExp(/(https:\/\/|http:\/\/)/).test((content))) {
                        content = 'http://' + content;
                    }
                    window.open(content);
                    break;
                //商品
                case '1':
                    jump_goods(content);
                    break;
                //店铺
                case '2':
                    jump_store({'store_id': content});
                    break;

                case '3':
                    break;
            }
        }
    },
    /**
     *  处理商品信息数据
     * @param v   商品数据   [
     *          shop_price:商品价格,
     *          is_group:是否拼团,
     *          is_bargain:是否砍价,
     *          is_limit:是否限时,
     *          group_num:拼团人数,
     *          group_price:拼团价格
     *          cut_price:砍价价格
     *          time_limit_price:限时抢购价格
     *          ]
     * @param discount   折扣
     * return data 返回数据   [
     *          label_str:商品状态标签
     *          price:商品当前价格
     *          group_str:商品拼团状态标签
     *                  ]
     */
    goods_label: function (v, discount) {
        var shop = ['自营', '个人', '公司'];
        var label = [shop[v.shop]];     //商品标签组合字符串
        var data = {};
        data.price = v.shop_price;       //商品价格
        data.label_str = '';             //商品标签字符串
        data.group_str = '';             //商品参加拼团字符串
        //判断当前商品处于哪个活动中
        switch (v.is_group.toString() + v.is_bargain.toString() + v.is_limit.toString()) {
            case '100':
                data.price = v.group_price;
                data.group_str = '<span class="group-buying primary-background-color">' + v.group_num + '人拼</span>';
                label.push('拼团');
                break;
            case '010':
                data.price = v.cut_price;
                data.group_str = '<span class="group-buying primary-background-color">' + '砍价</span>';
                label.push('砍价');
                break;
            case '001':
                data.price = v.time_limit_price;
                label.push('抢购');
                break;
            default :
                if (v.is_vip === 1) {
                    data.price = (parseFloat(v.shop_price) * parseInt(discount)).toFixed(2);
                }

        }
        //处理商品标签
        $.each(label, function (k, v) {
            data.label_str += '<span style="margin-right: 2px;" class="mark primary-color border-color">' + v + '</span>';
        });
        return data;
    },
    /**
     * 倒计时
     * @param param[
     *          time_select:时间字符串显示文本容器对象    每个元素对象身上应该有data(time_str)属性
     *          function_name:倒计时调用函数
     *          callback:倒计时时间到后处理函数
     *          dispose_callback:时间赋值回调
     *          ]
     */
    count_down: function (param) {
        //获取当前时间
        var date = new Date();
        var now = date.getTime();
        param.time_select.each(function (k, v) {
            //倒计时时间
            var time_str = $(v).data('time_str');
            //如果时间为-1则是时间到了之后后台没有修改状态
            if (time_str != -1) {
                //设置截止时间
                var endDate = new Date(time_str);
                var end = endDate.getTime();
                //时间差
                var leftTime = end - now;
                //定义变量 d,h,m,s保存倒计时的时间
                var y, h, m, s;
                if (leftTime >= 0) {
                    y = Math.floor(leftTime / 1000 / 60 / 60 / 24); //天
                    h = Math.floor(leftTime / 1000 / 60 / 60 % 24);      //时
                    m = Math.floor(leftTime / 1000 / 60 % 60);           //分
                    s = Math.floor(leftTime / 1000 % 60);                //秒
                    y = (y < 10 ? '0' + y : y);
                    h = (h < 10 ? '0' + h : h);
                    m = (m < 10 ? '0' + m : m);
                    s = (s < 10 ? '0' + s : s);
                    var html_str = '';
                    var time = {'y': y, 'h': h, 'm': m, 's': s};
                    if ('dispose_callback' in param) {
                        html_str = param.dispose_callback(time);
                    } else {
                        html_str = h + ':' + m + ':' + s;
                    }
                    //将倒计时赋值到div中
                    $(v).html(html_str);
                } else {
                    return param.callback();
                }
            }
        });
        //递归每秒调用countTime方法，显示动态时间效果
        setTimeout(param.function_name, 1000);
    },
    /**
     * 倒计时 new
     * @param param[
     *          time_select:时间字符串显示文本容器对象    每个元素对象身上应该有data(time_str)属性
     *          function_name:倒计时调用函数
     *          callback:倒计时时间到后处理函数
     *          dispose_callback:时间赋值回调
     *          ]
     */
    count_down_new: function (param) {
        //倒计时时间
        var time_str = param.count_down;
        console.log(time_str);
        var setTimeout_fun = function () {
            param.time_select.each(function (k, v) {
                //如果时间为-1则是时间到了之后后台没有修改状态
                if (time_str != -1) {
                    time_str--;
                    var leftTime = time_str;
                    //定义变量 d,h,m,s保存倒计时的时间
                    var d, h, m, s;
                    if (time_str >= 0) {
                        d = Math.floor(leftTime / 60 / 60 / 24); //天
                        h = Math.floor(leftTime / 60 / 60 % 24);      //时
                        m = Math.floor(leftTime / 60 % 60);           //分
                        s = Math.floor(leftTime % 60);                //秒
                        d = (d < 10 ? '0' + d : d);
                        h = (h < 10 ? '0' + h : h);
                        m = (m < 10 ? '0' + m : m);
                        s = (s < 10 ? '0' + s : s);
                        var html_str = '';
                        var time = {'d': d, 'h': h, 'm': m, 's': s};
                        if ('dispose_callback' in param) {
                            html_str = param.dispose_callback(time);
                        } else {
                            html_str = h + ':' + m + ':' + s;
                        }
                        //将倒计时赋值到div中
                        $(v).html(html_str);
                    } else {
                        clearTimeout(setTimeout_fun);
                        return param.callback();
                    }
                }
            });

            //递归每秒调用countTime方法，显示动态时间效果
            setTimeout(setTimeout_fun, 1000);
        }
        setTimeout_fun()
    },
    // 上传文件
    upload: function (obj) {
        $("#submit").attr("disabled", "disabled");
        var data = '';
        try {
            var formData = new FormData();
            var file_name = '';
            $.each(obj.data, function (k, v) {
                formData.append(k.toString(), v);
            });
            //判断是单个还是多个文件
            if ('one' in obj) {
                if (typeof obj.select[0].files[0] != 'undefined') {
                    formData.append(obj.file_name, obj.select[0].files[0]);
                }
                file_name = obj.file_name;
            } else {
                obj.select.each(function (k, v) {
                    if (typeof $(v)[0].files[0] != 'undefined') {
                        formData.append(obj.file_name + k, $(v)[0].files[0]);
                    }
                });
                file_name = obj.file_name + 0;
            }
            //如果有文件则上传
            if (formData.has(file_name)) {
                $.ajax({
                    url: obj.url || "/v2.0/image/app_upload",
                    type: "post",
                    data: formData,
                    contentType: false,
                    processData: false,
                    async: false,
                    mimeType: "multipart/form-data",
                    success: function (res) {
                        var _res = eval('(' + res + ')');
                        if (_res.code.toString() === '0') {
                            data = _res.url;
                        }
                    },
                });
            }
        } catch (e) {
            console.log(e);
        }
        $("#submit").removeAttr("disabled");
        return data;
    },
    /**
     *
     * @param obj[
     *             'express_value':快递编号,
     *             'express_number':快递单号,
     *             'order_id':订单ID,
     *             'type':订单ID,分类 积分订单：integral 普通订单：order 抽奖订单：draw 发票：invoice
     *             ]
     */
    express_view: function (obj) {
        main.ajax({
            'url': '/v2.0/express/view',
            'data': {
                'express_value': obj.express_value,
                'express_number': obj.express_number,
                'order_id': obj.order_id,
                'type': obj.type,
            },
            'callback': function (res) {
                if (!('status' in res.result) || res.result.status.toString() !== '200') {
                    layer.msg(res.result.message);
                } else {
                    var html = '<div>' + res.result.express_name + ':' + res.result.nu + '</div>';
                    $.each(res.result.data, function (k, v) {
                        html += '<div>' + v.time + ':' + v.context + '。</div>';
                    });
                    layer.open({
                        type: 1,
                        shadeClose: true,
                        title: '物流详情',
                        content: html,
                        area: ['50%', '40%'],
                    });
                }
            }
        });
    },
    //单删,多删
    destroy: function (url, id, msg) {
        if (id == undefined || !id) {
            id = extra.selected();
            if (!id) return false;
        }
        layer.msg(msg ? msg : '确定删除吗?', {
            offset: '400px',
            btn: ['确定', '取消'], yes: function () {
                main.ajax({
                    type: 'post', url: url, data: {'id': id}, callback: function (res) {
                        res.reload = true;
                        main.callback(res);
                    }
                });
            }, btn2: function () {
                layer.msg('操作已取消', {offset: '400px'});
            }
        });
    },
    msg: function (msg) {
        layer.msg(msg, {offset: 'auto'});
    },
    reload: function (param) {
        var self_url = window.location.href;
        var jump_url = window.location.href;
        if (typeof param != 'undefined' && param.constructor === Object) {
            if ('type' in param) {
                switch (param.type) {
                    //登录
                    case 1:
                        if (self_url.indexOf('cart/index') !== -1) {
                            jump_url = '/pc2.0/cart/index';
                        }
                        break;
                    //退出登录
                    case 1:
                        if (self_url.indexOf('cart/index') !== -1) {
                            jump_url = '/pc2.0/cart/index?identification=' + localStorage.identification;
                            return;
                        }
                        break;
                }
            }
        }
        window.location.href = jump_url;
    }
};


function get_valid_options(param) {
    return {
        type: "post",
        dataType: "json",
        resetForm: false,
        ind: 0,
        headers: {
            "token": get_token(),
        },
        beforeSend: function (request, from) {
            // 禁用按钮防止重复提交
            $((param.constructor === Object && 'btn_submit' in param) ? param.btn_submit : "#submit").attr("disabled", "disabled");
            this.ind = layer.load(1, {shade: 0.2, time: 3000}); //0代表加载的风格，支持0-2
        },
        success: (param.constructor === Object && 'success' in param) ? param.success : function (data, textStatus, resObj) {
            if (data.code === undefined) {
                return;
            }
            set_token(resObj, false);
            layer.msg(data.message, {
                time: 1200,
                offset: '400px', end: function () {
                    //如果是令牌无效则跳转到登录
                    if (data.code === -201 || data.code === -200) {
                        main.loginOut(2);
                        return;
                    }
                    if (data.code !== 0) {
                        $(".layui-btn").removeAttr("disabled");
                        return;
                    }
                    if (data.url) {
                        window.location.href = data.url;
                        //判断是否有回调地址
                    } else if (sessionStorage.return_url && sessionStorage.return_url !== '') {
                        var json_url = ObjectOrJson(sessionStorage.return_url);
                        //如果回跳地址转化后是对象  则判断是否应用到当前页面
                        if (json_url.constructor === Object) {
                            //如果限制标识存在则判断是否是当前页面
                            if (('use_url' in json_url) && window.location.href.indexOf(json_url.use_url) != -1) {
                                sessionStorage.return_url = '';
                                window.location.href = json_url.return_url;
                                return;
                            }
                            main.reload();
                        } else {
                            var return_url = sessionStorage.return_url;
                            sessionStorage.return_url = '';
                            if (!RegExp(/\/.*?/).test((return_url)) && sessionStorage.return_parame) {
                                return_url = return_url + '?' + sessionStorage.return_parame;
                            }
                            window.location.href = return_url;
                        }
                    } else {
                        main.reload();
                    }
                }
            });
        },
        complete: function () {
            layer.close(this.ind);
            $((param.constructor === Object && 'btn_submit' in param) ? param.btn_submit : "#submit").removeAttr("disabled");
        }
    };
}

var extra = {
    options: {
        type: "post",
        dataType: "json",
        resetForm: false,
        ind: 0,
        headers: {
            "token": get_token(),
        },
        beforeSend: function (request, from) {
            // 禁用按钮防止重复提交
            $("#submit").attr("disabled", "disabled");
            this.ind = layer.load(1, {shade: 0.2, time: 3000}); //0代表加载的风格，支持0-2
        },
        success: function (data, textStatus, resObj) {
            set_token(resObj, false);
            layer.msg(data.message, {
                time: 1200,
                offset: '400px', end: function () {
                    //如果是令牌无效则跳转到登录
                    if (data.code === -201 || data.code === -200) {
                        main.loginOut(2);
                        return;
                    }
                    if (data.code !== 0) {
                        $(".layui-btn").removeAttr("disabled");
                        return;
                    }
                    if (data.url) {
                        window.location.href = data.url;
                        //判断是否有回调地址
                    } else if (sessionStorage.return_url && sessionStorage.return_url !== '') {
                        var return_url = sessionStorage.return_url;
                        sessionStorage.return_url = '';
                        if (!RegExp(/\/.*?/).test((return_url)) && sessionStorage.return_parame) {
                            return_url = return_url + '?' + sessionStorage.return_parame;
                        }
                        window.location.href = return_url;
                    } else {
                        main.reload();
                    }
                }
            });
        },
        complete: function (a, b, c) {
            layer.close(this.ind);
            $("#submit").removeAttr("disabled");
        }
    },
};
//我的页面二级菜单和左侧选中样式关系映射
var url_map = {
    //我的订单
    '/order/order_list': [
        '/order/order_details',
    ],
    //线下订单
    '/order/orderunderlinelist': [
        '/order/orderunderlinedetails'
    ],
    //退款售后
    '/order/orderaftersalelist': [
        '/order/refundDetails',
        '/order/apply_for_after_sale',
    ],
    //我的积分
    '/integral/my': [
        '/integral/detail',
        '/integral/conversion_record',
        '/integral/conversion_view',
    ],
    //账户安全
    '/setting/safety': [
        '/setting/update_password',
        '/setting/update_phone',
        '/setting/edit_password',
        '/setting/update_pay_password',
        '/setting/forget_pay_password',
    ],
    //收益
    '/distribution_my/earnings_view': [
        '/distribution_my/earnings_details',
        '/distribution_withdrawal/index',
        '/distribution_withdrawal/record',
    ],
    //粉丝
    '/distribution_my/fans': [
        '/distribution_my/fans_earnings_details',
    ],
};

//设置token  is_empty  是否可设置空token
function set_token(resObj, is_empty) {
    var token = '';
    if (resObj.constructor === Object) {
        var _token = resObj.getResponseHeader('token') || resObj.getResponseHeader('Token');
        if (_token + '' !== '500') {
            token = _token;
        }
    }
    if (resObj.constructor === String) {
        token = resObj;
    }
    if (is_empty) {
        localStorage.token = token;
    } else {
        localStorage.token = token || localStorage.token;
    }
}

//设置sessionStorage
function set_sessionStorage(param) {
    sessionStorage[param.name] = param.value;
}

//获取sessionStorage
function get_sessionStorage(name) {
    return sessionStorage[name];
}

//设置localStorage
function set_localStorage(param) {
    localStorage[param.name] = param.value;
}

//获取localStorage
function get_localStorage(name) {
    return localStorage[name];
}

//获得token
function get_token() {
    //is_valid  用户登录标识
    if (!$('is_valid').length || !localStorage.token || !isNaN(parseInt(localStorage.token))) {
        $.ajax({
            type: 'post',
            url: '/pc2.0/my/get_token',
            dataType: 'json',
            async: false,
            complete: function (resObj) {
                set_token(resObj, true);
            }
        });
    }
    return localStorage.token;
}

//跳转商品详情
function jump_goods(goods_id) {
    main.ajax({
        'url': '/pc2.0/goods/goods_status',
        'data': {'goods_id': goods_id.goods_id || goods_id},
        'not_load': true,
        'callback': function (res) {
            if (res.code === 0) {
                layer.msg(res.message);
            } else {
                var jump_info = {'url': '/pc2.0/goods/view?goods_id=' + (goods_id.goods_id || goods_id)};
                if (goods_id.constructor === Object) {
                    if ('is_open' in goods_id) {
                        jump_info.is_open = goods_id.is_open;
                    }
                }
                main.jump(jump_info);
            }
        }
    });
}

//取消订单
function order_cancel(order_attach_id) {
    main.ajax({
        'url': '/v2.0/order/cancel',
        'data': {'order_attach_id': order_attach_id},
        'confirm_text': '是否确认取消订单'
    });
}

//删除订单
function destroy_order(order_attach_id) {
    main.ajax({
        'url': '/v2.0/order/destroyOrder',
        'data': {'order_attach_id': order_attach_id},
        'confirm_text': '是否确认删除订单'
    });
}

//确认收货
function confirm_collect(order_attach_id) {
    main.ajax({
        'url': '/v2.0/order/confirmCollect',
        'data': {'order_attach_id': order_attach_id},
        'confirm_text': '是否确认收货',
    });
}

//申请售后
function after_sale(order_goods_id) {
    main.jump({
        'url': '/pc2.0/order/apply_for_after_sale?order_goods_id=' + order_goods_id
    });
}

//售后详情
function after_sale_details(order_goods_id) {
    main.jump({
        'url': '/pc2.0/order/refundDetails?order_goods_id=' + order_goods_id + '&lat=' + localStorage.lat + '&lng=' + localStorage.lng
    });
}

//评价
function evaluate(order_attach_id) {
    main.jump({
        'url': '/pc2.0/evaluate/report?order_attach_id=' + order_attach_id
    });
}

//跳转店铺
function jump_store(param) {
    main.jump({
        'url': '/pc2.0/store/index?store_id=' + param.store_id
    });
}

/**
 *商品详情店铺收藏|取消收藏[
 *              type:1 收藏 2 取消,
 *              store_id: 店铺id
 */
function goods_collect_store(e, param) {
    switch (param.type.toString()) {
        case '1':
            main.ajax({
                'url': '/v2.0/store/collect_store', 'data': {'store_id': param.store_id}, 'callback': function (res) {
                    if (res.code === 0) {
                        param.type = 2;
                        $(e).attr('onclick', "goods_collect_store(this," + JSON.stringify(param).replace(/"/g, "'") + ")");
                        $(e).children('img').attr('src', '/template/computer/resource/imgs/start_on.png');
                        $(e).children('span').text('取消收藏');
                    } else {
                        layer.msg(res.message);
                    }
                }
            });

            break;
        case '2':
            main.ajax({
                'url': '/v2.0/store/view_collect_store_delete',
                'data': {'store_id': param.store_id},
                'callback': function (res) {
                    if (res.code === 0) {
                        param.type = 1;
                        $(e).attr('onclick', "goods_collect_store(this," + JSON.stringify(param).replace(/"/g, "'") + ")");
                        $(e).children('img').attr('src', '/template/computer/resource/imgs/start_off.png');
                        $(e).children('span').text('收藏店铺');
                    } else {
                        layer.msg(res.message);
                    }
                }
            });
            break;
    }
}

/**
 * 商品详情商品关注|取消关注[
 *              type:1 关注 2 取消,
 *              goods_id: 商品id
 *              ]
 */
function goods_collect_goods(e, param) {
    switch (param.type.toString()) {
        case '1':
            main.ajax({
                'url': '/v2.0/goods/collect_goods',
                'data': {'store_id': param.store_id, 'goods_id': param.goods_id},
                'callback': function (res) {
                    if (res.code === 0) {
                        if ('success_function1' in param) {
                            param.success_function1(res);
                            layer.msg('关注成功');
                            return;
                        }
                        param.type = 2;
                        $(e).attr('onclick', "goods_collect_goods(this," + JSON.stringify(param).replace(/"/g, "'") + ")");
                        $(e).children('img').attr('src', '/template/computer/resource/imgs/ysc.png');
                        $(e).children('span').text('取消关注');
                    } else {
                        layer.msg(res.message);
                    }
                }
            });

            break;
        case '2':
            main.ajax({
                'url': '/v2.0/goods/view_collect_goods_delete',
                'data': {'goods_id': param.goods_id},
                'callback': function (res) {
                    if (res.code === 0) {
                        if ('success_function2' in param) {
                            param.success_function2(res);
                            return;
                        }
                        param.type = 1;
                        $(e).attr('onclick', "goods_collect_goods(this," + JSON.stringify(param).replace(/"/g, "'") + ")");
                        $(e).children('img').attr('src', '/template/computer/resource/imgs/sc.png');
                        $(e).children('span').text('关注商品');
                    } else {
                        layer.msg(res.message);
                    }
                }
            });
            break;
    }


}

/**
 * 跳转购物车
 */
function jump_cart() {
    main.jump({
        'url': ($('is_valid').length > 0 ? '/pc2.0/cart/index' : '/pc2.0/cart/index?identification=' + localStorage.identification),
    });
}

/**
 *获得内网ip  未登录加入购物车用
 * @param callback
 */
function getIP(callback) {
    //内网ip
    var recode = {};
    var RTCPeerConnection = window.RTCPeerConnection || window.mozRTCPeerConnection || window.webkitRTCPeerConnection;
    // 如果不存在则使用一个iframe绕过
    if (!RTCPeerConnection) {
        // 因为这里用到了iframe，所以在调用这个方法的script上必须有一个iframe标签
        // <iframe id="iframe" sandbox="allow-same-origin" style="display:none;"></iframe>
        $('body').append('<iframe id="ip_iframe" sandbox="allow-same-origin" style="display:none;"></iframe>');
        // var win = iframe.contentWindow;
        var win = $('#ip_iframe')[0].contentWindow;
        RTCPeerConnection = win.RTCPeerConnection || win.mozRTCPeerConnection || win.webkitRTCPeerConnection;
    }
    //创建实例，生成连接
    var pc = new RTCPeerConnection();

    // 匹配字符串中符合ip地址的字段
    function handleCandidate(candidate) {
        var ip_regexp = /([0-9]{1,3}(\.[0-9]{1,3}){3}|([a-f0-9]{1,4}((:[a-f0-9]{1,4}){7}|:+[a-f0-9]{1,4}){6}))/;
        var ip_isMatch = candidate.match(ip_regexp)[1];

        if (!recode[ip_isMatch]) {
            callback(ip_isMatch);
            recode[ip_isMatch] = true;
        }
    }

    //监听icecandidate事件
    pc.onicecandidate = function (ice) {
        if (ice.candidate) {
            handleCandidate(ice.candidate.candidate);
        }
    };
    //建立一个伪数据的通道
    pc.createDataChannel('');
    pc.createOffer(function (res) {
        pc.setLocalDescription(res);
    }, function () {
    });
    //延迟，让一切都能完成
    setTimeout(function () {
        var lines = pc.localDescription.sdp.split('\n');
        lines.forEach(function (item) {
            if (item.indexOf('a=candidate:') == 0) {
                handleCandidate(item);
            }
        });
    }, 1000);
}

/**
 * 获取未登录时唯一标识
 */
function get_identification() {
    if (!localStorage.identification) {
        //公网ip
        $.getScript('//pv.sohu.com/cityjson?ie=utf-8', function () {
            getIP(function (public_ip) {
                localStorage.identification = (public_ip + returnCitySN['cip']);
            });
        });
    }
}

//领取优惠券
function get_coupon(param) {
    var data = {'coupon_id': param.coupon_id};
    switch (param.type.toString()) {
        case '0':
            data.store_id = param.classify_str;
            break;
        case '1':
            data.goods_classify_id = param.classify_str;
            break;
    }
    sessionStorage.return_url = ObjectOrJson({'use_url': 'login/index', 'return_url': window.location.href});
    main.ajax({
        'url': '/v2.0/member_coupon/get',
        'data': data,
    });
}


//对象转json字符串
function ObjectOrJson(val) {
    if (val === undefined) return '';
    //判断是不是对象  是对象转json  不是判断是否是json字符串  不是则返回自己
    return (val.constructor === Object) ? JSON.stringify(val) : (RegExp(/^{.*?}$/).test(val) ? JSON.parse(val) : val);
}


//数字千分制展示
function toThousands(num) {
    return parseFloat(num).toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, '$1,');
}

//数字千分制展示
function toThousands1(num, toFixed) {
    return parseFloat(num).toFixed(toFixed).replace(/(\d)(?=(\d{3})+\.)/g, '$1,');
}

//协议 规则
function agreement(id, title) {

    main.ajax({
        'url': '/pc2.0/html/ajax_article_view', 'data': {'article_id': id,}, callback: function (t) {
            if (t.code === 0) {
                layer.open({
                    type: 1,
                    shadeClose: true,
                    title: title,
                    content: t.result.content,
                    area: ['50%', '60%'],
                });
            } else {
                layer.msg(t.message, {time: 500});
            }
        }
    });
}

/**
 * 公共省市区四级联动选择
 * @param  param  Array [select:绑定对象(.a|#a|a)]
 * .common-address-input   省市区地址输入赋值所处标识
 */
function common_address_select(param) {
    var select = $(param.select);
    //如果没初始化过才初始化绑定事件
    if ($('div[id^="common_address_index_"]').length <= 0) {
        //地址选择样式插入
        $('head').append('<style>.common-address-linkage{position:relative;z-index:1000}.common-address-linkage .common-select-address .common-detailed-box{padding:10px;box-sizing:border-box;width:446px;border:1px solid #ccc;position:absolute;left:0;top:25px;font-size:12px;background:#fff}.common-address-linkage .common-select-address .common-detailed-box .common-detailed-box-top{height:24px;position:relative}.common-address-linkage .common-select-address .common-detailed-box .common-detailed-box-top:after{content:\'\';display:block;width:100%;height:2px;background:#ea5413;position:absolute;top:24px}.common-address-linkage .common-select-address .common-detailed-box .common-detailed-box-top .close{float:right;position:relative;top:7px}.common-address-linkage .common-select-address .common-detailed-box .common-detailed-box-top .common-address-text{float:left;height:24px;max-width:95px;padding-left:10px;padding-right:24px;line-height:24px;border:1px solid #ccc;border-bottom:0;margin-right:5px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;box-sizing:border-box;position:relative;z-index:1;background:#fff;cursor:pointer}.common-address-linkage .common-select-address .common-detailed-box .common-detailed-box-top .common-address-text a{color:#333}.common-address-linkage .common-select-address .common-detailed-box .common-detailed-box-top .common-address-text img{position:absolute;right:5px;top:7px}.common-address-linkage .common-select-address .common-detailed-box .common-detailed-box-top .active{border:2px solid #ea5413;border-bottom:0;height:26px}.common-address-linkage .common-select-address .common-detailed-box .common-address-detailed{margin-top:8px;padding:0 15px;min-height:70px;max-height:150px;overflow:auto;-ms-overflow-style:none;overflow:-moz-scrollbars-none}.common-address-linkage .common-select-address .common-detailed-box .common-address-detailed::-webkit-scrollbar{width:0!important}.common-address-linkage .common-select-address .common-detailed-box .common-address-detailed a{display:inline-block;padding:5px 8px;color:#333;margin-right:10px;margin-top:10px;cursor:pointer}.common-address-linkage .common-select-address .common-detailed-box .common-address-detailed .checked{color:#fff}</style>');
        //已选地址栏事件监听
        select.parent('div').on('click', '.common-address-text', function () {
            var num = $(this).index();
            //当前选择级别地址显示  其他隐藏
            $('.common-address-detailed').eq(num).show().siblings('.common-address-detailed').hide();
            if (num === 0 || $(this).prev().hasClass('finish')) {
                $(this).addClass('active').siblings('.common-address-text').removeClass('active');
            }
        });
        //注册地址选择事件监听
        select.parent('div').on('click', '.common-address-detailed > a', function () {
            //获取当前元素最外层元素
            var top_element = $(this).closest('div[id^="common_address_index_"]');
            //获取当前点击所绑定元素
            var bind_element = select.eq(top_element.data('index'));
            //初始化当前地址选择框所赋值城市地区input的id
            var clear_site = [];
            bind_element.parent('div').find('.common-address-input input').each(function (k, v) {
                clear_site.push($(v).attr('id'));
            });
            //获取当前选中的地址
            var txt = $(this).text();
            //获取当前是第几个地址选择
            var address_index = top_element.data('index');
            //切换选中样式
            $(this).addClass('primary-background-color checked').siblings('a').removeClass('primary-background-color checked');
            //获取当前选择地址id
            var parent_id = $(this).attr('data_id');
            var index, district;
            //判断是点击省市区不同操作
            switch ($(this).parent('div').attr('id')) {
                case 'province_' + address_index:
                    txt += '/';
                    index = 0;
                    district = 'city';
                    top_element.find('.common-address-detailed').eq(1).show().siblings('.common-address-detailed').hide();
                    break;
                case 'city_' + address_index:
                    txt += '/';
                    index = 1;
                    district = 'area';
                    top_element.find('.common-address-detailed').eq(2).show().siblings('.common-address-detailed').hide();
                    break;
                case 'area_' + address_index:
                    txt += '/';
                    index = 2;
                    district = 'street';
                    top_element.find('.common-address-detailed').eq(3).show().siblings('.common-address-detailed').hide();
                    break;
                case 'street_' + address_index:
                    index = 3;
                    district = undefined;
                    break;
                default :
                    return;
            }
            //设置当前选择省市区数据
            $('#' + clear_site[index]).val($(this).text());
            //清除数据
            $.each(clear_site, function (k, v) {
                if (k > index) {
                    console.log($('#' + v));
                    $('#' + v).val('');
                }
            });
            //设置当前选中的对应地址
            top_element.find('.common-address-text').eq(index).find('a').html(txt);
            //设置当前元素选中标识
            top_element.find('.common-address-text').eq(index).addClass('finish');
            //设置下一级地址选中样式和显示
            top_element.find('.common-address-text').eq(index).next('div').show().addClass('active').siblings().removeClass('active');
            //设置当前选择的下一个地址之后的全部隐藏
            top_element.find('.common-address-text').eq(index).next('div').html('<a href="javascript:;">--请选择--</a><img src="/template/computer/resource/imgs/my/datebottom.png" alt="">').nextAll('.common-address-text').hide();

            //如果
            if (bind_element.find('span').html() == '--请选择--') {
                bind_element.find('span').html('');
            }
            if (bind_element.find('span i').eq(index).length != 0) {
                bind_element.find('span i').eq(index).html(txt).nextAll().remove();
            } else {
                bind_element.find('span').append("<i>" + txt + "</i>");
            }

            //城市联动
            if (district) {
                var str = '';
                if (district === 'street') {
                    str += '<a class="' + district + '" data_id="000">暂不选择</a>';
                }
                str += get_address_linkage({'parent_id': parent_id});
                $('#' + district + '_' + address_index).html(str);
            } else {
                top_element.hide();
            }
        });
        //注册地址选择关闭事件监听
        select.parent('div').on('click', '.common-address-close', function () {
            $(this).closest('div[id^="common_address_index_"]').hide();
        });
    }
    //地址选择框展示隐藏
    select.click(function () {
        var that = $(this);
        //当前第几个地址选择
        var this_index = select.index(that);
        //判断对应地址是否初始化过
        if ($('#common_address_index_' + this_index).length > 0) {
            $('#common_address_index_' + this_index).toggle();
        } else {
            //初始化获取省级地址
            var provincial = get_address_linkage({index: this_index});
            //初始化获取省级地址
            var provincial = get_address_linkage({index: this_index});
            //初始化获取省级地址
            var provincial = get_address_linkage({index: this_index});
            //初始化获取省级地址
            var provincial = get_address_linkage({index: this_index});
            //地址选择html拼接
            var html = '<div id="common_address_index_' + this_index + '" class="common-address-linkage" data-index="' + this_index + '" style="top:' + $(this).data('top') + 'px">\n' +
                '    <div class="common-select-address">\n' +
                '        <div class="common-detailed-box">\n' +
                '            <div class="common-detailed-box-top">\n' +
                '                <div class="common-address-text active">\n' +
                '                    <a href="javascript:;">--请选择--</a>\n' +
                '                    <img src="/template/computer/resource/imgs/my/datebottom.png" alt="">\n' +
                '                </div>\n' +
                '                <div class="common-address-text" style="display:none;">\n' +
                '                    <a href="javascript:;">--请选择--</a>\n' +
                '                    <img src="/template/computer/resource/imgs/my/datebottom.png" alt="">\n' +
                '                </div>\n' +
                '                <div class="common-address-text" style="display:none;">\n' +
                '                    <a href="javascript:;">--请选择--</a>\n' +
                '                    <img src="/template/computer/resource/imgs/my/datebottom.png" alt="">\n' +
                '                </div>\n' +
                '                <div class="common-address-text" style="display:none;">\n' +
                '                    <a href="javascript:;">--请选择--</a>\n' +
                '                    <img src="/template/computer/resource/imgs/my/datebottom.png" alt="">\n' +
                '                </div>\n' +
                '                <img class="close common-address-close" src="/template/computer/resource/imgs/my/address-close.png" alt="">\n' +
                '            </div>\n' +

                '            <div class="common-address-detailed" id="province_' + this_index + '">' + provincial + '</div>' +
                '            <div class="common-address-detailed" id="city_' + this_index + '" style="display: none">\n' +
                '                <!--市-->\n' +
                '            </div>\n' +
                '            <div class="common-address-detailed" id="area_' + this_index + '" style="display: none">\n' +
                '                <!--区-->\n' +
                '            </div>\n' +
                '            <div class="common-address-detailed" id="street_' + this_index + '" style="display: none">\n' +
                '                <!--街道-->\n' +
                '            </div>\n' +
                '        </div>\n' +
                '    </div>\n' +
                '</div>';
            $(that).after(html);
        }
    });
}

//获得城市联动
function get_address_linkage(param) {
    var str = '';
    if (sessionStorage['address_' + (param.parent_id || 0)] && sessionStorage['address_' + (param.parent_id || 0)] != '') {
        str = sessionStorage['address_' + (param.parent_id || 0)];
    } else {
        main.ajax({
            'url': '/pc2.0/address/linkage',
            async: false,
            data: {'parent_id': param.parent_id || 0},
            callback: function (t) {
                if (t.code !== 0) {
                    layer.msg(t.message);
                } else {
                    $.each(t.result, function (k, v) {
                        str += '<a data_id="' + v.area_id + '">' + v.area_name + '</a>';
                    });
                    sessionStorage['address_' + (param.parent_id || 0)] = str;
                }
            }
        });
    }
    return str;
}

function show_invoice_info(res) {
    //设置函数调用计数器
    ('show_invoice_info_count' in window) ? ++window.show_invoice_info_count : window.show_invoice_info_count = 0;
    main.ajax({
        'url': '/pc2.0/invoice_explain/detail',
        'data': {'order_attach_id': res.order_attach_id},
        'callback': function (res) {
            if (res.code == 0) {
                if (window.show_invoice_info_count == 0) {
                    $('head').eq(0).append('<style>.invoice-box .invoice-tit{line-height:58px;font-size:14px;font-weight:bolder;background:#fff;text-indent:20px;margin-bottom:20px}.invoice-box .invoice-con{background:#fff;padding:20px}.invoice-box .invoice-group{padding:20px 0;border-bottom:1px solid #e5e5e5}.invoice-box .invoice-group .invoice-list{overflow:hidden;font-size:14px;color:#666;padding-top:5px}.invoice-box .invoice-group .invoice-list .invoice-title{float:left}.invoice-box .invoice-group .invoice-list .invoice-content{float:left}.invoice-box .invoice-group .invoice-list .invoice-content .invoice-btn{display:inline-block;padding:3px 5px;border:1px solid #e5e5e5;border-radius:3px;color:#666;margin-left:15px;float:right;margin-top:-4px}.invoice-box .invoice-group .btn{color:#fff;display:block;width:120px;height:40px;text-align:center;line-height:40px;font-size:14px;border-radius:5px;margin-top:20px}</style>');
                }
                var result = res.result;
                //billing_type = 0 未开票
                //查看物流
                var express_view_str = result.billing_type == 0 && result.express_value == '' ? '' : '<a class="btn primary-background-color" href="JavaScript:;" onclick="main.express_view({express_number:\'' + result.express_number + '\',express_value:\'' + result.express_value + '\',order_id:' + result.order_attach_id + ',type:\'invoice\'})">查看物流</a>';
                //查看发票
                var express_invoice_str = result.billing_type == 0 ? '' : '<a class="invoice-btn" href="javascript:;main.jump({\'url\':\'' + result.download_links + '\',is_open:true})">查看发票</a>';
                //修改发票
                var amend_invoice_str = result.status == 0 ? '<a class="btn primary-background-color" href="JavaScript:layer.closeAll();$(\'.invoice-modify-box\').show();" onclick="">修改发票</a>' : '';
                //发票内容
                var invoice_str = '<div class="invoice-box" style="">\n' +
                    '    <div class="invoice-con"><div class="invoice-group">\n' +
                    '            <div class="invoice-list">\n' +
                    '                <div class="invoice-title">发票状态：</div>\n' +
                    '                <div class="invoice-content">' + (result.billing_type == 0 ? '未开票' : '已开票') + '</div>\n' +
                    '            </div>\n' +
                    '        </div>\n' +
                    '        <div class="invoice-group">\n' +
                    '            <div class="invoice-list">\n' +
                    '                <div class="invoice-title">订单编号：</div>\n' +
                    '                <div class="invoice-content">' + result.order_attach_number + '</div>\n' +
                    '            </div>\n' +
                    '            <div class="invoice-list">\n' +
                    '                <div class="invoice-title">下单时间：</div>\n' +
                    '                <div class="invoice-content">' + result.create_time + '</div>\n' +
                    '            </div>' +
                    '</div>';
                //invoice_type  发票类型：0普通发票 1增值税发票;rise 发票抬头：1个人 2公司;invoice_open_type 发票开具后类型 0电子 1纸质 2增值税发票
                //根据发票类型判断展示展示不同内容
                switch (result.invoice_type.toString() + result.rise.toString() + result.invoice_open_type) {
                    //普通个人纸质
                    case '011':
                        invoice_str += '<div class="invoice-group">\n' +
                            '            <div class="invoice-list">\n' +
                            '                <div class="invoice-title">发票类型：</div>\n' +
                            '                <div class="invoice-content">普通发票</div>\n' +
                            '            </div>\n' +
                            '          <div class="invoice-list">\n' +
                            '                <div class="invoice-title">发票抬头：</div>\n' +
                            '                <div class="invoice-content">' + +result.rise_name + +'</div>\n' +
                            '            </div>\n' +
                            '          <div class="invoice-list">\n' +
                            '                <div class="invoice-title">发票内容：</div>\n' +
                            '                <div class="invoice-content">' + result.detail_type + '</div>\n' +
                            '            </div>' + express_view_str + '</div>';
                        break;
                    //普通个人电子
                    case '010':
                        invoice_str += '<div class="invoice-group">\n' +
                            '            <div class="invoice-list">\n' +
                            '                <div class="invoice-title">发票类型：</div>\n' +
                            '                <div class="invoice-content">普通发票\n' + express_invoice_str +
                            // '<a class="invoice-btn" href="JavaScript:;"></a>\n' +
                            '                </div>\n' +
                            '            </div>\n' +
                            '            <div class="invoice-list">\n' +
                            '                <div class="invoice-title">发票抬头：</div>\n' +
                            '                <div class="invoice-content">' + +result.rise_name + '</div>\n' +
                            '            </div>\n' +
                            '            <div class="invoice-list">\n' +
                            '                <div class="invoice-title">发票内容：</div>\n' +
                            '                <div class="invoice-content">' + result.detail_type + '</div>\n' +
                            '            </div>' + amend_invoice_str + '</div>';
                        break;
                    //普通企业纸质
                    case '021':
                        invoice_str += '<div class="invoice-group">\n' +
                            '            <div class="invoice-list">\n' +
                            '                <div class="invoice-title">发票类型：</div>\n' +
                            '                <div class="invoice-content">普通发票</div>\n' +
                            '            </div>\n' +
                            '            <div class="invoice-list">\n' +
                            '                <div class="invoice-title">发票抬头：</div>\n' +
                            '                <div class="invoice-content">' + result.rise_name + '</div>\n' +
                            '            </div>\n' +
                            '            <div class="invoice-list">\n' +
                            '                <div class="invoice-title">纳税人识别号：</div>\n' +
                            '                <div class="invoice-content">' + result.taxer_number + '</div>\n' +
                            '            </div>\n' +
                            '            <div class="invoice-list">\n' +
                            '                <div class="invoice-title">发票内容：</div>\n' +
                            '                <div class="invoice-content">' + result.detail_type + '</div>\n' +
                            '            </div>' + amend_invoice_str + express_view_str + '</div>';
                        break;
                    //普通企业电子
                    case '020':
                        invoice_str += '<div class="invoice-group">\n' +
                            '            <div class="invoice-list">\n' +
                            '                <div class="invoice-title">发票类型：</div>\n' +
                            '                <div class="invoice-content">普通发票\n' + express_invoice_str +
                            //'                    <a class="invoice-btn" href="JavaScript:;">发送邮箱</a>\n' +
                            '                </div>\n' +
                            '            </div>\n' +
                            '            <div class="invoice-list">\n' +
                            '                <div class="invoice-title">发票抬头：</div>\n' +
                            '                <div class="invoice-content">' + result.rise_name + '</div>\n' +
                            '            </div>\n' +
                            '            <div class="invoice-list">\n' +
                            '                <div class="invoice-title">纳税人识别号：</div>\n' +
                            '                <div class="invoice-content">' + result.taxer_number + '</div>\n' +
                            '            </div>\n' +
                            '            <div class="invoice-list">\n' +
                            '                <div class="invoice-title">发票内容：</div>\n' +
                            '                <div class="invoice-content">' + result.detail_type + '</div>\n' +
                            '            </div>' + amend_invoice_str + '</div>';
                        break;
                    //增值税专用发票
                    case '122':
                        invoice_str += '<div class="invoice-group">\n' +
                            '            <div class="invoice-list">\n' +
                            '                <div class="invoice-title">发票类型：</div>\n' +
                            '                <div class="invoice-content">增值税专用发票</div>\n' +
                            '            </div>\n' +
                            '            <div class="invoice-list">\n' +
                            '                <div class="invoice-title">发票抬头：</div>\n' +
                            '                <div class="invoice-content">' + result.rise_name + '</div>\n' +
                            '            </div>\n' +
                            '            <div class="invoice-list">\n' +
                            '                <div class="invoice-title">纳税人识别号：</div>\n' +
                            '                <div class="invoice-content">' + result.taxer_number + '</div>\n' +
                            '            </div>\n' +
                            '            <div class="invoice-list">\n' +
                            '                <div class="invoice-title">注册地址： </div>\n' +
                            '                <div class="invoice-content">' + result.address + '</div>\n' +
                            '            </div>\n' +
                            '            <div class="invoice-list">\n' +
                            '                <div class="invoice-title">注册电话：</div>\n' +
                            '                <div class="invoice-content">' + result.phone + '</div>\n' +
                            '            </div>\n' +
                            '            <div class="invoice-list">\n' +
                            '                <div class="invoice-title">开户行：</div>\n' +
                            '                <div class="invoice-content">' + result.bank + '</div>\n' +
                            '            </div>\n' +
                            '            <div class="invoice-list">\n' +
                            '                <div class="invoice-title">开户账号：</div>\n' +
                            '                <div class="invoice-content">' + result.account + '</div>\n' +
                            '            </div>\n' +
                            '            <div class="invoice-list">\n' +
                            '                <div class="invoice-title">发票内容：</div>\n' +
                            '                <div class="invoice-content">' + result.detail_type + '</div>\n' +
                            '            </div>' + express_view_str + '</div>';
                        break;
                }
                invoice_str += '</div></div>';
                var invoice_index = layer.open({
                    type: 1,
                    shadeClose: true,
                    title: '发票详情',
                    content: invoice_str,
                    offset: '20%',
                    area: ['35%'],
                });
            } else {
                layer.msg('网络繁忙');
            }
        }
    });
}

/**
 * 监听storage改变事件   同域下多窗口通信用
 * @param {String} key   监听的key
 * @param {Function} fn   改变后回调函数
 */
function storageChange(key, fn) {//key为要存储的名字，fn为触发storage后要执行的方法，value可以自己设置存的值，可以利用这个值跨页面传参
    // var val = value ? JSON.stringify(value) : new Date().getTime()//为value设置默认值
    // localStorage.setItem(key,val)
    window.addEventListener('storage', function (e) {
        if (e.key == key) {//判断是否是目标值发生改变
            fn(e);//执行fn，返回新值和旧值
        }
    });
}

/**
 * 跳转客服
 * @param {Object} param {store_id:店铺id,goods_id:商品id,diversion_id:分流id}
 */
function jump_service(param) {
    //如果为登录跳转到登录
    if ($('is_valid').length <= 0) {
        layer.msg('请先登录', function () {
            main.jump({
                url: '/pc2.0/login/index',
                return_url: ObjectOrJson({return_url: window.location.href, use_url: 'login/index'})
            });
        });
        return;
    }
    var _url = '/pc2.0/customer/customer_index?pid=' + param.store_id;
    //商品id
    if ('goods_id' in param) {
        set_localStorage({
            name: 'service_goods_id_' + param.store_id,
            value: ObjectOrJson({
                goods_id: param.goods_id,
                is_send: false,
                goods_name: param.goods_name,
                goods_price: param.goods_price,
                goods_file: param.goods_file
            }),
        });
    } else {
        //清空
        set_localStorage({
            name: 'service_goods_id_' + param.store_id,
            value: ''
        });
    }
    ;
    //分流id
    if ('diversion_id' in param) {
        set_localStorage({
            name: 'service_diversion_id_' + param.store_id,
            value: ObjectOrJson({
                diversion_id: param.diversion_id,
            }),
        });
    } else {
        //清空
        set_localStorage({
            name: 'service_diversion_id_' + param.store_id,
            value: ''
        });
    }
    ;
    main.jump({
        url: _url,
        is_open: true,
        open_name: 'service_name',
        open_specs: 'height=800,width=1130,titlebar=0,left=100px'
    });
}


//获得storage  已用大小
function get_cache_size(t) {
    t = t == undefined ? "l" : t;
    var obj = "";
    if (t === 'l') {
        if (!window.localStorage) {
            console.log('浏览器不支持localStorage');
        } else {
            obj = window.localStorage;
        }
    } else {
        if (!window.sessionStorage) {
            console.log('浏览器不支持sessionStorage');
        } else {
            obj = window.sessionStorage;
        }
    }
    if (obj !== "") {
        var size = 0;
        for (item in obj) {
            if (obj.hasOwnProperty(item)) {
                size += obj.getItem(item).length;
            }
        }
        console.log('当前已用存储：' + (size / 1024).toFixed(2) + 'KB');
    }
}


//获得浏览器类型
function browserType() {
    var userAgent = navigator.userAgent; //取得浏览器的userAgent字符串
    var isOpera = false;
    if (userAgent.indexOf('Edge') > -1) {
        return "Edge";
    }
    if (userAgent.indexOf('.NET') > -1) {
        return "IE";
    }
    if (userAgent.indexOf("Opera") > -1 || userAgent.indexOf("OPR") > -1) {
        isOpera = true;
        return "Opera";
    }
    ; //判断是否Opera浏览器
    if (userAgent.indexOf("Firefox") > -1) {
        return "FF";
    } //判断是否Firefox浏览器
    if (userAgent.indexOf("Chrome") > -1) {
        return "Chrome";
    }
    if (userAgent.indexOf("Safari") > -1) {
        return "Safari";
    } //判断是否Safari浏览器
    if (userAgent.indexOf("compatible") > -1 && userAgent.indexOf("MSIE") > -1 && !isOpera) {
        return "IE";
    }
    ; //判断是否IE浏览器
}

//js html 标签转化实体
function htmlspecialchars(str) {
    var s = "";
    return str;
    if (str.length == 0) return "";
    for (var i = 0; i < str.length; i++) {
        switch (str.substr(i, 1)) {
            case "<":
                s += "&lt;";
                break;
            case ">":
                s += "&gt;";
                break;
            // case "&":
            //     s += "&amp;";
            //     break;
            // case " ":
            //     if (str.substr(i + 1, 1) == " ") {
            //         s += " &nbsp;";
            //         i++;
            //     } else s += " ";
            //     break;
            case "\"":
                s += "&quot;";
                break;
            // case "\n":
            //     s += "<br>";
            //     break;
            default:
                s += str.substr(i, 1);
                break;
        }
    }
    return s;
}


function getCookie(c_name) {
    if (document.cookie.length > 0) {
        var c_start = document.cookie.indexOf(c_name + "=");
        if (c_start != -1) {
            c_start = c_start + c_name.length + 1;
            var c_end = document.cookie.indexOf(";", c_start);
            if (c_end == -1) c_end = document.cookie.length;
            return unescape(document.cookie.substring(c_start, c_end));
        }
    }
    return "";
}

// 返回顶部
$('.back-top').click(function () {
    $('html,body').animate({scrollTop: 0}, 300)
});

