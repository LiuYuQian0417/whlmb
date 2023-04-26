(function (main) {
    if (!!window.Notification) {
        var permission = Notification.permission, flag = false;
        if (permission == 'default') {
            Notification.requestPermission().then(function (permission) {
                if (permission == "granted") {
                    flag = true;
                } else {
                    alert("您将收不到任何平台消息");
                }
            });
        } else if (permission == "denied") {
            alert("请打开浏览器的桌面通知");
        } else {
            flag = true;
        }
        // 一切都是用户同意接收通知的前提下
        if (flag) {
            var isHttps = window.location.protocol.indexOf('https') === 0,
                domain = document.domain;
            var conf = {
                user: "ishop_dev",
                pass: "ishop_dev_pwd",
                vHost: "ishop_dev",
                port: 15673,
                destination: "/exchange/Dev_web_rabbit/21",
                debug: true
            };
            var ws = new WebSocket((isHttps ? 'wss' : 'ws') + '://' + domain + ':' + conf.port + '/ws');
            if (ws) {
                // 初始化客户端
                var client = Stomp.over(ws);
                // SockJS不支持心跳,禁用发送和接收的心跳
                client.heartbeat.outgoing = 50000;
                client.heartbeat.incoming = 50000;
                // 声明连接
                var on_connect = function (x) {
                    client.subscribe(conf.destination, function (d) {
                        if (d.body) {
                            var data = JSON.parse(d.body);
                        }
                        console.log(data);
                        var notification = new Notification(data.title || "新消息提醒", data);
                        // 根据消息标签区分点击事件
                        switch (notification.tag) {
                            case 'orderSuccess':
                                // 跳转到订单详情
                                reashItem(5 + '|' + 0 + '|client/order/examine?id='+data.data.order_attach_id)
                                break;
                        }
                    })
                };
                // 声明错误
                var on_error = function (error) {
                    console.log(error.headers.message);
                };
                // 连接stomp
                client.connect(conf.user, conf.pass, on_connect, on_error, conf.vHost);
                client.debug = function () {
                    if (window.console && console.log && console.log.apply && conf.debug) {
                        console.log.apply(console, arguments);
                    }
                };
            }
        }
    } else {
        alert("请更换为支持Notification的浏览器")
    }
})(main);