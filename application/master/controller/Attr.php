<?php
declare(strict_types = 1);
namespace app\master\controller;
use app\common\model\Products;
use app\common\model\Store;
use think\Controller;
use think\Db;
use think\Exception;
use think\facade\Cache;
use think\facade\Request;
use app\common\model\Goods;
use app\common\model\AttrType as AttrTypeModel;
use app\common\model\Attr as AttrModel;
use think\Queue;

/**
 * 商品属性
 * Class Attr
 * @package app\master\controller
 */
class Attr extends Controller
{
    /**
     * 自营/店铺商品属性列表
     * @param Request $request
     * @param AttrType $attrtype
     * @return mixed
     * @throws Exception
     */
    public function index(Request $request,AttrModel $attr)
    {
//        try {
            $param = $request::get();
            //初始查询自营的商品属性列表
            $pageLimits = $attr->pageLimits;
            $where[0] = ['a.attr_type_id', '=', $param['attr_type_id']];
            if (array_key_exists('keyword', $param) && $param['keyword'])
                $where[] = ['attr_name', 'like', '%' . $param['keyword'] . '%'];
            $data = $attr
                ->alias('a')
                ->join('attr_type at','at.attr_type_id = a.attr_type_id')
                ->field('a.attr_id,at.type_name,at.attr_type_id,a.sort,a.attr_value,a.attr_name,a.attr_input_type')
                ->order(['a.sort' => 'desc'])
                ->where($where)
                ->paginate($pageLimits, false, ['query' => $param]);

            return $this->fetch('', [
                'data' => $data,
                'attr_type_id'=>$param['attr_type_id']
            ]);
//        } catch (\Exception $e) {
//            throw new Exception($e->getMessage());
//        }
    }



    /**
     * 商品类型显示状态更新
     * @param Request $request
     * @param AttrTypeModel $attrType
     * @return array
     */
    public function auditing(Request $request, AttrTypeModel $attrType)
    {

        if ($request::isPost()) {
            try {
                $attrType->changeStatus($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 商品分类新增
     * @param Request $request
     * @param GoodsClassifyModel $GoodsClassifyModel
     * @param Adv $adv
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(Request $request, AttrTypeModel $attrType,AttrModel $attr)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $request::post();

                // 验证
                $check = $attr->valid($param, 'create');
                if ($check['code']) return $check;
                if(isset($param['attr_value'])){
                    $attr_value= explode("\n",$param['attr_value']);
                    foreach ($attr_value as $k=>$v) {
                        if ($v=="") {
                            unset($attr_value[$k]);
                        }
                    }
                    // 去除重复的值
                    $attr_value = array_unique($attr_value);

                    $param['attr_value']=implode("\r\n",$attr_value);
                }

                $state = $attr->allowField(true)->save($param);
                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/attr/index?attr_type_id='.$param['attr_type_id']];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        return $this->fetch('', [
              'attrTypeData'=>$attrType ->alias('a')
                  ->where(['attr_type_id'=>$request::get('attr_type_id')])->field('attr_type_id,type_name')->find()
        ]);
    }

    /**
     * 商品分类编辑
     * @param Request $request
     * @param GoodsClassifyModel $GoodsClassifyModel
     * @param Adv $adv
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, AttrTypeModel $attrType,AttrModel $attr)
    {

        if ($request::isPost()) {

            try {

                // 获取参数
                $param = $request::post();

                // 验证
                $check = $attr->valid($param, 'edit');
                if ($check['code']) return $check;

                // 如果
                if ($param['attr_input_type'] == 1 && empty($param['attr_value'])){
                    return ['code'=>-100,'message'=>'可选值列表不可为空'];
                }

                if(isset($param['attr_value'])){
                        $attr_value= explode("\n",$param['attr_value']);
                        foreach ($attr_value as $k=>$v) {
                            if ($v=="") {
                                unset($attr_value[$k]);
                            }
                        }
                        // 去除重复的值
                        $attr_value = array_unique($attr_value);
                        $param['attr_value']=implode("\r\n",$attr_value);
                    }
                $state = $attr->allowField(true)->isUpdate(true)->save($param);

                if ($state) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/attr/index?attr_type_id='.$param['attr_type_id']];

            } catch (\Exception $e) {

                return ['code' => -100, 'message' => $e->getMessage()];

            }
        }
         $data = $attr->get($request::get('id'));
        return $this->fetch('create', [
            'item'          =>$data ,
            'attrTypeData'=>$attrType ->alias('a')
                ->where(['attr_type_id'=>$data['attr_type_id']])->field('attr_type_id,type_name')->find()
        ]);
    }
    /**
     * 删除商品类型
     * @param Request $request
     * @param brandModel $brand
     * @return array
     */
    public function destroy(Request $request, AttrModel $attr)
    {
        if ($request::isPost()){
            try{
                // 删除
                $attr::destroy($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];

            }catch (\Exception $e){

                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }


    /**
     * 属性排序
     * @param Request $request
     * @param AttrModel $attr
     * @return array
     */
    public function text_update(Request $request, AttrModel $attr)
    {

        if ($request::isPost()) {
            try {
                $attr->clickEdit($request::post());
                return ['code' => 0, 'message' => ''];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

}