<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 短信模板
 * Class SmsTemplate
 * @package app\common\model
 */
class SmsTemplate extends BaseModel
{
    protected $resultSetType = 'collection';
    protected $pk = 'sms_id';
    public $type = [
        '缺省场景', '注册', '忘记密码', '找回密码',
        '修改密码', '付款', '下单', '发货',
        '降价提醒', '短信登录', '绑定手机号', '商家入驻通知',
    ];

    public static function init()
    {
        parent::init();
        self::beforeInsert(function ($e) {
            $e->status = ($e['type'] == 1) ? 1 : 0;
            $e->error = '';
        });
        self::beforeUpdate(function ($e) {
            $e->status = ($e['type'] == 1) ? 1 : 0;
        });
    }

    /**
     * 模板使用场景
     * @param $val
     * @param $data
     * @return mixed
     */
    public function getSceneTextAttr($val, $data)
    {
        return isset($this->type[$data['scene']]) ? $this->type[$data['scene']] : '未知';
    }

    /**
     * 检测该类型已有模板场景
     * @param int $type 短信类型 1阿里云 1腾讯云
     * @return array
     */
    public function checkHas($type = 1)
    {
        $has = $this
            ->where([
                ['type', '=', $type],
            ])
            ->distinct(true)
            ->column('scene');
        return $has;
    }

    /**
     * 短信模板通用添加
     * @param $param
     * @return false|int
     */
    public function add($param)
    {
        $res = $this
            ->allowField(true)
            ->save($param);
        return $res;
    }

    /**
     * 短信模板通用更新
     * @param $param
     * @return false|int
     */
    public function edit($param)
    {
        $res = $this
            ->allowField(true)
            ->isUpdate(true)
            ->save($param);
        return $res;
    }

}