$(function () {
    var $imageUpload = $('.imageUpload'),
        $image = $('#imageHandle'),
        $preview = $('#imageUploadPreview'),
        $submitImage = $('#submitImage'),
        maxWidth = 320,
        squareWidth = 160,      // 正方形的宽
        rectWidth = 250,   // 长方形的高
        defaultWidth = 135,
        imageView = $('#imageView'),
        imageViewWidth,     // 图片预览的宽
        imageViewHeight,    // 图片预览的高
        currentUpload   // 当前文件上传控件


    $('#imageRotateLeft').on('click', function () {
        $image.cropper('rotate', -90)
    });
    $('#imageRotateRight').on('click', function () {
        $image.cropper('rotate', 90)
    });

    // 循环所有的图片上传插件
    $imageUpload.each(function (index) {

        var up = $(this),
            upWidth = up.attr('width'),
            upHeight = up.attr('height'),
            $img = $('<img class="default" alt="imageUpload"/>'),
            $input_hidden = $('<input class="hidden_input" type="text">'),
            $input_file = $('<input class="file-input" type="file">'),
            $plus = $('<div class="plus"><img></div>'),
            upPlaceholder = up.attr('placeholder'),
            upDefault = up.attr('default'),
            validType = up.attr('valid-type'),
            validMsg = up.attr('valid-msg')

        if (upWidth === undefined || upHeight === undefined) {
            $img.load(function () {
                $('<img/>').attr('src', $(this).attr('src')).load(function () {

                    if (upWidth === undefined) {
                        upWidth = this.width
                        upHeight = this.height
                    } else {
                        upHeight = upWidth / this.width * this.height
                    }
                })
            })
        }

        if (validType !== undefined && validMsg !== undefined) {
            $input_hidden.attr('datatype',validType)
            $input_hidden.attr('errormsg',validMsg)
            $input_hidden.attr('nullmsg',validMsg)
        }

        up.append($plus);

        var defaultSetted = (upDefault !== undefined && upDefault !== ''),
            placeholderSetted = (upPlaceholder !== undefined && upPlaceholder !== '')

        // 判断是否设置默认图
        if (defaultSetted || placeholderSetted) {
            // 如果存在默认图片的话 隐藏 + 号
            up.children('.plus').hide()

            if (defaultSetted) {
                $img.attr('src', upDefault)
            } else if (placeholderSetted) {
                $img.attr('src', upPlaceholder)
            }
        } else {
            up.children('.plus').show()

            if (upWidth === undefined) {
                if (up.children('default') === '') {
                    upWidth = defaultWidth
                }
            }

            if (upHeight === undefined) {
                if (up.children('default') === '') {
                    upHeight = defaultWidth
                }
            }
        }
        mathUploadSize(up, upWidth, upHeight)

        if (up.attr('disabled') !== undefined) {
            $input_file.prop('disabled', 'disabled')
        }
        $input_file.attr('accept', up.attr('file-mime'))

        // 元素中添加 文件选择器
        up.append($input_file)
        // 添加图片
        up.append($img);
        // 元素中添加 文件路径上传器
        $input_hidden.attr('name', up.attr('name'))
        $input_hidden.val(up.attr('value') || up.attr('default'))
        up.append($input_hidden);


        // 图片更改的时候
        up.on('change', '.file-input', function () {

            if (this.files.length <= 0) {
                return
            }

            var file = this.files[0]

            currentUpload = index

            up.data('fileType', file.type)
            // 初始化文件读取器
            var reader = new FileReader();
            // 从文件中读取文件流
            reader.readAsDataURL(file);
            // 文件赋值
            reader.onload = function () {
                $image.attr('src', this.result)

                $image.cropper({
                    aspectRatio: up.attr('width') / up.attr('height'),
                    viewMode: 2,
                    preview: '#imageUploadPreview'
                })

                layer.open({
                    title: '图片处理',
                    type: 1,
                    area: ['800px', '500px'],
                    resize: false,
                    fixed: true,
                    maxmin: false,
                    scrollbar: false,
                    moveOut: false,
                    content: $('#imageUploadDialog'), //这里content是一个DOM，注意：最好该元素要存放在body最外层，否则可能被其它的相对元素所影响
                    end: function () {
                        up.children('.file-input').val('')
                        $image.cropper('destroy')
                    }
                });
            }
        })

        up.bind('mouseover', function (e) {
            if (up.attr('value') !== undefined && up.attr('value') !== '') {
                imageView.children('img').attr('src', up.children('.default').attr('src'))
                // 图片加载完成的时候
                imageView.children('img').load(function () {
                    imageViewWidth = imageView.width();
                    imageViewHeight = imageView.height();
                    imageView.css({
                        top: e.pageY - imageViewHeight - 20,
                        left: e.pageX - imageViewWidth / 2,
                    })
                    imageView.show()
                })

            }
        })

        up.bind('mousemove', function (e) {
            imageView.css({
                top: e.pageY - imageViewHeight - 20,
                left: e.pageX - imageViewWidth / 2,
            })
        })

        up.bind('mouseout', function () {
            imageView.hide()
        })


    })


    $submitImage.on('click', function () {
        var current = $imageUpload.eq(currentUpload),
            formData = new FormData(),
            options = {
                width: current.attr('width'),
                height: current.attr('height')
            };

        // 如果未设置宽 则设置高为 undefined
        if (options.width === undefined) {
            options.height = undefined
        }

        // 获取裁剪区域的宽高
        var containerData = $image.cropper('getData')

        // 如果宽高都未设置
        if (options.width === undefined && options.height === undefined) {

            // 如果 宽大于 最大宽度
            if (containerData.width > maxWidth) {
                // 宽度等于最大宽度
                options.width = maxWidth
                // 保持比例计高度
                options.height = maxWidth / containerData.width * containerData.height
                // 如果 宽度小于默认宽度
            } else if (containerData.width < defaultWidth) {
                // 宽度等于默认宽度
                options.width = defaultWidth
                // 保持比例计算高度
                options.height = defaultWidth / containerData.width * containerData.height
                // 宽度等于默认高度
            } else {
                // 使用裁减区域的宽高
                options.width = containerData.width
                options.height = containerData.height
            }
        } else {
            if (options.height === undefined) {
                options.height = options.width / containerData.width * containerData.height
            }
        }

        // 如果 文件类型 为jpg  设置白色的背景
        if (current.data('fileType') === 'image/jpeg') {
            options.fillColor = '#fff'
        }

        var imageDataURL = $image.cropper('getCroppedCanvas', options).toDataURL()

        formData.append('type', current.attr('dir'))
        formData.append('image', dataURLtoFile(imageDataURL, current.find('.file-input')[0].files[0].name))

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
                        mathUploadSize(current, options.width, options.height)
                        current.children('img').attr('src', imageDataURL)
                        current.children('.hidden_input').val(data.url)
                        current.children('.plus').hide()
                        current.children('.file-input').val('')
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
    })

    /**
     * 计算 上传元素的宽高
     * @param dom
     * @param width
     * @param height
     */
    function mathUploadSize(dom, width, height) {

        var dom_width = 0,
            dom_height = 0;

        // 如果宽高相等的话
        if (width == height) {
            dom_width = squareWidth
            dom_height = squareWidth
            dom.find('.plus>img').attr('src', '/public_resource/upload/img/square.png')

        } else {
            dom_width = rectWidth;
            dom_height = rectWidth / width * height;
            dom.find('.plus>img').attr('src', '/public_resource/upload/img/rect.png')
        }

        dom.css({
            width: dom_width,
            height: dom_height,
        })
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
