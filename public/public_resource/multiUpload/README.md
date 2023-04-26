#图片上传插件

## 使用方法

在页面中直接放入html

```html
<div
    class="multiImageUpload"
>
</div>
```

## 参数

- default 默认展示的图片链接地址,多个链接地址之间以 `,` 分割
    - `http://oss.com/a.png,http://oss.com/b.png`
    - 如果设置此参数需要同时设置相同图片数量的 `vlaue` 参数,否则上传图片插件不会进行初始化
- width 图片需要裁剪的宽度
    - `800`
    - 默认为 `150`
- height 图片需要裁剪的高度
    - `800`
    - 默认为 `150`
- file-mime 上传图片的限定格式
    - `image/gif,image/jpeg,image/png`
    - 默认为 `image/gif,image/jpeg,image/png`
- name 图片构建 `input` 的 `name` 值
    - `picArr`
    -  默认为 `file` 
- dir 图片上传至OSS的文件夹
    - `goods_image_album`
    -  默认为 `file`
- value 默认图片回传的地址,多个地址之间以 `,` 分割
    - `a.png,b.png`
    - 如果设置此参数需要同时设置相同图片数量的 `default` 参数,否则上传图片插件不会进行初始化
- valid-min 图片验证最小张数(依赖`validform`插件)
    - 数字 不能大于 valid-max
    - 无默认
- valid-max 拓美验证最大张数(依赖`validform`插件)
    - 数字 不能小于 valid-min
    - 无默认
- valid-msg="请上传商品多图"(依赖`validform`插件验证 和 `layer` 显示错误信息)
    - 文本 验证不通过时 提示的文本
    - 无默认

## 例子
```text
<div
    class="multiImageUpload"
    default="{$goodsData.multiple_file_data|default=''}"
    width="800"
    height="800"
    file-mime="image/gif,image/jpeg,image/png"
    name="picArr"
    dir="goods_image_album"
    value="{$goodsData.multiple_file_extra_data|default=''}"
    valid-min="1"
    valid-max="5"
    valid-msg="请上传商品多图"
>
</div>
```