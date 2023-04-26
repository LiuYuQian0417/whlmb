<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 权限组模型
 * Class AuthGroup
 * @package app\common\model
 */
class AuthGroup extends BaseModel
{
    protected $pk = 'auth_group_id';

    public static function init()
    {
        parent::init();
        self::beforeInsert(function ($e) {
            // 初始化全部权限
            $e->rules = 0;
        });
    }

    /**
     * 权限组所拥有权限[有效组]
     * @param $group_id
     * @return array
     */
    public function groupRules($group_id)
    {
        $ruleStr = $this
            ->where([
                ['auth_group_id', '=', $group_id],
            ])
            ->value('rules');
        return $ruleStr ? explode(',', $ruleStr) : [];
    }

    /**
     * 查询全部有效权限组
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getAuthGroup()
    {
        $authGroupData = $this
            ->where([
                ['status', '=', 1],
            ])
            ->field('auth_group_id,title')
            ->select();
        return $authGroupData;
    }
}