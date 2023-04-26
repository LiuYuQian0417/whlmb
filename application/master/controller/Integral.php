<?php
// 积分商城
declare(strict_types = 1);

namespace app\master\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\Integral as IntegralModel;
use app\common\model\IntegralClassify as IntegralClassifyModel;

class Integral extends Controller
{
    public function settings()
    {
        // todo 积分设置
        return $this->fetch('');
    }

    // 积分商品列表
    public function index(Request $request,IntegralModel $integral,IntegralClassifyModel $integralClassify)
    {
         try{
             // 获取参数
             $param = $request::get();

             // 条件定义
             $condition[] = ['a.integral_id','neq',0];
             if (isset($param['keyword']) && !empty($param['keyword'])) $condition[] = ['a.integral_name', 'like', '%' . $param['keyword'] . '%'];
             if(isset($param['classify_id']) && $param['classify_id']!=-1) $condition[] = ['a.integral_classify_id','=',$param['classify_id']];
             if(isset($param['type']) && $param['type'] != -1) $condition[] = ['a.type','=',$param['type']];
             $flag = '';
             if(isset($param['flag']) && $param['flag'] == 0) $flag = 'and a.integral_number <= integral.warn_number';

             $data = $integral
                 ->alias('a')
                 ->join('integral integral','a.integral_id = integral.integral_id  '. $flag .'')
                 ->where($condition)
                 ->order('a.sort','asc')
                 ->paginate(10,false,['query'=>$param]);

             $count = $integral
                 ->alias('a')
                 ->join('integral integral','a.integral_id = integral.integral_id and a.integral_number <= integral.warn_number')
                 ->count();

         }catch (\Exception $e){
             return ['code' => -100, 'message' => $e->getMessage()];
         }


        return $this->fetch('',[
            'data'          => $data,
            'classify_list' => $integralClassify->where('status',1)->field('integral_classify_id,parent_id,title,sort')->select(),
            'warn_count'    => $count,
        ]);
    }


    // 创建积分商品
    public function create(Request $request,IntegralModel $integral,IntegralClassifyModel $integralClassify)
    {
        if ($request::isPost()){

            try {
                // 获取参数
                $param = $request::post();
                //处理多图
                if (array_key_exists('picArr', $param) && $param['picArr'])
                    $param['multiple_file'] = implode(',', $param['picArr']);
                // 验证
                $check = $integral->valid($param,'create');
                if ($check['code']) return $check;

                // 写入
                $operation = $integral->allowField(true)->save($param);

                if ($operation) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/integral/index'];

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }

        }

        return $this->fetch('',[
            'classify_list' => $integralClassify->where('status',1)->field('integral_classify_id,parent_id,title,sort')->select(),
            'pc_config'         => config('user.pc.is_include'),
        ]);
    }


    // 编辑积分商品
    public function edit(Request $request,IntegralModel $integral,IntegralClassifyModel $integralClassify)
    {
        if ($request::isPost()){

            try {
                // 获取参数
                $param = $request::post();
                //处理多图
                if (array_key_exists('picArr', $param) && $param['picArr'])
                    $param['multiple_file'] = implode(',', $param['picArr']);
                // 验证
                $check = $integral->valid($param,'edit');
                if ($check['code']) return $check;

                // 写入
                $operation = $integral->allowField(true)->isUpdate(true)->save($param);

                if ($operation) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/integral/index'];

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        $data = $integral->where('integral_id', request::get('integral_id'))->find();
        $data['file_data'] = $data->getData('file');
        if (!empty($data['multiple_file'])) {
            $data['multiple_file_extra_data'] = $data->getData('multiple_file');
            $data['multiple_file_data'] = join(',', $data['multiple_file']);
        }

        return $this->fetch('create',[
            'item'          => $data,
            'classify_list' => $integralClassify->where('status',1)->field('integral_classify_id,parent_id,title,sort')->select(),
            'pc_config'         => config('user.pc.is_include'),
        ]);
    }


    // 删除积分商品
    public function destroy(Request $request,IntegralModel $integral)
    {
        if ($request::post()){
            try{

                $integral::destroy($request::post('id'));

                return ['code' => 0, 'message' => config('message.')[0]];

            }catch (\Exception $e){

                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }


    // 富文本编辑器展示页
    public function uEditor()
    {
        return $this->fetch('integral/uEditor');
    }
}