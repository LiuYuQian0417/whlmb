// 按规格存储规格值数据
var spec_group_checked = ['', ''];
var str = '';
var V = [];

function table_create(number) {
    into_array(number);
    goods_stock_set(number);
    fileClick();
}

// 将选中的规格放入数组
function into_array(number) {
    for (var num = 0; num < number; num++) {
        eval("spec_group_checked_" + num + '=[]');
        $('dl[nc_type="spec_group_dl_' + num + '"]').find('input[type="checkbox"]:checked').each(function () {
            // var i = $(this).attr('nc_type');
            var v = $(this).val();
            var length = eval("spec_group_checked_" + num).length;
            // eval("spec_group_checked_" + num)[length] = [v, i];
            eval("spec_group_checked_" + num)[length] = v;
        });
        spec_group_checked[num] = eval("spec_group_checked_" + num);
    }
}

// 生成库存配置
function goods_stock_set(number) {
    //  店铺价格 商品库存改为只读
    var specDl = $('#spec_dl');
    specDl.show();
    str = '<tr>';
    SpecSku(number);
    if (str == '<tr>') {
        //  店铺价格 商品库存取消只读
        specDl.hide();
    } else {
        $('tbody[nc_type="spec_table"]').empty().html(str).find('td[nc_type],input[nc_type]').each(function () {
            var s = $(this).attr('nc_type');
            try {
                var src = V[s]||'/template/master/resource/image/common/imageError.png';
                if(s.substring(s.length-3) == 'img'){
                    $(this).html('<img width="40" src="'+src+'" ' +
                        'alt="' + s.substr(0,s.length-4) + '" title="' + s.substr(0,s.length-4) + '">');
                }else{
                    $(this).val(V[s]);
                }
            } catch (ex) {
                $(this).val('');
            }
            if ($(this).attr('data_type') == 'attr_market_price' && $(this).val() == '') {
                $(this).val('0.00');
            }
            if ($(this).attr('data_type') == 'attr_shop_price' && $(this).val() == '') {
                $(this).val('0.00');
            }
            if ($(this).attr('data_type') == 'attr_cost_price' && $(this).val() == '') {
                $(this).val('0.00');
            }
            if ($(this).attr('data_type') == 'attr_goods_number' && $(this).val() == '') {
                $(this).val('0');
            }
            if ($(this).attr('data_type') == 'attr_warn_number' && $(this).val() == '') {
                $(this).val('0');
            }
        });
    }
}

function SpecSku(len) {
    var data = '';
    for (var i = 0; i < len; i++) {
        data += "for (var i_" + i + "=0; i_" + i + "<spec_group_checked[" + i + "].length; i_" + i + "++){" +
            "td_" + (i + 1) + " = spec_group_checked[" + i + "][i_" + i + "];\n";
    }
    data += "var tmp_spec_td = new Array();\n";
    for (var i = 0; i < len; i++) {
        // data += "tmp_spec_td[" + i + "] = td_" + (i + 1) + "[1];\n";
        data += "tmp_spec_td[" + i + "] = td_" + (i + 1) + ";\n";
    }
    data += "tmp_spec_td.sort(function(a,b){return a-b});\n";
    data += "var spec_bunch = '';\n";
    for (var i = 0; i < len; i++) {
        data += "spec_bunch += tmp_spec_td[" + i + "];\n ";
    }
    for (var i = 0; i < len; i++) {
        data += "str +='<td>" +
            "<input type=\"hidden\" name=\"spec['+spec_bunch+'][goods_attr][]\" value=\"'+td_" + (i + 1) + "+'\" />" +
            "'+td_" + (i + 1) + "+'" +
            "</td>';\n";
    }
    data += "str +='" +
        //  市场价格
        "<td>" +
        "<input type=\"hidden\" name=\"spec['+spec_bunch+'][products_id]\" nc_type=\"'+spec_bunch+'|products_id\" value=\"\" />" +
        "<input class=\"text attr_market_price\" type=\"text\" name=\"spec['+spec_bunch+'][attr_market_price]\" data_type=\"attr_market_price\" nc_type=\"'+spec_bunch+'|attr_market_price\" value=\"\" />" +
        "<em class=\"add-on\"><i class=\"icon-renminbi\"></i></em>" +
        "</td>" +
        //  售卖价格
        "<td>" +
        "<input class=\"text attr_shop_price\" type=\"text\" name=\"spec['+spec_bunch+'][attr_shop_price]\" data_type=\"attr_shop_price\" nc_type=\"'+spec_bunch+'|attr_shop_price\" value=\"\" />" +
        "<em class=\"add-on\"><i class=\"icon-renminbi\"></i></em>" +
        "</td>" +
        //  成本价
        "<td>" +
        "<input class=\"text attr_cost_price\" type=\"text\" name=\"spec['+spec_bunch+'][attr_cost_price]\" data_type=\"attr_cost_price\" nc_type=\"'+spec_bunch+'|attr_cost_price\" value=\"\" />" +
        "<em class=\"add-on\"><i class=\"icon-renminbi\"></i></em>" +
        "</td>" +
        //  商品库存
        "<td>" +
        "<input class=\"text attr_goods_number\" type=\"text\" name=\"spec['+spec_bunch+'][attr_goods_number]\" data_type=\"attr_goods_number\" nc_type=\"'+spec_bunch+'|attr_goods_number\" value=\"\" />" +
        "</td>" +
        //  商品库存预警值
        "<td>" +
        "<input class=\"text stock\" type=\"text\" name=\"spec['+spec_bunch+'][attr_warn_number]\" data_type=\"attr_warn_number\" nc_type=\"'+spec_bunch+'|attr_warn_number\" value=\"\" />" +
        "</td>" +
        //  商品货号
        "<td>" +
        "<input class=\"text sku\" type=\"text\" name=\"spec['+spec_bunch+'][attr_goods_sn]\" nc_type=\"'+spec_bunch+'|attr_goods_sn\" value=\"\" />" +
        "</td>" +
        //  缩略图上传
        "<td style=\"position: relative\">" +
        "<i class=\"fa fa-plus _check uploadImg\" data-title=\"'+spec_bunch+'\" style=\"height: 10px;width: 10px;cursor:pointer;\">" +
        "</i>" +
        "<input class=\"text stock uploadStore\" type=\"hidden\" name=\"spec['+spec_bunch+'][attr_file]\" data_type=\"images\" nc_type=\"'+spec_bunch+'|images\" value=\"\" />" +
        "</td>" +
        //  缩略图预览
        "<td data_type=\"img\" nc_type=\"'+spec_bunch+'|img\">" +
        "</td>" +
        "</tr>';\n";
    for (var i = 0; i < len; i++) {
        data += "}\n";
    }
    // console.log(data);
    eval(data);
}

