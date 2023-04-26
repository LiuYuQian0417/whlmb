<?php
declare(strict_types=1);

namespace app\common\model;

use think\exception\ValidateException;
use think\facade\Env;
use think\Model;
use think\model\concern\SoftDelete;

/**
 * 模型基类
 * Class BaseModel
 * @package app\common\model
 */
class BaseModel extends Model
{
    use SoftDelete;
    public $pageLimits = 10;
    //oss文件是否私有
    protected $isPrivateOss = true;
    public static $oneOrMore;
    public static $oneStoreId;
    public static $storeAuthSql;

    public static function init()
    {
        // 当前0单店铺还是1多店铺
        self::$oneOrMore = config('user.one_more');
        // 单店铺id
        self::$oneStoreId = config('user.one_store_id');
        // 店铺是否有效sql
        self::$storeAuthSql = 's.delete_time is null and s.status = 4 and if(s.end_time,unix_timestamp(s.end_time) > unix_timestamp(),1)';
        // 单店铺拼接单店铺id
        if (!self::$oneOrMore) {
            self::$storeAuthSql .= ' and s.store_id = ' . self::$oneStoreId;
        }
        self::beforeWrite(
            function ($e) {
                $e->update_time = date('Y-m-d H:i:s');
            }
        );
        self::beforeInsert(
            function ($e) {
                $e->create_time = date('Y-m-d H:i:s');
            }
        );
    }

    /**
     * 上传文件
     * @param        $name         string 上传文件的字段名
     * @param        $prefix       string 上传到oss的文件路径前缀
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException*@throws \Exception
     * @throws \Exception
     */
    public static function upload($name, $prefix)
    {
        $file = request()->file($name);
        $info = $file ? $file->getInfo() : [];
        $ossFileName = '';
        if (!empty($info)) {
            $ossFileName = $prefix . md5(microtime()) . strtolower(strrchr($_FILES[$name]['name'], '.'));
            $ossManage = app('app\\common\\service\\OSS');
            $ossManage->fileUpload($ossFileName, $info['tmp_name']);
        }
        return $ossFileName;
    }

    /**
     * 验证数据
     *
     * @param $data  array 被验证的数据
     * @param $scene string 验证场景
     */
    public function valid($data, $scene = '')
    {
        $validInstance = app(str_replace('model', 'validate', get_class($this)));
        $res = $validInstance
            ->scene($scene)
            ->check($data);
        if (!$res) {
            throw new ValidateException($validInstance->getError(), -2);
        }
    }

    /**
     * 更改单信息状态[当前只适用于两种状态切换]
     *
     * @param        $id        string|integer 主键ID值
     * @param string $parameter 参数
     */
    public function changeStatus($id, $parameter = 'status')
    {
        //查询当前状态
        $curStatus = $this->where([$this->pk => $id])->value($parameter);
        //更改当前状态
        $this->isUpdate(TRUE)->where([$this->pk => $id])->update([$parameter => $curStatus ? 0 : 1]);
    }

    /**
     * 点击编辑文本内容
     *
     * @param $param array 传输数据
     */
    public function clickEdit($param)
    {
        //更改当前数据
        $this->isUpdate(TRUE)->where([$this->pk => $param['id']])->update([$param['parameter'] => $param['data']]);
    }

    /**
     * 公共更改oss图片路径
     * @param $value
     * @return array|string
     */
    public function getOssUrl($value)
    {
        if (is_null($value) || $value == '') {
            return '';
        }
        $config = config('oss.');
        if (is_array($value)) {
            $image = [];
            foreach ($value as $val) {
                $image[] = $config['prefix'] . $val . $config['style'][0];
            }
            return $image;
        }
        return $config['prefix'] . $value . $config['style'][0];
    }

    /**
     * 获取图片oss地址
     * @param $value
     * @return mixed
     */
    public function getFileAttr($value)
    {
        return $this->getOssUrl($value);
    }

    public function getFile1Attr($value)
    {
        return $this->getOssUrl($value);
    }

    public function getFile2Attr($value)
    {
        return $this->getOssUrl($value);
    }



    /**
     * 获取多张图片oss地址
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getMultipleFileAttr($value, $data)
    {
        if (!empty($value)) {
            $arr = explode(',', $value);
            return $this->getOssUrl($arr);
        } elseif (!array_key_exists('video', $data) || !$data['video']) {
            // 并且无视频文件,则默认传输缩略图
            if (array_key_exists('file', $data) && $data['file']) {
                return [$this->getOssUrl($data['file'])];
            }
        }
        return [];
    }

    /**
     * 获取视频oss地址
     * @param $value
     * @return mixed
     */
    public function getVideoAttr($value)
    {
        $config = config('oss.');
        return $value ? $config['prefix'] . $value : '';
    }

    /**
     * 输出视频截帧
     * @param $value
     * @param $data
     * @return string
     */
    public function getVideoSnapshotAttr($value, $data)
    {
        $img = '';
        if (isset($data['video']) && $data['video']) {
            $slice = '?x-oss-process=video/snapshot,t_00,f_jpg,w_0,h_0';
            $config = config('oss.');
            $img = $config['prefix'] . $data['video'] . $slice;
        }
        return $img;
    }

    /**
     * 获取头像
     * @param $value
     * @return array|string
     */
    public function getAvatarAttr($value)
    {
        return $this->getOssUrl($value);
    }

    /**
     * 获取品牌logo
     * @param $value
     * @return array|string
     */
    public function getBrandLogoAttr($value)
    {
        return $this->getOssUrl($value);
    }

