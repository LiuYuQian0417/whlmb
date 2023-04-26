<?php
declare(strict_types=1);

namespace app\master\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\PaymentConfig as PaymentConfigModel;

class Payment extends Controller
{

    /**
     * 支付方式列表
     * @param PaymentConfigModel $paymentConfigModel
     * @return array|mixed
     */
    public function index(PaymentConfigModel $paymentConfigModel)
    {


        try {

            $data = $paymentConfigModel
                ->field('payment_config_id,type,name,describe,sort,status')
                ->order('sort', 'desc')
                ->select();

        } catch (\Exception $e) {

            return ['code' => -100, 'message' => $e->getMessage()];

        }

        return $this->fetch('', [
            'data' => $data
        ]);
    }

    /**
     * 安装支付方式
     * @param Request $request
     * @param PaymentConfigModel $paymentConfigModel
     * @return array|mixed
     * @throws \think\exception\DbException
     */
    public function create(Request $request, PaymentConfigModel $paymentConfigModel)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                $param['status'] = 1;

                // 验证
                $check = $paymentConfigModel->valid($param, 'create');
                if ($check['code']) return $check;

                $state = $paymentConfigModel->allowField(true)->isUpdate(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/payment/index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch('', [
            'item' => $paymentConfigModel->get($request::get('payment_config_id'))
        ]);
    }

    /**
     * 编辑支付方式
     * @param Request $request
     * @param PaymentConfigModel $paymentConfigModel
     * @return array|mixed
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, PaymentConfigModel $paymentConfigModel)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $paymentConfigModel->valid($param, 'edit');
                if ($check['code']) return $check;

                $state = $paymentConfigModel->allowField(true)->isUpdate(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/payment/index'];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }

        return $this->fetch('create', [
            'item'          => $paymentConfigModel->get($request::get('payment_config_id'))
        ]);
    }

    /**
     * 卸载支付方式
     * @param Request $request
     * @param PaymentConfigModel $paymentConfigModel
     * @return array
     */
    public function unload(Request $request, PaymentConfigModel $paymentConfigModel)
    {
        if ($request::isPost()) {

            try {

                $information = [
                    'status'             => 0,
                    'sort'               => 0
                ];

                $paymentConfigModel->save($information, ['payment_config_id' => $request::post('id')]);

                return ['code' => 0, 'message' => config('message.')[0]];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }
    }

    /**
     * 支付方式排序更新
     * @param Request $request
     * @param PaymentConfigModel $paymentConfigModel
     * @return array
     */
    public function text_update(Request $request, PaymentConfigModel $paymentConfigModel)
    {

        if ($request::isPost()) {
            try {
                $paymentConfigModel->clickEdit($request::post());
                return ['code' => 0, 'message' => ''];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

}