<?php
declare(strict_types = 1);
namespace app\master\controller;

use app\common\model\SmsTemplate;
use think\Controller;
use think\Exception;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Env;
use think\facade\Request;

class Sms extends Controller
{
    /**
     * 短信配置文件路径
     * @var
     */
    private $filename;

    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.sms');
        $this->filename = Env::get('APP_PATH') . 'common/ini/.sms';
    }

    public function statistics()
    {
        // todo 短信统计
        return $this->fetch('');
    }
    /**
     * 短信设置
     * @param Request $request
     * @return mixed
     * @throws Exception
     */
    public function index(Request $request)
    {
        try {
            $config = Env::get();
            return $this->fetch('', [
                'config' => $config,
            ]);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 保存短信设置
     * @param Request $request
     * @return array
     */
    public function saveConfig(Request $request)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                foreach ($param as $key => $item) {
                    list($mode, $key) = explode('_', $key);
                    if (is_numeric($item)) $item = abs($item);
                    if (!ini_file($mode, $key, $item, $this->filename)) {
                        return ['code' => -5, 'message' => config('message.')[-5]];
                    };
                }
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/sms/index'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 腾讯云短信模板列表
     * @param Request $request
     * @param SmsTemplate $smsTemplate
     * @return mixed
     * @throws Exception
     */
    public function indexTX(Request $request, SmsTemplate $smsTemplate)
    {
        $param = $request::get();
        $condition[] = ['sms_id', '>', 0];
        if (isset($param['keyword']) && $param['keyword'])
            $condition[] = ['sms_id|temp_id|content', 'like', '%' . $param['keyword'] . '%'];
        if (isset($param['type']) && $param['type'])
            $condition[] = ['type', '=', $param['type']];
        if (isset($param['date']) && $param['date']) {
            list($begin, $end) = explode(' - ', $param['date']);
            $end = $end . ' 23:59:59';
            $condition[] = ['create_time', 'between', [$begin, $end]];
        }
        $where['type'] = 2;
        $data = $smsTemplate
            ->where($where)
            ->field('delete_time', true)
            ->paginate($smsTemplate->pageLimits, false, ['query' => $param]);
        //检测模板审核状态(审核中需再次向服务商查询状态)
        if ($data->count()) {
            $query = ['tpl_page' => ['max' => 10, 'offset' => 0]];
            foreach ($data as $value) {
                if ($value['status'] === 0 && $value['temp_id']) {
                    $query['tpl_id'][] = $value['temp_id'];
                }
            }
            if (array_key_exists('temp_id', $query) && $query['temp_id']) {
                $QCloud = app('app\common\sms\QCloud', ['param' => $query]);
                //进行查询
                $queryRes = $QCloud->queryTempState();
                if ($queryRes['result'] !== 0) $this->error($queryRes['errmsg']);
                $update = [];
                if (isset($queryRes['data']))
                    foreach ($queryRes['data'] as $key => $val) {
                        //0：已通过, 1：待审核, 2：已拒绝
                        if ($val['status'] !== 1) {
                            foreach ($data as $item) {
                                if ($item['temp_id'] == $val['id']) {
                                    $update[$key]['id'] = $item['sms_id'];
                                    $item['status'] = $update[$key]['status'] = ($val['status'] === 0) ? 1 : 2;
                                    $item['error'] = $update[$key]['error'] = $val['reply'];
                                    $item['content'] = $update[$key]['content'] = $val['text'];
                                }
                            }
                        }
                    }
                if ($update) $smsTemplate->allowField(true)->isUpdate(true)->saveAll($update);
            }
        }
        $keys = array_keys($smsTemplate->type);
        //检测该类型已有模板
        $has = $smsTemplate->checkHas(2);
        return $this->fetch('indexTX', [
            'data' => $data,
            'type' => $smsTemplate->type,
            'diff' => array_diff($keys, $has),
        ]);
    }

    /**
     * 创建腾讯云短信模板
     * @param Request $request
     * @param SmsTemplate $smsTemplate
     * @return array|mixed
     */
    public function createTX(Request $request, SmsTemplate $smsTemplate)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $check = $smsTemplate->valid($param, 'createTX');
                if ($check['code']) return $check;
//                $checkReg = self::checkReg(2, $param['content']);
//                if ($checkReg['code']) return $checkReg;
                $temp = [
                    'remark' => $smsTemplate->type[$param['scene']],
                    'text' => $param['content'],
                    'title' => $smsTemplate->type[$param['scene']],
                    'type' => 0,  //短信类型，Enum{0：普通短信, 1：营销短信}
                ];
                $QCloud = app('app\common\sms\QCloud', ['param' => $temp]);
                // 1为添加 0为修改
                $type = ($param['sms_id']) ? 1 : 0;
                $res = $QCloud->addTemp($type);
                if ($res['result'] !== 0) return ['code' => -1001, 'message' => $res['errmsg']];
                if ($res['data']['status'] == 2) return ['code' => -1001, 'message' => '腾讯云已拒绝添加短信'];
                $param['temp_id'] = $res['data']['id'];
                $smsTemplate->add($param);
                // 清除缓存
                Cache::rm('flatMaster_smsTemp_2');
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/sms/indexTX'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        $type = $smsTemplate->type;
        //检测该类型已有模板
        $has = $smsTemplate->checkHas(2);
        if ($has)
            foreach ($type as $k => $v) {
                if (in_array($k, $has)) unset($type[$k]);
            }
        return $this->fetch('createTX', [
            'type' => $type,
        ]);
    }

    /**
     * 编辑短信模板(腾讯云)
     * @param Request $request
     * @param SmsTemplate $smsTemplate
     * @return array|mixed
     */
    public function editTX(Request $request, SmsTemplate $smsTemplate)
    {
        $id = $request::get('id');
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $check = $smsTemplate->valid($param, 'editTX');
                if ($check['code']) return $check;
//                $checkReg = self::checkReg(2, $param['content']);
//                if ($checkReg['code']) return $checkReg;
                $temp = [
                    'remark' => $smsTemplate->type[$param['scene']],
                    'text' => $param['content'],
                    'title' => $smsTemplate->type[$param['scene']],
                    'type' => 0,  //短信类型，Enum{0：普通短信, 1：营销短信}
                ];
                $QCloud = app('app\common\sms\QCloud', ['param' => $temp]);
                // 1为添加 0为修改
                $type = ($param['sms_id']) ? 1 : 0;
                $res = $QCloud->addTemp($type);
                if ($res['result'] !== 0) return ['code' => -1001, 'message' => $res['errmsg']];
                if ($res['data']['status'] == 2) return ['code' => -1001, 'message' => '腾讯云已拒绝添加短信'];
                $smsTemplate->edit($param);
                // 清除缓存
                Cache::rm('flatMaster_smsTemp_2');
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/sms/indexTX'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        $data = $smsTemplate
            ->where(['sms_id' => $id])
            ->field('scene,content')
            ->find();
        $type = $smsTemplate->type;
        //检测该类型已有模板
        $has = $smsTemplate->checkHas(2);
        $has = array_diff($has, [$data['scene']]);
        if ($has)
            foreach ($type as $k => $v) {
                if (in_array($k, $has)) unset($type[$k]);
            }
        return $this->fetch('createTX', [
            'type' => $type,
            'data' => $data,
        ]);
    }

    /**
     * 删除短信模板（腾讯云）
     * @param Request $request
     * @param SmsTemplate $smsTemplate
     * @return array
     */
    public function destroyTX(Request $request, SmsTemplate $smsTemplate)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $tempIdArr = $smsTemplate
                    ->where(['sms_id' => ['in', $param['id']]])
                    ->column('temp_id');
                $QCloud = app('app\common\sms\QCloud', ['param' => $tempIdArr]);
                $delRes = $QCloud->destroyTemp();
                // 清除缓存
                Cache::rm('flatMaster_smsTemp_2');
                if ($delRes['result'] !== 0) return ['code' => -1001, 'message' => $delRes['errmsg']];
                $smsTemplate::destroy($param['id']);
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 阿里云短信模板列表
     * @param Request $request
     * @param SmsTemplate $smsTemplate
     * @return mixed
     */
    public function indexAL(Request $request, SmsTemplate $smsTemplate)
    {
        $param = $request::get();
        $condition[] = ['sms_id', '>', 0];
        if (isset($param['keyword']) && $param['keyword'])
            $condition[] = ['sms_id|temp_id|content', 'like', '%' . $param['keyword'] . '%'];
        if (isset($param['type']) && $param['type'] != -1)
            $condition[] = ['type', '=', $param['type']];
        if (isset($param['date']) && $param['date']) {
            list($begin, $end) = explode(' - ', $param['date']);
            $end = $end . ' 23:59:59';
            $condition[] = ['create_time', 'between', [$begin, $end]];
        }
        $where['type'] = 1;
        $data = $smsTemplate
            ->where($where)
            ->field('delete_time', true)
            ->paginate($smsTemplate->pageLimits, false, ['query' => $param]);
        $keys = array_keys($smsTemplate->type);
        //检测该类型已有模板
        $has = $smsTemplate->checkHas(1);
        return $this->fetch('indexAL', [
            'data' => $data,
            'type' => $smsTemplate->type,
            'diff' => array_diff($keys, $has),
        ]);
    }

    /**
     * 创建阿里云短信模板
     * @param Request $request
     * @param SmsTemplate $smsTemplate
     * @return array|mixed
     */
    public function createAL(Request $request, SmsTemplate $smsTemplate)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $check = $smsTemplate->valid($param, 'createAL');
                if ($check['code']) return $check;
                $checkReg = self::checkReg(1, $param['content']);
                if ($checkReg['code']) return $checkReg;
                // 阿里云短信是审核过后配置
                $param['type'] = 1;
                $smsTemplate->add($param);
                // 清除缓存
                Cache::rm('flatMaster_smsTemp_1');
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/sms/indexAL'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        $type = $smsTemplate->type;
        //检测该类型已有模板
        $has = $smsTemplate->checkHas(1);
        if ($has)
            foreach ($type as $k => $v) {
                if (in_array($k, $has)) unset($type[$k]);
            }
        return $this->fetch('createAL', [
            'type' => $type,
        ]);
    }

    /**
     * 编辑短信模板(阿里云)
     * @param Request $request
     * @param SmsTemplate $smsTemplate
     * @return array|mixed
     */
    public function editAL(Request $request, SmsTemplate $smsTemplate)
    {
        $id = $request::get('id');
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $check = $smsTemplate->valid($param, 'editAL');
                if ($check['code']) return $check;
                $checkReg = self::checkReg(1, $param['content']);
                if ($checkReg['code']) return $checkReg;
                $smsTemplate->edit($param);
                // 清除缓存
                Cache::rm('flatMaster_smsTemp_1');
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/sms/indexAL'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        $data = $smsTemplate
            ->where(['sms_id' => $id])
            ->field('scene,content,temp_id')
            ->find();
        $type = $smsTemplate->type;
        //检测该类型已有模板
        $has = $smsTemplate->checkHas(1);
        $has = array_diff($has, [$data['scene']]);
        if ($has)
            foreach ($type as $k => $v) {
                if (in_array($k, $has)) unset($type[$k]);
            }
        return $this->fetch('createAL', [
            'type' => $type,
            'data' => $data,
        ]);
    }

    /**
     * 删除短信模板（阿里云）
     * @param Request $request
     * @param SmsTemplate $smsTemplate
     * @return array
     */
    public function destroyAL(Request $request, SmsTemplate $smsTemplate)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $smsTemplate::destroy($param['id']);
                // 清除缓存
                Cache::rm('flatMaster_smsTemp_1');
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 各类短信统计
     * @param Request $request
     * @param SmsTemplate $smsTemplate
     * @return array|mixed
     */
    public function countTX(Request $request, SmsTemplate $smsTemplate)
    {
        if ($request::isPost()) {
            try {
                $args = $request::post();
                $where['type'] = 0;
                if (array_key_exists('type', $args) && $args['type']) {
                    $where['type'] = $args['type'];
                }
                // 查询缓存
                $count = Cache::store('default')->handler();
                $count->select(2);
                $data = [];
                for ($i = 0; $i < 7; $i++) {
                    $date = date('Y-m-d', strtotime('-' . $i . ' days'));
                    $data[$date] = ($val = $count->zScore('sms_count_2' . '_' . $where['type'], $date)) ? $val : 0;
                }
                $data = array_reverse($data);
                return ['code' => 0, 'message' => '查询成功', 'data' => ['keys' => array_keys($data), 'values' => array_values($data)]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        $type = $smsTemplate->type;
        unset($type[0]);
        return $this->fetch('countTX', [
            'type' => $type,
        ]);
    }


    /**
     * 阿里各类短信统计
     * @param Request $request
     * @param SmsTemplate $smsTemplate
     * @return array|mixed
     */
    public function countAL(Request $request, SmsTemplate $smsTemplate)
    {
        if ($request::isPost()) {
            try {
                $args = $request::post();
                $where['type'] = 0;
                if (array_key_exists('type', $args) && $args['type']) {
                    $where['type'] = $args['type'];
                }
                // 查询缓存
                $count = Cache::store('default')->handler();
                $count->select(2);
                $data = [];
                for ($i = 0; $i < 7; $i++) {
                    $date = date('Y-m-d', strtotime('-' . $i . ' days'));
                    $data[$date] = ($val = $count->zScore('sms_count_1' . '_' . $where['type'], $date)) ? $val : 0;
                }
                $data = array_reverse($data);
                return ['code' => 0, 'message' => '查询成功', 'data' => ['keys' => array_keys($data), 'values' => array_values($data)]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        $type = $smsTemplate->type;
        unset($type[0]);
        return $this->fetch('countAL', [
            'type' => $type,
        ]);
    }

    /**
     * 检测模板内容是否符合规格
     * @param $type int 短信类型
     * @param $content  string 模板内容
     * @return array
     */
    protected function checkReg($type, $content)
    {
        $reg = ($type == 1) ? '/\$\{code\}/is' : '/\{1\}/is';
        preg_match_all($reg, $content, $match);
        $msg = ($type == 1) ? '${code}' : '{1}';
        return (count($match[0]) !== 1) ? ['code' => -1, 'message' => '模板内容应含有且仅有一个变量' . $msg] : ['code' => 0];
    }
}