    /**
     * 获取logo
     * @param $value
     * @return mixed
     */
    public function getLogoAttr($value)
    {
        return $this->getOssUrl($value);
    }

    /**
     * 获取头像
     * @param $value
     * @return string
     */
    public function getImgAttr($value)
    {
        return $this->getOssUrl($value);
    }

    /**
     * 获取推荐地址
     * @param $value
     * @return mixed
     */
    public function getRecommeFileAttr($value)
    {
        return $this->getOssUrl($value);
    }


    /**
     * 获取pc推荐地址
     * @param $value
     * @return mixed
     */
    public function getPcRecommeFileAttr($value)
    {
        return $this->getOssUrl($value);
    }
    /**
     * @param $value
     * @return array|string
     */
    public function getWebFileAttr($value)
    {
        return $this->getOssUrl($value);
    }

    /**
     * 邀请
     * @param $value
     * @return array|string
     */
    public function getInviteCodeAttr($value)
    {
        return $this->getOssUrl($value);
    }

    /**
     * 获取规格属性oss图
     * @param $value
     * @return array|string
     */
    public function getAttrFileImgAttr($value, $data)
    {
        return $this->getOssUrl($data['attr_file']);
    }

    public function getAttrFileExtraAttr($value, $data)
    {
        return $this->getOssUrl($data['attr_file']);
    }

    /**
     * 当前商品是否为真实砍价状态
     * @param $value
     * @param $data
     * @return int
     */
    public function getIsBargainAttr($value, $data)
    {
        if ($data['is_bargain'] && env('is_cut', 1)) {
            Env::load(Env::get('app_path') . 'common/ini/.config');
            $checkCut = (new Goods())
                ->alias('g')
                ->where([
                    ['g.goods_id', '=', $data['goods_id']],
                ])
                ->join('cut_goods cg', 'cg.goods_id = g.goods_id and cg.status = 1 and cg.delete_time is null
            and cg.up_shelf_time <= \'' . date('Y-m-d') . '\' and cg.down_shelf_time >= \'' . date('Y-m-d') . '\'')
                ->value('cut_goods_id');
            return is_null($checkCut) ? 0 : 1;
        }
        return 0;
    }

    /**
     * 当前商品是否为真实拼团状态
     * @param $value
     * @param $data
     * @return int
     */
    public function getIsGroupAttr($value, $data)
    {
        if ($data['is_group'] && env('is_group', 1)) {
            Env::load(Env::get('app_path') . 'common/ini/.config');
            $checkCut = (new Goods())
                ->alias('g')
                ->where([
                    ['g.goods_id', '=', $data['goods_id']],
                    ['g.is_putaway', '=', 1],
                    ['g.review_status', '=', 1],
                ])
                ->join('group_goods gg', 'gg.goods_id = g.goods_id and gg.status = 1 and gg.delete_time is null
            and gg.up_shelf_time <= \'' . date('Y-m-d') . '\' and gg.down_shelf_time >= \'' . date('Y-m-d') . '\'')
                ->value('group_goods_id');
            return is_null($checkCut) ? 0 : 1;
        }
        return 0;
    }

    /**
     * 商品列表检测是否为有效限购
     * @param $value
     * @param $data
     * @return int
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getIsLimitAttr($value, $data)
    {
        if ($data['is_limit'] && env('is_limit', 1)) {
            Env::load(Env::get('app_path') . 'common/ini/.config');
            // 查询数据
            $limit_find = (new Limit())
                ->where([
                    ['goods_id', '=', $data['goods_id']],
                    ['status', '=', 1],
                    ['up_shelf_time', '<=', date('Y-m-d')],
                    ['down_shelf_time', '>=', date('Y-m-d')],
                    ['exchange_num', '>', 0],
                ])
                ->field('available_sale,exchange_num,interval_id')
                ->order(['create_time' => 'desc'])
                ->find();
            if (!is_null($limit_find)) {
                // 查询时间段
                $now_limit_interval = (new LimitInterval())
                    ->where([
                        ['start_time', '<=', date('H')],
                        ['end_time', '>', date('H')],
                    ])
                    ->value('limit_interval_id');
                if (!is_null($now_limit_interval)) {
                    $has = in_array($now_limit_interval, explode(',', $limit_find['interval_id']));
                    return $has ? 1 : 0;
                }
                return 0;
            }
            return 0;
        }
        return 0;
    }

    /**
     * 是否为可分销商品[排除活动]
     * @param $value
     * @param $data
     * @return int
     */
    public function getIsDistributionAttr($value, $data)
    {
        if ($data['is_distribution']) {
            Env::load(Env::get('app_path') . 'common/ini/.distribution');
            $distributionSet = Env::get();
            if (!$distributionSet['DISTRIBUTION_STATUS'] || $data['is_limit'] || $data['is_group'] || $data['is_bargain']) {
                return 0;
            }
            return 1;
        };
        return $data['is_distribution'];
    }

    /**
     * 是否为指定分销商品[排除活动]
     * @param $value
     * @param $data
     * @return int
     */
    public function getIsDistributorAttr($value, $data)
    {
        if ($data['is_distributor']) {
            Env::load(Env::get('app_path') . 'common/ini/.distribution');
            $distributionSet = Env::get();
            if (!$distributionSet['DISTRIBUTION_STATUS'] || $data['is_limit'] || $data['is_group'] || $data['is_bargain']) {
                return 0;
            }
            return 1;
        }
        return $data['is_distributor'];
    }

}