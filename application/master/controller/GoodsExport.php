<?php
declare(strict_types = 1);
namespace app\master\controller;

use app\common\model\Area;
use app\common\model\Attr;
use app\common\model\AttrType;
use app\common\model\Brand;
use app\common\model\GoodsAttr;
use app\common\model\GoodsClassify;
use app\common\model\MemberRank;
use app\common\model\Products;
use app\common\model\Store;
use think\Controller;
use think\Db;
use think\Exception;
use think\facade\Cache;
use think\facade\Request;
use app\common\model\Goods as GoodsModel;
use app\common\model\CouponAttach as CouponAttachModel;
use think\Queue;

/**
 * 商品批量操作 编辑商品
 * Class GoodsBatchOperation
 * @package app\master\controller
 */
class GoodsExport extends Controller
{
    /**
     * 自营商品批量操作 选择商品列表
     * @param Request $request
     * @param GoodsModel $goods
     * @return mixed
     * @throws Exception
     */

    public function index(Request $request, GoodsModel $goods,GoodsClassify $goodsClassify,Brand $brand)
    {
        try {
            $param = $request::param();
        $map=[];
        if (array_key_exists('cateId', $param) && $param['cateId'])
            $map['goods_classify_id']=$param['cateId'];
        if (array_key_exists('brandId', $param) && $param['brandId'])
           $map['brand_id']= $param['brandId'];
        if (array_key_exists('keyword', $param) && $param['keyword'])
            $map['goods_name|spell_keyword|describe']=['like', '%' . $param['keyword'] . '%'];
        if (array_key_exists('search', $param) && $param['search']){
            //查找商品
            $data = $goods->where($map) ->order(['sort' => 'asc'])->field('goods_id,goods_name')->select();
        }else{
            $data=[];
        }
        $where[] = ['parent_id', '=', 0];
         //查询商品一级分类
        $categoryOne = $goodsClassify
            ->where($where)
            ->field('goods_classify_id,parent_id,title,count')
            ->order(['sort' => 'asc', 'update_time' => 'desc'])
            ->select();
        //查询全部品牌
        $brandData = $brand
            ->where([['brand_id', '>', 0]])
            ->field('brand_id,brand_name,brand_first_char')
            ->order(['sort' => 'asc', 'update_time' => 'desc'])
            ->select();
        //查询全部品牌首字母
        $brandFirstChr = $brand
            ->where([['brand_id', '>', 0]])
            ->distinct(true)
            ->order(['brand_first_char' => 'asc'])
            ->column('brand_first_char');
            return $this->fetch('', [
                'categoryOne' => $categoryOne,
                'brandFirstChr' => $brandFirstChr,
                'brand'=>$brandData,
                'data'=>$data
            ]);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 左边商品批量选择 展示在右边
     * @param Request $request
     * @param GoodsModel $goods
     * @return mixed
     * @throws \Exception
     */
   public function get_goods(Request $request, GoodsModel $goods){
       try {
           $param = $request::param();
           $data = Db::name('goods')->where('goods_id','in',$param['id']) ->field('goods_id,goods_name')->select();
          return $data;
       } catch (\Exception $e) {
           throw new \Exception($e->getMessage());
       }
   }

    /**
     * 导出
     * @param Request $request
     * @param GoodsModel $goods
     * @return mixed
     * @throws \Exception
     */
    public function create(Request $request, GoodsModel $goods,Brand $brand){
//        try {
            $param = $request::param();
        if($param['status']==1){
            //根据商品ID
            $w='g.goods_id';
        }else{
            //根据货号
            $w='g.goods_sn';
        }
        $data =  $goods->alias('g')
            ->relation('spec')
            ->relation('parameter')
            ->join('goodsClassify gc','gc.goods_classify_id=g.goods_classify_id','left')
            ->join('attrType at','at.attr_type_id = g.attr_type_id','left')
            ->join('brand b','b.brand_id = g.brand_id','left')
            ->where($w,'in',$param['id'])
            ->field('g.store_id,g.goods_id,g.goods_sn,g.goods_name,g.goods_classify_id,g.attr_type_id,g.brand_id,g.shop_price,
            g.market_price,g.cost_price,g.goods_number,g.warn_number,g.describe,
            g.keyword,g.is_vip,g.store_particularly_recommend,g.store_recommend,
            g.store_poster,g.is_best,g.is_hot,g.is_popularity,g.is_preference,g.goods_sn,
            g.spell_goods_name,gc.title,at.type_name,b.brand_name')
            ->select();
        $csv_header = array('商品名称','分类名称(最后一级)','商品属性类型','商品品牌','本店售价','市场价','成本价','商品库存','库存预警',
            '商品描述','关键词','是否使用店铺会员折扣(0否/1是)','是否店铺特别推荐','是否店铺普通推荐','是否为店铺海报推荐','是否为精品',
            '是否为热销否','是否为人气','是否为特惠','商品明细(多个明细用|隔开)','商品sku(属性名称*售卖价*市场价*成本价*库存*库存预警值|...)','店铺ID');
        foreach($data as $v){
            if(count($v['spec']) !=0){
                foreach($v['spec'] as $spec){
                    $product[] =str_replace(',',"+",$spec['goods_attr']).'*'.$spec['attr_shop_price'].'*'.$spec['attr_market_price'].'*'.$spec['attr_cost_price'].'*'.$spec['attr_goods_number'].'*'.$spec['attr_warn_number'];
                }
                $product_str = implode('|',$product);
            }else{
                $product_str='';
            }

            if(count($v['parameter']) !=0) {

                foreach ($v['parameter'] as $p) {
                    $par[] = $p['parameter_name'] . '*' . $p['parameter_val'];
                }
                $parameter_str = implode('|', $par);
            }else{
                $parameter_str='';
            }
            $csv_body[] = [
                $v['goods_name'],$v['title'],$v['type_name'],$v['brand_name'],$v['shop_price'],$v['market_price'],$v['cost_price'],$v['goods_number'],
                $v['warn_number'],$v['describe'],$v['keyword'],$v['is_vip'],$v['store_particularly_recommend'],$v['store_recommend'],$v['store_poster'],
                $v['is_best'],$v['is_hot'],$v['is_popularity'],$v['is_preference'],$parameter_str,$product_str, $v['store_id']
            ];
        }
           $this->putCsv('ishop商品导出.csv', $csv_body, $csv_header);
//        } catch (\Exception $e) {
//            throw new \Exception($e->getMessage());
//        }
    }




    public function putCsv($csvFileName, $dataArr ,$haderText){
        $handle = fopen($csvFileName,"w");//写方式打开
        if(!$handle){
            return '文件打开失败';
        }
        $header = implode(',', $haderText) . PHP_EOL;
// 处理内容
        $content = '';
        foreach ($dataArr as $k => $v) {

            $content .= implode(',', $v) . PHP_EOL;
        }
        $csvData = $header.$content;
// 写入并关闭资源

        header("Content-type:text/csv;");
        header("Content-Disposition:attachment;filename=" . $csvFileName);
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        $csvContent=iconv("utf-8","gb2312",$csvData);
        echo $csvContent;
        exit();
    }
}