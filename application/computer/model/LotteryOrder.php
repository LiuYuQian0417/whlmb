<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/21 0021
 * Time: 16:15
 */

namespace app\computer\model;

use app\common\model\LotteryOrder as LotteryOrderModel;

class LotteryOrder extends LotteryOrderModel
{


    protected $pk='lottery_order_id';

    public static function init()
    {
        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s');
        });
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
        });
    }


    /**
     * 获取图片oss地址
     * @param $value
     * @return mixed
     */
    public function getFileAttr($value)
    {

        if ($this->isPrivateOss && $value) {
            $ossManage = app('app\\common\\service\\OSS');
//            $res = $ossManage->getSignUrlForGet($value . config('oss.')['style'][2]);
            $res = $ossManage->getSignUrlForGet($value);
            $value = ($res['code'] === 0) ? $res['url'] : '';
        }
        return $value;
    }

    /**
     * 图片源路径
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getFileExtraAttr($value, $data)
    {
        return $data['file'];
    }



    /**
     * 关联用户(相对一对多)
     * @return \think\model\relation\BelongsTo
     */
    public function member()
    {
        return $this->belongsTo('Member', 'member_id', 'member_id');
    }

}