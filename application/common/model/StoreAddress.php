<?php
declare(strict_types=1);

namespace app\common\model;


use think\Db;
use think\facade\Session;

class StoreAddress extends BaseModel
{
    protected $pk = 'store_address_id';

    public function storeBlt()
    {
        return $this->belongsTo('Store', 'store_id', 'store_id');
    }

    /**
     * 获取联系人手机号
     * @param $val
     * @param $data
     * @return mixed
     */
    protected function getContactsNumberAttr($val, $data)
    {
        if (!empty($data['telephone'])) {
            return $data['telephone'];
        } else {
            return $data['phone_number'];
        }
    }

    /**
     * 获取联系方式
     * @param $val
     * @param $data
     * @return string
     */
    protected function getContactNumberAttr($val, $data)
    {
        return $data['phone_number'] ?? $data['telephone'];
    }


    protected function getProvinceAttr($val, $data)
    {
        return explode('-', $data['address_area'])[0];
    }

    protected function getCityAttr($val, $data)
    {
        return explode('-', $data['address_area'])[1];
    }

    protected function getAreaAttr($val, $data)
    {
        $array = explode('-', $data['address_area']);

        if (isset($array[2])) {
            return explode('-', $data['address_area'])[2];
        }
    }

    protected function getTelephoneForEditAttr($val, $data)
    {
        if (!empty($data['telephone'])) {
            return explode('-', $data['telephone']);
        }
    }

    /**
     * 商家管理后台列表
     */
    public static function _GetListForConsole()
    {
        try {
            return self::where([
                'store_id' => Session::get('client_store_id')
            ])
                ->order('store_address_id DESC')
                ->paginate(10, false);
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function _CreateForConsole($data)
    {
        try {
            Db::startTrans();
            $_data = [
                'store_id' => $data['store_id'] ?? Session::get('client_store_id'),
                'contact_name' => $data['contact_name'],
                'phone_number' => $data['phone_number'],
                'address_area' => $data['province'] . '-' . $data['city'] . '-' . $data['area'],
                'address_area_text' => $data['province'] . $data['city'] . $data['area'],
                'address_location_text' => $data['address'],
                'address_lng' => $data['lng'],
                'address_lat' => $data['lat'],
                'default_return_address' => $data['default_return_address']
            ];
            // 是否填写了座机号
            if (!empty($data['telephone'][0]) && !empty($data['telephone'][1])) {
                $_data['telephone'] = $data['telephone'][0] . '-' . $data['telephone'][1];
                // 是否填写分机号
                if (!empty($_data['telephone'][2])) {
                    $_data['telephone'] .= '-' . $data['telephone'][2];
                }
            }
            //是否填写了手机号
            if (!empty($data['phone_number'])) {
                $_data['phone_number'] = $data['phone_number'];
            }
            // 是否是退货地址
            if (isset($data['return_address']) && $data['return_address'] == '1') {
                $_data['return_address'] = 1;
            }
            // 是否是发货地址
            if (isset($data['shipping_address']) && $data['shipping_address'] == '1') {
                $_data['shipping_address'] = 1;
            }

            $area = Area::where([
                'area_id' => [
                    $data['province'],
                    $data['city'],
                    $data['area']
                ]
            ])
                ->field([
                    'area_id',
                    'area_name',
                ])
                ->orderRaw("field(area_id,{$data['province']},{$data['city']},{$data['area']})")
                ->select();
            $_data['address_area'] = "{$area[0]['area_name']}-{$area[1]['area_name']}-{$area[2]['area_name']}";
            $_data['address_area_text'] = $area[0]['area_name'] . $area[1]['area_name'] . $area[2]['area_name'];
            $_result = self::create($_data);
            if ($data['default_return_address'] == '1') {
                // 取消其他的地址的默认退货地址
                self::update(['default_return_address' => 2], [
                    ['store_address_id', '<>', $_result->store_address_id],
                    ['store_id', '=', $data['store_id'] ?? Session::get('client_store_id')]
                ]);
            }
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }

    }

    public static function _UpdateForConsole($data)
    {
        try {
            Db::startTrans();
            $_data = [
                'contact_name' => $data['contact_name'],
                'phone_number' => $data['phone_number'],
                'address_area' => $data['province'] . '-' . $data['city'] . '-' . $data['area'],
                'address_area_text' => $data['province'] . $data['city'] . $data['area'],
                'address_location_text' => $data['address'],
                'address_lng' => $data['lng'],
                'address_lat' => $data['lat'],
                'default_return_address' => $data['default_return_address']
            ];
            // 是否填写了座机号
            if (!empty($data['telephone'][0]) && !empty($data['telephone'][1])) {
                $_data['telephone'] = $data['telephone'][0] . '-' . $data['telephone'][1];
                // 是否填写分机号
                if (!empty($_data['telephone'][2])) {
                    $_data['telephone'] .= '-' . $data['telephone'][2];
                }
            }else{
                $_data['telephone'] = '';
            }

            //是否填写了手机号
            if (!empty($data['phone_number'])) {
                $_data['phone_number'] = $data['phone_number'];
            }
            // 是否是退货地址
            if (isset($data['return_address']) && $data['return_address'] == '1') {
                $_data['return_address'] = 1;
            } else {
                $_data['return_address'] = 2;
            }
            // 是否是发货地址
            if (isset($data['shipping_address']) && $data['shipping_address'] == '1') {
                $_data['shipping_address'] = 1;
            } else {
                $_data['shipping_address'] = 2;
            }

            if ($data['default_return_address'] == '1') {
                // 取消其他的地址的默认退货地址
                self::update(['default_return_address' => 2], [
                    ['store_address_id', '<>', $data['store_address_id']],
                    ['default_return_address', '=', 1],
                    ['store_id', '=', $data['store_id'] ?? Session::get('client_store_id')]
                ]);
            }

            $area = Area::where([
                'area_id' => [
                    $data['province'],
                    $data['city'],
                    $data['area']
                ]
            ])
                ->field([
                    'area_id',
                    'area_name',
                ])
                ->orderRaw("field(area_id,{$data['province']},{$data['city']},{$data['area']})")
                ->select();

            $_data['address_area'] = "{$area[0]['area_name']}-{$area[1]['area_name']}-{$area[2]['area_name']}";
            $_data['address_area_text'] = $area[0]['area_name'] . $area[1]['area_name'] . $area[2]['area_name'];

            self::update($_data, [
                'store_address_id' => $data['store_address_id']
            ]);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    public static function _GetForEditForConsole($id)
    {
        try {
            $_storeAddress = self::get(['store_address_id' => $id]);

            return [
                'store_address_id' => $_storeAddress['store_address_id'],
                'contact_name' => $_storeAddress['contact_name'],
                'phone_number' => $_storeAddress['phone_number'],
                'telephone_for_edit' => $_storeAddress['telephone_for_edit'],
                'province' => $_storeAddress['province'],
                'city' => $_storeAddress['city'],
                'area' => $_storeAddress['area'],
                'address_location_text' => $_storeAddress['address_location_text'],
                'address_lng' => $_storeAddress['address_lng'],
                'address_lat' => $_storeAddress['address_lat'],
                'return_address' => $_storeAddress['return_address'],
                'shipping_address' => $_storeAddress['shipping_address'],
                'default_return_address' => $_storeAddress['default_return_address'],
            ];

        } catch (\Exception $e) {
            dump($e->getMessage());
            return [];
        }

    }
}