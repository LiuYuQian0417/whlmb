<?php
declare(strict_types = 1);

namespace app\interfaces\behavior;

use app\common\model\Store;

class StoreCheck
{
    /**
     * 检测会员当时是否入驻
     * @param $args
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function isSettledIn($args)
    {
        $ret = [
            'code' => -1,
            'store_name' => '',
        ];
        // 多店情况验证
        if (array_key_exists('member_id', $args) && $args['member_id'] && config('user.one_more')) {
            $storeModel = new Store();
            $check = $storeModel::withTrashed()
                ->where([
                    ['member_id', '=', $args['member_id']],
                ])
                ->field('store_id,status,store_name,delete_time')
                ->find();
            if (!is_null($check)) {
                $ret = [
                    'code' => $check['delete_time'] ? 6 : $check['status'],
                    'store_name' => $check['store_name'],
                ];
            }
        }
        return $ret;
    }
}