// 添加规格值
function specAdd(attr_id) {
    $('#open_' + attr_id).hide();
    $('#close_' + attr_id).show();
}

// 取消
function specAddCancel(attr_id) {
    $('#open_' + attr_id).show();
    $('#close_' + attr_id).hide();
    $('#attr' + attr_id).val('');
}

// 属性添加
function specAddSubmit(attr_id, attr_count) {
    var val = $('#attr' + attr_id).val();
    $('#set_attr').before('<li>' +
        '<span nctype="input_checkbox">' +
        '<input type="checkbox" name="sp_val[' + attr_id + '][]" value="' + val + '" onclick="table_create(' + attr_count + ')" />' +
        '</span>' +
        '<span nctype="pv_name">' + val + '</span>' +
        '</li>');
    specAddCancel(attr_id);
}
/**
 * 上传缩略图
 */
function fileClick() {
    var ind = 0;
    $('.uploadImg').each(function (i,o) {
        main.upload({elem: $(o),
            url: '/file_act/upload',
            field: 'attr_file',
            data: {name:'attr_file',dir:'attr_file',auth:'',style:1,crypt:1},
            before: function(){
            ind = layer.load();
        },done: function (res,index,upload) {
            layer.close(ind);
            layer.msg(res.message);
            if (res.code == 0){
                var text = '<img src="' + res.data.ossCryptUrl + '" onclick="openShadeImg($(this))"' +
                    ' style="width: 40px;" title="' + $(o).attr('data-title') + '" ' +
                    'alt="' + $(o).attr('data-title') + '">' ;
                $(o).siblings('.uploadStore').attr('value', res.data.ossUrl).parent().next('td').empty().append(text);
            }
        },error: function () {
            layer.close(ind);
            layer.msg('上传失败');
        }});
    });
}

// 展示
function batchShow(id) {
    $('#' + id).show();
}

// 隐藏
function batchHide(id) {
    $('#' + id).hide();
}

// 批量设置
function batchSet(id) {
    var _this = $('input[data-name="' + id + '" ]'),_value = _this.val();
    var _type = id;
    if (_type == 'attr_market_price' || _type == 'attr_shop_price' || _type == 'attr_cost_price') {
        _value = number_format(_value, 2);
    }
    if (_type == 'attr_goods_number' && _value > 255) {
        _value = 255;
    }
    if (isNaN(_value)) {
        _value = 0;
    }
    $('#' + id).hide();
    _this.val('');
    if (_type == 'attr_market_price' || _type == 'attr_shop_price' || _type == 'attr_cost_price') {
        $('input[data_type="' + id + '"]').val(number_format(_value, 2));
    }
    if (_type == 'attr_goods_number' || _type == 'attr_warn_number') {
        $('input[data_type="' + id + '"]').val(parseInt(_value));
    }
}

