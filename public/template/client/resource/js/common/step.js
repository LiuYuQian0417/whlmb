(function (window, main, attrControl) {
    'use strict';
    var extra = {
        // 固定提交按钮
        FixBtn: function () {
            var scrollTop = $(window).scrollTop();
            var scrollHeight = $(document).height();
            var windowHeight = $(window).height();
            curFixBtn.css({'box-shadow': (scrollTop + windowHeight == scrollHeight) ? 'none' : '0 -2px 6px #c4c4c4'});
            curFixBtn.css({top: (winH - 118 + scrollTop) + 'px'});
        },
        // 改变固定按钮
        changeBtn: function (num) {
            var prev = $('.step_btn .prev'),
                next = $('.step_btn .next');
            num = parseInt(num);
            console.log(num);
            switch (num) {
                case 1:
                    prev.show().html('上一步，选择商品分类').attr('data-num', num);
                    next.html('下一步，填写商品规格及图片');
                    break;
                case 2:
                    let fright_type = $('[name="freight_status"]:checked').val();
                    let freight_id = $('[name="freight_id"]').val();
                    console.log(fright_type);
                    console.log(freight_id);
                    if (fright_type === '1' && !freight_id) {
                        layer.msg('请设置运费模板');
                        return false;
                    }else{
                        prev.show().html('上一步，填写商品基本信息').attr('data-num', num);
                        next.html('<i class="fa fa-check" aria-hidden="true"></i> 完成，发布商品');
                    }
                    //next.html('下一步，填写商品规格及图片');
                    break;
                //case 3:
                //    prev.show().html('上一步，填写商品规格及图片').attr('data-num',num);
                //    next.html('<i class="fa fa-check" aria-hidden="true"></i> 完成，发布商品');
                //    break;
                case 0:
                    prev.hide().attr('data-num', 0);
                    next.html('下一步，填写商品基本信息');
                    break;
            }
            return true;
        },
        //ajax调用
        ajax: function (param) {
            $.ajax({
                type: param.type || 'post',
                url: param.url,
                data: param.data || '',
                dataType: 'json',
                beforeSend: function (request) {
                    layer.load(1, {shade: [0.1, '#fff']});
                },
                success: param.callback,
                complete: function () {
                    layer.closeAll('loading');
                },
                error: function (request, err) {
                    layer.msg(err, {time: 2000});
                }
            });
        },
        // 获取地址栏参数
        getQueryVar: function (variable) {
            var query = window.location.search.substring(1);
            var vars = query.split("&");
            for (var i = 0; i < vars.length; i++) {
                var pair = vars[i].split("=");
                if (pair[0] == variable) {
                    return pair[1];
                }
            }
            return (false);
        }
    };
    var stepNum = extra.getQueryVar('id') ? 2 : 1;
    $('.stepList:not(:nth-child(' + stepNum + '))').each(function (i, o) {
        if (extra.changeBtn(stepNum == 2 ? 1 : 0) && stepNum == 2){
            $('.step2').addClass('active dynamic');
            $('.step3').addClass('dynamic');
            $(o).hide();
        }
    });
    $(document).on('click', '.brand_form .brand-container .brand-list li', function () {
        $('.brand_form .brand-container').hide();
        $('[name="brand_name"]').val(this.title).css({background: 'url(/template/master/resource/image/common/xljt.png) 280px 12px no-repeat rgb(255, 255, 255)'});
        $('[name="brand_id"]').val(this.value);
        $(this).addClass('choose').siblings('li').removeClass('choose');
    }).on('click', '.brand_keyword .brand-container .brand-list li', function () {
        $('.brand_keyword .brand-container').hide();
        $('.brand_name_keyword').val(this.title).css({background: 'url(/template/master/resource/image/common/xljt.png) 280px 12px no-repeat rgb(255, 255, 255)'});
        $('.brand_id_keyword').val(this.value);
    }).on('click', '.brand_keyword2 .brand-container .brand-list li', function () {
        $('.brand_keyword2 .brand-container').hide();
        $('.brand_name_keyword2').val(this.title).css({background: 'url(/template/master/resource/image/common/xljt.png) 280px 12px no-repeat rgb(255, 255, 255)'});
        $('.brand_id_keyword2').val(this.value);
    }).on('click', '.brand-container .brand-header .letter li,.brand-container .brand-header .letter li a', function () {
        $('.brand-container .brand-header .letter li,.brand-container .brand-header .letter li a').removeClass('backStyle');
        $(this).addClass('backStyle').children('a').addClass('backStyle');
    }).on('click', 'li[class^="step"].dynamic', function () {
        if (stepNum == 2) step.toPrev($(this).index() + 1);
    }).on('mouseover', 'div[class^="m-"]', function () {
        $(this).children('.tools').css('display', 'block');
    }).on('mouseout', 'div[class^="m-"]', function () {
        $(this).children('.tools').css('display', 'none');
    }).on('click', '.tools .move-remove', function () {
        $(this).parents('div[class^="m-"]').remove();
        main.tools.sort($('div[class^="m-"]'));
    }).on('click', '.tools .move-up:not(.disabled)', function () {
        var m = $('div[class^="m-"]');
        if (m.length >= 2) {
            var par = $(this).parents('div[class^="m-"]');
            if (par.index() != 0) {
                par.after(par.prev());
            }
            main.tools.sort(m);
        }
    }).on('click', '.tools .move-down:not(.disabled)', function () {
        var m = $('div[class^="m-"]');
        if (m.length >= 2) {
            var par = $(this).parents('div[class^="m-"]');
            if (par.index() != m.length - 1) {
                par.before(par.next());
            }
            main.tools.sort(m);
        }
    }).on('click', '.cate-container .cate-list li:not(.empty)', function (event) {
        event.stopPropagation();
        step.getCate(this.value, $(this).attr('data-count'));
    }).on('click', document, function () {
        $('.brand-container').hide();
        $('.drop-down').css('backgroud', '#fff url(/template/master/resource/image/common/xljt.png) 280px 12px no-repeat');
        $('.cate-container').hide();
        $('.drop-down-cate').css('background', '#fff url(/template/master/resource/image/common/xljt.png) 430px 12px no-repeat');
    }).on('click', '.cate-container,.brand-header', function (e) {
        e.stopPropagation();
    });
    $('.info').bind('DOMSubtreeModified', function () {
        var html = $('.mobile-content .info').html();
        if (html) {
            html = html.replace(/class="move-remove"/g, 'class="move-remove" hidden ');
        }
        $('[name="web_content"]').val(html);
    });
    if ("{:input(\'get.id\',0)}" != 0) {
        $('.step-round li a').addClass('stepLabel');
    }
    // 品牌
    $('.drop-down').on('click', function (e) {
        e.stopPropagation();
        var style = ($(this).siblings('.brand-container').toggle().css('display')) == 'none' ?
            '#fff url(/template/master/resource/image/common/xljt.png) 280px 12px no-repeat' :
            '#fff url(/template/master/resource/image/common/xljts.png) 280px 12px no-repeat';
        $(this).css('background', style);
    });
    // 分类
    $('.drop-down-cate').on('click', function (event) {
        event.stopPropagation();
        var style = ($('.cate-container').toggle().css('display')) == 'none' ?
            '#fff url(/template/master/resource/image/common/xljt.png) 430px 12px no-repeat' :
            '#fff url(/template/master/resource/image/common/xljts.png) 430px 12px no-repeat';
        $('.drop-down-cate').css('background', style);
    });
    var loadIndex = 0,
        winH = window.innerHeight,
        curFixBtn = $('.step_btn_fixed');
    // curFixBtn.css({top:(winH-118)+'px'});
    // $(window).on('scroll',extra.FixBtn);
    window.step = {
        // 提交验证
        sub: function (i, u) {
            $.Tipmsg.r = null;
            $.Tipmsg.p = null;
            $.Tipmsg.c = null;
            var lf = $('.layui-form').Validform({
                btnSubmit: '#submit',
                tipSweep: true,
                beforeSend: function () {
                    // 禁用按钮防止重复提交
                    $(".layui-btn").attr({disabled: "disabled"});
                    loadIndex = layer.load(0, {shade: false}); //0代表加载的风格，支持0-2
                },
                beforeCheck: function (curform) {
                    // 表单验证之前
                },
                tiptype: function (msg) {
                    layer.msg(msg, {time: 2000});
                },
                ajaxPost: true,
                //忽略验证隐藏表单元素
                ignoreHidden: true,
                datatype: {
                    s30: /^[\w\W]{1,30}$/,
                    s200: /^[\w\W]{1,200}$/,
                    num: /^(([0-9]+)|([0-9]+\.[0-9]{1,2}))$/,
                    zNum: /^[1-9]\d*$/
                },
                beforeSubmit: function (formObj) {
                    var visStep = $('.stepList:visible'),
                        num = parseInt(visStep.attr('data-num')),
                        len = $('.stepList').length,
                        ind = visStep.index() + 1;
                    if (len === ind) {
                        var dt = attrControl.insDT();
                        var formSer = formObj.serialize() + "&" + dt.$(':input').serialize();
                        extra.ajax({
                            data: formSer, url: u, callback: function (res) {
                                console.log(res);
                                layer.msg(res.message);
                                if (res.code === 0) {
                                    window.location.href = '/goods/index';
                                }
                            }
                        })
                    } else {
                        if(extra.changeBtn(ind)){
                            visStep.hide().next('.stepList').show();
                            $('li[class^="step"]').eq(ind).addClass('active dynamic');
                        };
                        // extra.FixBtn();
                    }
                    return false;
                },
                callback: function (res) {

                },
                complete: function () {
                    layer.close(loadIndex);
                    $(".layui-btn").removeAttr("disabled");
                }
            });
        },
        // 获取品牌
        getBrand: function (obj) {
            main.ajax({
                data: obj.data || {}, url: obj.link_url || '', callback: function (res) {
                    if (res.code == 0) {
                        $('.brand-list ul').html(res.data);
                    } else {
                        layer.msg(res.message);
                    }
                }
            });
        },
        // 返回到上级
        toPrev: function (num) {
            if (extra.changeBtn(num - 1)){
                $('.stepList').hide().eq(num - 1).show();
                $('li[class^="step"]').eq(num - 1).addClass('active')
                    .nextAll().removeClass('active').end()
                    .prevAll().addClass('active');
            }
        },
        // 第一步获取子级分类
        getSon: function (type, id) {
            var curContainer = $('.category_box .category_content:nth-child(' + type + ')', document),
                nextContainer = $('.category_box .category_content:nth-child(' + (type + 1) + ')', document),
                cate = $('#cate_' + id, document),
                curCateContent = $('#curCateContent', document),
                curCateContentInfo = [],
                classifyId = $('.classifyId', document);

            $('.speed_three_class_id', document).val('');
            $('.speed_three_class_title', document).val('');
            cate.addClass('cur').siblings('li').removeClass('cur');
            $('.category_box .cur').each(function (i, o) {
                curCateContentInfo[i] = $(o).text().trim();
                if (i == (type - 1)) return false;
            });
            curCateContent.val(curCateContentInfo.join(' / '));
            $('[name="goods_classify_info"]').val(curCateContentInfo.join(' / '));
            classifyId.val(id);
            //  二级
            if (type == 1){
                $('.speed_two_class_id', document).val(id);
                $('.speed_two_class_title', document).val(curCateContentInfo[type-1]);
            }
            //  三级
            if (type == 2){
                $('.speed_three_class_id', document).val(id);
                $('.speed_three_class_title', document).val(curCateContentInfo[type-1]);
            }

            if (type < 3) {
                curContainer.find('i').each(function (i, o) {
                    $(o).removeClass('fa fa-angle-right');
                });
                cate.find('i').addClass('fa fa-angle-right');
                main.ajax({
                    data: {'id': id, 'type': type}, url: '/goods/getSonCate', callback: function (res) {
                        if (res.code === 0) {
                            if (res.flag) classifyId.val('').attr('nullmsg', (type == 1) ? '请选择二级商品分类' : '请选择三级商品分类');
                            nextContainer.find('ul').html('').html(res.data);
                            if (type == 1) nextContainer.next('.category_content').find('ul').html('<li>请选择三级分类</li>');
                            $(".layui-btn").removeAttr("disabled");
                        } else {
                            layer.msg(res.message);
                        }
                    }
                });
            }
        },
        // 第四步获取分类
        getCate: function (cateId, count, keyword) {
            var checkedCate = $('.checkedCate');
            main.ajax({
                data: {'id': cateId || '', 'keyword': keyword || ''}, url: "/goods/getCate", callback: function (res) {
                    if (res.code == 0) {
                        if (count < 3) $('.cate-list ul').html(res.data);
                        $('.checkedCate').html(res.head);
                        $('.drop-down-cate').val(res.headContent);
                        $('.cate_id_keyword').val(cateId);
                    } else {
                        layer.msg(res.message);
                    }
                }
            });
        },
        batchGoods: function () {
            var cateId = $('.cate_id_keyword').val(),
                brandId = $('.brand_id_keyword').val(),
                keyword = $('.search_keyword').val();
            window.location.href = '/goods_batch_operation/index?cateId=' + cateId + '&brandId=' + brandId + '&keyword=' + keyword + '&search=' + 1;
        },
        exportGoods: function () {
            var cateId = $('.cate_id_keyword').val(),
                brandId = $('.brand_id_keyword').val(),
                keyword = $('.search_keyword').val();
            window.location.href = '/goods_export/index?cateId=' + cateId + '&brandId=' + brandId + '&keyword=' + keyword + '&search=' + 1;
        },
        searchGoods: function () {

            var cateId = $('.cate_id_keyword').val(),
                brandId = $('.brand_id_keyword').val(),
                keyword = $('.search_keyword').val();
            main.ajax({
                data: {cateId: cateId || '', brandId: brandId || '', keyword: keyword || ''},
                url: '/goods/searchGoods',
                callback: function (res) {
                }
            });
        },
        // 打开frame
        openCity: function (obj, type) {
            var param = 'cateId=' + $('.cate_id_keyword').val()
                + '&brandId=' + $('.brand_id_keyword').val()
                + '&keyword=' + $('.search_keyword').val() + '&type=' + type;
            layer.open({
                type: 2,
                title: obj.title,
                move: false,
                shade: 0.2,
                shadeClose: true,
                area: ['80%', '700px'],
                btn: obj.btn,
                content: '/goods/searchGoods?' + param
            });
        },

        // 自提订单核销
        cancellation: function (obj) {
            var param = 'order_id=' + $('#order_id').val()
            layer.open({
                type: 2,
                title: obj.title,
                move: false,
                shade: 0,
                shadeClose: true,
                area: ['80%', '700px'],
                btn: obj.btn,
                content: '/to_store/cancellation?' + param
            });
        },

        batchChoose: function () {
            alert('批量')
        },

        // 详情工具html
        toolsHtml: '<div class="tools">' +
            '<i class="fa fa-arrow-up move-up"></i>' +
            '<i class="fa fa-arrow-down move-down"></i>' +
            '<em class="move-remove">' +
            '<i class="fa fa-times" aria-hidden="true"></i> 移除' +
            '</em><div class="cover"></div></div>',
        // 添加文字
        addFont: function (c, maxLength) {
            layer.prompt({
                title: '请输入文字',
                formType: 2,
                offset: 300,
                maxlength: maxLength || 500,
                move: false
            }, function (text, index) {
                var html = '<div class="m-txt">' + text + step.toolsHtml + '</div>';
                $(c).append(html);
                main.tools.sort($('div[class^="m-"]'));
                layer.close(index);
            });
        },
        // 阶梯价格trHtml
        trSample: function (name) {
            var one = '', two = '', limit = '';
            switch (name) {
                case 'is_full':
                    one = 'full_price';
                    two = 'sub_price';
                    limit = 'onkeyup="if(parseInt(this.value)<=0 || isNaN(parseInt(this.value))){this.value=\'\';}else{this.value=parseInt(this.value)}" />';
                    break;
                case 'is_ladder':
                    one = 'ladder_price_num';
                    two = 'ladder_price';
                    limit = 'onkeyup="if(parseInt(this.value)<=0 || isNaN(parseInt(this.value))){this.value=\'\';}else{this.value=parseInt(this.value)}" />';
                    break;
                case 'is_parameter':
                    one = 'parameter_name';
                    two = 'parameter_val';
                    limit = '';
                    break;
            }
            return '<tr>' +
                '<td><input title="" class="text" type="text" name="' + one + '[]" ' +
                'autocomplete="off" value="" ' + limit +
                '</td>' +
                '<td><input title="" class="text" type="text" name="' + two + '[]" ' +
                'autocomplete="off" value="" ' + limit +
                '</td>' +
                '<td><a class="btn_trash" href="javascript:void(0);" onclick="$(this).parents(\'tr\').remove();"><i class="fa fa-trash-o"></i>删除</a></td></tr>';
        },
        // 添加特殊价格tr
        addTr: function (o, name) {
            if ($(o).parents('.extra-div').find('tr:not(.head):not(.addTrDiv)').length == 10) {
                layer.msg('不能添加更多了');
                return false;
            }
            $(o).parents('tr').before(step.trSample(name));
        }
    };
})(window, window.main, window.attrControl);