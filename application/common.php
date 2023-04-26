<?php
// 应用公共文件

/**
 * 递归level
 * @param $data
 * @param $major_key
 * @param int $parent_id
 * @param int $level
 * @return array
 */
function find_level($data, $major_key, $parent_id = 0, $level = 1)
{
    $information = [];
    foreach ($data as $item) {
        if ($item['parent_id'] == $parent_id) {
            $item['level'] = $level;
            $information[] = $item;
            $child = find_level($data, $major_key, $item[$major_key], $level + 1);
            if (is_array($child)) {
                $information = array_merge($information, $child);
            }
        }
    }
    return $information;
}

/**
 * 生成 oss 文件地址
 * 如果传入的文件地址为空 则 返回空
 *
 * @param string $link
 * @return string
 */
function buildOssFileLink($link = '')
{
    return $link ? config('oss.prefix') . $link : '';
}

/**
 * 获取当前网址
 * @return string
 */
function get_url()
{
    $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
    $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
    $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
    $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self . (isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : $path_info);
    return $sys_protocal . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . $relate_url;
}

/**
 * 浏览记录
 * @param $type
 */
function url_logs($type)
{
    // 不记录浏览记录的地址
    $ignorePath = [
        'client/goods/distribution.html',
        'client/store_goods_classify/fast_create',                      // 商家后台-商品-商品分类-快捷创建分类
        'client/goods/searchGoods',                                     // 商家后台-营销-拼团-编辑拼团-选择拼团商品
        'client/goods/productShow',                                     // 商家后台-商品-商品列表-修改SKU
        'index/index',
        'distribution_card/qr_code',                                    // 平台后台-营销-分销应用-推广-获取二维码
        'group_activity/editAL',                                        //  拼团详情
        'member/destroy',   // 会员黑名单
    ];
    
    //如果是ajax不记录
    if (\think\facade\Request::isAjax()) {
        return;
    }
    
    // 非get 请求 不记录
    if (!\think\facade\Request::isGet()) {
        return;
    }
    
    // 解析 当前url
    $_parsedUrl = parse_url(get_url());
    
    // 浏览器的记录
    $_urlLog = \think\facade\Session::get($type) ?? [];
    
    if (!is_array($_urlLog)) {
        Session::delete($type);
        $_urlLog = [];
    }
    // 如果未定义path 就退出
    if (!isset($_parsedUrl['path'])) {
        return;
    }
    
    // 去除解析地址的path 开头的 / 字符
    $_parsedUrl['path'] = substr($_parsedUrl['path'], 1);
    
    // 如果当前地址在 不记录 的地址中 则退出
    if (in_array($_parsedUrl['path'], $ignorePath)) {
        return;
    }
    
    $_count = count($_urlLog);
    
    // 判断是否是重复访问的当前页面
    if ($_parsedUrl['path'] == (isset($_urlLog[$_count - 1]['path']) ? $_urlLog[$_count - 1]['path'] : '')) {
        $_urlLog[count($_urlLog) - 1] = $_parsedUrl;
        return;
    }
    
    
    //  如果当前的页面
    if ($_count > 20) {
        $_urlLog = array_slice($_urlLog, -20, 20);
        $_count = count($_urlLog);
    }
    
    if ($_count >= 2 && $_urlLog[$_count - 2]['path'] == $_parsedUrl['path']) {
        $_urlLog = array_slice($_urlLog, 0, $_count - 2);
    }
    $_urlLog[] = $_parsedUrl;
    // 重新记录
    \think\facade\Session::set($type, $_urlLog);
}

/**
 * 浏览记录 返回上一页
 * @param $type
 * @return mixed
 */
function url_logs_up($type)
{
    $logs = \think\facade\Session::get($type);
    //过滤所有是根据参数分割出来的数据
    $count = count($logs);
    $k = $count - 2;
    
    // 已经不存在了
    if (!isset($logs[$k])) {
        return false;
    }
    
    return $logs[$k]['path'] . (isset($logs[$k]['query']) ? '?' . $logs[$k]['query'] : '');
}

////将浏览记录中的地址域名替换掉

function url_logs_replace($log)
{
    return $log;
    //     '/';
    //    $new_log = str_replace($domain, '', $log);
    //    switch ($new_log) {
    //        平台
    //        case 'index/index':
    //            $new_log = 'home/index';
    //            break;
    //        case 'client/index/index':
    //            $new_log = 'client/desk/index';
    //            break;
    //    }
    //    return $new_log;
}

/**
 * curl提交
 * @param $type integer 1 get 2 post
 * @param $url  string  访问的地址
 * @param $param  array 请求的数据
 * @param int $is_ignore
 * @return mixed|string    返回的数据
 */
