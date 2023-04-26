//拓展
var extra = {
    options: {
        type: "post",
        dataType: "json",
        resetForm: false,
        ind: 0,
        ajaxJumpTimeOut:500,
        beforeSend: function (request, from) {
            // 禁用按钮防止重复提交
            $(".layui-btn").attr("disabled", "disabled");
            this.ind = layer.load(1, {shade: [0.1, '#fff']}); //0代表加载的风格，支持0-2
            // dataTables表单额外数据
            var spec = $('.spec_table');
            if (spec.length && spec.dataTable() != undefined) {
                var dt = spec.dataTable().api();
                from.data = from.data + '&' + dt.$(':input').serialize();
            }
        },
        success: function (data) {
            // layer.msg(data.message);
            // if (data.msg !== undefined){
            //     layer.msg(data.msg, {time:1000,offset: '300px',end:function () {
            //             location.reload()
            //         }});
            // } else{
            //     layer.msg(data.message, {time:1000,offset: '300px',end:function () {
            //             location.reload()
            //         }});
            // }
            layer.msg(data.message || data.msg, {offset: '400px'});
            if (data.code !== 0) {
                $(".layui-btn").removeAttr("disabled");
            }
            if (data.code == 0) {
                if (data.sid) {
                    window.sessionStorage.setItem('sid', data.sid);
                }
                var topK = $('.ecsc-nav .ecsc-nav-ul', window.parent.document).find('li.current').attr('data-param'),
                    leftK = $('#navTabs_' + topK, window.parent.document).children('.current').index();
                setTimeout(function () {
                    if (topK == undefined && leftK == -1) {
                        location.href = data.url;
                    } else {
                        if (data.url.indexOf('/') === 0) {
                            data.url = data.url.substr(1);
                        }
                        window.parent.reashItem(topK + '|' + leftK + '|' + data.url);
                    }
                }, extra.options.ajaxJumpTimeOut);
            }
        },
        complete: function () {
            layer.close(this.ind);
            $(".layui-btn").removeAttr("disabled");
        }
    },
    selected: function () {
        //查看当前选中的复选框
        var checked = $('tbody .chk:checked');
        if (checked.length === 0) {
            layer.msg('请选择操作数据', {offset: '300px', time: 2000});
            return;
        }
        var id = [];
        checked.each(function (i, o) {
            id.push($(o).val());
        });
        if (id.length > 0) id = id.join(',');
        return id;
    }
};
window.main = {
    //ajax调用
    ajax: function (param) {
        var loadIndex = 0;
        $.ajax({
            type: param.type || 'post',
            url: param.url,
            data: param.data || '',
            dataType: 'json',
            beforeSend: function (request) {
                loadIndex = layer.load(1, {shade: [0.1, '#fff']});
            },
            success: param.callback,
            complete: function () {
                layer.close(loadIndex);
            },
            error: function (request, err) {
                layer.msg(err, {time: 2000});
            }
        });
    },
    // ajax回调函数
    callback: function (result) {
        if (result.message) layer.msg(result.message || '操作完成', {
            offset: '300px',
            time: 2000
        });
        if (result.code === 0) {
            setTimeout(function () {
                if (result.url) {
                    location.href = result.url;
                } else {
                    location.reload();
                }
            }, 1000);
        }
    },
    // 查看文本编辑
    viewText: function (id, parameter, url) {
        $('#' + parameter + id).html('<input type="text" maxlength="8" style="text-align: center" id="data' + id + '" value="' + $('#' + parameter + id).text() + '" autocomplete="off" placeholder="请输入分类名称" class="layui-input" onblur=main.editText("' + id + '","' + parameter + '","' + url + '")>');
        // $('#' + parameter + id).find('input').focus();
    },
    //改变文本编辑
    editText: function (id, parameter, url) {
        main.ajax({
            type: 'post', url: url, data: {'id': id, 'parameter': parameter, 'data': $('#data' + id).val()}
            , callback: function (res) {
                main.callback(res);
            }
        });
        $("#" + parameter + id).html("<span class='onpress' onclick=main.viewText('" + id + "','" + parameter + "','" + url + "')>" + $('#data' + id).val() + "</span>");
    },
    // 实时文本编辑
    triggerText: function (id, parameter, url) {

        main.ajax({
            type: 'post', url: url, data: {'id': id, 'parameter': parameter, 'data': $('#' + parameter + id).val()}
            , callback: function (res) {
                main.callback(res);
            }
        });
    },
    //备份数据库
    backup: function (url) {
        main.ajax({
            type: 'post', url: url, data: '', callback: function (res) {
                main.callback(res);
            }
        });
    },
    // 网站地图
    sitMap: function (url) {
        main.ajax({
            type: 'get', url: url, data: '', callback: function (res) {
                main.callback(res);
            }
        });
    },
    //清除缓存
    clearCache: function () {
        var checked = $('[name="like[]"]:checked'), obj = [];
        if (checked.length === 0) {
            layer.msg('请至少选择一项进行清除');
            return false;
        }
        checked.each(function (i, o) {
            obj.push($(o).val());
        });
        main.ajax({
            type: 'post', url: '/data_base/clear_cache', data: {'selected': obj}, callback: function (res) {
                main.callback(res);
            }
        });
    },
    //清理缓存(开发用)
    clearCacheTest: function () {
        var obj = ['flatClient'];
        main.ajax({
            type: 'post', url: '/client/data_base/clear_cache', data: {'selected': obj}, callback: function (res) {
                main.callback(res);
            }
        });
    },
    //退出登录
    loginOut: function () {
        layer.msg('确定退出登录吗?', {
            offset: '300px',
            btn: ['确定', '取消'], time: 2000, yes: function () {
                main.ajax({
                    type: 'post', url: '/client/login/out_login', data: '', callback: function (res) {
                        res.reload = true;
                        main.callback(res);
                    }
                });
            }
        });
    },
    //valid表单验证提交
    valid: function (Selector) {
        $.Tipmsg.r = null;
        $.Tipmsg.c = null;
        $.Tipmsg.p = null;
        $(Selector).Validform({
            //默认绑定提交按钮
            btnSubmit: '#submit',
            tipSweep: true,
            tiptype: function (msg) {
                layer.msg(msg, {offset: '300px', time: 2000});
                // layer.msg(msg, {'auto', time: 2000});
            },
            datatype: {
                alphaNum: /^[a-zA-Z0-9]{1,20}$/,
                password: /^\S{6,18}$/
            },
            ajaxPost: true,
            //忽略验证隐藏表单元素
            ignoreHidden: true,
            beforeCheck: function (formObj) {

            },
            beforeSubmit: function (formObj) {
                $(Selector).ajaxSubmit(extra.options);
                return false;
            },
            usePlugin: {}
        });
    },
    //跳转4级导航
    jumpFour: function (url, topK, leftK) {
        try {
            topK = window.parent.Y_data_param[url.split('?')[0]]['topK'];
            leftK = window.parent.Y_data_param[url.split('?')[0]]['leftK'];
        } catch (e) {
            topK = $('.ecsc-nav .ecsc-nav-ul', window.parent.document).find('li.current').attr('data-param'),
                leftK = $('#navTabs_' + topK, window.parent.document).children('.current').index();
            window.parent.openItem(topK + '|' + leftK + '|' + url);
        }
        window.parent.reashItem(topK + '|' + leftK + '|' + url);
        // if (topK === undefined) {
        //     var topK = $('.ecsc-nav .ecsc-nav-ul', window.parent.document).find('li.current').attr('data-param'),
        //         leftK = $('#navTabs_' + topK, window.parent.document).children('.current').index();
        //     window.parent.openItem(topK + '|' + leftK + '|' + url);
        // } else {
        // topK = window.parent.Y_data_param[url.split('?')[0]]['topK'];
        // leftK = window.parent.Y_data_param[url.split('?')[0]]['leftK'];
        // window.parent.reashItem(topK + '|' + leftK + '|' + url);
        // }
    },
    //获取textarea标签中的换行符和空格
    getMatCode: function (value) {
        return value.replace(/\r\n/g, ',').replace(/\n/g, ',')
    },
    rightBatchChoose: function () {
        //查看当前选中的复选框
        var checked = $('.chk:checked');
        if (checked.length === 0) {
            layer.msg('请选择商品', {offset: '300px', time: 2000});
            return;
        }
        var id = [];
        checked.each(function () {
            id.push($(this).val());
        });
        if (id.length > 0) id = id.join(',');
        main.ajax({
            type: 'post',
            url: '/client/goods_batch_operation/get_goods',
            data: {'id': id},
            callback: function (res) {
                var html = '<ul>';
                $.each(res, function (i, o) {
                    html += '<li>' +
                        '<input type="checkbox" value="' + o.goods_id + '" title="' + o.goods_name + '" lay-skin="primary" class="c_right" lay-filter="c_right">' +
                        '<div class="layui-unselect layui-form-checkbox" lay-skin="primary"><span>' + o.goods_name + '</span><i class="layui-icon layui-icon-ok"></i></div>' +
                        '</li>'
                });
                html += '</ul>';

                $('.relation-right-container').append(html);
                main.form();
            }
        });
        return id;
    },
    //layui form模块
    form: function (obj) {
        layui.use(['form'], function () {
            var formObj = layui.form;
            if (obj == undefined || !$.isArray(obj)) obj = [];
            // 表单全选
            obj.push({
                selector: 'checkbox(chkAll)', callback: function (data) {
                    var checked = data.elem.checked;
                    $('.chk').each(function (i, o) {
                        $(o).prop('checked', checked);
                    });
                    formObj.render();
                }
            }, {
                selector: 'checkbox(chk)', callback: function (data) {

                    var checked = $('.chk:checked').length == $('.chk').length;
                    $('.chkAll').prop('checked', checked);
                    formObj.render();
                }
            });
            $.each(obj, function (i, o) {
                if (o.hasOwnProperty('selector') && o.hasOwnProperty('callback')) {
                    formObj.on(o.selector, function (data) {
                        if (typeof o.callback == 'function')
                            o.callback.call(formObj, data, formObj);
                    });
                }
            });
            formObj.render();
        });
    },
    // 上传文件
    upload: function (obj) {
        layui.use('upload', function () {
            var upload = layui.upload;
            $.each(obj, function (i, o) {
                if (!o.hasOwnProperty('url'))
                    o.url = '/client/file_act/upload';
                if (!o.hasOwnProperty('accept'))
                    o.accept = 'images';
                if (!o.hasOwnProperty('acceptMime'))
                    o.acceptMime = 'image/*';
                o.method = 'post';
                o.before = function () {
                    layer.load(1, {shade: [0.1, '#fff']});
                };
                upload.render(o);
            });
        })
    },
    //layui laydate模块
    laydate: function (obj) {
        layui.use('laydate', function () {
            var laydate = layui.laydate;
            if (obj == undefined || !$.isArray(obj)) obj = [];

            $.each(obj, function (i, o) {
                laydate.render(o);
            });
            laydate.render({
                elem: '#hours',
                type: 'time',
                range: true,
                format: 'HHmm'
            })
        });
    },
    //上架,下架
    shelves: function (url, id, type) {
        if (id == undefined || !id) {
            id = extra.selected();
            if (!id) return false;
        }
        layer.msg('确定要批量操作吗？', {
            offset: 'auto',
            btn: ['确定', '取消'], yes: function () {
                main.ajax({
                    type: 'post', url: url, data: {'id': id, type: type}, callback: function (res) {
                        res.reload = true;
                        main.callback(res);
                    }
                });
            }, btn2: function () {
                layer.msg('操作已取消', {offset: '300px'});
            }
        })
    },
    //单删,多删
    destroy: function (url, id) {
        if (id == undefined || !id) {
            id = extra.selected();
            if (!id) return false;
        }
        layer.msg('确定删除吗?', {
            offset: '300px',
            btn: ['确定', '取消'], yes: function () {
                main.ajax({
                    type: 'post', url: url, data: {'id': id}, callback: function (res) {
                        res.reload = true;
                        main.callback(res);
                    }
                });
            }, btn2: function () {
                layer.msg('操作已取消', {offset: '300px'});
            }
        })
    },
    switchEnabled: function (type, elem, id) {
        main.ajax({
            type: 'post', url: '/client/store/switch_enabled',
            data: {
                type: type,
                enable: elem.checked ? 1 : 0,
                store_id: id
            },
            callback: function (res) {
                console.log(elem.checked)
                if (res.code == 0) {
                    layer.msg('操作成功');
                } else {
                    layer.msg(res.message);
                    elem.checked = !elem.checked
                    main.form()
                }
            }
        });
    },
    // 单个/多个恢复
    recover: function (url, id) {
        if (id == undefined || !id) id = extra.selected();
        layer.msg('确定恢复吗?', {
            offset: '300px',
            btn: ['确定', '取消'], yes: function () {
                main.ajax({
                    type: 'post', url: url, data: {'id': id}, callback: function (res) {
                        res.reload = true;
                        main.callback(res);
                    }
                });
            }, btn2: function () {
                layer.msg('操作已取消', {offset: '300px'});
            }
        })
    },
    // 发票冲红
    flushing: function (url, id) {
        if (id == undefined || !id) id = extra.selected();
        var stagger = $(":input[name='stagger']").val();
        layer.msg('确定冲红吗?', {
            offset: '300px',
            btn: ['确定', '取消'], yes: function () {
                main.ajax({
                    type: 'post', url: url, data: {'id': id, 'stagger': stagger}, callback: function (res) {
                        res.reload = true;
                        main.callback(res);
                    }
                });
            }, btn2: function () {
                layer.msg('操作已取消', {offset: '300px'});
            }
        })
    },
    // 卸载
    unload: function (url, id) {
        if (id == undefined || !id) id = extra.selected();
        layer.msg('确定卸载该支付方式吗?', {
            offset: '300px',
            btn: ['确定', '取消'], yes: function () {
                main.ajax({
                    type: 'post', url: url, data: {'id': id}, callback: function (res) {
                        res.reload = true;
                        main.callback(res);
                    }
                });
            }, btn2: function () {
                layer.msg('操作已取消', {offset: '300px'});
            }
        })
    },
    //保存权限数据
    saveAuthData: function (url, data) {
        main.ajax({
            type: 'post', url: url, data: data, callback: function (res) {
                main.callback(res);
            }
        });
    },
    tools: {
        sort: function (m) {
            if (m.length == 1) {
                // 不可上下移动
                m.find('.tools .move-up').addClass('disabled')
                    .siblings('.tools .move-down').addClass('disabled');
            } else if (m.length > 1) {
                m.each(function (i, o) {
                    if ($(o).index() === 0) {
                        // 第一个元素不可向上移动,可向下移动
                        $(o).find('.tools .move-up').addClass('disabled')
                            .siblings('.tools .move-down').removeClass('disabled');
                    } else if ($(o).index() === (m.length - 1)) {
                        // 最后一个元素不可向下移动,可向上移动
                        $(o).find('.tools .move-down').addClass('disabled')
                            .siblings('.tools .move-up').removeClass('disabled');
                    } else {
                        // 其他元素上下正常
                        $(o).find('.tools .move-up').removeClass('disabled')
                            .siblings('.tools .move-down').removeClass('disabled');
                    }
                });
            }
        },
        open: function (param) {
            parent.layer.open({
                title: param.title,
                type: 2,
                shade: 0.3,
                area: param.area,
                content: param.openUrl
            })
        }
    }
};





