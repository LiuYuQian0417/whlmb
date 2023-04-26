<?php
declare(strict_types = 1);

namespace app\common\model;

use app\common\model\MemberRank as MemberRankModel;
use app\common\model\Store as StoreModel;
use app\common\service\Inform;
use app\common\service\IntegratingCloud;
use app\common\service\QrCode;
use think\Db;
use think\facade\Cache;
use think\facade\Hook;
use think\facade\Request;
use think\facade\Session;

/**
 * 会员模型
 * Class Manage
 *
 * @package app\common\model
 */
class Member extends BaseModel
{
    protected $pk = 'member_id';
    
    public static function init()
    {
        self::beforeWrite(
            function ($e) {
                $e->update_time = date('Y-m-d H:i:s');
                if (isset($e['password'])) {
                    if (strlen($e['password']) <= 18 && strlen($e['password']) >= 6) {
                        $e->password = passEnc(trim($e['password']));
                    } else {
                        unset($e['password']);
                    }
                }
                if (isset($e['pay_password'])) {
                    if (strlen($e['pay_password']) == 6) {
                        $e->pay_password = passEnc(trim($e['pay_password']));
                    } else {
                        unset($e['pay_password']);
                    }
                }
                $file = self::upload('image', 'avatar/file/' . date('Ymd') . rand() . '/');
                if ($file) {
                    $e->avatar = $file;
                }
            }
        );
        self::beforeInsert(
            function ($e) {
                $sole = uniqid('shop_');
                if (!isset($e['username']) || !$e['username']) {
                    $e->username = $sole;
                }
                if (!isset($e['nickname']) || !$e['nickname']) {
                    $e->nickname = $sole;
                }
                $e->card_number = get_card();
                $e->register_time = date('Y-m-d H:i:s');
                // 初始用户信息
                $e->status = 1;
                $e->register_ip = Request::ip();
                $e->login_time = date('Y-m-d H:i:s');
                $e->login_ip = Request::ip();
            }
        );
        self::afterInsert(
            function ($e) {
                self::where('member_id', $e->member_id)->update(
                    ['invite_code' => (new QrCode())->member_qrCode($e->member_id)]
                );
            }
        );
        self::afterUpdate(
            function ($e) {
                if (Request::post('nickname') || Request::file('avatar')) {
                    // 完善信息状态
                    $info_state = (new MemberTask())
                        ->where([
                            ['member_id', '=', $e->member_id],
                        ])->value('info_state');
                    if (empty($info_state)) {
                        // 是否完善
                        $info = self::where([
                            ['member_id', '=', $e->member_id],
                        ])
                            ->field('avatar,nickname')
                            ->find();
                        if ($info['nickname'] && $info['avatar']) {
                            merge($e->member_id, 0);
                        }
                    }
                }
            }
        );
    }
    
    /**
     * 用户是否领取过礼包
     * @param $member_id
     * @return int
     */
    public function isGift($member_id)
    {
        $val = 1;
        if ($member_id) {
            $isGift = $this
                ->where([
                    ['member_id', '=', $member_id],
                ])->value('is_gift');
            $val = $isGift ? 0 : 1;
            if ($val) {
                $arr = Cache::store('file')->get('close_gift_list', []);
                if (in_array($member_id, $arr)) {
                    $val = 0;
                }
            }
        }
        return $val;
    }
    
    /**
     * 获取头像oss地址
     * @param $val
     * @param $data
     * @return string
     */
    public function getHeadImgAttr($val, $data)
    {
        // 如果没有设置头像的话,返回默认头像
        if (empty($data['avatar'])) {
            return Request::server('REQUEST_SCHEME') . '://' . Request::server('HTTP_HOST') . '/static/img/user_pic.png';
        }
        
        $config = config('oss.');
        
        return $config['prefix'] . $data['avatar'];
    }
    
