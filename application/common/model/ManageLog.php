<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 管理员日志
 * Class ManageLog
 * @package app\common\model
 */
class ManageLog extends BaseModel
{
    protected $pk = 'manage_log_id';

    /**
     * 关联管理表
     * @return \think\model\relation\BelongsTo
     */
    public function manage()
    {
        return $this->belongsTo('Manage', 'manage_id', 'manage_id');
    }

}