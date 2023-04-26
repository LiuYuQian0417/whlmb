<?php
declare(strict_types=1);

namespace app\master\controller;

use app\common\service\OssImage;
use app\common\model\Search;
use think\Controller;
use think\Exception;
use think\facade\Env;
use think\facade\Request;
use think\Db;

class Config extends Controller
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

    // 证照信息设置
    public function license(Request $request)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();

                $licence = '';
                // 证照信息
                if (array_key_exists('other_licence_path', $param) && $param['other_licence_path']) {
                    foreach ($param['other_licence_path'] as $key => $item) {
                        $licence .= $param['other_licence_name'][$key] . ',-,' . $item . '-,-';
                    }
                    $param['licence'] = rtrim($licence, '-,-');
                    unset($param['other_licence_name'], $param['other_licence_path']);
                } else {
                    $param['licence'] = '';
                }

                foreach ($param as $key => $item) {
                    if (is_numeric($item)) {
                        $item = abs($item);
                    }
                    if (!ini_file(NULL, $key, $item, $this->filename)) {
                        return ['code' => -5, 'message' => config('message.')[-5]];
                    }
                }
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/config/license'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        $licence = Env::get('licence', '');
        $licenceArr = [];
        if ($licence) {
            $licenceArr = array_map(
                function ($v) {
                    $arr = explode(',-,', $v);
                    return [
                        'name' => reset($arr),
                        'path' => end($arr),
                    ];
                },
                explode('-,-', $licence)
            );
        }


        $_ossImage = new OssImage;

        $_logo = $_ossImage->buildOssUrl(Env::get('logo', ''));
        $_licence = $_ossImage->buildOssUrl(Env::get('business_license', ''));

//        dump($_logo);