function curl($type,
              $url,
              $param = [],
              $is_ignore = 0)
{
    $_param = '';
    if (array_key_exists('sig', $param))
        $param['sig'] = urlencode($param['sig']);
    if ($type == 1) {
        foreach ($param as $key => $item) {
            $_param .= $key . "=" . $item . "&";
        }
        $url .= "?" . rtrim($_param, '&');
    } else {
        $_param = json_encode($param);
    }
    //初始化
    $curl = curl_init();
    if ($is_ignore) {
        ignore_user_abort();
    }
    //设置header
    $headers = ["Content-type: application/json;charset='utf-8'"];
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    //设置抓取的url
    curl_setopt($curl, CURLOPT_URL, $url);
    //设置获取的信息以文件流的形式返回，而不是直接输出。
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    if ($type == 2) {
        //设置post方式提交
        curl_setopt($curl, CURLOPT_POST, 1);
        //设置post数据
        curl_setopt($curl, CURLOPT_POSTFIELDS, $_param);
    }
    //执行命令
    $data = curl_exec($curl);
    //关闭URL请求
    curl_close($curl);
    
    return json_decode($data, true);
}


/**
 * @param $mode : ini节名
 * @param $key : 键名key
 * @param null $value : 键值
 * @param $filename : 文件名
 * @return mixed
 */
function ini_file($mode, $key, $value = null, $filename = '')
{
    if (!file_exists($filename)) return false;
    //读取文件
    $iniArr = parse_ini_file($filename, true);
    // 更新后的ini文件内容
    $newIni = "";
    if ($mode != null) {
        //节名不为空
        if ($value === null) {
            return @$iniArr[$mode][$key] === null ? null : $iniArr[$mode][$key];
        } else {
            $YNedit = @$iniArr[$mode][$key] === $value ? false : true;//若传入的值和原来的一样，则不更改
            @$iniArr[$mode][$key] = $value;
        }
    } else {
        //节名为空
        if ($value === null) {
            return @$iniArr[$key] === null ? null : $iniArr[$key];
        } else {
            $YNedit = @$iniArr[$key] === $value ? false : true;//若传入的值和原来的一样，则不更改
            @$iniArr[$key] = $value;
        }
        
    }
    if (!$YNedit) return true;
    //更改
    $keys = array_keys($iniArr);
    $num = 0;
    foreach ($iniArr as $k => $v) {
        if (!is_array($v)) {
            $newIni = $newIni . $keys[$num] . "=" . $v . "\r\n";
            $num += 1;
        } else {
            $newIni = $newIni . '[' . $keys[$num] . "]\r\n";//节名
            $num += 1;
            $jieM = array_keys($v);
            $jieS = 0;
            foreach ($v as $k2 => $v2) {
                $newIni = $newIni . $jieM[$jieS] . "=" . $v2 . "\r\n";
                $jieS += 1;
            }
        }
    }
    
    if (($fi = fopen($filename, "w"))) {
        flock($fi, LOCK_EX);//排它锁
        fwrite($fi, $newIni);
        flock($fi, LOCK_UN);
        fclose($fi);
        return true;
    }
    return false;//写文件失败
}

/**
 * 密码加密
 * @param $pass
 * @return string
 */
function passEnc($pass)
{
    $pass .= 'IShop';
    $new = hash('sha256', $pass);
    return $new;
}

/**
 * 二维数组转字符串并用逗号分隔
 * @param $data
 * @return string
 */
function goodsAttrStr($data)
{
    // $result = []; //想要的结果
    // foreach (json_decode($data) as $key => $value) {
    //
    //     $result[$key] = $value['attr_value'];
    // }
    return $data;
}

/**
 * 设置商品分类缓存
 * @param $type int 1自营 0店铺
 * @param $flag string 标识(自营为管理员ID,店铺为用户ID)
 * @param $content
 */
function setCateCache($type, $flag, $content)
{
    //获取商品分类缓存
    $cache = \think\facade\Cache::get($type ? 'flatMaster_category_' : 'shop_category_' . $flag);
    if ($cache || $cache !== '') {
        $cache = json_decode($cache, true);
        array_unshift($cache, $content);
        array_slice($cache, 0, 10);
    } else {
        $cache[] = $content;
    }
    if ($cache) \think\facade\Cache::set($type ? 'flatMaster_category_' : 'shop_category_' . $flag, $cache);
}

/**
 * 取汉字的第一个字的首字母
 * @param type string $str
 * @return string|null
 */
