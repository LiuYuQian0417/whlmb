<?php
declare(strict_types=1);

namespace app\master\controller;

use app\common\service\Lock;
use think\Controller;
use think\facade\Env;
use think\facade\Request;
use app\common\model\DistributionLevel as DistributionLevelModel;

class DistributionSwitch extends Controller
{
    /**
     * 分销系统设置文件路径
     * @var
     */
    private $filename;

    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.distribution');
        $this->filename = Env::get('APP_PATH') . 'common/ini/.distribution';
    }

    /**
     * 分销系统设置
     * @param Request $request
     * @return array|mixed
     */
    public function index(Request $request)
    {
        $distribution = Env::get();
        if ($request::isPost()) {
            try {
                $param = $request::post();
                // 分销功能开关
                $distribution_status = 0;
                if (array_key_exists('distribution_status', $param)) {
                    $distribution_status = $param['distribution_status'] == 'on' ? 1 : 0;
                    if ($distribution_status) {
                        self::createFirstLevel((new DistributionLevelModel()));
                        self::createFirstBecome(TRUE);
                    }
                }
                // 是否允许商家编辑分销商等级的分佣比例
                $distribution_proportion = $distribution_goods_profit = 0;
                if (array_key_exists('distribution_proportion', $param)) {
                    $distribution_proportion = $param['distribution_proportion'] == 'on' ? 1 : 0;
                }
                // 是否按照商品利润分佣
                if (array_key_exists('distribution_goods_profit', $param)) {
                    $distribution_goods_profit = $param['distribution_goods_profit'] == 'on' ? 1 : 0;
                }
                $lock = new Lock();
                $lr = $lock->lock(['dist_set'], 10000);
                if ($lr === FALSE) {
                    return ['code' => -1, 'message' => '检测正在有其他人员修改,请重试'];
                }
                if ($distribution_status != $distribution['DISTRIBUTION_STATUS']) {
                    ini_file(NULL, 'distribution_status', $distribution_status, $this->filename);
                }
                if ($distribution_proportion != $distribution['DISTRIBUTION_PROPORTION']) {
                    ini_file(NULL, 'distribution_proportion', $distribution_proportion, $this->filename);
                }
                if ($distribution_goods_profit != $distribution['DISTRIBUTION_GOODS_PROFIT']) {
                    ini_file(NULL, 'distribution_goods_profit', $distribution_goods_profit, $this->filename);
                }
                $lock->unlock($lr);
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        return $this->fetch('', [
            'distribution' => $distribution,
            'single_store' => config('user.one_more'),
        ]);
    }

    /**
     * 创建默认分销商等级
     * @param DistributionLevelModel $distributionLevel
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function createFirstLevel(DistributionLevelModel $distributionLevel)
    {
        // 查看当前是否含有默认等级
        $lowest = $distributionLevel
            ->where([['level_weight', '=', 0]])
            ->field('distribution_level_id')
            ->find();
        if (is_null($lowest)) {
            $c = [
                'level_title'               => '默认分销商等级',
                'level_weight'              => 0,
                'mark'                      => '',
                'upgrade_total_brokerage'   => 1000,
                'upgrade_total_order_num'   => 100,
                'upgrade_total_order_sum'   => 5000,
                'upgrade_direct_next_num'   => 100,
                'upgrade_next_num'          => 100,
                'upgrade_relation'          => 1,
                'downgrade_brokerage_cycle' => 30,
                'downgrade_brokerage_sum'   => 5000,
                'downgrade_order_cycle'     => 30,
                'downgrade_order_sum'       => 5000,
                'one_ratio'                 => 3,
                'two_ratio'                 => 2,
                'three_ratio'               => 1,
            ];
            // 创建默认分销商等级
            $distributionLevel
                ->allowField(TRUE)
                ->isUpdate(FALSE)
                ->save($c);
        }
    }

    /**
     * 分销模块开启下至少保证开启一个成为分销商机制
     * @param bool $open 手动注明模块是否开启
     */
    public function createFirstBecome($open = FALSE)
    {
        $set = Env::get();
        if ($set['DISTRIBUTION_STATUS'] || $open) {
            // 检测是否成为分销商机制全部未开启
            if (($set['DISTRIBUTION_MANUAL'] + $set['DISTRIBUTION_REGISTER'] + $set['DISTRIBUTION_BUY'] + $set['DISTRIBUTION_ACCUMULATIVE']) == 0) {
                // 开启默认手动申请成为分销商
                ini_file(NULL, 'distribution_manual', "1", $this->filename);
                (new DistributionRule())->checkForm();
            }
        }
    }

}