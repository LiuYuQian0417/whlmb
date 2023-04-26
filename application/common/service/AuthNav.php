<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\model\Store;
use think\facade\Cache;
use think\facade\Cookie;
use think\facade\Session;

/**
 * 权限导航
 * Class AuthNav
 * @package app\common\service
 */
class AuthNav
{
    private static $authManage;
    private $manage_id;
    const openType = 1;  //1 以用户权限展示  默认现有导航全部展示

    public function __construct($manage_id = null, $user_group_id = null, $type = null)
    {
        if ($type == 'master') {
            $init = ['manage_id' => $manage_id, 'user_group_id' => $user_group_id, 'type' => 'master'];
            $this->manage_id = $manage_id;
        } else {
            $init = ['manage_id' => $user_group_id, 'user_group_id' => $user_group_id, 'type' => 'client'];
            $this->manage_id = $user_group_id;
        }

        self::$authManage = app('app\common\service\AuthManage', $init);
    }

    /**
     * 获取个人导航html
     * @return array
     */
    public function getNavHtml()
    {
        $mainNav = Cache::get('flatMaster_mainNav_' . $this->manage_id);
        $sideNav = Cache::get('flatMaster_sideNav_' . $this->manage_id);
        // halt($mainNav);
        $data_param = Cache::get('flatMaster_data_param_' . $this->manage_id);
        if (!$mainNav || !$sideNav || !$data_param) {
            $show = Cache::get((self::openType == 1) ? 'flatMaster_userAuthTree_' . $this->manage_id : 'flatMaster_allAuthTree');
            if (!$show) {
                $refresh = (self::openType == 1) ? 'userAuthData' : 'allAuthData';
                $authGroup = app('app\common\model\AuthGroup');
                $show = self::$authManage->$refresh(0, 1, $authGroup);
            }
            if ($show && is_array($show)) {
                $mainNav = $sideNav = '';
                $data_param = '{';
                foreach ($show as $key => $value) {
                    if ($value['id'] != 999) //一级top导航
                        $mainNav .= '<li data-param="' . $key . '"><a href="javascript:void(0);">' . $value['title'] . '</a></li>';
                    $sideNav .= '<div id="navTabs_' . $key . '" class="nav-tabs">';  //关联一级top的值
                    if (!empty($value['children'])) {
                        //二级导航
                        //记录导航对应下标数组
                        foreach ($value['children'] as $ke => $val) {
                            if (!empty($val['children'])) {
                                $icon = $val['icon'] ?: 'fa fa-sitemap';
                                $sideNav .= '<dl><dt><a href="javascript:void(0);"><span class="' . $icon . '"></span><h3>' . $val['title'] . '</h3></a></dt>';
                                $sideNav .= '<dd class="sub-menu"><ul>';
                                //三级导航
                                if (array_key_exists('children', $val) && is_array($val['children']) && count($val['children']) > 0)
                                    foreach ($val['children'] as $k => $v) {
                                        $data_param .= "'" . $v['url'] . "'" . ':{topK:' . $key . ',leftK:' . $k . '},';
                                        $sideNav .= '<li><a href="javascript:void(0);" data-param="' . $key . '|' . $k . '|' . $v['url'] . '">' . $v['title'] . '</a>';
                                        //四级导航[隐藏]
                                        if (array_key_exists('children', $v) && is_array($v['children']) && count($v['children']) > 0)
                                            foreach ($v['children'] as $_k => $_v) {
                                                $data_param .= "'" . $_v['url'] . "'" . ':{topK:' . $key . ',leftK:' . $k . '},';
                                                $sideNav .= '<a style="display:none" href="javascript:void(0);" data-param="' . $key . '|' . $k . '|' . $_v['url'] . '">' . $_v['title'] . '</a>';
                                            }
                                        $sideNav .= '</li>';
                                    }
                                $sideNav .= '</ul></dd></dl>';
                            }
                        }
                    }
                    $sideNav .= '</div>';
                    //清除浏览器记录地址
                    Cookie::set('workspaceParam', null, ['prefix' => '']);
                }
                //记录导航对应下标数组
                $data_param = trim($data_param, ',') . '}';
                if ($mainNav) Cache::set('flatMaster_mainNav_' . $this->manage_id, $mainNav);
                if ($sideNav) Cache::set('flatMaster_sideNav_' . $this->manage_id, $sideNav);
                if ($data_param) Cache::set('flatMaster_data_param_' . $this->manage_id, $data_param);
            }
        }
        return ['x' => $mainNav ?: $mainNav, 'y' => $sideNav ?: $sideNav, 'data_param' => $data_param ?: $data_param];
    }