function _getFirstCharter($str)
{
    if (empty($str)) {
        return '';
    }
    $firstChar = ord($str{0});
    if ($firstChar >= ord('A') && $firstChar <= ord('z')) return strtoupper($str{0});
    $s1 = iconv('UTF-8', 'gb2312', $str);
    $s2 = iconv('gb2312', 'UTF-8', $s1);
    $s = $s2 == $str ? $s1 : $str;
    $asc = ord($s{0}) * 256 + ord($s{1}) - 65536;
    if ($asc >= -20319 && $asc <= -20284) return 'A';
    if ($asc >= -20283 && $asc <= -19776) return 'B';
    if ($asc >= -19775 && $asc <= -19219) return 'C';
    if ($asc >= -19218 && $asc <= -18711) return 'D';
    if ($asc >= -18710 && $asc <= -18527) return 'E';
    if ($asc >= -18526 && $asc <= -18240) return 'F';
    if ($asc >= -18239 && $asc <= -17923) return 'G';
    if ($asc >= -17922 && $asc <= -17418) return 'H';
    if ($asc >= -17417 && $asc <= -16475) return 'J';
    if ($asc >= -16474 && $asc <= -16213) return 'K';
    if ($asc >= -16212 && $asc <= -15641) return 'L';
    if ($asc >= -15640 && $asc <= -15166) return 'M';
    if ($asc >= -15165 && $asc <= -14923) return 'N';
    if ($asc >= -14922 && $asc <= -14915) return 'O';
    if ($asc >= -14914 && $asc <= -14631) return 'P';
    if ($asc >= -14630 && $asc <= -14150) return 'Q';
    if ($asc >= -14149 && $asc <= -14091) return 'R';
    if ($asc >= -14090 && $asc <= -13319) return 'S';
    if ($asc >= -13318 && $asc <= -12839) return 'T';
    if ($asc >= -12838 && $asc <= -12557) return 'W';
    if ($asc >= -12556 && $asc <= -11848) return 'X';
    if ($asc >= -11847 && $asc <= -11056) return 'Y';
    if ($asc >= -11055 && $asc <= -10247) return 'Z';
    return null;
}

/**
 * 快递100 post_curl
 * @param $data
 * @param $url
 * @return mixed
 */
function express_post($data, $url)
{
    $o = "";
    foreach ($data as $k => $v) {
        $o .= "$k=" . urlencode($v) . "&";        //默认UTF-8编码格式
    }
    $post_data = substr($o, 0, -1);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    //value为0表示直接输出结果
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $is_success = curl_exec($ch);
    curl_close($ch);
    return json_decode($is_success, true);
}

/**
 * web页面方法
 * @param $title
 * @param $content
 * @return \think\response
 */
function web_page($title, $content)
{
    $data = <<<EOX
<!DOCTYPE html><html lang="en"><head><meta http-equiv="content-type" content="text/html; charset=UTF-8"><meta charset="utf-8"><meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
<title>$title</title>
<style> img {vertical-align:middle; width:100%;} </style></head><body>
EOX;
    $data .= str_replace('class="tools"', 'class="tools" hidden', $content);
    $data .= <<<EOX
</body></html>
EOX;
    
    return \think\facade\Response::create($data, 'html');
}

/**
 * 生成新的订单号
 * @return string
 */
function get_order_sn()
{
    // return date('Ymd') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
    
    //订单号码主体（YYYYMMDDHHIISSNNNNNNNN）
    $order_id_main = date('YmdHis') . rand(10000000, 99999999);
    //订单号码主体长度
    $order_id_len = strlen($order_id_main);
    $order_id_sum = 0;
    for ($i = 0; $i < $order_id_len; $i++) {
        $order_id_sum += (int)(substr($order_id_main, $i, 1));
    }
    //唯一订单号码（YYYYMMDDHHIISSNNNNNNNNCC）
    return $order_id_main . str_pad((100 - $order_id_sum % 100) % 100, 2, '0', STR_PAD_LEFT);
}

/**
 * 生成新的卡号
 * @return string
 */
