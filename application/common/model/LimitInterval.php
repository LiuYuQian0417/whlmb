<?php
declare(strict_types=1);

namespace app\common\model;

/**
 * 固定限时抢购时段表
 * Class LimitInterval
 * @package app\common\model
 */
class LimitInterval extends BaseModel
{
    protected $pk = 'limit_interval_id';

    public function getStatusAttr($value, $data)
    {
        // 当前状态
        $limit_interval_id = self::where([
            ['start_time', '<=', date('H')],
            ['end_time', '>', date('H')]
        ])
            ->value('limit_interval_id');
        if ($limit_interval_id == $data['limit_interval_id']) {
            return 1;
        } else if ($limit_interval_id < $data['limit_interval_id']) {
            return 2;
        } else {
            return 0;
        }
    }

    /**
     * 返回当前限购时间段信息
     * @return array|\PDOStatement|string|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCurrent()
    {
        $cur = $this
            ->where([
                ['start_time', '<=', date('H')],
                ['end_time', '>', date('H')]
            ])
            ->field('limit_interval_id,interval_name,end_time')
            ->find();
        if (!is_null($cur)) {
            // 倒计时
            $cur['count_down'] = strtotime(date('Y-m-d ' . $cur['end_time'] . ':00:00')) - time();
        }
        return is_null($cur) ? [] : $cur;
    }

    /**
     * 联系获得5个时间段
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getStage()
    {
        $stage = $this
            ->where([
                ['end_time', '>', date('H')],
            ])
            ->field('limit_interval_id,interval_name,end_time')
            ->order(['start_time' => 'asc'])
            ->limit(5)
            ->select();
        return $stage->isEmpty() ? [] : $stage->toArray();
    }

}