<?php
declare(strict_types = 1);

namespace app\client\controller;

use think\Controller;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Cookie;
use think\facade\Request;
use think\facade\Session;

class DataBase extends Controller
{

    /**
     * 清除数据缓存
     * @param Request $request 获取参数类库
     * @return array|\Exception|mixed
     */
    public function clear_cache(Request $request)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post('selected/a');
                if ($param) {
                    foreach ($param as $item) {
                        switch ($item) {
                            case 'Session' :
                                Session::clear();
                                break;
                            case 'Cookie' :
                                Cookie::clear('client_');
                                break;
                            default :
                                //查询此分类下的缓存标识
                                $prefix = Config::get('cache.default')['prefix'];
                                $cls = Cache::handler()->keys($prefix . $item . '_*');

                                if ($cls) {
                                    foreach ($cls as $val) {
                                        Cache::handler()->del($val);
                                    }
                                }
                                break;
                        }
                    }
                }
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        //查询缓存分类
        $classify = config('cache.')['classify'];
        return $this->fetch('', [
            'classify' => $classify,
        ]);
    }
}