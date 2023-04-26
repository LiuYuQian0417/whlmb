<?php
declare(strict_types=1);

namespace app\common\model;


class ShopStyle extends BaseModel
{

    /**
     * 获取所有颜色
     * @return array
     */
    public static function _GetAll()
    {
        $_data = [
            'base' => [],
            'diy' => [],
            'current' => [
                'id' => NULL,
                'primary' => [
                    'r' => 255,
                    'g' => 255,
                    'b' => 255,
                ],
                'deputy' => [
                    'r' => 255,
                    'g' => 255,
                    'b' => 255,
                ],
                'contrast' => [
                    'r' => 255,
                    'g' => 255,
                    'b' => 255,
                ],
            ],
        ];

        try {
            $_styles = self::all();

            if (!$_styles->isEmpty()) {
                foreach ($_styles as $val) {
                    if ($val['can_change'] == 2) {
                        $_data['base'][] = $val;
                    } else {
                        $_data['diy'][] = $val;
                    }
                }
            }
        } catch (\Exception $e) {
        }
        return $_data;
    }

    public static function _GetCurrentColor($id)
    {

        $_data = [
            'code' => 0,
            'message' => '',
            'result' => [],
        ];

        try {
            $_color = self::get($id);

            if ($_color) {
                $_data['result'] = [
                    // 主要颜色
                    'primary_r' => $_color['primary_r'],
                    'primary_g' => $_color['primary_g'],
                    'primary_b' => $_color['primary_b'],
                    'primary_hex' => RGBToHex([
                        $_color['primary_r'],
                        $_color['primary_g'],
                        $_color['primary_b'],
                    ]),
                    // 次要颜色
                    'deputy_r' => $_color['deputy_r'],
                    'deputy_g' => $_color['deputy_g'],
                    'deputy_b' => $_color['deputy_b'],
                    'deputy_hex' => RGBToHex([
                        $_color['deputy_r'],
                        $_color['deputy_g'],
                        $_color['deputy_b'],
                    ]),
                    // 防混淆颜色
                    'contrast_r' => $_color['contrast_r'],
                    'contrast_g' => $_color['contrast_g'],
                    'contrast_b' => $_color['contrast_b'],
                    'contrast_hex' => RGBToHex([
                        $_color['contrast_r'],
                        $_color['contrast_g'],
                        $_color['contrast_b'],
                    ]),
                ];
            }
        } catch (\Exception $e) {
            $_data['code'] = -1;
            $_data['message'] = "获取风格出错";
        }

        return $_data;
    }

    public static function _Destroy($id)
    {
        try {
            $_style = self::get($id);

            if (!$_style) {
                return ['code' => -1, 'message' => '不存在的配色'];
            }

            if ($_style['can_change'] == 2) {
                return ['code' => -1, 'message' => '您不能修改基础配色'];
            }

            $_style->delete();

            return ['code' => 0, 'message' => '删除成功'];

        } catch (\Exception $exception) {
            return ['code' => '-1000', 'message' => '请求出错'];
        }
    }
}