(function (main) {
    var Notification = window.Notification || window.mozNotification || window.webkitNotification,
        sid = $('is_valid').length > 0 ? $('is_valid').attr('id') : '';
    if (!!Notification) {
        var permission = Notification.permission, flag = false;
        if (permission == 'default') {
            Notification.requestPermission().then(function (permission) {
                if (permission == "granted") {
                    flag = true;
                } else {
                    // layer.msg("您将收不到任何平台消息", {time: 2000});
                }
            });
        } else if (permission == "denied") {
            // layer.msg("请打开浏览器的桌面通知", {time: 2000});
            console.log("请打开浏览器的桌面通知");
        } else {
            flag = true;
        }
        // 一切都是用户同意接收通知的前提下
        if (flag && sid) {
            var isHttps = window.location.protocol.indexOf('https') === 0,
                domain = document.domain;
            var ws = new ReconnectingWebSocket((isHttps ? 'wss' : 'ws') + '://' + domain + '/notify', null, {
                reconnectInterval: 3000,
                reconnectDecay: 0,
                timeoutInterval: 3000,
            });

            if (ws) {
                ws.onopen = function () {
                    ws.send(JSON.stringify({
                        'TYPE': 'LOGIN',
                        'DATA': {
                            'TYPE': 'PC_CLIENT',
                            'ID': sid,
                            'SESSION_ID': getCookie('PHPSESSID'),
                        }
                    }))
                };

                ws.onmessage = function (data) {
                    var _data = JSON.parse(data.data);
                    if ('type' in _data && _data.type == 'message_push') {
                        var notification = new Notification(_data['base']['title'], _data['data']);
                        // 注册 通知 点击事件
                        notification.onclick = function () {
                            //判断类型执行对应操作
                            switch (notification.tag) {
                                case 'userMsgList':
                                    // 跳转到消息列表;
                                    main.jump({url: '/pc2.0/message/index?type=0'});
                                    break;
                            }
                        }
                    }
                };
                ws.onclose = function () {
                    ws.close()
                };
                ws.onerror = function () {
                    console.log('webcsocket 链接出现错误')
                }
            }
        }
    } else {
        // layer.msg("请更换为支持Notification的浏览器", {time: 2000});
    }

    function getCookie(c_name) {
        if (document.cookie.length > 0) {
            var c_start = document.cookie.indexOf(c_name + "=")
            if (c_start != -1) {
                c_start = c_start + c_name.length + 1
                var c_end = document.cookie.indexOf(";", c_start)
                if (c_end == -1) c_end = document.cookie.length
                return unescape(document.cookie.substring(c_start, c_end))
            }
        }
        return ""
    }
})(main);