//        dump($licence);
        return $this->fetch(
            '',
            [
                'licenceArr' => $licenceArr,
                'logo'       => $_logo,
                'licence'    => $_licence,
            ]
        );
    }

    // 开关设置
    public function function_index(Request $request)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();

                foreach ($param as $key => $item) {
                    if (is_numeric($item)) {
                        $item = abs($item);
                    }
                    if (!ini_file(NULL, $key, $item, $this->filename)) {
                        return ['code' => -5, 'message' => config('message.')[-5]];
                    }
                }
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/config/function_index'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        // todo 展示内容
        return $this->fetch('');
    }

    // 版本设置
    public function versions(Request $request)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();

                foreach ($param as $key => $item) {
                    if (is_numeric($item)) {
                        $item = abs($item);
                    }
                    if (!ini_file(NULL, $key, $item, $this->filename)) {
                        return ['code' => -5, 'message' => config('message.')[-5]];
                    }
                }
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/config/versions'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        return $this->fetch('');
    }

    /**
     * 系统设置
     * @return mixed
     */
    public function index()
    {
        $licence = Env::get('licence', '');
        $licenceArr = [];
        if ($licence) {
            $licenceArr = array_map(
                function ($v) {
                    $arr = explode(',-,', $v);
                    return [
                        'name' => reset($arr),
                        'path' => end($arr),
                    ];
                },
                explode('-,-', $licence)
            );
        }


        $_ossImage = new OssImage;

        $_logo = $_ossImage->buildOssUrl(Env::get('logo', ''));
        $_licence = $_ossImage->buildOssUrl(Env::get('business_license', ''));

        return $this->fetch(
            '',
            [
                'licenceArr' => $licenceArr,
                'logo'       => $_logo,
                'licence'    => $_licence,
            ]
        );
    }

    /**
     * 保存系统设置
     * @param Request $request
     * @return array
     */
    public function saveConfig(Request $request)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                // 抛出logo
                foreach ($param as $key => &$item) {

                    foreach ([
                                 '!',
                                 '#',
                                 '^',
                                 '&',
                                 '=',
                                 '|',
                                 '(',
                                 ')',
                                 '"',
                                 "'",
                                 '$',
                                 '%',
                                 '*',
                                 '+',
                                 '{',
                                 '}',
                                 '<',
                                 '>',
                                 '[',
                                 ']',
                                 '\\',
                                 '~',
                             ] as $value) {
                        $item = str_ireplace($value, '?', $item);
                    }

                    if (is_numeric($item)) {
                        $item = abs($item);
                    }

                    if (!ini_file(NULL, $key, $item, $this->filename)) {
                        return ['code' => -5, 'message' => config('message.')[-5]];
                    }
                }
                //  单店 更新店铺信息
                $one_store = config('user.one_more');
                if (empty($one_store)) {
                    $key = config('user.common');
                    $url = "https://restapi.amap.com/v3/geocode/geo?address={$param['address']}&city={$param['city']}&key={$key['gao_de']['webapi_key']}";
                    $curl = curl_init();
                    // 设置你需要抓取的URL
                    curl_setopt($curl, CURLOPT_URL, $url);
                    // 设置header
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    // 设置cURL 参数，要求结果保存到字符串中还是输出到屏幕上。
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    // https请求 不验证证书 其实只用这个就可以了
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
                    //https请求 不验证HOST
                    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
                    //执行速度慢，强制进行ip4解析
                    curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                    // 运行cURL，请求网页
                    $addressMessage = json_decode(curl_exec($curl));
                    // 关闭URL请求
                    curl_close($curl);

                    $_update = [];

                    // $addressMessage->geocodes[0]->location
                    if ($addressMessage->status == 1) {
                        $ini = $addressMessage->geocodes[0];
                        $location = explode(',', $ini->location);
                        $_update['province'] = $ini->province;
                        $_update['city'] = $ini->city;
                        $_update['area'] = $ini->district;
                        $_update['address'] = $param['address'];
                        $_update['lng'] = $location[0];
                        $_update['lat'] = $location[1];
                    }

                    // 修改店铺手机号
                    if (!empty($param['phone']))
                    {
                        $_update['phone'] = $param['phone'];
                    }

                    if (!empty($_update))
                    {
                        Db::name('store')->where('store_id', config('user.one_store_id'))->update($_update);
                    }


                }
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/config/index'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 功能开关开关
     * @param Request $request
     * @param Icon $icon
     * @return array
     */
    public function function_status(Request $request)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                // 开关
                if (array_key_exists('switchType', $param)) {
                    $changeVal = [
                        'is_coupon',
                        'is_red_packet',
                        'is_group',
                        'is_cut',
                        'is_limit',
                        'is_sign_in',
                        'is_recharge',
                        'is_ranking',
                        'is_brand',
                        'is_goods_recommend',
                        'is_classify_recommend',
                        'is_balance',
                        'is_customer',
                    ];
                    $param[$changeVal[$param['switchType']]] = $param['checked'];
                    //根据开关状态修改对应功能展示
                    $this->update_function($changeVal[$param['switchType']], $param['checked']);
                }
                unset($param['switchType']);
                unset($param['checked']);
                foreach ($param as $key => $item) {
                    if (!ini_file(NULL, $key, $item, $this->filename)) {
                        return ['code' => -5, 'message' => config('message.')[-5]];
                    }
                }
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    function update_function($switchType, $checked)
    {
        $icon = app('app\\common\\model\\Icon');
        if ($checked == 0) {
            //修改图标显示状态
            $icon::destroy(
                function ($query) use ($switchType) {
                    $query->where(
                        [['name', '=', str_replace('is_', '', $switchType)]]
                    );
                }
            );
        } else {
            //修改图标显示状态
            $_icon = $icon::onlyTrashed()->where(
                [['name', '=', str_replace('is_', '', $switchType)]]
            )->find();
            if (!empty($_icon)) {
                $_icon->restore();
            }
        }
    }

    /**
     *  热搜管理
     */
    public function hot_search(Search $search,Request $request){
        if($request::isPost()){
            $param = $request::post();
            if(!empty($param['id'])){
                $idArr = explode(',', $param['id']);
                if(count($idArr) ==1){
                    $search::destroy($param['id']);
                }else{
                    $search::destroy($idArr);
                }
                return json(['code'=>0,'message'=>'操作成功']);
            }
            return json(['code'=>200,'message'=>'数据有问题，重新操作']);
        }
        $searchArr = $search->field('search_id,keyword,type,number')->paginate(10);
        // halt($searchArr);
        return $this->fetch(
            '',
            [
                'data' => $searchArr,
                'search' => $searchArr,
            ]
        );
    }
}