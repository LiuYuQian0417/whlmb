<?php
declare(strict_types=1);

namespace app\client\controller;

use app\common\model\AttrType;
use app\common\model\Brand;
use app\common\model\GoodsClassify;
use app\common\model\GoodsParameter;
use app\common\model\Products;
use think\Controller;
use think\Db;
use think\Exception;
use think\facade\Request;
use app\common\model\Goods as GoodsModel;
use think\facade\Session;

/**
 * 商品批量操作 编辑商品
 * Class GoodsBatchOperation
 * @package app\master\controller
 */
class GoodsImport extends Controller
{
    /**
     * 自营商品批量操作 批量导入列表
     * @param Request $request
     * @param GoodsModel $goods
     * @return mixed
     * @throws Exception
     */

    public function index(Request $request)
    {
        try {
            $param = $request::param();
            return $this->fetch('', [
            ]);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }


    /**
     * 导入的提交
     * @param AttrType $attrType
     * @param Products $products
     * @param GoodsModel $goods
     * @param Brand $brand
     * @param GoodsClassify $classify
     * @param GoodsParameter $goodsParameter
     * @return mixed
     * @throws \Exception
     */
    public function create(AttrType $attrType, Products $products, Brand $brand, GoodsClassify $classify, GoodsParameter $goodsParameter)
    {
        try {
            Db::startTrans();
            $filename = $_FILES['file']['tmp_name'];
            if (strpos($_FILES['file']['name'], '.csv') === FALSE) return ['code' => -100, 'message' => '请上传.csv文件'];
            $handle = fopen($filename, 'r');
            $result = $this->input_csv($handle);
            $len_result = count($result);
            if ($len_result == 0) {
                return ['code' => -100, 'message' => '数据为空'];
            }
            $store_id = Session::get('client_store_id');
            for ($i = 1; $i < $len_result; $i++) { //循环获取各字段值
//                $r = ['goods_classify_id' => [1, '分类名称(最后一级)'], 'attr_type_id' => [2, '商品属性类型'], 'brand_id' => [3, '商品品牌']];
                $r = ['goods_classify_id' => [1, '分类名称(最后一级)'], 'brand_id' => [3, '商品品牌']];
                $goods_classify_id = $classify->where('title', trim(iconv('gb2312', 'utf-8', $result[$i][1])))->value('goods_classify_id');
                $attr_type_id = $attrType->where('type_name', trim(iconv('gb2312', 'utf-8', $result[$i][2])))->where('store_id', $store_id)->value('attr_type_id','');
                if(empty(trim(iconv('gb2312', 'utf-8', $result[$i][2])))){
//                    unset($r['attr_type_id']);
                }
                $brand_id = $brand->where('brand_name', trim(iconv('gb2312', 'utf-8', $result[$i][3])))->value('brand_id');
                foreach ($r as $k => $v) {
                    if (empty($$k)) {
                        return ['code' => 401, 'message' => $i + 1 . '行------' . $v[1] . '下的' . iconv('gb2312', 'utf-8', $result[$i][$v[0]]) . '不存在'];
                    }
                }
                $where = [
                    'store_id' => $store_id,
                    'goods_name' => iconv('gb2312', 'utf-8', $result[$i][0]),
                    'goods_classify_id' => $goods_classify_id,
                    'attr_type_id' => $attr_type_id,
                    'brand_id' => $brand_id,
                    'shop_price' => iconv('gb2312', 'utf-8', $result[$i][4]),
                    'market_price' => iconv('gb2312', 'utf-8', $result[$i][5]),
                    'cost_price' => iconv('gb2312', 'utf-8', $result[$i][6]),
                    'goods_number' => iconv('gb2312', 'utf-8', $result[$i][7]),
                    'warn_number' => iconv('gb2312', 'utf-8', $result[$i][8]),
                    'describe' => str_replace('^',PHP_EOL,iconv('gb2312', 'utf-8', $result[$i][9])),//将^替换回车换行符
                    'spell_keyword' => iconv('gb2312', 'utf-8', $result[$i][10]),
                    'is_vip' => iconv('gb2312', 'utf-8', $result[$i][11]),
                    'store_particularly_recommend' => iconv('gb2312', 'utf-8', $result[$i][12]),
                    'store_recommend' => iconv('gb2312', 'utf-8', $result[$i][13]),
                    'store_poster' => iconv('gb2312', 'utf-8', $result[$i][14]),
                    'is_best' => iconv('gb2312', 'utf-8', $result[$i][15]),
                    'is_hot' => iconv('gb2312', 'utf-8', $result[$i][16]),
                    'is_popularity' => iconv('gb2312', 'utf-8', $result[$i][17]),
                    'is_preference' => iconv('gb2312', 'utf-8', $result[$i][18]),
                    'goods_sn' => '00',
                    'spell_goods_name' => str_replace(' ', '', iconv('gb2312', 'utf-8', $result[$i][0])),
                ];
                $goods=GoodsModel::create($where,true);//->isUpdate(false)->allowField(true)->save($where);
                $goods_id = $goods->goods_id;
                //设置商品货号
                $goods_sn = $goods->setGoodsSn($goods_id);
                //商品参数
                $parameter_str = iconv('gb2312', 'utf-8', $result[$i][19]);
                if(empty($parameter_str)){
                    $parameter_array=[];
                }else{
                    $parameter_array = explode('|', $parameter_str);
                }
                $parameter_where=$product_where=[];
                for ($m = 0; $m < count($parameter_array); $m++) {
                    $parameter = explode('*', $parameter_array[$m]);
                    $parameter_where[] = [
                        'goods_id' => $goods_id,
                        'parameter_name' => $parameter[0],
                        'attr_shop_price' => $parameter[1],
                    ];
                }
                $goodsParameter->saveAll($parameter_where);
                //处理商品属性
                $product_str = iconv('gb2312', 'utf-8', $result[$i][20]);
                if(empty($product_str)){
                    $product_data = [];
                }else{
                    $product_data = explode('|', $product_str);
                }
                for ($t = 0; $t < count($product_data); $t++) {
                    $product_array = explode('*', $product_data[$t]);
                    $sn = $goods_id + $t;
                    $product_where[] = [
                        'attr_goods_sn' => $goods_sn . '_' . $sn,
                        'goods_id' => $goods_id,
                        'goods_attr' => str_replace('+', ",", $product_array[0]),
                        'attr_shop_price' => $product_array[1],
                        'attr_market_price' => $product_array[2],
                        'attr_cost_price' => $product_array[3],
                        'attr_goods_number' => $product_array[4],
                        'attr_warn_number' => $product_array[5],
                    ];
                }
                $products->saveAll($product_where);
            }
            Db::commit();
            return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/goods_import/index'];
        } catch (\Exception $e) {
            Db::rollback();
            halt($e->getMessage().'-'.$e->getLine());
            throw new \Exception($e->getMessage());
        }
    }

    function input_csv($handle)
    {
        $out = array();
        $n = 0;
        while ($data = fgetcsv($handle, 10000)) {
            $num = count($data);
            for ($i = 0; $i < $num; $i++) {
                $out[$n][$i] = $data[$i];
            }
            $n++;
        }
        return $out;
    }

    function createcsv()
    {
        // 头部标题
        $this->aa('ishop商品导入模板.csv');
    }

    public function aa($fileName)
    {
        $csv_header = array('商品名称', '分类名称(最后一级)', '商品属性类型', '商品品牌', '本店售价', '市场价', '成本价', '商品库存', '库存预警',
            '商品描述', '关键词', '是否使用店铺会员折扣(0否/1是)', '是否店铺特别推荐', '是否店铺普通推荐', '是否为店铺海报推荐', '是否为精品',
            '是否为热销否', '是否为人气', '是否为特惠', '商品明细(多个明细用|隔开)', '商品sku(属性名称*售卖价*市场价*成本价*库存*库存预警值|...)');
        $header = implode(',', $csv_header) . PHP_EOL;

        $csv_body = ['测试商品', '智能设备', '手机', '阿玛尼', '4000', '5000', '3600', '59', '20', '此商品是手机', '进口高端', '1', '0', '1', '1', '0', '1', '0', '1',
            '产地*美国|材质*金属|型号*iphoneX', '红色+64G*4000*5000*3900*58*20|土豪金+128G*5000*6000*4800*55*20'];

        $content = implode(',', $csv_body) . PHP_EOL;


        $csvData = $header . $content;
        header("Content-type:text/csv;");
        header("Content-Disposition:attachment;filename=" . $fileName);
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        $csvContent = iconv("utf-8", "gb2312", $csvData);
        echo $csvContent;
    }


}