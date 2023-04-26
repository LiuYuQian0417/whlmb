if (_user_id) {
    var isHttps = window.location.protocol.indexOf('https') === 0,
        domain = document.domain;
    var ws = new ReconnectingWebSocket((isHttps ? 'wss' : 'ws') + '://' + domain + '/ws', null, {
        reconnectInterval: 1000,
        reconnectDecay: 1,
        timeoutInterval: 3000,
    });
    //websocket 是否连接上
    ws.is_open = false;
    if (ws) {
        ws.onopen = function () {
            ws.is_open = true;
            //登录
            service_login();
            //初始化点击 如果历史记录店铺聊天数大于0
            if ($('div[modification=store_list]').length > 0) {
                $('div[modification=store_list]').eq(0).click();
            }
            marks({type: 'success'});
            //设置心跳
            setTimeout(heartbeat, 1000);
        };

        ws.onmessage = function (data) {
            var message_data = ObjectOrJson(data.data);
            //判断消息类型
            switch (message_data.TYPE) {
                //消息成功服务器回应
                case 'SUCCESS':
                    customer_message_success(message_data.DATA);
                    break;
                //服务器警告
                case 'WARNING':
                    customer_message_warning(message_data.DATA);
                    break;
                //服务器错误
                case 'ERROR':
                    customer_message_error(message_data.DATA);
                    break;
                //服务器消息
                case 'MESSAGE':
                    //判断来消息店铺是否是当前所选店铺
                    if ($('#store_list_' + message_data.DATA.FROM_ID).hasClass('active')) {
                        customer_message_message(message_data.DATA);
                    } else {
                        //来消息不是当前所选店铺
                        $('#store_list_' + message_data.DATA.FROM_ID).addClass('new_message_style');//改变背景颜色
                        $('#store_list_' + message_data.DATA.FROM_ID).attr('new_message','');//打上有新消息标签
                        message.is_show = true;
                        message.show();
                    }
                    break;
            }
        };

        ws.onerror = function () {
            marks({type: 'close'});
            console.log('webcsocket 链接出现错误')
        };

        //连接关闭的回调方法
        ws.onclose = function () {
            marks({type: 'close'});
        };

        //监听窗口关闭事件，当窗口关闭时，主动去关闭websocket连接，防止连接还没断开就关闭窗口，server端会抛异常。
        window.onbeforeunload = function () {
            ws.close();
        };

        //登录
        function service_login() {
            ws.send(JSON.stringify({
                "TYPE": "LOGIN",
                "DATA": {
                    "USER_TYPE": "USER",     // 用户类型
                    "MEMBER_ID": _user_id,       // 用户ID
                    "PLATFORM_ID": "1"         // 平台ID 目前设置为1 固定
                }
            }));
        }

        //发送客服分配
        function send_match_customer(message_info) {
            ws.send(JSON.stringify({
                "TYPE": "MATCH_CUSTOMER",
                "DATA": {
                    "TARGET_ID": message_info.store_id.toString(),           // 接收者店铺ID
                    "DIVERSION_ID": message_info.diversion_id.toString(),         // 客服分流ID
                }
            }))
        }


        //检测当前店铺是否有客服在线
        function check_service_status(message_info) {
            ws.send(JSON.stringify({
                "TYPE": "MATCH_CUSTOMER",
                "DATA": {
                    "TARGET_ID": message_info.store_id.toString(),           // 接收者店铺ID
                    "DIVERSION_ID": message_info.diversion_id.toString(),         // 客服分流ID
                }
            }));
        }

        //心跳
        function heartbeat() {
            ws.send(JSON.stringify({
                "TYPE": "HEART",
            }));
            setTimeout(heartbeat, 59000);
        }

        //发送文本消息
        function send_text_message(message_info, fn) {
            var send_data = {
                "TYPE": "MESSAGE",
                "DATA": {
                    "MESSAGE_ID": get_msel(),    // 字符串类型的毫秒级时间戳
                    "MESSAGE_TYPE": "TEXT",         // 文本
                    "MESSAGE_DATA": message_info.message.toString(),    // 消息内容
                    "TARGET_TYPE": "CUSTOMER",     // 接收者用户类型
                    "TARGET_ID": message_info.store_id.toString(),           // 接收者店铺ID
                    "DIVERSION_ID": message_info.diversion_id.toString(),            // 客服分流ID
                }
            };
            send_message(send_data);
            //发送消息后回调函数
            fn({type: 'USER', message: send_data.DATA});
        }

        //发送图片消息
        function send_image_message(message_info, fn) {
            var send_data = {
                "TYPE": "MESSAGE",
                "DATA": {
                    "MESSAGE_ID": get_msel(),   // 字符串类型的毫秒级时间戳
                    "MESSAGE_TYPE": "IMAGE",                  // 图片
                    "MESSAGE_DATA": message_info.image_path.toString(),   // 图片地址
                    "TARGET_TYPE": "CUSTOMER",                 // 接收者用户类型
                    "TARGET_ID": message_info.store_id.toString(),                       // 接收者店铺ID
                    "DIVERSION_ID": message_info.diversion_id.toString(),            // 客服分流ID
                }
            };
            send_message(send_data);
            //发送消息后回调函数
            fn({type: 'USER', message: send_data.DATA});
        }

        //发送语音消息
        function send_voice_message(message_info) {
            var send_data = {
                "TYPE": "MESSAGE",
                "DATA": {
                    "MESSAGE_ID": get_msel(),    // 字符串类型的毫秒级时间戳
                    "MESSAGE_TYPE": "VOICE",                   // 语音
                    "MESSAGE_DATA": message_info.voice_path.toString(),   // 语音地址
                    "VOICE_TIME": "5",                        // 语音时长(秒)
                    "TARGET_TYPE": "CUSTOMER",                 // 接收者用户类型
                    "TARGET_ID": message_info.store_id.toString(),                       // 接收者店铺ID
                    "DIVERSION_ID": message_info.diversion_id.toString(),            // 客服分流ID
                }
            };
            send_message(send_data);
            //发送消息后回调函数
            fn({type: 'USER', message: send_data.DATA});
        }

        //发送商品消息
        function send_goods_message(message_info, goods_data) {

            var send_data = {
                "TYPE": "MESSAGE",
                "DATA": {
                    "MESSAGE_ID": get_msel(),    // 字符串类型的毫秒级时间戳
                    "MESSAGE_TYPE": "GOODS",                           // 商品
                    "MESSAGE_DATA": message_info.goods_id.toString(),       // 商品ID
                    "TARGET_TYPE": "CUSTOMER",                             // 接收者用户类型
                    "TARGET_ID": message_info.store_id.toString(),                                         // 接收者店铺ID
                    "DIVERSION_ID": message_info.diversion_id.toString(),            // 客服分流ID
                }
            };
            send_message(send_data);

            $('#consult_goods .goods-name').text(goods_data.goods_name);
            $('#consult_goods').find('span.price').text('￥' + goods_data.goods_price);
            $('#consult_goods .goods-img').find('img').attr('src', goods_data.file);
            $('#consult_goods').show();

            send_data.DATA.GOODS_DATA = goods_data;

            var chat_log_html_str = dispose_chat_log({type: 'USER', message: send_data.DATA});
            $('.chat-con').append(chat_log_html_str);
            //设置消息列表滚动条置低
            set_scroll_bar_bottom('.chat-con');

        }

        //发送订单消息
        function send_order_message() {

        }

        //发送消息
        function send_message(send_data) {
            if (ws.is_open) {
                ws.send(JSON.stringify(send_data));
            }
        }
    }
} else {

}