function attr(goods_id, id, item_attr_id) {
    $.ajax({
        url: '/goods/attrList',
        data: {'id': id},
        dataType: 'json',
        type: 'post',
        success: function (data) {
            if (data.code !== 0){
                layer.msg(data.message);
                return;
            }
            var result = '<label class="layui-form-label">商品规格</label><div class="layui-input-block">',
                table_result = '',
                checked = '',
                length = data.data.length;
            $('#number').val(length);
            if (length > 0){
                // 商品规格渲染
                $(data.data).each(function (key, item) {
                    // result += '<div nc_type="spec_group_dl_' + key + '" nctype="spec_group_dl" class="layui-card-header">' + item.attr_name + '：</div>';
                    result += '<dl nc_type="spec_group_dl_' + key + '" nctype="spec_group_dl"><dt>' + item.attr_name + '：</dt>';
                    // result += '<div class="layui-card-body"><ul class="spec">';
                    result += '<dd nvtype="sp_group_val"><ul class="spec">';
                    if (item.attr_input_type == 1) {
                        //系统定义的规格属性
                        item.attr_value.split("\r\n").forEach(function (val) {
                            // 编辑状态下检测是否选中状态
                            if(item_attr_id){ checked = getString(item.attr_input_type,val, item.goodsAttr);}
                            result += '<li>' +
                                '<span nctype="input_checkbox">' +
                                '<input type="checkbox" value="' + val + '" nc_type="' + goods_id + val + '" class="sp_val" onclick="table_create(' + data.data.length + ')"  ' + checked + '>' +
                                '</span>' +
                                '<span nctype="pv_name">' + val + '</span>' +
                                '</li>';
                        });
                    } else {
                        //手工录制的规格属性
                        item.goodsAttr.forEach(function (val,ind) {
                            // 编辑状态下检测是否选中状态
                            if(item_attr_id){ checked = getString(item.attr_input_type,val, item.goodsAttr);}
                            result += '<li>' +
                                '<span nctype="input_checkbox">' +
                                '<input type="checkbox" value="' + val.attr_value + '" nc_type="' + val.attr_value + '" class="sp_val" name="sp_val[' + item.attr_id + ']['+ind+'][]" ' + checked + ' onclick="table_create(' + data.data.length + ')">' +
                                '<input type="hidden" value="' + val.goods_attr_id + '" name="sp_val[' + item.attr_id + ']['+ind+'][]" />'+
                                '</span>' +
                                '<span nctype="pv_name">' + val.attr_value + '</span>' +
                                '</li>';
                        });
                        result += '<li id="set_attr">' +
                            '<div id="open_' + item.attr_id + '">' +
                            '<a href="javascript:void(0);" class="ncsc-btn" onclick="specAdd(' + item.attr_id + ')">设置属性值</a>' +
                            '</div>' +
                            '<div id="close_' + item.attr_id + '" style="display:none;width: 200px;">' +
                            '<input class="form-control" style="width: 80px;float: left" id="attr' + item.attr_id + '" value="" type="text" placeholder="属性值名称" maxlength="20">' +
                            '<a href="javascript:void(0);" nctype="specAddSubmit" class="ncsc-btn ncsc-btn-acidblue" style="margin-left: 5px;" onclick="specAddSubmit(' + item.attr_id + ',' + data.data.length + ')">确认</a>' +
                            '<a href="javascript:void(0);" onclick="specAddCancel(' + item.attr_id + ')" class="ncsc-btn ncsc-btn-orange">取消</a>' +
                            '</div>' +
                            '</li>';
                    }
                    result += '</ul></div>';
                    table_result += '<th class="w110" nctype="spec_name_' + item.attr_id + '">' + item.attr_name + '</th>'
                });
                result += '<dl nc_type="spec_dl" id="spec_dl" class="spec-bg" hidden>';
                result += '<dt>库存配置（请先选择好商品规格，在进行填写）：</dt>';
                result += '<dd class="spec-dd">';
                result += '<table border="0" cellpadding="0" cellspacing="0" class="spec_table">';
                result += '<thead>';
                result += table_result;
                result += '<th class="w90">市场价<div class="batch"><b onclick="batchShow(\'attr_market_price\')" class="fa fa-edit" title="批量操作"></b>';
                result += '<div class="batch-input" id="attr_market_price" style="display:none;"><h6>批量设置市场价：</h6><a href="javascript:void(0)" class="close" onclick="batchHide(\'attr_market_price\')">';
                result += '<div class="fa fa-close (alias)"></div></a><input data-name="attr_market_price" type="text" class="text price"/>';
                result += '<a href="javascript:void(0)" class="ncsc-btn-mini" data-type="attr_market_price" onclick="batchSet(\'attr_market_price\')">设置</a>';
                result += '<span class="arrow"></span></div></div></th>';
                result += '<th class="w90">售卖价格<div class="batch"><b onclick="batchShow(\'attr_shop_price\')" class="fa fa-edit" title="批量操作"></b>';
                result += '<div class="batch-input" id="attr_shop_price" style="display:none;"><h6>批量设置售卖价格：</h6><a href="javascript:void(0)" class="close" onclick="batchHide(\'attr_shop_price\')">';
                result += '<div class="fa fa-close (alias)"></div></a><input data-name="attr_shop_price" type="text" class="text price"/>';
                result += '<a href="javascript:void(0)" class="ncsc-btn-mini" data-type="attr_shop_price" onclick="batchSet(\'attr_shop_price\')">设置</a>';
                result += '<span class="arrow"></span></div></div></th>';
                result += '<th class="w90">成本价格<div class="batch"><b onclick="batchShow(\'attr_cost_price\')" class="fa fa-edit" title="批量操作"></b>';
                result += '<div class="batch-input" id="attr_cost_price" style="display:none;"><h6>批量设置成本价格：</h6><a href="javascript:void(0)" class="close" onclick="batchHide(\'attr_cost_price\')">';
                result += '<div class="fa fa-close (alias)"></div></a><input data-name="attr_cost_price" type="text" class="text price"/>';
                result += '<a href="javascript:void(0)" class="ncsc-btn-mini" data-type="attr_cost_price" onclick="batchSet(\'attr_cost_price\')">设置</a>';
                result += '<span class="arrow"></span></div></div></th>';
                result += '<th class="w60">商品库存<div class="batch"><b onclick="batchShow(\'attr_goods_number\')" class="fa fa-edit" title="批量操作"></b>';
                result += '<div class="batch-input" id="attr_goods_number" style="display:none;"><h6>批量设置商品库存：</h6><a href="javascript:void(0)" class="close" onclick="batchHide(\'attr_goods_number\')">';
                result += '<div class="fa fa-close (alias)"></div></a><input data-name="attr_goods_number" type="text" class="text stock"/>';
                result += '<a href="javascript:void(0)" class="ncsc-btn-mini" data-type="attr_goods_number" onclick="batchSet(\'attr_goods_number\')">设置</a>';
                result += '<span class="arrow"></span></div></div></th>';
                result += '<th class="w60">商品库存预警值<div class="batch"><b onclick="batchShow(\'attr_warn_number\')" class="fa fa-edit" title="批量操作"></b>';
                result += '<div class="batch-input" id="attr_warn_number" style="display:none;"><h6>批量设置商品库存预警值：</h6><a href="javascript:void(0)" class="close" onclick="batchHide(\'attr_warn_number\')">';
                result += '<div class="fa fa-close (alias)"></div></a><input data-name="attr_warn_number" type="text" class="text stock"/>';
                result += '<a href="javascript:void(0)" class="ncsc-btn-mini" data-type="attr_warn_number" onclick="batchSet(\'attr_warn_number\')">设置</a>';
                result += '<th class="w100">货号</th><th class="w60">缩略图上传</th><th>缩略图预览</th>';
                result += '</thead>';
                result += '<tbody nc_type="spec_table">';
                result += '</tbody>';
                result += '<span class="arrow"></span></div></div></th>';
                result += '</table>';
                result += '</dd></dl>';
                result += '<div class="shadeImg" onclick="closeShadeImg()"><img class="showImg" src=""/></div>'
                $('#attr').html(result);
                if(item_attr_id) table_create(length);
            }else{
                $('#attr').html('');
            }
        }
    });
}
function openShadeImg(o) {
    $('.shadeImg').fadeIn(500);
    $('.showImg').attr('src',o.attr('src'));
}

function closeShadeImg() {
    $('.shadeImg').fadeOut(500);
}
// 编辑状态下检测是否选中状态
function getString(type,val, objarr) {
    return ($.inArray(val,objarr) > -1) || (type == 0) ? 'checked' : '';
}
// 两位小数点
function number_format(num, ext) {
    if (ext < 0)    return num;
    num = Number(num);
    if (isNaN(num)) num = 0;
    var _str = num.toString(),
        _arr = _str.split('.'),
        _int = _arr[0],
        _flt = _arr[1];
    if (_str.indexOf('.') == -1) {
        if (ext == 0)   return _str;
        var _tmp = '';
        for (var x = 0; x < ext; x++) {
            _tmp += '0';
        }
        _str = _str + '.' + _tmp;
    } else {
        if (_flt.length == ext) return _str;
        if (_flt.length > ext) {
            _str = _str.substr(0, _str.length - (_flt.length - ext));
            if (ext == 0)   _str = _int;
        } else {
            for (var i = 0; i < ext - _flt.length; i++) {
                _str += '0';
            }
        }
    }
    return _str;
}