function get_card()
{
    return substr(time(), 0, -6) . substr(implode(null, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8) . rand(1000, 9999);
}

/**
 * 获取祖辈/子辈分类
 * @param $id
 * @param \app\common\model\GoodsClassify $goodsClassify
 * @param int $type 1 获取祖先 0 获取子孙集合
 * @param array $total
 * @return array
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
function getParCate($id, \app\common\model\GoodsClassify $goodsClassify, $type = 1, $total = [])
{
    $getModel = $goodsClassify
        ->where([[$type ? 'goods_classify_id' : 'parent_id', 'in', $id], ['status', '=', 1]])
        ->field('goods_classify_id,parent_id,title,count');
    $get = $type ? $getModel->find() : $getModel->select()->toArray();
    if ($get) {
        if ($type) {
            array_unshift($total, $get->toArray());
            $total = getParCate($get['parent_id'], $goodsClassify, 1, $total);
        } else {
            $total = array_merge($total, getParCate(implode(',', array_column($get, 'goods_classify_id')), $goodsClassify, 0, $get));
        }
    }
    return $total ?: [['goods_classify_id' => $id]];
}

/**
 * 获取店铺分类祖辈/子辈分类
 * @param $id
 * @param \app\common\model\StoreGoodsClassify $storeGoodsClassify
 * @param int $type
 * @param array $total
 * @return array
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
function getStoreParCate($id, \app\common\model\StoreGoodsClassify $storeGoodsClassify, $type = 1, $total = [])
{
    $getModel = $storeGoodsClassify
        ->where([[$type ? 'store_goods_classify_id' : 'parent_id', 'in', $id], ['status', '=', 1]])
        ->field('store_goods_classify_id,parent_id,title,count');
    $get = $type ? $getModel->find() : $getModel->select()->toArray();
    if ($get) {
        if ($type) {
            array_unshift($total, $get->toArray());
            $total = getStoreParCate($get['parent_id'], $storeGoodsClassify, 1, $total);
        } else {
            $total = getStoreParCate(implode(',', array_column($get, 'store_goods_classify_id')), $storeGoodsClassify, 0, $get);
        }
    }
    return $total;
}


/**
 * 获取利率
 * @param $member_id
 * @return mixed
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
function discount($member_id)
{
    
    if (!$member_id) {
        return '100';
    }
    // 会员价格利率
    $growth_value = countGrowth($member_id);
    // 利率
    $dis = (new \app\common\model\MemberRank())
        ->where([
            ['min_points', '<=', $growth_value],
        ])
        ->order(['min_points' => 'desc'])
        ->field('discount')
        ->find();
    return is_null($dis) ? '100' : $dis['discount'];
}

/**
 * 数组分组
 * @param $data
 * @param $val
 * @param $title
 * @param $list
 * @return array
 */
function arrayGrouping($data, $val, $title, $list)
{
    if (empty($data)) return [];
    
    $result = array_values(array_reduce($data, function ($value, $key) use ($val, $title, $list) {
        $name = $key[$val];
        unset($key[$val]);
        $value[$name][$title] = $name;
        $value[$name][$list][] = $key;
        return $value;
    }));
    
    return $result;
}

/**
 * 计算小数点
 * @param int $min
 * @param int $max
 * @return float|int
 */
function randomFloat($min = 0, $max = 1)
{
    return $min + mt_rand() / mt_getrandmax() * ($max - $min);
}

/**
 * 计算成长值
 * @param $member_id
 * @return float|int
 */
function countGrowth($member_id)
{
    $growth_value = 0;
    if ($member_id) {
        $growth_value = \think\Db::name('member_growth_record')
            ->where([
                ['member_id', '=', $member_id],
                ['create_time', 'between time', [date("Y-m-31", strtotime("-1 year")), date('Y-m-31')]],
            ])
            ->sum('growth_value') ?? 0;
    }
    return $growth_value;
}

/**
 * 为你推荐 - 自动生成
 * @param \app\common\model\Goods $goods
 * @param int $number
 * @param string $member_id
 * @return array|PDOStatement|string|\think\Collection
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
function recommend_list(\app\common\model\Goods $goods, $number = 4, $member_id = '')
{
    $condition[] = ['goods_number', '>', 0];
    $condition[] = ['review_status', '=', 1];
    $condition[] = ['is_putaway', '=', 1];
    $condition[] = ['is_limit', '=', 0];
    // 如果存在
    $class_id = implode(',', array_unique(\think\Db::name('record_goods')
                                              ->where([
                                                          ['member_id', '=', $member_id],
                                                      ])
                                              ->column('goods_classify_id')));
    if ($class_id) {
        $condition[] = ['goods_classify_id', 'in', $class_id];
    }
    $extraCondition = [];
    // 单店铺模式
    if (!config('user.one_more')) {
        array_push($condition, ['store.store_id', '=', config('user.one_store_id')]);
        array_push($extraCondition, ['store.store_id', '=', config('user.one_store_id')]);
    }
    // 功能状态条件
    \think\facade\Env::load(\think\facade\Env::get('app_path') . 'common/ini/.config');
    $function_status = [];
    // 拼团关闭
    if (\think\facade\Env::get('is_group') == 0) {
        $function_status[] = ['is_group', 'eq', '0'];
    }
    // 砍价关闭
    if (\think\facade\Env::get('is_cut') == 0) {
        $function_status[] = ['is_bargain', 'eq', '0'];
    }
    // 限时抢购关闭
    if (\think\facade\Env::get('is_limit') == 0) {
        $function_status[] = ['is_limit', 'eq', '0'];
    }
    //查询字段
    $field = 'goods.goods_id,goods_classify_id,goods_name,shop_price,goods.sales_volume,
    freight_status,shop,store_name,file,is_group,is_bargain,freight_status,shop,group_price,
    cut_price,group_num,is_vip,attr_type_id,file as cart_file,goods_number,goods.store_id,
    is_limit,time_limit_price,market_price,is_distribution,is_distributor';
    // 推荐数据
    $result = $goods
        ->alias('goods')
        ->join('store store', 'store.store_id = goods.store_id and store.delete_time is null and store.status = 4')
        ->where($condition)
        ->where($function_status)
        ->field($field)
        ->orderRand()
        ->limit($number)
        ->group('goods.goods_id')
        ->append(['attribute_list', 'limit_state']);
    $arr = $result->column('goods_id');
    $result = $result
        ->select()
        ->toArray();
    // 如果不够 补进数据
    if (count($result) < $number) {
        $extraArr = $goods
            ->alias('goods')
            ->join('store store', 'store.store_id = goods.store_id and store.delete_time is null and store.status = 4')
            ->where([
                        ['goods.goods_id', 'not in', implode(',', $arr)],
                        ['goods_number', '>', 0],
                        ['review_status', '=', 1],
                        ['is_putaway', '=', 1],
                    ])
            ->where($extraCondition)
            ->where($function_status)
            ->field($field)
            ->orderRand()
            ->limit($number - count($result))
            ->append(['attribute_list', 'limit_state'])
            ->select();
        if (!$extraArr->isEmpty()) {
            $result = array_merge_recursive($result, $extraArr->toArray());
        }
    }
    return $result;
}

/**
 * 注册用户默认生成表
 * @param \app\common\model\Member $memberModel
 * @param $data
 * @param $invite_member_id
 * @param $type 0 手机注册 1 三方注册
 * @return array
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 * @throws \think\Exception
 */
function default_generated(\app\common\model\Member $memberModel, $data, $invite_member_id, $type = 0)
{
    // 创建会员
    $memberModel->allowField(true)->save($data);
    // halt($memberModel);
    if ($memberModel->member_id) {
        // 计算积分和成长值
        taskOver($memberModel->member_id, $type);
        // 开启红包功能
        \think\facade\Env::load(\think\facade\Env::get('APP_PATH') . 'common/ini/.config');
        if (\think\facade\Env::get('is_red_packet') == 1) {
            // 如果存在邀请人
            if ($invite_member_id) {
                // 新人邀请关联表
                \think\Db::name('invite')->insert([
                    'member_id' => $invite_member_id,
                    'invite_member_id' => $memberModel->member_id,
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
                // 读取邀请红包
                $packet = \think\Db::name('red_packet')
                    ->where([
                        ['status', '=', 1],
                        ['type', '=', 2],
                    ])
                    ->field('red_packet_id,title,type,min_actual_price,max_actual_price,extend_time')
                    ->find();
                if (!is_null($packet) && $packet['max_actual_price'] > 0) {
                    // 注册红包随机金额
                    $price = mt_rand($packet['min_actual_price'], $packet['max_actual_price']);
                    // 新增邀请人红包
                    $member_packet_id = \think\Db::name('member_packet')->insertGetId([
                        'member_id' => $invite_member_id,
                        'red_packet_id' => $packet['red_packet_id'],
                        'title' => $packet['title'],
                        'type' => $packet['type'],
                        'actual_price' => $price,
                        'full_subtraction_price' => 0,
                        'start_time' => date('Y-m-d H:i:s'),
                        'end_time' => date('Y-m-d H:i:s', strtotime('+' . $packet['extend_time'] . ' hour')),
                        'update_time' => date('Y-m-d H:i:s'),
                        'create_time' => date('Y-m-d H:i:s'),
                    ]);
                    // 时间计算
                    $time = strtotime(date('Y-m-d H:i:s', strtotime('+' . $packet['extend_time'] . ' hour'))) - time();
                    if ($time > 0) {
                        $msg = json_encode([
                            'queue' => 'packetExpireChangeStatus',
                            'id' => $member_packet_id,
                            'time' => date('Y-m-d H:i:s'),
                        ]);
                        (new \app\common\service\Beanstalk())->put($msg, $time);
                        $new_time = ($time - 10800) <= 0 ? 0 : ($time - 10800);
                        $new_msg = json_encode(['queue' => 'packetExpireRemind',
                            'id' => $member_packet_id, 'uid' => $invite_member_id,
                            'time' => date('Y-m-d H:i:s')]);
                        (new \app\common\service\Beanstalk())->put($new_msg, $new_time);
                    }
                    // 邀请新人红包记录表
                    \think\Db::name('invite_packet_log')->insert([
                        'member_id' => $invite_member_id,
                        'invite_member_id' => $memberModel->member_id,
                        'member_packet_id' => $member_packet_id,
                        'title' => '邀请注册红包',
                        'price' => $price,
                        'create_time' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    } else {
        throw new \think\Exception("创建用户失败");
    }
    // dump($memberModel->member_id);
    return [
        'member_id' => $memberModel->member_id,
        'phone' => isset($memberModel->phone) ? $memberModel->phone : '',
        'avatar' => isset($memberModel->avatar) ? $memberModel->avatar : '',
        'nickname' => isset($memberModel->nickname) ? $memberModel->nickname : '',
        'web_open_id' => isset($memberModel->web_open_id) ? $memberModel->web_open_id : null,
        'subscribe_time' => isset($memberModel->subscribe_time) ? $memberModel->subscribe_time : null,
        'micro_open_id' => isset($memberModel->micro_open_id) ? $memberModel->micro_open_id : null,
    ];
}

/**
 * @param $member_id
 * @param $type 1 第三方 0 手机
 * @param int $is_create
 * @throws \think\Exception
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 * @throws \think\exception\PDOException
 */
function taskOver($member_id, $type, $is_create = 1)
{
    $it = new \app\common\model\IntegralTask();
    $mt = new \app\common\model\MemberTask();
    $ir = new \app\common\model\IntegralRecord();
    $mr = new \app\common\model\MemberGrowthRecord();
    $irData = [
        [   // 积分记录新增
            'member_id' => $member_id,
            'type' => 0,
            'origin_point' => $type ? 6 : 5,
            'integral' => $integral = \think\facade\Env::get($type ? 'integral_third_party' : 'integral_phone'),
            'describe' => $type ? '绑定三方社交账号 - 微信' : '绑定手机号',
            'create_time' => date('Y-m-d H:i:s'),
        ],
    ];
    $mgrData = [
        [   // 成长值记录新增
            'member_id' => $member_id,
            'type' => 0,
            'growth_value' => \think\facade\Env::get($type ? 'growth_third_party' : 'growth_phone'),
            'describe' => $type ? '绑定三方社交账号 - 微信' : '绑定手机号',
            'create_time' => date('Y-m-d H:i:s'),
        ],
    ];
    if ($is_create) {
        // 积分任务创建
        $it->allowField(true)->isUpdate(false)->save([
            $type ? 'third_party_state' : 'phone_state' => 1,
            $type ? 'third_party_is_first' : 'phone_is_first' => 1,
            'info_state' => 0,
            'member_id' => $member_id,
        ]);
        // 成长值任务创建
        $mt->allowField(true)->isUpdate(false)->save([
            $type ? 'third_party_state' : 'phone_state' => 1,
            $type ? 'third_party_is_first' : 'phone_is_first' => 1,
            'info_state' => 0,
            'member_id' => $member_id,
        ]);
    } else {
        $itInfo = $it
            ->where([
                ['member_id', '=', $member_id],
            ])
            ->field('delete_time', true)
            ->find();
        $mtInfo = $mt
            ->where([
                ['member_id', '=', $member_id],
            ])
            ->field('delete_time', true)
            ->find();
        $state = $type ? 'third_party_state' : 'phone_state';
        $first = $type ? 'third_party_is_first' : 'phone_is_first';
        if (!is_null($itInfo) && (($itInfo->phone_is_first && !$type) || ($itInfo->third_party_is_first && $type))) {
            return;
        }
        if (!is_null($mtInfo) && (($mtInfo->phone_is_first && !$type) || ($mtInfo->third_party_is_first && $type))) {
            return;
        }
        if (is_null($itInfo) || (!$itInfo->phone_is_first && !$type) || (!$itInfo->third_party_is_first && $type)) {
            array_push($irData,
                [   // 积分记录新增 - 完善个人信息
                    'member_id' => $member_id,
                    'type' => 0,
                    'origin_point' => 4,
                    'integral' => $tmpI = \think\facade\Env::get('integral_info'),
                    'describe' => '完善个人信息',
                    'create_time' => date('Y-m-d H:i:s'),
                ]
            );
            $integral += $tmpI;
            if (is_null($itInfo)) {
                $it->allowField(true)
                    ->isUpdate(false)
                    ->save([
                        'phone_state' => 1,
                        'third_party_state' => 1,
                        'phone_is_first' => 1,
                        'third_party_is_first' => 1,
                        'info_state' => 1,
                        'member_id' => $member_id,
                    ]);
            } else {
                $itInfo->$state = 1;
                $itInfo->$first = 1;
                $itInfo->info_state = 1;
                $itInfo->save();
            }
        }
        if (is_null($mtInfo) || (!$mtInfo->phone_is_first && !$type) || (!$mtInfo->third_party_is_first && $type)) {
            array_push($mgrData,
                [   // 成长值记录新增 - 完善个人信息
                    'member_id' => $member_id,
                    'type' => 0,
                    'growth_value' => \think\facade\Env::get('growth_info'),
                    'describe' => '完善个人信息',
                    'create_time' => date('Y-m-d H:i:s'),
                ]
            );
            if (is_null($mtInfo)) {
                $mt->allowField(true)
                    ->isUpdate(false)
                    ->save([
                        'phone_state' => 1,
                        'third_party_state' => 1,
                        'phone_is_first' => 1,
                        'third_party_is_first' => 1,
                        'info_state' => 1,
                        'member_id' => $member_id,
                    ]);
            } else {
                $mtInfo->$state = 1;
                $mtInfo->$first = 1;
                $mtInfo->info_state = 1;
                $mtInfo->save();
            }
        }
    }
    $ir->allowField(true)
        ->isUpdate(false)
        ->saveAll($irData);
    $mr->allowField(true)
        ->isUpdate(false)
        ->saveAll($mgrData);
    // 增加积分
    \think\Db::name('member')
        ->where([
            ['member_id', '=', $member_id],
        ])
        ->update(['pay_points' => \think\Db::raw('pay_points + ' . $integral)]);
}

/**
 * 合并操作 - 成长值、积分
 * @param $member_id
 * @param $type
 * @return bool
 * @throws \think\Exception
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 * @throws \think\exception\PDOException
 */
function merge($member_id, $type = 1)
{
    
    // 积分值
    $integral_value = 0;
    
    // 积分 - 完善账户状态
    $integral_info = \think\Db::name('integral_task')
        ->where('member_id', $member_id)
        ->field('info_state,third_party_state')
        ->find();
    
    // 更改的值
    $data = [];
    
    // 积分 - 如果没有完善账户
    if (empty($integral_info['info_state'])) {
        
        // 增加记录
        \think\Db::name('integral_record')->insert([
            'member_id' => $member_id,
            'type' => 0,
            'origin_point' => 4,
            'integral' => \think\facade\Env::get('integral_info'),
            'describe' => '完善个人信息',
            'create_time' => date('Y-m-d H:i:s'),
        ]);
        
        $data['info_state'] = 1;
        
        $integral_value += \think\facade\Env::get('integral_info');
    }
    
    // 积分 - 绑定三方社交账号 - 微信
    if (empty($integral_info['third_party_state']) && !empty($type)) {
        
        \think\Db::name('integral_record')->insert([
            'member_id' => $member_id,
            'type' => 0,
            'origin_point' => 6,
            'integral' => \think\facade\Env::get('integral_third_party'),
            'describe' => '绑定三方社交账号 - 微信',
            'create_time' => date('Y-m-d H:i:s'),
        ]);
        
        $data['third_party_state'] = 1;
        
        $integral_value += \think\facade\Env::get('integral_third_party');
        
    }
    
    // 成长值 - 完善账户状态
    $member_info = \think\Db::name('member_task')
        ->where('member_id', $member_id)
        ->field('info_state,third_party_state')
        ->find();
    
    // 成长值 - 如果没有完善账户
    if (empty($member_info['info_state'])) {
        
        // 增加记录
        \think\Db::name('member_growth_record')->insert([
            'member_id' => $member_id,
            'type' => 0,
            'growth_value' => \think\facade\Env::get('growth_info'),
            'describe' => '完善个人信息',
            'create_time' => date('Y-m-d H:i:s'),
        ]);
        
    }
    
    // 成长值 - 绑定三方社交账号 - 微信
    if (empty($member_info['third_party_state']) && !empty($type)) {
        
        \think\Db::name('member_growth_record')->insert([
            'member_id' => $member_id,
            'type' => 0,
            'growth_value' => \think\facade\Env::get('growth_third_party'),
            'describe' => '绑定三方社交账号 - 微信',
            'create_time' => date('Y-m-d H:i:s'),
        ]);
        
    }
    
    // 如果存在值
    if (!empty($integral_value)) {
        // 更新积分
        \think\Db::name('member')
            ->where('member_id', $member_id)
            ->update(['pay_points' => \think\Db::raw('pay_points+' . $integral_value)]);
    }
    
    // 如果存在条件
    if (!empty($data)) {
        // 更新成长值状态
        \think\Db::name('member_task')->where('member_id', $member_id)->update($data);
        
        // 更新积分状态
        \think\Db::name('integral_task')->where('member_id', $member_id)->update($data);
    }
    
    
    return true;
    
    
}

/**
 * 编码
 * @param $member_id
 * @param $time
 * @return string
 */
function coding($member_id, $time = 60)
{
    
    if (cache($member_id)) {
        return cache($member_id);
    } else {
        $code = rand(1346, 1349) . ' ' . rand(2000, 5000) . ' ' . rand(6000, 9000) . ' ' . rand(200000, 700000);
        cache($member_id, $code, $time);
        cache($code, $member_id, $time);
        return $code;
    }
    
}

/**
 * 秒转换时间格式
 * @param $s
 * @return string
 */
function timeFormatConverse($s)
{
    if (!$s) return '';
    // 秒数
    $sec = $s % 60;
    $sec = $sec ? $sec . '秒前' : '';
    // 分钟
    $min = floor($s % 3600 / 60);
    $min = $min ? $min . '分钟' . ($sec ? '' : '前') : '';
    // 小时
    $hour = floor($s / 3600);
    $hour = $hour ? $hour . '小时' . (($min || $sec) ? '' : '前') : '';
    return $hour . $min . $sec;
}


/**
 * 热门搜索设置
 * @param $keyword
 * @throws \think\Exception
 */
function searchSet($keyword)
{
    $search_id = \think\Db::name('search')
        ->where([
            ['type', '=', 1],
            ['keyword', '=', $keyword],
        ])
        ->value('search_id');
    
    if ($search_id) {
        \think\Db::name('search')
            ->where([
                ['type', '=', 1],
                ['keyword', '=', $keyword],
            ])
            ->setInc('number');
    } else {
        \think\Db::name('search')->insert([
            'type' => 1,
            'number' => 1,
            'keyword' => $keyword,
        ]);
    }
}

/**
 * oss 获取单图路径
 * @param $url
 * @return mixed
 */
function fileOss($url)
{
    if ($url) {
        $ossConfig = config('oss.');
        return $ossConfig['prefix'] . $url . $ossConfig['style'][0];
    }
}

/**
 * 获得当前店铺对应缓存
 * @param $cache_name
 * @param string $default
 * @return mixed
 */
function get_self_store_cache($cache_name, $default = '')
{
    $store_id = \think\facade\Session::get('client_store_id');
    return \think\facade\Cache::get($cache_name . '_' . $store_id, $default) ?: $default;
}

/**
 * http url 转 https
 *
 * @param string $url 需要转换的链接,如果链接不包含<http://>则原样返回
 * @return string
 */
function linkHttp2Https($url)
{
    if (!mb_strstr($url, 'http://')) {
        return $url;
    }
    
    return str_replace("http://", "https://", $url);
}

/**
 * RGB 转 hex
 *
 * @param array $color rgb 颜色[111,222,333]
 * @return string 返回 hex 的色
 */
function RGBToHex($color)
{
    $hexColor = "#";
    
    $hex = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F'];
    
    for ($i = 0; $i < 3; $i++) {
        
        $r = null;
        
        $c = $color[$i];
        
        $hexAr = [];
        
        while ($c > 16) {
            
            $r = $c % 16;
            
            $c = ($c / 16) >> 0;
            array_push($hexAr, $hex[$r]);
        }
        array_push($hexAr, $hex[$c]);
        
        $ret = array_reverse($hexAr);
        
        $item = implode('', $ret);
        
        $item = str_pad($item, 2, '0', STR_PAD_LEFT);
        
        $hexColor .= $item;
    }
    
    return $hexColor;
}

/**
 * 发票计算单价
 * @param $invoice
 * @return array
 */
function bill($invoice)
{
    $billPrice = [];
    $totalPrice = bcmul($invoice['single_price'], $invoice['quantity'], 2);
    $reducePrice = $invoice['sub_share_shop_coupon_price'] + $invoice['sub_share_platform_coupon_price']
        + $invoice['subtotal_share_platform_packet_price'] + $invoice['sub_fullSub_price'];
    $actualPrice = bcsub($totalPrice, $reducePrice, 2);
    $unitPrice = bcdiv($actualPrice, $invoice['quantity'], 8);
    $billPrice['unit'] = $unitPrice;
    $billPrice['sum'] = $actualPrice;
    return $billPrice;
}

/**
 * 格式化金额
 * @param $num
 * @return string
 */
function fmtPrice($num)
{
    if ($num == 0) {
        return "0.00";
    }
    return number_format($num, 2, '.', '');
}

/**
 * 通过id获取店铺地址列表
 *
 * @param $province
 * @param $city
 * @param $area
 * @return array
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
function getAreaListFromId($province, $city, $area)
{
    if (!empty($province) && !empty($city) && !empty($area)) {
        // 将获取到的area_id 赋值给
        $_addressIdList['province'] = $province;
        $_addressIdList['city'] = $city;
        $_addressIdList['area'] = $area;
    } else {
        
        $_addressIdList['province'] = 110000;
        $_addressIdList['city'] = 110100;
        $_addressIdList['area'] = 110101;
    }
    
    $_area = [
        'province' => [],
        'city' => [],
        'area' => [],
    ];
    
    $_areaList = \app\common\model\Area::field(
        'group_concat(area_id) area_id,group_concat(area_name) area_name'
    )->where(
        [
            [
                'parent_id',
                'in',
                "0,{$_addressIdList['province']},{$_addressIdList['city']}",
            ],
        ]
    )->group('parent_id')->select();
    
    for ($i = 0; $i < count($_areaList); $i++) {
        $_areaList[$i]['area_id'] = explode(',', $_areaList[$i]['area_id']);
        $_areaList[$i]['area_name'] = explode(',', $_areaList[$i]['area_name']);
        
        for ($k = 0; $k < count($_areaList[$i]['area_id']); $k++) {
            switch ($i) {
                case 0:
                    $_area['province'][$k]['area_id'] = $_areaList[$i]['area_id'][$k];
                    $_area['province'][$k]['area_name'] = $_areaList[$i]['area_name'][$k];
                    break;
                case 1:
                    $_area['city'][$k]['area_id'] = $_areaList[$i]['area_id'][$k];
                    $_area['city'][$k]['area_name'] = $_areaList[$i]['area_name'][$k];
                    break;
                case 2:
                    $_area['area'][$k]['area_id'] = $_areaList[$i]['area_id'][$k];
                    $_area['area'][$k]['area_name'] = $_areaList[$i]['area_name'][$k];
                    break;
            }
        }
    }
    
    return $_area;
}


/**
 * 通过文字获取店铺地址列表
 *
 * @param $province
 * @param $city
 * @param $area
 * @return array
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
function getAreaListFromText($province, $city, $area)
{
    
    if (!empty($province) && !empty($city) && !empty($area)) {
        $_areaList = \app\common\model\Area::where([
            ['area_name', 'in', "{$province},{$city},{$area}"],
        ])->orderRaw("field(area_name,'{$province}','{$city}','{$area}')")->column('area_id');
        
        // 将获取到的area_id 赋值给
        $_addressIdList['province'] = $_areaList[0];
        $_addressIdList['city'] = $_areaList[1];
        $_addressIdList['area'] = $_areaList[2];
    } else {
        $_addressIdList['province'] = 110000;
        $_addressIdList['city'] = 110100;
        $_addressIdList['area'] = 110101;
    }
    
    return getAreaListFromId($_addressIdList['province'], $_addressIdList['city'], $_addressIdList['area']);
}

function everyStrToChinaTime($time)
{
    return date('Y-m-d H:i:s', strtotime($time));
}

/**
 * API输出格式
 * @param $code
 * @param string $message
 * @param array $data
 * @return array
 */
function apiShow($data = [], $message = '成功', $code = 0)
{
    if (!empty($data) && $code == 0) {
        $result = [
            'code' => $code,
            'message' => $message,
            'result' => $data,
        ];
    } elseif (empty($data) && $code != 0) {
        $result = [
            'code' => $code,
            'message' => $message,
        ];
    } else {
        $result = [
            'code' => $code,
            'message' => $message,
        ];
    }
    return $result;
}
