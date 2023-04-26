(function (main) {
    var Notification = window.Notification || window.mozNotification || window.webkitNotification;
    if (!!Notification) {
        var permission = Notification.permission, flag = false;
        if (permission == 'default') {
            Notification.requestPermission().then(function (permission) {
                if (permission == "granted") {
                    flag = true;
                } else {
                    layer.msg("您将收不到任何平台消息", {time: 2000});
                }
            });
        } else if (permission == "denied") {
            // layer.msg("请打开浏览器的桌面通知", {time: 2000});
            console.log("请打开浏览器的桌面通知");
        } else {
            flag = true;
        }
        // 一切都是用户同意接收通知的前提下
        if (flag) {
            var isHttps = window.location.protocol.indexOf('https') === 0,
                domain = document.domain,
                store_id = $('#__store_id__').val(),
                session_id = $('#__session_id__').val();
            var ws = new ReconnectingWebSocket((isHttps ? 'wss' : 'ws') + '://' + domain + '/notify', null, {
                reconnectInterval: 3000,
                reconnectDecay: 0,
                timeoutInterval: 3000,
            });

            var audio = new Audio();
            audio.autoplay = true
            audio.controls = false
            audio.loop = false

            if (ws) {

                console.log('websocket 链接初始化成功')
                console.log(store_id)

                ws.onopen = function () {
                    ws.send(JSON.stringify({
                        'TYPE': 'LOGIN',
                        'DATA': {
                            'TYPE': 'CLIENT',
                            'ID': store_id,
                            'SESSION_ID': session_id,
                        }
                    }))
                }

                ws.onmessage = function (data) {
                    var _data = JSON.parse(data.data)

                    console.log(_data);
                    // 判断参数是否全部存在

                    if (_data['base'] === undefined || _data['data'] === undefined) {
                        return;
                    }

                    if (_data['base']['title'] === undefined) {
                        _data['base']['title'] = ''
                    }

                    if (_data['base']['sound'] === undefined) {
                        _data['base']['sound'] = ''
                    }

                    if (_data['data']['body'] === undefined) {
                        _data['data']['body'] = ''
                    }

                    if (_data['data']['tag'] === undefined) {
                        _data['data']['tag'] = ''
                    }

                    if (_data['data']['icon'] === undefined) {
                        _data['data']['icon'] = ''
                    }

                    if (_data['data']['renotify'] === undefined) {
                        _data['data']['renotify'] = ''
                    }

                    var notification = new Notification(_data['base']['title'], _data['data'])

                    // 注册 通知 点击事件
                    notification.onclick = function () {
                        switch (notification.tag) {
                            case 'orderSuccess':
                                // 跳转到订单详情
                                main.jumpFour('client/order/examine?id=' + _data['base']['id']);
                                break;
                        }
                    }

                    notification.onshow = function () {
                        // 停止播放
                        audio.pause()
                        audio.currentTime = 0
                        // 重新设置 声音
                        audio.src = _data['base']['sound']
                        audio.play()
                    }
                }

                ws.onerror = function () {
                    console.log('webcsocket 链接出现错误')
                }

                //
                // // 初始化客户端
                // var client = Stomp.over(ws);
                // // SockJS不支持心跳,禁用发送和接收的心跳
                // client.heartbeat.outgoing = 30000;
                // client.heartbeat.incoming = 30000;
                // // 声明连接
                // var on_connect = function (x) {
                //     client.subscribe(conf.destination, function (d) {
                //         if (d.body) {
                //             var data = JSON.parse(d.body);
                //         }
                //         var notification = new Notification(data.title || "新消息提醒", data);
                //         // 根据消息标签区分点击事件
                //         notification.onclick = function () {
                //             switch (notification.tag) {
                //                 case 'orderSuccess':
                //                     // 跳转到订单详情
                //                     main.jumpFour('client/order/examine?id=' + data.data.order_attach_id);
                //                     break;
                //             }
                //             notification.close();
                //         };
                //         notification.onshow = function () {
                //             // 窗口显示 播放音频
                //             var audio = new Audio(data.sound);
                //             audio.play();
                //         }
                //     })
                // };
                // // 声明错误
                // var on_error = function (error) {
                //     console.log(error.headers.message);
                // };
                // // 连接stomp
                // client.connect(conf.user, conf.pass, on_connect, on_error, conf.vHost);
                // client.debug = function () {
                //     if (window.console && console.log && console.log.apply && conf.debug) {
                //         console.log.apply(console, arguments);
                //     }
                // };
            }
        }
    } else {
        layer.msg("请更换为支持Notification的浏览器", {time: 2000});
    }
})(main);