    /**
     * 获取会员等级
     *
     * @param $value
     * @param $data
     *
     * @return mixed
     */
    public function getRankNameAttr($value, $data)
    {
        $growth_value = countGrowth($data['member_id']);
        return (new MemberRankModel)
            ->where('min_points', '<=', $growth_value)
            ->order(['min_points' => 'desc'])
            ->value('rank_name');
    }
    
    public function getGroupValueAttr($value, $data)
    {
        return countGrowth($value);
    }
    
    /**
     * 会员登录
     * @param     $param  array 登录数据
     * @param int $type 1 手机号登录 2 验证码登录
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function login($param, $type = 1)
    {
        $where = ['phone' => $param['phone']];
        if ($type == 1) {
            $where['password'] = passEnc($param['password']);
        }
        $find = $this
            ->where($where)
            ->field('member_id,nickname,phone,avatar,status,login_time,login_ip')
            ->find();
        // 用户不存在或帐号密码错误
        if (is_null($find)) {
            if ($type == 1) {
                return [
                    'code' => -1,
                    'message' => '账号或密码错误',
                ];
            }
            //进入短信登录注册
            $regCtl = app('app\\interfaces\\controller\\auth\\Register');
            $find = $regCtl->common($this, $param, (new Inform()));
            // 注册即成为分销商检测
            $regToBe = [
                'member_id' => $find['member_id'],
                'nickname' => $find['nickname'],
                'phone' => $find['phone'],
                'sex' => 0,     //默认女
                'web_open_id' => $find['web_open_id'],
                'subscribe_time' => $find['subscribe_time'],
                'micro_open_id' => $find['micro_open_id'],
                'distribution_superior' => $param['distribution_superior'],
                'bType' => 2,   //成为分销商途径注册自动成为分销商
                'text' => 2,    //注册即成为分销商
            ];
            $rb = Hook::exec(['app\\interfaces\\behavior\\Distribution', 'regToBe'], $regToBe);
            $find['status'] = 1;
            $find['distribution_id'] = '';
            if ($rb) {
                $find['distribution_id'] = $rb['distribution_id'];
            }
            if (is_null($find['avatar'])) {
                $find['avatar'] = '';
            }
        }
        // 用户被禁用
        if (!$find['status']) {
            return [
                'code' => -2,
                'message' => '账号已禁用，请联系客服',
            ];
        }
        //更改登录时间,IP等信息
        $update = [
            'member_id' => $find['member_id'],
            'login_num' => Db::raw('login_num + 1'),
            'login_time' => date('Y-m-d H:i:s'),
            'login_ip' => Request::ip(),
            'last_login_time' => array_key_exists('login_time', $find) ? $find['login_time'] : date('Y-m-d H:i:s'),
            'last_login_ip' => array_key_exists('login_ip', $find) ? $find['login_ip'] : '',
        ];
        $this->allowField(true)->isUpdate(true)->save($update);
        // 返回token
        $jwt = app('app\\common\\service\\JwtManage', [
            'param' => [
                'mid' => $find['member_id'],
                'dev_type' => $param['dev_type'],
            ]], true)->issueToken();
        header("token:" . $jwt);
        //登录成功返回用户信息
        return [
            'code' => 0,
            'message' => '登录成功',
            'data' => self::retData($find),
        ];
    }
    
    /**
     * 注册成功返回数据
     * @param $find array 总数据集合
     * @return array
     */
    public function retData($find)
    {
        $ret = [
            // 'member_id' => $find['member_id'],
            'avatar' => $find['avatar'],
            'nickname' => $find['nickname'],
            'phone' => $find['phone'],
        ];
        if (array_key_exists('distribution_id', $find) && $find['distribution_id']) {
            $ret['distribution_id'] = $find['distribution_id'];
        }
        return $ret;
    }
    