//回流消息(其他客户端登录的账号发送给客服的消息)
function customer_message_reflux_success(data) {
    switch (data.TYPE) {
        //用户
        case 'CUSTOMER':
            var chat_log_html_str = dispose_chat_log({type: 'USER', message: data});
            $('.chat-con').append(chat_log_html_str);
            //设置消息列表滚动条置低
            set_scroll_bar_bottom('.chat-con');
            break;
    }
}

//消息成功服务器返回消息处理
function customer_message_success(data) {
    switch (data.TYPE) {
        //登录成功
        case 'LOGIN':
            //发送客服匹配
            send_match_customer({store_id: store_id, diversion_id: get_diversion_id()});
            break;
        //消息送达(用户已收到消息)
        case 'MESSAGE_DELIVERD':
            break;
        //消息送达(服务器已收到消息)
        case 'MESSAGE_DELIVERD_SERVER':
            break;
        //预分配客服的结果(只有重新分配客服了才会接收到此消息,极端情况下会出现分配了客服但是无法获取此消息的情况,影响不大忽略)
        case 'MATCH_CUSTOMER':
            marks({type: 'match_customer', message: data.MESSAGE});
            break;

    }
}

//服务器警告消息处理函数
function customer_message_warning(data) {
    switch (data.TYPE) {
        //店铺无坐席在线
        case 'STORE_NOT_ONLINE':
            marks({type: 'store_not_online', message: data.MESSAGE});
            break;
    }
}

