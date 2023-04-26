$(function () {

    var $multiImageUpload = $('.multiImageUpload'), // 上传图片的框体
        defaultUploadSize = {
            width: 150,
            height: 150
        },
        $imageHandle = $('#multiImgHandle'),
        $multiImgSubmit = $('#multiImgSubmit'), // 多图裁剪按钮
        currentUpload = {
            dir: '',
            name: '',
            index: 0,
            fileType: '',
            fileName: '',
            cutWidth: 0,
            curHeight: 0,
            showWidth: 0,
            showHeight: 0,
            limit: 0
        };

    $('#multiImgRotateLeft').on('click', function () {
        $imageHandle.cropper('rotate', -90)
    });
    $('#multiImgRotateRight').on('click', function () {
        $imageHandle.cropper('rotate', 90)
    });

    // 循环所有的元素 初始化
    $multiImageUpload.each(function (index) {
        var $th = $(this),
            imageWidth = $th.attr('width') !== undefined ? $th.attr('width') : defaultUploadSize.width,         // 上传的图片的宽
            imageHeight = $th.attr('height') !== undefined ? $th.attr('height') : defaultUploadSize.height,     // 上传图片的高
            imageDefault = $th.attr('default') !== undefined ? $th.attr('default') : '',
            imageValue = $th.attr('value') !== undefined ? $th.attr('value') : '',
            allowedFileType = $th.attr('file-mime') !== undefined ? $th.attr('file-mime') : 'image/gif,image/jpeg,image/png',
            name = $th.attr('name') !== undefined ? $th.attr('name') : 'file',
            dir = $th.attr('dir') !== undefined ? $th.attr('dir') : 'file',
            validNumMin = $th.attr('valid-min'),   // 限制个数不超过 *
            validNumMax = $th.attr('valid-max'),   // 限制个数不超过 *
            validMsg = $th.attr('valid-msg')    // 限制文本

        if (!imageWidth && !imageHeight) {
            return
        }

        var cutSize = {
            width: imageWidth,
            height: imageHeight
        }

        var showSize = mathImageSize(imageWidth, imageHeight);

        var $upload = $('<div class="imageUploadControl"></div>'),
            $uploadDiv = $('<div class="imageUploadImageBorder"></div>'),
            $uploadDivImage = $('<img src="/public_resource/upload/img/square.png">'),
            $uploadInput = $('<input class="imageUploadInput" type="file">'),
            $validInput = $('<input class="hidden_input" type="text">')

        // 如果验证消息 与 上传个数最大值 不等于空
        if (validMsg !== undefined && validNumMax !== undefined) {
            var min = 0,
                max = validNumMax
            // 如果 验证最小值 有值
            if (validNumMin !== undefined) {
                min = validNumMin
            } else {
                // 否则
                min = validNumMax
            }

            $validInput.attr('datatype', 's' + min + '-' + max);
            $validInput.attr('nullmsg', validMsg);
            $validInput.attr('errormsg', validMsg);
        }

        $uploadInput.attr('accept', allowedFileType);
        $upload.width(showSize.width).height(showSize.height);
        $uploadDiv.append($uploadDivImage);
        $upload.append($uploadDiv);
        $upload.append($uploadInput);
        $upload.append($validInput);

        // 为图片添加上传图片控件
        $th.append($upload);

        if (imageDefault !== '' && imageValue !== '') {
            // 插入图片列表
            var imageDefaultArr = imageDefault.split(',');
            var imageValueArr = imageValue.split(',');

            for (var i = 0; i < imageDefaultArr.length; i++) {
                appendDataUrlImageToList(index, validNumMax, showSize.width, showSize.height, name, imageValueArr[i], imageDefaultArr[i])
            }
        }

        // 图片被更改时监听
        $uploadInput.on('change', function () {
            // 如果文件没有
            if (this.files.length <= 0) {
                return
            }

            currentUpload.dir = dir;
            currentUpload.name = name;
            currentUpload.index = index;
            currentUpload.fileType = this.files[0].type;
            currentUpload.fileName = this.files[0].name;
            currentUpload.cutWidth = cutSize.width;
            currentUpload.curHeight = cutSize.height;
            currentUpload.showWidth = showSize.width;
            currentUpload.showHeight = showSize.height;
            currentUpload.limit = validNumMax;

            // 初始化文件读取器
            var reader = new FileReader();

            // 把图片读取出来
            reader.readAsDataURL(this.files[0]);
            // 图片读取出来之后 初始化 文件裁剪器
            reader.onload = function () {
                $imageHandle.attr('src', this.result);
                $imageHandle.cropper({
                    aspectRatio: cutSize.width / cutSize.height,
                    viewMode: 2,
                    preview: '#multiImgUploadPreview',
                    ready:function () {
                        $image.cropper('setCropBoxData',{
                            'width':99999999,
                        })
                    }
                });

                layer.open({
                    title: '图片处理',
                    type: 1,
                    area: ['800px', '500px'],
                    resize: false,
                    fixed: true,
                    maxmin: false,
                    scrollbar: false,
                    moveOut: false,
                    content: $('#multiImgUploadDialog'), //这里content是一个DOM，注意：最好该元素要存放在body最外层，否则可能被其它的相对元素所影响
                    end: function () {
                        $uploadInput.val('');
                        $imageHandle.cropper('destroy');
                    }
                });
            };
        });
    });

    // 多图裁剪按钮点击处理
    $multiImgSubmit.on('click', function () {

        var formData = new FormData,
            options = {
                width: currentUpload.cutWidth,
                height: currentUpload.curHeight
            };

        // 如果 文件类型 为jpg  设置白色的背景
        if (currentUpload.fileType === 'image/jpeg') {
            options.fillColor = '#fff'
        }

        var dataUrl = $imageHandle.cropper('getCroppedCanvas', options).toDataURL('image/png')

        formData.append('type', currentUpload.dir)
        formData.append('image', dataURLtoFile(dataUrl))

        layer.load(1, {shade: [0.1, '#fff']});

        $.ajax({
            url: '/v2.0/image/upload',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (data) {
                try {
                    if (data.code === 0) {
                        // 添加图片到图片列表中
                        appendDataUrlImageToList(currentUpload.index, currentUpload.limit, currentUpload.showWidth, currentUpload.showHeight, currentUpload.name, data.url, dataUrl)
                        layer.closeAll()
                    }
                } catch (e) {
                    layer.msg('系统出错,请稍后再试', {time: 2000})
                }
                layer.msg(data.message, {time: 2000})
            },
            error: function () {
                layer.msg('系统出错,请稍后再试', {time: 2000})
            },
            complete: function () {
                layer.closeAll('loading');
            }
        });
    });

    /**
     * 添加图片到图片列表中
     * @param index
     * @param num
     * @param width
     * @param height
     * @param name
     * @param value
     * @param def
     */
    function appendDataUrlImageToList(index, num, width, height, name, value, def) {
        var node = $('<div class="imageItem"></div>');
        var image = $('<img>');
        var left = $('<div class="imageMove imageMoveLeft"><img src="/public_resource/multiUpload/img/left.png"></div>');
        var right = $('<div class="imageMove imageMoveRight"><img src="/public_resource/multiUpload/img/right.png"></div>');
        var remove = $('<div class="imageMove imageRemove"><img src="/public_resource/multiUpload/img/remove.png"></div>');
        var hiddenInput = $('<input type="hidden">');
        var controller = $('<div class="imageItemController"></div>');

        if (!def) {
            def = value
        }


        image.attr('src', def);
        hiddenInput.attr('value', value).attr('name', name + '[]').attr('value', value)
        controller.append(left).append(right).append(remove)

        node.css({
            width: width,
            height: height
        }).append(controller)
            .append(image)
            .append(hiddenInput)


        var $imageUpload = $('.multiImageUpload').eq(index)

        $imageUpload.children('.imageUploadControl').before(node)


        var $hiddenInput = $imageUpload.find('.hidden_input')


        $imageUpload.find('.hidden_input').val()

        if (num && $imageUpload.find('.imageItem').length >= num) {
            $imageUpload.find('.imageUploadControl').hide()
        }
        $hiddenInput.val('s' + $hiddenInput.val())
    }

    $multiImageUpload.on('click', '.imageMoveLeft', function () {   // 向前移动
        var $imageItem = $(this).parent().parent()
        // 如果当前元素是第一个元素
        if ($imageItem.index() === 0) {
            return
        }

        // 移动
        $imageItem.prev().before($imageItem)
    }).on('click', '.imageMoveRight', function () {                 // 向后移动
        var $imageItem = $(this).parent().parent()
        // 如果当前是最后一个元素
        if ($imageItem.next().hasClass('imageUploadControl')) {
            return
        }

        // 移动
        $imageItem.next().after($imageItem)
    }).on('click', '.imageRemove', function () {                    // 删除

        var multiImageUpload = $(this).parents('.multiImageUpload')
        var hiddenInput = multiImageUpload.find('.hidden_input');

        hiddenInput.val(hiddenInput.val().slice(0,-1));

        multiImageUpload.find('.imageUploadControl').show();

        $(this).parent().parent().remove()

    })

    /**
     * 匹配显示的图片大小
     * @param width
     * @param height
     * @returns {{width: *, height: *}}
     */
    function mathImageSize(width, height) {

        var returnData = {
            width: width,
            height: height
        }

        // 如果图片的宽度大于了默认的图片宽度
        if (width > defaultUploadSize.width) {
            returnData.width = defaultUploadSize.width;
            height = returnData.height = defaultUploadSize.width / width * height
        }

        // 如果图片的高度大于了默认的图片宽度
        if (height > defaultUploadSize.height) {
            returnData.height = defaultUploadSize.height
            returnData.width = defaultUploadSize.height / height * returnData.width
        }

        return returnData
    }

    /**
     * dataURL转file
     * @param dataurl
     * @param filename
     * @returns {File}
     */
    function dataURLtoFile(dataurl,filename) {
        var arr = dataurl.split(','),
            mime = arr[0].match(/:(.*?);/)[1],
            bstr = atob(arr[1]),
            n = bstr.length,
            u8arr = new Uint8Array(n);
        while (n--) {
            u8arr[n] = bstr.charCodeAt(n);
        }

        var blob = new Blob([u8arr], { type: mime });

        blob.lastModifiedDate = new Date();
        blob.name = filename;
        return blob;
    }

});