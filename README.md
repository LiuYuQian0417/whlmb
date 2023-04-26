# ISHOP v2.1.19
#  配置文档
https://shimo.im/docs/WVCPdfZNd9oOJ2n0/read
## 订单提醒(多店)

#### 伪静态
```nginx
location /notify{
    proxy_pass http://127.0.0.1:60004;
    proxy_read_timeout 300s;
    
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
}
```

#### 启动命令
```
php think daemonService:notify
```

## 开关
所有的开关定义在 `/application/common/ini/.config` 中

`/application/common/Behavior.php` 文件 `appInit` 方法将 `$_iniConfigAllowed` 中的值 赋值给全局常量 `INI_CONFIG`

其他 php类 或 模板 直接使用即可

## 项目结构文档
http://note.youdao.com/noteshare?id=48cd6672144d37ab31a4b9340b368366

## 文案

### 按钮

新增: 提交
更新: 保存

### 弹窗

取消 提交|保存

### 页面

返回 提交|保存
 
**所有的提交按钮放在最右侧**

## SESSION
### client 商家端
|名称|含义|
|:---:|:---:|
|client_storeName|店铺名称|
|client_storeLogo|店铺LOGO|
|client_member_id|店铺创建者的用户ID|
|client_store_id|店铺ID|

## CACHE
### 总后台
|名称|含义|时长(s)|
|:---:|:---:|:---:|
|MASTER_CREATE_STORE_{member_id}|创建的店铺的信息|86400|
|MASTER_CREATE_STORE_AUTH_{member_id}|创建的店铺的认证信息|86400|


## 更新记录

#### v2.1.19
- 去除授权加密

#### v2.1.18
- 个人中心消息接口如果系统不存在`MongoDB`抑制错误信息

#### v2.1.17
- 修复分销商对账中数据错误的问题

#### v2.1.16
- 修复手机站微信登录 报错的问题

#### v2.1.15
- 修改订单导订单号与订单列表订单号不一致的问题

#### v2.1.14
- 添加自动部署功能页面
- 添加伪静态以及运行命令的显示和一键复制

#### v2.1.13
- 单店在平台后台修改客服电话时同时修改店铺客服电话

#### v2.1.12
- 修复平台后台创建地区报错
- 平台后台禁止创建相同名称的地区

#### v2.1.11
- 修复用户端 一二级分类按照排序从大到小排序但是第三级分类按照从小到大排序的问题
- 重新构建 `vendor` 目录, 减少报错

#### v2.1.10
- 修复 vendor 中 beanstalk 错误

#### v2.1.9
- 修复商家后台完善对私账户信息银行卡位数错误

#### v2.1.8
- 修改平台后台和商家后台快递配送模板页面打不开的问题

#### v2.1.7
- 更新vendor文件夹composer下载丢文件问题

#### v2.1.6
- 加入融云客服

#### v2.1.5
- user.php 中添加 备案相关信息

#### v2.1.4
- 修复 每次浏览任何后台页面都会处理关闭店铺门店自提和同城配送的问题(将操作放置到清理缓存位置)

#### v2.1.3
- `user.php` 添加配置项 `sms_verify` 用于是否启用验证短信验证码

#### v2.1.2
- 去除 管理员操作日志 分类

#### v2.1.1
- 增加PC端详情描述 判断覆盖率
