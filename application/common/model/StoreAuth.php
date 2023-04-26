<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 店铺认证模型
 * Class Manage
 *
 * @package app\common\model
 */
class StoreAuth extends BaseModel
{
    protected $pk = 'store_auth_id';

    public static function init()
    {
        self::beforeInsert(
            function ($e) {
                $e->create_time = date('Y-m-d H:i:s');
            }
        );
    }

    public function getIDFrontFileAttr($value)
    {
        $config = config('oss.');
        return $value ? $config['prefix'] . $value : '';
    }

    public function getIDBackFileAttr($value)
    {
        $config = config('oss.');
        return $value ? $config['prefix'] . $value : '';
    }

    public function getBankFileAttr($value)
    {
        $config = config('oss.');
        return $value ? $config['prefix'] . $value : '';
    }

    public function getLicenceFileAttr($value)
    {
        $config = config('oss.');
        return $value ? $config['prefix'] . $value : '';
    }

    public function getQualificationFileAttr($value)
    {
        $config = config('oss.');
        return $value ? $config['prefix'] . $value : '';
    }
    public function getBankProvinceNameAttr($value, $data)
    {
        return (new Area())
            ->where([
                ['area_id', '=', $data['bank_province']],
            ])
            ->value('area_name');
    }

    public function getBankCityNameAttr($value, $data)
    {
        return (new Area())
            ->where([
                ['area_id', '=', $data['bank_city']],
            ])
            ->value('area_name');
    }

    public function getBankAreaNameAttr($value, $data)
    {
        return (new Area())
            ->where([
                ['area_id', '=', $data['bank_area']],
            ])
            ->value('area_name');
    }
}