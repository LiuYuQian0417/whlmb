<?php
declare(strict_types=1);

namespace app\common\model;

use app\common\model\IntegralClassify as IntegralClassifyModel;

/**
 * 积分商城模型
 * Class Integral
 * @package app\common\model
 */
class Integral extends BaseModel
{
    protected $pk = 'integral_id';

    public static function init()
    {
        self::beforeInsert(function ($e) {
            $e->create_time = date('Y-m-d H:i:s');
        });
        self::beforeWrite(function ($e) {
            $e->update_time = date('Y-m-d H:i:s');
            $file = self::upload('image', 'integral/file/' . date('Ymd') . '/');
            if ($file) {
                $e->file = $file;
            }
        });
    }

    /**
     * web内容解析
     * @param $value
     * @return mixed
     */
    public function getWebContentAttr($value)
    {
        $content = '<meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">';
        $content .= $value;
        return $content;
    }

    // 积分商品分类获取器
    public function getIntegralClassifyIdTextAttr($value, $data)
    {
        $className = (new IntegralClassifyModel())
            ->where([
                ['integral_classify_id', '=', $data['integral_classify_id']],
            ])
            ->value('title');
        return $className;
    }

    public function getMultipleFileExtraAttr($value, $data)
    {
        return $data['multiple_file'] ? explode(',', $data['multiple_file']) : [];
    }
}