    /**
     * 店铺登录
     * @param $param
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function clientLogin($param)
    {
        // 加密密码替换原数组中 password 字段中的内容
        $param['password'] = passEnc($param['password']);
        // 查找对应的用户
        $find = $this
            ->where(['phone' => $param['phone'], 'password' => $param['password']])
            ->field('member_id,nickname,avatar,status,store_login_time')
            ->find();
        // 用户不存在或者密码错误
        if (!$find) {
            return [
                'code' => -1,
                'message' => '账号或密码错误',
            ];
        }
        // 用户被禁用
        if ($find['status'] === 0) {
            return [
                'code' => -2,
                'message' => '此账号登录受限',
            ];
        }
        
        // 判断店铺是否存在
        $store = (new StoreModel())->where(['member_id' => $find['member_id']])
            ->field('store_name,shop,store_grade_id,status,logo,store_id')
            ->find();
        
        if (!$store) {
            return ['code' => -1, 'message' => '该店铺不存在'];
        }
        
        // 判断店铺的审核状态
        switch ($store['status']) {
            case 0:
                // 入驻申请中
                return ['code' => -2, 'message' => '您的入驻申请正在审核中,请耐心等待平台审核'];
                break;
            case 2:
                // 入驻审核被拒绝
                return ['code' => -2, 'message' => '很抱歉,您的店铺入驻申请审核被拒绝'];
                break;
        }
        
        //更改登录时间,IP等信息
        $update = [
            // 用户iD
            'member_id' => $find['member_id'],
            // 最后一次登录时间
            'store_login_time' => date('Y-m-d H:i:s'),
            // 上一次登录的时间
            'store_last_login_time' => $find['store_login_time'],
        ];
        // 更新数据
        $this->allowField(TRUE)->isUpdate(TRUE)->save($update);
        // session存储
        // 店铺名称
        Session::set('client_storeName', $store['store_name']);
        // 店铺LOGO
        Session::set('client_storeLogo', $store['logo']);
        // 店铺创建者的用户ID
        Session::set('client_member_id', $find['member_id']);
        // 店铺创建者的用户昵称
        Session::set('client_nickname', $find['nickname']);
        // 店铺ID
        Session::set('client_store_id', $store['store_id']);
        // 存入融云token
        if (config('user.rong_cloud_switch') == 1 && $store['status'] == 4) {
            $store_name = Session::get('client_storeName');
            $logo = !empty(Session::get('client_storeLogo')) ? Session::get('client_storeLogo') : config('rongyun.default_avatar');
            Session::set('client_rong_token', (new IntegratingCloud())->getToken($find['member_id'], $store_name, $logo)['token']);
        }
        
        return [
            'code' => 0,
            'message' => '登录成功',
            'url' => '/client/index/index',
            'sid' => $store['store_id'],
        ];
        
    }
    
    /**
     * 获取会员等级图片
     *
     * @param $value
     *
     * @return mixed
     */
    public function RankImg($value)
    {
        $img = (new MemberRankModel)
            ->where('min_points', '<=', $value)
            ->order(['min_points' => 'desc'])
            ->value('file');
        return self::getOssUrl($img);
    }
    
    /**
     * 会员转分销商
     *
     * @return \think\model\relation\HasOne
     */
    public function distributionConvert()
    {
        return self::distribution()
            ->field('distribution_id,member_id,audit_status,top_id,branch_strand');
    }
    
    /**
     * 分销商(关联一对一)
     *
     * @return \think\model\relation\HasOne
     */
    public function distribution()
    {
        return $this->hasOne('distribution', 'member_id', 'member_id');
    }
    
    /**
     * 有分销商记录信息
     *
     * @return \think\model\relation\HasOne
     */
    public function distributionRecord()
    {
        return self::distribution()
            ->where([
                ['audit_status', '<>', 2],
            ])
            ->field(
                'distribution_id,top_id,branch_strand,distribution_level_id,
            member_id,referrer_id,audit_status'
            );
    }
    
    public function supDistributionInfo()
    {
        return self::supDistribution()
            ->with(['memberBaseInfo'])
            ->field('distribution_id,member_id');
    }
    
    /**
     * 上级分销商(相对一对多)
     *
     * @return \think\model\relation\BelongsTo
     */
    public function supDistribution()
    {
        return $this->belongsTo('distribution', 'distribution_superior', 'distribution_id');
    }
}