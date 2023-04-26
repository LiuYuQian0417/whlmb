(function (window, $, main) {
    'use strict';
    var extra = {
        addNormsHtml: '<a href="javascript:void(0);"' +
            ' onclick="attrControl.toggleNorms(this,1)" class="attr-btn">' +
            '<span><i class="fa fa-plus"></i> 添加规格</span></a>',
        setNormsHtml: '<input type="text" name="" class="norms-txt" />' +
            '<a href="javascript:void(0);"' +
            ' onclick="attrControl.setNorms(this)" class="attr-btn">' +
            '<span><i class="fa fa-check"></i> 设置</span></a>' +
            '<a href="javascript:void(0);"' +
            ' onclick="attrControl.toggleNorms(this,0)" class="attr-btn">' +
            '<span><i class="fa fa-times"></i> 取消</span></a>',
        batchHtml: function (title, type) {
            return '<div class="batch">' +
                '<b onclick="$(\'.batch-input\').not($(this).next(\'.batch-input\')).hide();' +
                '$(this).next(\'.batch-input\').toggle();$(\'.batch_set\').focus();" ' +
                'class="fa fa-edit" title="批量设置此列"></b>' +
                '<div class="batch-input" style="display: none;">' +
                '<h6>批量设置' + title + '：</h6>' +
                '<a href="javascript:void(0)" class="close" ' +
                'onclick="$(this).parent(\'.batch-input\').hide();">' +
                '<div class="fa fa-close (alias)"></div></a>' +
                '<input type="text" class="text price batch_set">' +
                '<a href="javascript:void(0)" class="ncsc-btn-mini" ' +
                'onclick="attrControl.batchColumn($(this));">设置</a>' +
                '<span class="arrow"></span>' +
                '</div></div>';
        },
        normsItem: function (txt, flag, ind) {
            return '<input type="checkbox" lay-filter="attr-val" name="spec_attr[' + flag + '][]" ' +
                'data-parent="' + flag + '" data-index="' + ind + '" ' +
                'name="" lay-skin="primary" value="' + txt + '"' +
                ' title="' + txt + '" checked />';
        }
    };

    window.attrControl = {
        init: function (goods_id, id) {
            attrControl.insDT().destroy(true);
            $.ajax({
                url: '/goods/attrList',
                data: {id: id, goods_id: goods_id},
                dataType: 'json',
                type: 'post',
                success: function (res) {
                    if (res.code != 0) {
                        layer.msg(res.message);
                        return false;
                    }
                    var html = '', checked = '';
                    if (res.data.length > 0) {
                        html = '<label class="layui-form-label">商品规格</label><div class="layui-input-block">';
                        $.each(res.data, function (index, value) {
                            var ga = [];
                            $.each(value.goods_attr, function (i, o) {
                                ga.push(o.attr_value);
                            });
                            // 设置规格标头
                            html += '<div class="layui-card"><div class="layui-card-header"><span class="attr-name">' + value.attr_name + '</span>';
                            if (value.attr_input_type === '系统定义') {
                                //系统默认
                                html += '</div><div class="layui-card-body attr-val" data-flag="' + value.attr_id + '">';
                                $.each(value.attr_value.split("\r\n"), function (_index, _value) {
                                    checked = attrControl.checked(value.attr_input_type, _value, ga);
                                    html += '<div class="attr-item">' +
                                        '<input type="checkbox" data-parent="' + value.attr_id + '" ' +
                                        'data-index="' + index + '" ' +
                                        'name="spec_attr[' + value.attr_id + '][]"  lay-filter="attr-val" lay-skin="primary" value="' + _value + '" ' +
                                        'title="' + _value + '" ' + checked + ' /></div>'
                                });
                                html += '</div></div>'
                            } else {
                                //手工录入
                                html += '<div class="attr-extra">' + extra.addNormsHtml + '</div>';
                                html += '</div><div class="layui-card-body attr-val" data-flag="' + value.attr_id + '">';
                                $.each(value.goods_attr, function (_index, _value) {
                                    checked = attrControl.checked(value.attr_input_type, _value.attr_value, ga);
                                    html += '<div class="attr-item">' +
                                        '<input type="checkbox" data-parent="' + value.attr_id + '" ' +
                                        'data-index="' + index + '" ' +
                                        'name="spec_attr[' + value.attr_id + '][]" lay-filter="attr-val" lay-skin="primary" value="' + _value.attr_value + '" ' +
                                        'title="' + _value.attr_value + '" ' + checked + ' /></div>'
                                });
                                html += '</div></div>'
                            }
                        });
                        html += '</div>';
                    }
                    $('.attr-container').html(html);
                    main.form();
                }
            })
        },
        // 检测是否选中状态
        checked: function (type, val, arr) {
            return ($.inArray(val, arr) > -1) || (type == 0) ? 'checked' : '';
        },
        // 添加规格开关
        toggleNorms: function (dom, type) {
            $(dom).parent('.attr-extra').html(type ? extra.setNormsHtml : extra.addNormsHtml);
        },
        // 设置规格
        setNorms: function (dom) {
            var text = $(dom).prev('.norms-txt').val().trim(),
                flagElem = $(dom).parents('.layui-card-header').next('.layui-card-body'),
                flag = flagElem.attr('data-flag');
            if (text) {
                var confirmItem = flagElem.find('.attr-item').children('input');
                // 只在
                if (confirmItem.length > 0) {
                    var compare = [];
                    confirmItem.each(function (i, o) {
                        compare.push($(o).val());
                    });
                    if ($.inArray(text, compare) >= 0) {
                        layer.msg('规格已存在，请勿重复添加', {time: 2000});
                        return;
                    }
                }
                var checkbox = extra.normsItem(text, flag, $(dom).parents('.layui-card').index());
                var body = $(dom).parents('.layui-card-header').next('.layui-card-body');
                body.append('<div class="attr-item">' + checkbox + '</div>');
                $(dom).prev('.norms-txt').val('');
                this.toggleNorms(dom, 0);
                main.form();
                //直接加到table中
                var sib = body.find('input:checked').length;
                this.changeTable($(checkbox), sib);
            }
        },
        // 笛卡尔乘积
        descartes: function (arg) {
            if (!$.isArray(arg) || arg.length == 0) return [];
            if (arg.length == 1) return [arg];
            return arg.reduce(function (previousValue, currentValue) {
                var res = [];
                $.each(previousValue, function (i, o) {
                    $.each(currentValue, function (_i, _o) {
                        res.push([].concat(o, _o));
                    })
                });
                return res;
            });
        },
        // 表头数据
        tableHeader: '<table class="layui-table compact spec_table"><thead><tr>' +
            '<th>规格</th>' +
            '<th>市场价' + extra.batchHtml('市场价', '') + '</th>' +
            '<th>售卖价' + extra.batchHtml('售卖价', '') + '</th>' +
            '<th>成本价' + extra.batchHtml('成本价', '') + '</th>' +
            '<th>库存' + extra.batchHtml('库存', '') + '</th>' +
            '<th>库存预警值' + extra.batchHtml('库存预警值', '') + '</th>' +
            '<th>商品重量' + extra.batchHtml('商品重量', '') + '</th>' +
            '<th>货号</th>' +
            '<th>上传</th>' +
            '<th>预览</th>' +
            '</tr></thead></table>',
        // 表内容
        tableChild: function (arr, keyArr) {
            if (!$.isArray(arr) || arr.length <= 0) return '';
            var html = [];
            $.each(arr, function (i, o) {
                var flag = o.join(','),
                    attr = keyArr[i].join(' ');
                html.push([
                    // 规格
                    ['<td>' +
                    '<span class="' + o.join('#!@!#') + '">' + flag + '</span>' +
                    '<input type="hidden" name="spec[' + flag + '][goods_attr]" value="' + flag + '" form="deniedform" />' +
                    '<input type="hidden" name="spec[' + flag + '][attr]" value="' + attr + '" form="deniedform" />' +
                    '<input type="hidden" name="spec[' + flag + '][products_id]" value="" form="deniedform" />' +
                    '</td>'
                    ],
                    // 市场价
                    ['<td><input type="text" class="attr-input batch-1" name="spec[' + flag + '][attr_market_price]" value="" placeholder="0.00" form="deniedform" /></td>'],
                    // 售卖价
                    ['<td><input type="text" class="attr-input batch-2" name="spec[' + flag + '][attr_shop_price]" value="" placeholder="0.00" form="deniedform" /></td>'],
                    // 成本价
                    ['<td><input type="text" class="attr-input batch-3" name="spec[' + flag + '][attr_cost_price]" value="" placeholder="0.00" form="deniedform" /></td>'],
                    // 库存
                    ['<td><input type="text" class="attr-input batch-4" name="spec[' + flag + '][attr_goods_number]" value="" placeholder="0" form="deniedform" /></td>'],
                    // 库存预警值
                    ['<td><input type="text" class="attr-input batch-5" name="spec[' + flag + '][attr_warn_number]" value="" placeholder="0" form="deniedform" /></td>'],
                    // 商品重量
                    ['<td><input type="text" class="attr-input batch-6 " name="spec[' + flag + '][attr_goods_weight]" value="" placeholder="不填用默认" form="deniedform" /></td>'],
                    // 货号
                    ['<td><input type="text" class="attr-input" name="spec[' + flag + '][attr_goods_sn]" value="" placeholder="不填自生成" form="deniedform" /></td>'],
                    ['<td><i class="fa fa-plus chose" data-flag="' + o.join('_') + '_img' + '"></i></td>'],
                    ['<td>' +
                    '<img src="/template/master/resource/image/common/imageError.png" class="spec-img" title="' + flag + '" alt="' + flag + '" />' +
                    '<input type="hidden" name="spec[' + flag + '][attr_file]" class="attr_file" value="" form="deniedform" /></td>']
                ]);
            });
            return html;
        },
        changeTable: function (JQ, sib) {
            // 数据展示容器
            var attrRet = $('.attr-ret'),
                // 类型下的规格组
                attrVal = $('.attr-val'),
                inpChk = $('input[name^="spec_attr"]:checked'),
                arg = [],
                otherArg = [],
                key = [];
            if (attrVal.length) {
                $.each(attrVal, function (i, o) {
                    // 组名(例如尺寸)
                    var _key = $(o).prev('.layui-card-header').children('.attr-name').text();
                    // 组ID
                    var _id = $(o).data('flag')

                    if (JQ != undefined && JQ.attr('data-index') == i) {
                        arg.push([JQ.attr('title')]);
                        key.push([_key + '：' + JQ.attr('title')]);
                    } else {
                        var _arg = [],
                            _keyArr = [];
                        $(o).find('[type="checkbox"]:checked').each(function (i, o) {
                            _arg.push(o.getAttribute('title'));
                            otherArg.push(o.getAttribute('title'));
                            _keyArr.push(_key + '：' + o.getAttribute('title'));
                        });
                        if (_arg.length > 0) {
                            arg.push(_arg);
                        }
                        if (_keyArr.length > 0) {
                            key.push(_keyArr);
                        }
                    }
                });
                if (arg.length == 1 && JQ == undefined) {
                    arg = arg.shift().map(function (o) {
                        return [o];
                    });
                }
                if (key.length == 1 && JQ == undefined) {
                    key = key.shift().map(function (o) {
                        return [o];
                    });
                }
                // if (arg.length == attrVal.length && key.length == attrVal.length) {
                if (arg.length == key.length) {
                    // 空table时渲染表头
                    if (attrRet.html() === '') {
                        attrRet.prepend(this.tableHeader);
                    }
                    var last_length = inpChk.length,
                        pChk = inpChk.parents('.attr-val').length;
                    var dt = this.insDT(),
                        arr = (JQ == undefined && pChk == 1) ? arg : this.descartes(arg),
                        keyArr = (JQ == undefined && pChk == 1) ? key : this.descartes(key);
                    sib = (sib == undefined) ? 0 : sib;
                    if (JQ != undefined && sib == 0) {
                        sib = JQ.parents('.attr-val').find('input:checked').length;
                    }
                    if (JQ == undefined || JQ.prop('checked')) {
                        if (pChk > 1 && sib == 1 && otherArg.length > 0) {
                            var xArr = [];
                            for (var x in otherArg) {
                                xArr.push('.spec_tr:contains(' + otherArg[x] + ')')
                            }
                            // 清除单一属性
                            dt.rows(xArr.join(',')).remove().sort().draw(false);
                        }
                        // 选中则插入
                        dt.rows.add(this.tableChild(arr, keyArr)).sort().draw(false);
                    } else {
                        // 取消选中则移除
                        // dt.rows('.spec_tr:has(input[value*="[' + JQ.data('parent')+ '-' + JQ.val() + ',"]) , .spec_tr:has(input[value*=",' + JQ.data('parent')+ '-' + JQ.val() + ',"]) , .spec_tr:has(input[value*=",' + JQ.data('parent')+ '-' + JQ.val() + ']"]) , .spec_tr:has(input[value*="[' + JQ.data('parent')+ '-' + JQ.val() + ']"])').remove().sort().draw(false);
                        dt.rows('.spec_tr:has(input[name*="[' + JQ.val() + ',"]) , .spec_tr:has(input[name*=",' + JQ.val() + ',"]) , .spec_tr:has(input[name*=",' + JQ.val() + ']"]) , .spec_tr:has(input[name*="[' + JQ.val() + ']"])').remove().sort().draw(false);
                        // 没有规格则检测是否含有单一属性否则完全销毁实例
                        if ($('.spec_tr').length == 0) {
                            if (last_length > 0) {
                                // 剩余单一属性
                                this.changeTable()
                            } else {
                                dt.destroy(true);
                            }
                        }
                    }
                }
            }
        },
        insDT: function () {
            var dt = {};
            var options = {
                columnDefs: [
                    {orderable: false, targets: [1, 2, 3, 4, 5, 6, 7, -1]}
                ],
                bLengthChange: false,
                createdRow: function (row) {
                    $(row).addClass('spec_tr');
                },
                language: {
                    "url": "/template/master/resource/js/DataTables-1.10.15/media/chinese.json"
                }
            };
            if ($.fn.dataTable.isDataTable('.spec_table')) {
                dt = $('.spec_table').dataTable().api();
            } else {
                dt = $('.spec_table').dataTable(options).api();
            }
            return dt;
        },
        // 批量设置列值
        batchColumn: function (jq) {
            var td = this.insDT(),
                txt = jq.prev('.batch_set').val(),
                index = jq.parents('th').index();
            jq.parent('.batch-input').hide();
            td.$('.batch-' + index).val(txt);
        }
    };
})(window, window.jQuery, window.main);