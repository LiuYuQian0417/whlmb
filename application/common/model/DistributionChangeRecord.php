<?php
declare(strict_types=1);

namespace app\common\model;

use think\facade\Env;

/**
 * 分销商升降级记录
 * Class DistributionChangeRecord
 * @package app\common\model
 */
class DistributionChangeRecord extends BaseModel
{
    protected $pk = 'distribution_change_record_id';

    /**
     * 升降级记录内容
     * @param $value
     * @param $data
     * @return string
     */
    public function getRecordContentAttr($value, $data)
    {
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $content = '';
        if ($data['change_type'] == 1) {
            // 升级
            $upgrade_content = Env::get('upgrade_content');
            if ($upgrade_content && strstr($upgrade_content, '%s')) {
                $content = sprintf($upgrade_content, $data['now_title']);
            }
        } else {
            // 降级
            $down_content = Env::get('down_content');
            if ($down_content && strstr($down_content, '%s')) {
                $content = sprintf($down_content, $data['now_title']);
            }
        }
        return $content;
    }
}