    /**
     * 获取店铺导航html
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getClientNavHtml()
    {
        $mainNav = Cache::get('flatClient_mainNav_' . $this->manage_id);
        $sideNav = Cache::get('flatClient_sideNav_' . $this->manage_id);
        $data_param = Cache::get('flatMaster_data_param_' . $this->manage_id);
        if (!$mainNav || !$sideNav || !$data_param) {
//            Cache::rm('flatClient_allAuthTree_' . $this->manage_id);

            $show = Cache::get('flatClient_allAuthTree_' . $this->manage_id);
            if (!$show) {
                $refresh = 'allClientData';
                $authGroup = app('app\common\model\AuthGroup');
                $show = self::$authManage->$refresh(0, 1, $authGroup);
            }
            if ($show && is_array($show)) {
                //记录导航对应下标数组
                $data_param = '{';
                $mainNav = $sideNav = '';
                //店铺审核状态
                $_client_status = Store::where([['store_id', '=', Session::get('client_store_id')]])->field('status,shop')->find();
//                halt($show);
                foreach ($show as $key => $value) {
                    if ($value['id'] != 999)
                        //一级top导航
                        $mainNav .= '<li data-param="' . $key . '"><a  data-status="' . $_client_status . '" data-status="' . $_client_status . '" href="javascript:void(0);">' . $value['title'] . '</a><div class="arrow"></div></li>';
                    $sideNav .= '<ul id="navTabs_' . $key . '" class="seller_center_left_menu">';  //关联一级top的值
                    // && count($value['children']) > 0
                    if (array_key_exists('children', $value) && is_array($value['children'])&& !empty($value['children'])) {
                        //二级导航
                        foreach ($value['children'] as $ke => $val) {
                            $sideNav .= '<li><a href="javascript:void(0);" data-param="' . $key . '|' . $ke . '|' . $val['url'] . '">' . $val['title'] . '</a><div class="arrow"></div>';
                            $sideNav .= '<dd class="sub-menu">';
                            if (!empty($val['children'])) {
                                // 三级导航（隐藏）
                                if (array_key_exists('children', $val) && is_array($val['children']) && count($val['children']) > 0)
                                    foreach ($val['children'] as $k => $v) {
                                        $data_param .= "'" . $v['url'] . "'" . ':{topK:' . $key . ',leftK:' . $ke . '},';
                                        $sideNav .= '<a style="display:none" href="javascript:void(0);" data-param="' . $key . '|' . $k . '|' . $v['url'] . '">' . $v['title'] . '</a>';
                                    }
                            }
                            $sideNav .= '</dd></li>';
                        }
                    }
                    $sideNav .= '</ul>';
                    //清除浏览器记录地址
                    Cookie::set('client_workspace', null, ['prefix' => '']);
                }
                $data_param = trim($data_param, ',') . '}';
                if ($mainNav) Cache::set('flatClient_mainNav_' . $this->manage_id, $mainNav);
                if ($sideNav) Cache::set('flatClient_sideNav_' . $this->manage_id, $sideNav);
                if ($data_param) Cache::set('flatMaster_data_param_' . $this->manage_id, $data_param);
            }
        }
        return ['x' => $mainNav ?: $mainNav, 'y' => $sideNav ?: $sideNav, 'data_param' => $data_param ?: $data_param];
    }

}
