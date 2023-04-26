<?php


namespace app\common\validate;


use app\common\model\Area as AreaModel;
use app\common\model\StoreClassify as StoreClassifyModel;
use think\Exception\DbException;
use think\Validate;

class BaseValidate extends Validate
{

    /**
     * 验证身份证号
     *
     * @param $idCard
     * @return bool
     */
    protected function IdCard($idCard)
    {
        //15位和18位身份证号码的正则表达式
        $_regIdCard = '/^[1-9]\d{5}[1-9]\d{3}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])((\d{4})|\d{3}[Xx])$/';

        //如果通过该验证，说明身份证格式正确，但准确性还需计算
        //if($_regIdCard.test(idCard)){
        if (!preg_match($_regIdCard, $idCard)) {
            return FALSE;
        }


        if (strlen($idCard) !== 18) {
            return FALSE;
        }

        $_idCardWi = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2]; //将前17位加权因子保存在数组里
        $_idCardY = [1, 0, 10, 9, 8, 7, 6, 5, 4, 3, 2]; //这是除以11后，可能产生的11位余数、验证码，也保存成数组
        $_idCardWiSum = 0; //用来保存前17位各自乖以加权因子后的总和

        for ($i = 0; $i < 17; $i++) {
            $_idCardWiSum += substr($idCard, $i, 1) * $_idCardWi[$i];
        }

        $_idCardMod = $_idCardWiSum % 11;//计算出校验码所在数组的位置

        $_idCardLast = substr($idCard, -1);//得到最后一位身份证号码

        //如果等于2，则说明校验码是10，身份证号码最后一位应该是X
        if ($_idCardMod === 2) {
            if ($_idCardLast === "X" || $_idCardLast === "x") {
                return TRUE;
            }
        } else {
            //用计算出的验证码与最后一位身份证号码匹配，如果一致，说明通过，否则是无效的身份证号码
            if ($_idCardLast == $_idCardY[$_idCardMod]) {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * 验证店铺分类是否存在
     *
     * @param $value
     * @return bool
     * @throws DbException
     */
    protected function storeCategoryExist($value)
    {
        return StoreClassifyModel::get($value)? true:false;
    }


    /**
     * 验证省市区是否存在
     *
     * @param $value
     * @return bool
     * @throws DbException
     */
    protected function areaExist($value)
    {
        return AreaModel::get($value) ? true:false;
    }

}