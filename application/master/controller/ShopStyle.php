<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/6 0006
 * Time: 13:57
 */

namespace app\master\controller;

use app\common\model\ShopStyle as ShopStyleModel;
use think\Controller;
use think\facade\Env;

/**
 * 全店风格
 *
 * Class ShopStyle
 * @package app\master\controller
 */
class ShopStyle extends Controller
{

    /**
     * 系统设置文件路径
     * @var
     */
    private $filename;

    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
        $this->filename = Env::get('APP_PATH') . 'common/ini/.config';
    }

    /**
     * 首页
     */
    public function index()
    {

        if ($this->request->isPost()) {

            $_param = $this->request->post();

            $_data = [
                'primary_r'  => $_param['primaryColor']['r'],
                'primary_g'  => $_param['primaryColor']['g'],
                'primary_b'  => $_param['primaryColor']['b'],
                'deputy_r'   => $_param['deputyColor']['r'],
                'deputy_g'   => $_param['deputyColor']['g'],
                'deputy_b'   => $_param['deputyColor']['b'],
                'contrast_r' => $_param['contrastColor']['r'],
                'contrast_g' => $_param['contrastColor']['g'],
                'contrast_b' => $_param['contrastColor']['b'],
                'can_change' => 1,
            ];

            try {
                $_result = ShopStyleModel::create($_data);

                return ['code' => 0, 'message' => '添加成功', 'data' => $_result->id];
            } catch (\Exception $exception) {
                return ['code' => -1, 'message' => '添加失败,请稍后再试'];
            }
        }

        $_shopStyle = ShopStyleModel::_GetAll();
        $_currentId = Env::get('shop_style');

        if (!empty($_shopStyle['base'])) {
            foreach ($_shopStyle['base'] as $val) {
                if ($val['id'] == $_currentId) {
                    $_shopStyle['current']['id'] = $_currentId;
                    $_shopStyle['current']['primary'] = [
                        'r' => $val['primary_r'],
                        'g' => $val['primary_g'],
                        'b' => $val['primary_b'],
                    ];
                    $_shopStyle['current']['deputy'] = [
                        'r' => $val['deputy_r'],
                        'g' => $val['deputy_g'],
                        'b' => $val['deputy_b'],
                    ];
                }
            }
        }

        if (is_null($_shopStyle['current']['id']) && !empty($_shopStyle['base'])) {
            foreach ($_shopStyle['diy'] as $val) {
                if ($val['id'] == $_currentId) {
                    $_shopStyle['current']['id'] = $_currentId;
                    $_shopStyle['current']['primary'] = [
                        'r' => $val['primary_r'],
                        'g' => $val['primary_g'],
                        'b' => $val['primary_b'],
                    ];
                    $_shopStyle['current']['deputy'] = [
                        'r' => $val['deputy_r'],
                        'g' => $val['deputy_g'],
                        'b' => $val['deputy_b'],
                    ];
                }
            }
        }

        if ((765 - ($_shopStyle['current']['deputy']['r'] + $_shopStyle['current']['deputy']['g'] + $_shopStyle['current']['deputy']['b'])) < 100) {
            $_shopStyle['current']['contrast']['r'] = $_shopStyle['current']['primary']['r'];
            $_shopStyle['current']['contrast']['g'] = $_shopStyle['current']['primary']['g'];
            $_shopStyle['current']['contrast']['b'] = $_shopStyle['current']['primary']['b'];
        }
        $this->assign('data', $_shopStyle);

        return $this->fetch();
    }

    /**
     * 保存格式
     */
    public function saveStyle()
    {
        if (!$this->request->isPost()) {
            $this->error('请求出错');
        }

        if (!ini_file(NULL, 'shop_style', $this->request->post('id'), $this->filename)) {
            return ['code' => -5, 'message' => config('message.')[-5]];
        }

        return ['code'=>0,'message'=>'保存成功'];
    }

    /**
     * 删除
     *
     * @return array
     */
    public function delete()
    {
        if (!$this->request->isPost()) {
            $this->error('请求出错');
        }

        return ShopStyleModel::_Destroy($this->request->post('id'));

    }
}