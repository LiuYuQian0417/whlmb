<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 数据库表备份文件过渡表
 * Class DbBackup
 * @package app\common\model
 */
class DbBackup extends BaseModel
{
    protected $pk = 'backup_id';
}