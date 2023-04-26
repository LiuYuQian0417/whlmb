<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/26 0026
 * Time: 8:58
 */

namespace app\client\controller;


use app\common\model\FreightExpress;
use app\common\model\FreightExpressClassify;
use think\Controller;
use think\Db;
use think\facade\Session;

class BusinessExpressClassify extends Controller
{
    /**
     * 删除
     *
     * @param FreightExpressClassify $freightExpressClassify
     * @param FreightExpress $freightExpress
     * @return array
     */
    public function destroy(FreightExpressClassify $freightExpressClassify, FreightExpress $freightExpress)
    {
        if ($this->request->isPost()) {

            try {
                Db::startTrans();
                $_param = $this->request->post();
                $_valid = $freightExpressClassify->valid($_param, 'destroy');
                if ($_valid['code']) return $_valid;

                // 删除分类
                $freightExpressClassify::destroy([
                                                     'freight_express_classify_id' => $_param['freight_express_classify_id']
                                                 ]);
                // 删除分类下的模板
                $freightExpress::destroy([
                                             'freight_express_classify_id' => $_param['freight_express_classify_id']
                                         ]);
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => "/client/goods/index"];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        return ['code' => -100, 'message' => config('message.404')];
    }


    /**
     * 设为默认
     */
    public function setDefault()
    {
        $_freightExpressClassifyId = $this->request->post('freight_express_classify_id');

        $_storeId = Session::get('client_store_id');

        try {
            Db::startTrans();
            // 把非自己的设置为非默认
            FreightExpressClassify::where([
                                              'store_id'   => $_storeId,
                                              'is_default' => 1,
                                          ])->update([
                                                         'is_default' => 2,
                                                     ]);
            // 把自己设置为默认
            FreightExpressClassify::where([
                                              'freight_express_classify_id' => $_freightExpressClassifyId
                                          ])->update([
                                                         'is_default' => 1
                                                     ]);
            Db::commit();
            return json(['code' => 0, 'message' => config('message.')[0]]);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => -100, 'message' => $e->getMessage()]);
        }
    }
}