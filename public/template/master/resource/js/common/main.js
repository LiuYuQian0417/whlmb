(function (window, $) {
    'use strict';
    //拓展
    var extra = {
        options: {
            type: "post",
            dataType: "json",
            resetForm: false,
            ind: 0,
            beforeSend: function (request, from) {
                // 禁用按钮防止重复提交
                $(".layui-btn").attr("disabled", "disabled");
                this.ind = layer.load(1, {shade: 0.2, time: 3000}); //0代表加载的风格，支持0-2
                // dataTables表单额外数据
                var spec = $('.spec_table');
                if (spec.length && spec.dataTable() != undefined) {
                    var dt = spec.dataTable().api();
                    from.data = from.data + '&' + dt.$(':input').serialize();
                }
            },
            success: function (data) {
                layer.msg(data.message, {offset: '400px'});
                if (data.code !== 0) {
                    $(".layui-btn").removeAttr("disabled");
                }
                var top = $('.nc-module-menu .nc-row', window.parent.document).find('li.active'), topK;
                topK = top.attr('data-param');
                var left = $('#navTabs_' + topK, parent.document).find('li.active').index();
                if (data.url && data.url.indexOf('/') === 0) {
                    data.url = data.url.substr(1);
                }
                if (data.code === 0) {
                    setTimeout(function () {
                        if (data.url) {
                            window.parent.openItem(topK + '|' + left + '|' + data.url);
                        } else {
                            location.reload();
                        }
                    }, 500);
                }
            },
            error:function(){
                $(".layui-btn").removeAttr("disabled");
            },
            complete: function () {
                // layer.close(this.ind);
                layer.closeAll('loading');
            }
        },
        selected: function () {
            //查看当前选中的复选框
            var checked = $('tbody .chk:checked');
            if (checked.length === 0) {
                layer.msg('请选择操作数据', {offset: '400px', time: 2000});
                return false;
            }
            var id = [];
            checked.each(function (i, o) {
                id.push($(o).val());
            });
            if (id.length > 0) id = id.join(',');
            return id;
        },
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
                offset: '400px',
                time: 2000
            });
            if (result.code === 0) {
                setTimeout(function () {
                    if (result.url) {
                        location.href = result.url;
                    } else {
                        if (result.reload) {
                            location.reload();
                        }
                    }
                }, 500);
            }
        },
        // 查看文本编辑
        viewText: function (id, parameter, url) {
            $('#' + parameter + id).html('<input type="text" maxlength="6" style="text-align: center" id="data' + id + '" value="' + $('#' + parameter + id).text() + '" autocomplete="off" placeholder="请输入分类名称" class="layui-input" onblur=main.editText("' + id + '","' + parameter + '","' + url + '")>');
            // $('#' + parameter + id).find('input').focus();
        },
        //改变文本编辑
        editText: function (id, parameter, url) {
            main.ajax({
                type: 'post', url: url, data: {'id': id, 'parameter': parameter, 'data': $('#data' + id).val()}
                , callback: function (res) {
                    res.reload = false;
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
                    res.reload = false;
                    main.callback(res);
                }
            });
        },
        //备份数据库
        backup: function (url) {
            main.ajax({
                type: 'post', url: url, data: '', callback: function (res) {
                    res.reload = false;
                    main.callback(res);
                }
            });
        },
        // 网站地图
        sitMap: function (url) {
            main.ajax({
                type: 'get', url: url, data: '', callback: function (res) {
                    res.reload = false;
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
                    res.reload = false;
                    main.callback(res);
                }
            });
        },
        //清理缓存(开发用)
        clearCacheTest: function () {
            var obj = ['flatMaster'];
            main.ajax({
                type: 'post', url: '/data_base/clear_cache', data: {'selected': obj}, callback: function (res) {
                    res.reload = false;
                    main.callback(res);
                }
            });
        },
        //退出登录
        loginOut: function () {
            layer.msg('确定退出登录吗?', {
                offset: '400px',
                btn: ['确定', '取消'], time: 2000, yes: function () {
                    main.ajax({
                        type: 'post', url: '/login/outLogin', data: '', callback: function (res) {
                            res.reload = true;
                            main.callback(res);
                        }
                    });
                }
            });
        },
        //:TODO 修改表单验证可传入回调函数
        //valid表单验证提交
        valid: function (Selector) {
            if(typeof(Selector)!='string'){
                var call_back=Selector[1];
                Selector=Selector[0];
            }
            $.Tipmsg.r = null;
            $.Tipmsg.c = null;
            $.Tipmsg.p = null;
            $(Selector).Validform({
                //默认绑定提交按钮
                btnSubmit: '#submit',
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
                ignoreHidden: true,
                beforeCheck: typeof call_back!= "undefined"?call_back:function(formObj){

                },
                beforeSubmit: function (formObj) {
                    $(Selector).ajaxSubmit(extra.options);
                    return false;
                },
                usePlugin: {}
            });
        },
        //跳转4级导航
        jumpFour: function (url, topK, left) {
            try {
                topK = window.parent.Y_data_param[url.split('?')[0]]['topK'];
                left = window.parent.Y_data_param[url.split('?')[0]]['leftK'];
            } catch (e) {
                var top = $('.nc-module-menu .nc-row', window.parent.document).find('li.active');

            }
            if (topK == undefined) topK = top.attr('data-param');
            if (left == undefined) left = $('#navTabs_' + topK, parent.document).find('li.active').index();
            window.parent.openItem(topK + '|' + left + '|' + url);
        },
        //获取textarea标签中的换行符和空格
        getMatCode: function (value) {
            return value.replace(/\r\n/g, ',').replace(/\n/g, ',')
        },
        rightBatchChoose: function () {
            //查看当前选中的复选框
            var checked = $('.chk:checked');
            if (checked.length === 0) {
                layer.msg('请选择商品', {offset: '400px', time: 2000});
                return;
            }
            var id = [];
            checked.each(function () {
                id.push($(this).val());
            });
            if (id.length > 0) id = id.join(',');
            $('.relation-right-container').html('');
            main.ajax({
                type: 'post', url: '/goods_batch_operation/get_goods', data: {'id': id}, callback: function (res) {
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
                        $('.chkAll').prop('checked', checked);
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
                        o.url = '/file_act/upload';
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
            })
        },
        //上架,下架
        shelves: function (url, id, type,message) {
            if (id == undefined || !id) {
                id = extra.selected();
                if (!id) return false;
            }
            layer.msg(message === undefined?'确定要批量操作吗？':message, {
                offset: 'auto',
                time:false,
                btn: ['确定', '取消'], yes: function () {
                    main.ajax({
                        type: 'post', url: url, data: {'id': id, type: type}, callback: function (res) {
                            res.reload = true;
                            main.callback(res);
                        }
                    });
                }, btn2: function () {
                    layer.msg('操作已取消', {offset: window.pageYOffset + 400 + 'px'});
                }
            })
        },
        // 单个/多个恢复
        recover: function (url, id) {
            if (id == undefined || !id) id = extra.selected();
            layer.msg('确定恢复吗?', {
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
            })
        },
        // 卸载
        unload: function (url, id) {
            if (id == undefined || !id) id = extra.selected();
            layer.msg('确定卸载该支付方式吗?', {
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
            })
        },
        //保存权限数据
        saveAuthData: function (url, data) {
            main.ajax({
                type: 'post', url: url, data: data, callback: function (res) {
                    res.reload = false;
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
                    shade: 0,
                    area: param.area,
                    content: param.openUrl
                })
            }
        }
    };
})(window, window.jQuery);





