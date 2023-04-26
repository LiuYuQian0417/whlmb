<?php
namespace app\master\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\Brand as brandModel;
use app\common\model\StoreBrand as StoreBrandModel;
use app\common\model\BrandClassify as BrandClassifyModel;
use app\common\model\GoodsClassify as GoodsClassifyModel;


class Brand extends Controller
{
    /**
     * 品牌列表
     * @param Request $request
     * @param brandModel $brand
     * @param StoreBrandModel $storeBrand
     * @param BrandClassifyModel $brandClassify
     * @return array|mixed
     */
    public function index(Request $request, brandModel $brand,StoreBrandModel $storeBrand,BrandClassifyModel $brandClassify)
    {
        try {
            // 获取数据
            $param = $request::param();
            $data = '';
            switch (array_key_exists('type',$param) && $param['type']){
                case 0:
                    // 筛选条件
                    $condition[] = ['brand_id', '>', 0];
                    if (array_key_exists('keyword', $param) && $param['keyword'])
                        $condition[] = ['brand_name', 'like', '%' . $param['keyword'] . '%'];
                    if (array_key_exists('goods_classify_id',$param)&&$param['goods_classify_id'])
                        $condition[] = ['goods_classify_id','=',$param['goods_classify_id']];

                    // 获取数据
                    $data = $brand->where($condition)
                        ->field('create_time,update_time,delete_time',true)
                        ->order(['sort' => 'asc', 'update_time' => 'desc'])
                        ->paginate(10, false, ['query' => $param]);
                 break;
                case 1:
                    // 筛选条件
                    $condition[] = ['store_brand_id', '>', 0];
                    if (array_key_exists('keyword', $param) && $param['keyword'])
                        $condition[] = ['store_brand_name', 'like', '%' . $param['keyword'] . '%'];

                    // 获取数据
                    $data = $storeBrand->where($condition)
                        ->field('store_brand_id as brand_id,store_brand_name,brand_letter,brand_logo')
                        ->order(['update_time' => 'desc'])
                        ->paginate(10, false, ['query' => $param]);

            }

//halt($data);
        } catch (\Exception $e) {
            return $e->getMessage();
//            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('', [
            'data'       => $data,
            'count'      => $brand->CountBrand(),
            'classifies' => $brandClassify->where('status', 1)
                ->field('brand_classify_name,brand_classify_id')->select(),
        ]);
    }


    /**
     * 创建品牌
     * @param Request $request
     * @param brandModel $brand
     * @param BrandClassifyModel $brandClassify
     * @return array|mixed
     */
    public function create(Request $request, brandModel $brand,BrandClassifyModel $brandClassify,GoodsClassifyModel $classify)
    {
        if ($request::isPost()){
            try {
                // 获取数据
                $param = $request::post();
                // 验证
                $check = $brand->valid($param,'create');
                if ($check['code']) return $check;
                // 写入
                $operation = $brand->allowField(true)->save($param);
                if ($operation) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/brand/index'];

            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        return $this->fetch('',[
            'classifies'=>$brandClassify->where('status',1)
                ->field('brand_classify_name,brand_classify_id')->select(),
            'goods_classfly'=>$classify->where(['parent_id'=>0,'status'=>1])->field('goods_classify_id,title')->select()
        ]);
    }


    /**
     * 编辑品牌
     * @param Request $request
     * @param brandModel $brand
     * @return array|mixed
     */
    public function edit(Request $request, brandModel $brand, BrandClassifyModel $brandClassify,GoodsClassifyModel $classify)
    {
       if ($request::isPost()){
           try {
               // 获取数据
               $param = $request::post();

               // 验证
               $check  = $brand->valid($param,'edit');
               if ($check['code']) return $check;

               // 更新
               $operation = $brand->allowField(true)->isUpdate(true)->save($param);
               if ($operation) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/brand/index'];

           } catch (\Exception $e) {
               return ['code' => -100, 'message' => $e->getMessage()];
           }
       }


       $_brand = $brand::get($request::get('id'));

       $_brand['brand_logo_data'] = $_brand->getData('brand_logo');

       return $this->fetch('create',[
            'item'=>$_brand,
            'classifies'=>$brandClassify->where('status',1)
               ->field('brand_classify_name,brand_classify_id')->select(),
           'goods_classfly'=>$classify->where(['parent_id'=>0,'status'=>1])->field('goods_classify_id,title')->select()

       ]);
    }


    /**
     * 删除品牌
     * @param Request $request
     * @param brandModel $brand
     * @return array
     */
    public function destroy(Request $request, brandModel $brand)
    {
        if ($request::isPost()){
            try{
                // 删除
                $brand::destroy($request::post('id'));

                return ['code' => 0, 'message' => config('message.')[0]];

            }catch (\Exception $e){

                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }


    /**
     * 品牌排序
     * @param Request $request
     * @param brandModel $brand
     * @return array
     */
    public function text_update(Request $request, brandModel $brand)
    {

        if ($request::isPost()) {
            try {
                $brand->clickEdit($request::post());
                return ['code' => 0, 'message' => ''];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }


    /**
     * 获取品牌list
     * @param Request $request
     * @param brandModel $brand
     * @return array
     */
    public function getBrand(Request $request, brandModel $brand)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $where[] = ['brand_id', '>', 0];
                if (array_key_exists('letter', $param) && $param['letter'])
                    $where[] = ['brand_first_char', '=', $param['letter']];
                if (array_key_exists('keyword', $param) && $param['keyword'])
                    $where[] = ['brand_name|brand_letter|brand_describe', 'like', '%' . $param['keyword'] . '%'];
                $data = $brand
                    ->where($where)
                    ->field('brand_id,brand_name,brand_first_char')
                    ->order(['sort' => 'asc', 'update_time' => 'desc'])
                    ->select();
                $html = '';
                if ($data->count()) {
                    foreach ($data as $key => $item) {
                        $html .= '<li title="' . $item['brand_name'] . '" value="' . $item['brand_id'] . '" ><em>' . $item['brand_first_char'] . '</em>' . $item['brand_name'] . '</li>';
                    }
                } else {
                    $html .= '<li class="empty">暂无品牌数据</li>';
                }
                return ['code' => 0, 'message' => config('message.')[0], 'data' => $html];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    public function auditing(Request $request,brandModel $brand)
    {
        if ($request::isPost()) {
            try {
                $brand->changeIsRecommend($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
}