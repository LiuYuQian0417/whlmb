<?php

namespace app\master\controller;

use app\common\service\Lock;
use think\Controller;
use think\facade\Env;
use think\facade\Request;

class DistributionRule extends Controller
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

    public function index()
    {
        return $this->fetch('', [
            'single_store' => config('user.one_more'),
        ]);
    }

    /**
     * 保存分销系统设置
     * @param Request $request
     * @return array
     */
    public function editVal(Request $request)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $lock = new Lock();
                $lr = $lock->lock(['dist_set'], 10000);
                if ($lr === false) {
                    return ['code' => -1, 'message' => '检测正在有其他人员修改,请重试'];
                }
                $par = explode('|', $param['parameter']);

                if (count($par) == 2) {
                    foreach ($par as $_par) {
                        ini_file(null, $_par, $param['data'], $this->filename);
                    }
                } else {
                    if ($param['parameter'] == 'distribution_manual' && $param['data'] == 1) {
                        self::checkForm();
                        ini_file(null, 'distribution_register', 0, $this->filename);
                        ini_file(null, 'distribution_buy', 0, $this->filename);
                        ini_file(null, 'distribution_accumulative', 0, $this->filename);
                    } else if ($param['parameter'] == 'distribution_register' && $param['data'] == 1) {
                        ini_file(null, 'distribution_manual', 0, $this->filename);
                        ini_file(null, 'distribution_buy', 0, $this->filename);
                        ini_file(null, 'distribution_accumulative', 0, $this->filename);
                    } else if ($param['parameter'] == 'distribution_buy' && $param['data'] == 1) {
                        ini_file(null, 'distribution_manual', 0, $this->filename);
                        ini_file(null, 'distribution_register', 0, $this->filename);
                        ini_file(null, 'distribution_accumulative', 0, $this->filename);
                    } else if ($param['parameter'] == 'distribution_accumulative' && $param['data'] == 1) {
                        ini_file(null, 'distribution_manual', 0, $this->filename);
                        ini_file(null, 'distribution_register', 0, $this->filename);
                        ini_file(null, 'distribution_buy', 0, $this->filename);
                    }
                    $arr = ['distribution_register', 'distribution_manual', 'distribution_buy', 'distribution_accumulative'];
                    if ($param['parameter'] != 'distribution_commission' && !$param['data'] && in_array($param['parameter'], $arr)) {
                        $lock->unlock($lr);
                        return ['code' => 0, 'message' => '分销规则不可全部关闭'];
                    }
                    ini_file(null, $param['parameter'], $param['data'], $this->filename);
                }
                $lock->unlock($lr);
                return ['code' => 0];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 开启表单注册成为分销商
     * 检测表单项
     */
    public function checkForm()
    {
        $set = Env::get();
        // 表单项
        $form = [
            'distribution_name_show', 'distribution_name_required',
            'distribution_number_show', 'distribution_number_required',
            'distribution_wechat_show', 'distribution_wechat_required',
            'distribution_phone_show', 'distribution_phone_required',
            'distribution_sex_show', 'distribution_sex_required',
            'distribution_address_show', 'distribution_address_required',
        ];
        $showNum = $requiredNum = $si = 0;
        $show = $required = [];
        foreach ($form as $key => $_form) {
            if ($set[strtoupper($_form)]) {
                if ($key % 2 === 0) {
                    $showNum++;
                    array_push($show, $_form);
                } else {
                    $requiredNum++;
                    array_push($required, $_form);
                }
            }
        }
        if ($showNum === 0 || $requiredNum === 0) {
            // 默认开启第一项表单项
            ini_file(null, 'distribution_phone_show', 1, $this->filename);
            ini_file(null, 'distribution_phone_required', 1, $this->filename);
        }
    }

    /**
     * 默认分佣规则
     * @param Request $request
     * @return array
     */
    public function ratio(Request $request)
    {
        $ratio = Env::get('RATIO_SET');
        if ($request::isPost()) {
            try {

                $param = $request::post();

                switch ($param['distribution_hierarchy']) {
                    case 1:
                        if ($param['distribution_one'] == NULL) return ['code' => -1, 'message' => '请填写一级分销比例'];
                        break;
                    case 2:
                        if ($param['distribution_one'] == NULL) return ['code' => -1, 'message' => '请填写一级分销比例'];
                        if ($param['distribution_two'] == NULL) return ['code' => -1, 'message' => '请填写二级分销比例'];
                        break;
                    case 3:
                        if ($param['distribution_one'] == NULL) return ['code' => -1, 'message' => '请填写一级分销比例'];
                        if ($param['distribution_two'] == NULL) return ['code' => -1, 'message' => '请填写二级分销比例'];
                        if ($param['distribution_three'] == NULL) return ['code' => -1, 'message' => '请填写三级级分销比例'];
                        break;
                }

                // 总数
                $val = $param['distribution_one'] + $param['distribution_two'] + $param['distribution_three'];

                // 百分比不能大于设置值
                if ($val > $ratio) return ['code' => -1, 'message' => "您设置百分比的值不能大于等于$ratio%"];

                foreach ($param as $key => $item) {
                    if (is_numeric($item)) $item = abs($item);
                    if (!ini_file(null, $key, $item, $this->filename)) {
                        return ['code' => -5, 'message' => config('message.')[-5]];
                    }
                }

                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/distribution_rule/ratio'];

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        return $this->fetch('', [
            'ratio' => $ratio,
            'single_store' => config('user.one_more'),
        ]);
    }

    /**
     * 分销商申请设置
     * @return array
     */
    public function distributor()
    {
        return $this->fetch('');
    }

    /**
     * 分销说明设置
     * @param Request $request
     * @return array|mixed
     */
    public function explainSet(Request $request)
    {
        $set = Env::get();
        if ($request::isPost()) {
            try {
                $args = $request::post();
                foreach ($args as $key => $val) {
                    if ($val) {
                        $val = str_replace("\n", "<@>", $val);
                    }
                    ini_file(null, $key, $val, $this->filename);
                }
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        $data = [
            'income_explain' => $set['INCOME_EXPLAIN'] ? str_replace("<@>", "\n", $set['INCOME_EXPLAIN']) : '',
            'about_income' => $set['ABOUT_INCOME'] ? str_replace("<@>", "\n", $set['ABOUT_INCOME']) : '',
            'noun_explain' => $set['NOUN_EXPLAIN'] ? str_replace("<@>", "\n", $set['NOUN_EXPLAIN']) : '',
            'income_strategy' => $set['INCOME_STRATEGY'] ? str_replace("<@>", "\n", $set['INCOME_STRATEGY']) : '',
        ];
        return $this->fetch('distribution_rule/explain_set', [
            'data' => $data,
            'single_store' => config('user.one_more'),
        ]);
    }

}