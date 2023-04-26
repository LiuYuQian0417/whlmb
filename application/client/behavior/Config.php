<?php
declare(strict_types=1);

namespace app\client\behavior;

use app\common\model\Store;
use think\Controller;
use think\facade\Env;
use think\facade\Request;
use think\facade\Session;

class Config extends Controller
{
    public function run($params)
    {
//        file_put_contents('.1.txt', "11111", FILE_APPEND);
        url_logs('client_logs'); //传入参数区分 手机端还是电脑端避免混淆

        $CONTROLLER_NAME = $this->request->controller();
        $ACTION_NAME = $this->request->action();

        if (strtolower("{$CONTROLLER_NAME}-{$ACTION_NAME}") !== 'order-dadacallback') {
            if ($CONTROLLER_NAME <> 'Login' && $ACTION_NAME <> 'check_order') {
                if (Session::has('client_store_id') == FALSE) {
                    exit('<script language="javascript">top.location.href="/client/login/index"</script>');
                }
                //检查商家访问权限
                $_client_status = Store::where([['store_id', '=', Session::get('client_store_id')]])->field(
                    'status,shop'
                )->find();
                if(empty($_client_status)){
                    $_info = '您的店铺已注销，如想重新开启，请联系客服'.Env::get('phone');
                    $_url = '/client/login/index';
                    exit('<script src="/template/client/resource/js/jquery-1.9.1.min.js"></script><script src="/template/client/resource/layui/lay/modules/layer.js"></script><script src="/template/client/resource/js/masterCp.js"></script><link type="text/css" rel="stylesheet" href="/template/client/resource/layui/css/modules/layer/default/layer.css?v=3.1.1" ><script language="javascript">layer.msg("' . $_info . '",{time:1000,area: ["auto","auto"],offset:["36%",""],end:function(){window.location.href=("' . $_url . '");}});</script>');
                }else{
                    $_auth = [
                        'client/store/index',           // 店铺信息-基本信息
                        'client/index/index',           // iframe
                        'client/store/contact',         // 店铺信息-联系我们
                        'client/store/images',          // 店铺信息-图片信息
                        'client/store/storeAuth',       // 店铺认证
                        'client/data_base/clear_cache', // 清理缓存
                        'client/login/out_login',       // 登出
                        'client/login/index',           // 登录
                    ];
                    if (!in_array(Request::path(), $_auth) && $_client_status['status'] != 4) {

                        // 页面弹出的信息
                        $_info = '';
                        // 跳转到 横向第二个 纵向第0个链接地址
                        $_url = '2|0|client/store/index';

                        switch ($_client_status['status']) {
                            case 1:
                                $_info = '您的店铺入驻申请审核通过,请填写您的认证信息,并补全您的店铺信息';
                                break;
                            case 3:
                                $_info = '您的认证信息已填写,请耐心等待平台审核';
                                break;
                            case 5:
                                $_info = '您的认证信息审核未通过,请您修改您的认证信息,提交后请耐心等待平台审核';
                                break;
                        }
                        exit('<script src="/template/client/resource/js/jquery-1.9.1.min.js"></script><script src="/template/client/resource/layui/lay/modules/layer.js"></script><script src="/template/client/resource/js/masterCp.js"></script><link type="text/css" rel="stylesheet" href="/template/client/resource/layui/css/modules/layer/default/layer.css?v=3.1.1" ><script language="javascript">layer.msg("' . $_info . '",{time:1000,area: ["auto","auto"],offset:["36%",""],end:function(){window.parent.reashItem("' . $_url . '");}});</script>');
                    }
                }

            }
        }
    }
}