<?php
declare(strict_types = 1);

namespace app\common;

use app\common\service\Lock;
use Exception;
use Overtrue\Socialite\AuthorizeFailedException;
use think\Db;
use think\exception\Handle;
use think\exception\ValidateException;
use think\exception\PDOException;
use think\exception\ErrorException;
use EasyWeChat\Kernel\Exceptions\DecryptException;

class Except extends Handle
{
    /**
     * 异常接管
     * @param Exception $e
     * @return \think\Response
     */
    public function render(Exception $e)
    {
        (new Lock())->unlock(); //抛异常 解锁
        $res = [
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
        ];
        switch ($e) {
            case $e instanceof ValidateException:    // 验证异常
                $res = [
                    'code' => -1,
                    'message' => $e->getMessage(),
                ];
                break;
            case $e instanceof Exception:
                $message = $e->getMessage();
                if (!config('user.debug') &&
                    ($e instanceof PDOException ||
                        $e instanceof ErrorException ||
                        $e instanceof DecryptException)) {
                    $message = "网络不给力，请重试！";
                }
                $res['code'] = $e->getCode() < 0 ? $e->getCode() : -1;
                $res['message'] = $message;
                break;
            case $e instanceof AuthorizeFailedException:
                if ($e->body['errcode'] == 40163) {    // 微信登录code重复使用
                    $res = [
                        'code' => 40163,
                        'message' => '微信授权失败,请重新授权',
                    ];
                }
                break;
            default:
                return parent::render($e);
                break;
        }
        Db::rollback();
        \think\Response::create($res, 'json')->send();
    }
}