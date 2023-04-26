<?php

namespace app\client\controller;

use app\common\model\Brand as brandModel;
use app\common\model\BrandClassify as BrandClassifyModel;
use app\common\model\StoreBrand as StoreBrandModel;
use think\Controller;
use think\facade\Request;
use think\facade\Session;

class Brand extends Controller
{
    /**
     * @param Request            $request
     * @param StoreBrandModel    $storeBrand
     * @param BrandClassifyModel $brandClassify
     *
     * @optimization Malson 2019-03-13 16:32
     *
     * @return array|mixed
     */
    public function index(Request $request, StoreBrandModel $storeBrand, BrandClassifyModel $brandClassify)
    {
        try
        {
            // 获取数据
            $param = $request::param();

            // 配置 默认的 查询条件
            $condition[0] = ['s.store_id', '=', Session::get('client_store_id')];

            if (array_key_exists('brand_classify_id', $param) && $param['brand_classify_id'])
            {
                $condition[] = ['s.brand_classify_id', '=', $param['brand_classify_id']];
            }

            // 品牌名称
            if (array_key_exists('keyword', $param) && $param['keyword'])
            {
                $condition[] = ['store_brand_name', 'like', '%' . $param['keyword'] . '%'];
            }

            // 搜索店铺品牌
            $data = $storeBrand->where($condition)->alias('s')
                ->join('brand b', 'b.brand_id=s.brand_id')
                ->join('brand_classify bc', 'bc.brand_classify_id=b.brand_classify_id')
                ->field(
                    'b.brand_name,bc.brand_classify_name,s.store_brand_id,s.store_brand_name,s.brand_letter,s.brand_logo,s.status'
                )
                ->paginate(15, FALSE, ['query' => $param]);
        } catch (\Exception $e)
        {
            return ['code' => -100, 'message' => config('message.')['-1']];
        }
        return $this->fetch(
            '', [
                  'data' => $data,
              ]
        );
    }


    /**
     * 创建品牌
     *
     * @param Request         $request
     * @param StoreBrandModel $storeBrand
     * @param brandModel      $brand
     *
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @optimization Malson 2019-03-13 16:32
     *
     */
    public function create(Request $request, StoreBrandModel $storeBrand, brandModel $brand)
    {
        if ($request::isPost())
        {
            try
            {
                // 获取数据
                $param = $request::post();

                $param['store_id'] = Session::get('client_store_id');

                // 验证
                $check = $storeBrand->valid($param, 'create');
                if ($check['code'])
                {
                    return $check;
                }

                // 判断是否已存在
                if ($storeBrand->field(['store_brand_id'])->where([
                    ['store_brand_name','=',$param['store_brand_name']]
                ])->find() !== null){
                    return ['code' => -100, 'message' => '已存在的品牌名称,请检查'];
                }

                // 写入
                $operation = $storeBrand->allowField(TRUE)->save($param);
                if ($operation)
                {
                    return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/brand/index'];
                }
            } catch (\Exception $e)
            {
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }


        return $this->fetch(
            'create', [
                        //查询全部品牌
                        'brand'         => $brand
                            ->where([['brand_id', '>', 0]])
                            ->field('brand_id,brand_name,brand_first_char')
                            ->order(['sort' => 'asc', 'update_time' => 'desc'])
                            ->select(),
                        //查询全部品牌首字母
                        'brandFirstChr' => $brand
                            ->where([['brand_id', '>', 0]])
                            ->distinct(TRUE)
                            ->order(['brand_first_char' => 'asc'])
                            ->column('brand_first_char'),
                    ]
        );
    }


    /**
     * 编辑品牌
     *
     * @param Request         $request
     * @param brandModel      $brand
     *
     * @param StoreBrandModel $storeBrand
     *
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, brandModel $brand, StoreBrandModel $storeBrand)
    {
        if ($request::isPost())
        {
            try
            {
                $param = $request::post();

                $param['store_id'] = Session::get('client_store_id');

                // 验证
                $check = $storeBrand->valid($param, 'edit');
                if ($check['code'])
                {
                    return $check;
                }

                // 更新
                $operation = $storeBrand->isUpdate(TRUE)->allowField(TRUE)->save($param);
                if ($operation)
                {
                    return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/brand/index'];
                }

            } catch (\Exception $e)
            {
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }

        $data = $storeBrand->alias('s')
            ->join('brand', 'brand.brand_id=s.brand_id')
            ->where('store_brand_id', $request::get('id'))
            ->field(
                's.store_brand_id,brand.brand_name,s.store_brand_name,s.brand_id,s.brand_letter,s.brand_logo,s.brand_describe,s.status'
            )
            ->find();
        $data['brand_logo_data'] = $data->getData('brand_logo');
        return $this->fetch(
            'create', [
                        'item'          => $data,
                        //查询全部品牌
                        'brand'         => $brand
                            ->where([['brand_id', '>', 0]])
                            ->field('brand_id,brand_name,brand_first_char')
                            ->order(['sort' => 'asc', 'update_time' => 'desc'])
                            ->select(),
                        //查询全部品牌首字母
                        'brandFirstChr' => $brand
                            ->where([['brand_id', '>', 0]])
                            ->distinct(TRUE)
                            ->order(['brand_first_char' => 'asc'])
                            ->column('brand_first_char'),
                    ]
        );
    }


    /**
     * 删除品牌
     *
     * @param Request         $request
     * @param StoreBrandModel $storeBrand
     *
     * @return array
     */
    public function destroy(Request $request, StoreBrandModel $storeBrand)
    {
        if ($request::isPost())
        {
            try
            {
                // 删除
                $storeBrand::destroy($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e)
            {
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }
    }

    /**
     * 获取品牌list
     *
     * @param Request    $request
     * @param brandModel $brand
     *
     * @return array
     */
    public function getBrand(Request $request, brandModel $brand)
    {
        if ($request::isPost())
        {
            try
            {
                $param = $request::post();
                $where[] = ['brand_id', '>', 0];
                if (array_key_exists('letter', $param) && $param['letter'])
                {
                    $where[] = ['brand_first_char', '=', $param['letter']];
                }
                if (array_key_exists('keyword', $param) && $param['keyword'])
                {
                    $where[] = ['brand_name|brand_letter|brand_describe', 'like', '%' . $param['keyword'] . '%'];
                }

                $data = $brand
                    ->where($where)
                    ->field('brand_id,brand_name,brand_first_char')
                    ->order(['sort' => 'asc', 'update_time' => 'desc'])
                    ->select();
                $html = '';
                if ($data->count())
                {
                    foreach ($data as $key => $item)
                    {
                        $html .= "<li title=\"{$item['brand_name']}\" value=\"{$item['brand_id']}\" ><em>{$item['brand_first_char']}</em>{$item['brand_name']}</li>";
                    }
                } else
                {
                    $html .= '<li class="empty">暂无品牌数据</li>';
                }
                return ['code' => 0, 'message' => config('message.')[0], 'data' => $html];
            } catch (\Exception $e)
            {
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }
    }

}