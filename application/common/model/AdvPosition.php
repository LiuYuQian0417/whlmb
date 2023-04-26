<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 广告位置模型
 * Class Manage
 * @package app\common\model
 */
class AdvPosition extends BaseModel
{
    protected $pk = 'adv_position_id';

    public function getParentNameAttr($value, $data)
    {
        return $this
            ->where([
                [$this->pk, '=', $data['parent_id']],
            ])
            ->value('title');
    }

    public function getTypeNameAttr($value, $data)
    {
        $status = [0 => '单图', 1 => '多图'];
        return $status[$data['type']];
    }


}