//服务器错误消息处理函数
function customer_message_error(data) {
    switch (data.TYPE) {
        //您需要登录
        case 'NOT_LOGIN':
            break;
    }
}

//收到服务器消息函数
function customer_message_message(data) {
    //回馈服务器已经收到消息
    ws.send(JSON.stringify({
        "TYPE": "MESSAGE_DELIVERD",
        "DATA": {
            "MESSAGE_ID": data.MESSAGE_ID.toString(),    // 消息ID
            "TARGET_TYPE": "CUSTOMER",     // 收到消息的店铺ID 写死
            "TARGET_ID": data.FROM_ID.toString()            // 对方店铺ID
        }
    }));
    //设置标题闪动
    message.show();
    //判断消息类型对消息做二次处理
    switch (data.MESSAGE_TYPE) {
        //文本消息
        case 'TEXT':
            break;
        //图片消息
        case 'IMAGE':
            break;
        //商品消息
        case 'GOODS':
            //查询商品信息
            main.ajax({
                'url': '/pc2.0/customer/get_goods_info',
                data: {goods_id: data.MESSAGE_DATA},
                async: false,
                not_load:true,
                callback: function (res) {
                    if (res.code == 0) {
                        data.GOODS_DATA = res.data;
                    }
                }
            });
            break;
        //订单消息
        case 'ORDER':
            break;
    }
    var chat_log_html_str = dispose_chat_log({type: 'CUSTOMER', message: data});

    $('.chat-con').append(chat_log_html_str);
    //设置消息列表滚动条置低
    set_scroll_bar_bottom('.chat-con');
}


var marks_timeout = '';

//消息提示
function marks(data) {
    switch (data.type) {
        //连接出错
        case 'error':
            $('#marks').html('&nbsp&nbsp阿偶，消息连接断开了！');
            $('#marks').css({'background-color': '#f7bfbf', 'color': '#f7513b'});
            $('#marks').show();
            break;
        //连接断开
        case 'close':
            $('#marks').html('&nbsp&nbsp阿偶，消息连接断开了！');
            $('#marks').css({'background-color': '#f7bfbf', 'color': '#f7513b'});
            $('#marks').show();
            break;
        //连接成功
        case 'success':
            $('#marks').html('&nbsp&nbsp消息连接成功');
            $('#marks').css({'background-color': '#f0f9c0', 'color': '#68c23f'});
            $('#marks').show();
            if (marks_timeout != '') {
                clearTimeout(marks_timeout);
                marks_timeout = '';
            }
            marks_timeout = setTimeout(function () {
                $('#marks').hide();
            }, 2000);
            break;
        //有客服服务
        case 'match_customer':
            $('#marks').html(data.message);
            $('#marks').css({'background-color': '#f0f9c0', 'color': '#68c23f'});
            $('#marks').show();
            if (marks_timeout != '') {
                clearTimeout(marks_timeout);
                marks_timeout = '';
            }
            marks_timeout = setTimeout(function () {
                $('#marks').hide();
            }, 2000);
            break;
        //无客服在线
        case 'store_not_online':
            $('#marks').html('&nbsp&nbsp' + data.message);
            $('#marks').css({'background-color': '#f7bfbf', 'color': '#f7513b'});
            $('#marks').show();
            if (marks_timeout != '') {
                clearTimeout(marks_timeout);
                marks_timeout = '';
            }
            marks_timeout = setTimeout(function () {
                $('#marks').hide();
            }, 2000);
            break;
        //需要从新登录
        case 'not_login':
            break;
    }

}

// 封装title来新消息闪烁
var message = {
    time: 0,
    title: document.title,
    timer: null,
    is_show: false,
    // 显示新消息提示
    show: function () {
        //如果客服标签页面未显示或已经起过定时器就返回
        if (!this.is_show || this.timer !== null) return;
        var title = message.title.replace("【　　　】", "").replace("【新消息】", "");
        // 定时器，设置消息切换频率闪烁效果就此产生
        message.timer = setInterval(function () {
            message.time++;
            if (message.time % 2 == 0) {
                document.title = "【收到新消息】" + title
            } else {
                document.title = "【　　　】" + title
            };
        }, 300);
        return [message.timer, message.title];
    },
    // 取消新消息提示
    clear: function () {
        clearInterval(message.timer);
        message.timer = null;
        document.title = message.title;
    }
};

//获得当前毫秒级时间戳
function get_msel() {
    return moment().valueOf().toString();
}

