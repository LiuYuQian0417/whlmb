<?php
// 商品管理
declare(strict_types = 1);

namespace app\client\controller;

use app\client\validate\Goods as GoodsValid;
use app\common\model\Area;
use app\common\model\Area as AreaModel;
use app\common\model\Attr;
use app\common\model\AttrType;
use app\common\model\Brand;
use app\common\model\Cart;
use app\common\model\CollectGoods;
use app\common\model\CutGoods as CutGoodsModel;
use app\common\model\DistributionLevel;
use app\common\model\FreightExpressClassify;
use app\common\model\Goods as GoodsModel;
use app\common\model\GoodsAttr;
use app\common\model\GoodsClassify;
use app\common\model\GoodsOperation;
use app\common\model\GoodsParameter;
use app\common\model\GroupGoods as GroupGoodsModel;
use app\common\model\Limit as LimitModel;
use app\common\model\MemberRank;
use app\common\model\Member;
use app\common\model\Message;
use app\common\model\Products;
use app\common\model\Store;
use app\common\model\StoreGoodsClassify as StoreGoodsClassifyModel;
use think\Controller;
use think\Db;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Env;
use think\facade\Request;
use think\facade\Session;
use app\common\model\Limit;
use app\common\model\LimitInterval;
use app\common\model\GroupGoods;
use app\common\model\GroupClassify;
use app\common\model\CutGoods;
use app\common\service\Beanstalk;
use app\common\model\Integral as IntegralModel;
use app\common\model\IntegralClassify as IntegralClassifyModel;
use app\common\model\GoodsEvaluate;
use app\common\model\Brand as BrandModel;
use app\common\model\GoodsClassify as GoodsClassifyModel;
use app\client\model\Tag;
use app\client\model\TagBindGoods;
use app\client\model\TagClassify;


class Goods extends Controller
{
    
    
    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.distribution');
    }
    
    /**
     * 商品列表
     *
     * @param Request $request 请求库
     * @param GoodsModel $goods 商品模型
     * @param Brand $brand 品牌模型
     *
     * @optimization Malson 2019-03-12 13:51
     *
     * @return mixed
     * @throws Exception
     */
    public function index(Request $request, GoodsModel $goods, Brand $brand)
    {
        //        try {
        $_storeId = Session::get('client_store_id');
        // 获取平台的分类
        $_goodsClassifyListSourceData = GoodsClassify::all();
        
        // 店铺的品牌列表
        $_brandList = Brand::all();
        
        // 商品的分类数据
        $_goodsClassifyList = [];
        
        foreach ($_goodsClassifyListSourceData as $value) {
            $_goodsClassifyList[$value['goods_classify_id']] = $value;
        }
        
        // 给页面显示的商品分类
        $_goodsClassifyListForPage = find_level(
            $_goodsClassifyListSourceData,
            'goods_classify_id'
        );
        
        $_filterWhere = $this->filterWhere();
        
        // 商品模型类
        $_goodsList = $_filterWhere['GOODS_LIST'];
        // 查询条件
        $_where = $_filterWhere['WHERE'];
        // 分页配置
        $_pageConfig = $_filterWhere['PAGE_CONFIG'];
        
        // 商品分类列表
        $this->assign('goods_classify_list', $_goodsClassifyListForPage);
        // 店铺品牌列表
        $this->assign('store_brand_list', $_brandList);
        // 页面条件
        $this->assign('filter_where', $_pageConfig);
        
        $_goodsList = $_goodsList
            ->alias('Goods')
            ->with([
                'groupHO',// 拼团
                'cutHO',// 砍价
                'limitHO',// 限时抢购
            ])
            ->join('Brand Brand', 'Brand.brand_id = Goods.brand_id', 'left')
            ->where($_where)
            ->field([
                'Goods.store_id' => 'store_id',
                'Goods.is_limit' => 'is_limit',
                'Goods.is_group' => 'is_group',
                'Goods.is_bargain' => 'is_bargain',
                'Goods.goods_id' => 'goods_id',
                'Goods.goods_classify_id' => 'goods_classify_id',
                'Goods.file' => 'file',                     // 商品缩略图
                'Goods.goods_name' => 'goods_name',         // 商品名称
                'Goods.shop_price' => 'shop_price',         // 售价
                'Goods.goods_sn' => 'goods_sn',             // 货号
                'Goods.goods_number' => 'goods_number',     // 库存
                'Goods.sales_volume' => 'sales_volume',     // 销量
                'Goods.collect_number' => 'collect_number', // 收藏
                'Goods.review_status' => 'review_status',   // 审核状态
                'Goods.is_putaway' => 'is_putaway',         // 上架状态
                'Goods.is_freight' => 'is_freight',         // 是否包邮
                'Goods.freight_status' => 'freight_status', // 快递运费状态
                'Goods.freight_price' => 'freight_price',   // 快递固定运费价格
                'Goods.is_distributor' => 'is_distributor',   // 快递固定运费价格
                'Goods.is_distribution' => 'is_distribution',   // 快递固定运费价格
                'Brand.brand_name' => 'brand_name',    // 品牌名称
                'Goods.delete_time' => 'delete_time'        // 商品是否删除
            ])
            ->paginate(null, false, [
                'query' => $_pageConfig,
            ]);
        
        
        foreach ($_goodsList as &$_goods) {
            // 为商品插入数据 商品规格数量
            $_goods['spec_count'] = count($_goods->spec);
            
            
            // 获取商品的分类文字
            
            // 初始化商品分类名称
            $_classifyNameList = [];
            // 赋值商品分类ID
            $_goodsClassifyId = $_goods['goods_classify_id'];
            
            // 循环赋值 分类的ID 获取到的分类名称
            while (isset($_goodsClassifyList[$_goodsClassifyId])) {
                array_unshift($_classifyNameList, $_goodsClassifyList[$_goodsClassifyId]['title']);
                $_goodsClassifyId = $_goodsClassifyList[$_goodsClassifyId]['parent_id'];
            }
            
            // 判断商品是否有分类数据
            if (empty($_classifyNameList)) {
                $_goods['classify_name'] = '-';
            } else {
                $_goods['classify_name'] = join('/', $_classifyNameList);
            }
            
        }
        
        $_configDistributionProportion = env('distribution_proportion') == 1 ? true : false;
        
        return $this->fetch(
            '', [
                'data' => $_goodsList,
                'configDistributionProportion' => $_configDistributionProportion,
            ]
        );
        //        } catch (\Exception $e) {
        //            throw new Exception($e->getMessage());
        //        }
    }
    
    /**
     * 修改上/下架状态
     * @return array
     */
    public function changePutAway()
    {
        
        $_POST = $this->request->post();
        
        $_valid = new GoodsValid;
        
        if (!$_valid->scene('change_put_away')->check($_POST)) {
            return [
                'code' => -100,
                'message' => $_valid->getError(),
            ];
        }
        
        $_goodsId = $_POST['goods_id'];
        
        // 保存到数据库的信息
        $_saveData = [
            // 商品
            'GOODS' => [
                // 默认下架
                'is_putaway' => 0,
                'is_group' => 0,
                'is_bargain' => 0,
                'is_limit' => 0,
            ],
            // 限时抢购
            'LIMIT' => [
                'status' => 0,  // 审核状态
                'reason' => ''   // 审核内容
            ],
            // 拼团
            'GROUP_GOODS' => [
                'status' => 0,  // 审核状态
                'reason' => ''   // 审核内容
            ],
            // 砍价
            'CUT_GOODS' => [
                'status' => 0,  // 审核状态
                'reason' => ''   // 审核内容
            ],
        ];
        
        Db::startTrans();
        try {
            $_goods = GoodsModel::withTrashed()->find($_goodsId);
            
            if (!$_goods) {
                return ['code' => -100, 'message' => '商品不存在'];
            }
            
            // 上架
            if ($_POST['up'] == 1) {
                if ($_goods['is_putaway'] == 1) {
                    return ['code' => -100, 'message' => '商品已上架,请勿重复操作'];
                }
                
                if ($_goods['review_status'] != 1) {
                    return ['code' => -100, 'message' => '只能上架审核通过的商品'];
                }
                
                // 上架商品
                GoodsModel::withTrashed()->where([
                    ['goods_id', '=', $_goodsId],
                ])->update(['is_putaway' => 1]);
                
            } else {  // 下架
                
                // 判断商品是否是下架状态
                if ($_goods['is_putaway'] == 0) {
                    return ['code' => -100, 'message' => '商品已下架,请勿重复操作'];
                }
                
                // 修改下架的商品的活动审核内容
                $_saveData['LIMIT']['reason'] = $_saveData['GROUP_GOODS']['reason'] = $_saveData['CUT_GOODS']['reason'] = '主商品被下架';
                
                GoodsModel::withTrashed()->where([
                    ['goods_id', '=', $_goodsId],
                ])->update($_saveData['GOODS']);
                
                // 修改 显示抢购
                Limit::where([
                    ['goods_id', '=', $_goodsId],
                    ['status', '<>', 0],
                ])->update($_saveData['LIMIT']);
                
                // 修改 拼团
                GroupGoods::where([
                    ['goods_id', '=', $_goodsId],
                    ['status', '<>', 0],
                ])->update($_saveData['GROUP_GOODS']);
                
                // 修改 砍价
                CutGoods::where([
                    ['goods_id', '=', $_goodsId],
                    ['status', '<>', 0],
                ])->update($_saveData['CUT_GOODS']);
            }
            Db::commit();
            return ['code' => 0, 'message' => config('message.')[0]];
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => -100, 'message' => config('message.')[-1]];
        }
        
    }
    
    
    /**
     * 创建商品展示
     *
     * @param \think\Request $request 请求库
     * @param FreightExpressClassify $freightExpressClassify 快递邮寄运费模板分类模型
     * @param StoreGoodsClassifyModel $classifyModel 店铺商品分类模型
     * @param GoodsModel $goods 商品模型
     * @param GoodsClassify $goodsClassify 商品分类模型
     * @param Brand $brand 平台品牌模型
     * @param AttrType $attrType 商品属性类型模型
     * @param MemberRank $memberRank 会员等级模型
     * @param DistributionLevel $distributionLevel 分销商等级 模型
     *
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(\think\Request $request,
                           FreightExpressClassify $freightExpressClassify,
                           StoreGoodsClassifyModel $classifyModel,
                           GoodsModel $goods,
                           GoodsClassify $goodsClassify,
                           Brand $brand,
                           AttrType $attrType,
                           MemberRank $memberRank,
                           DistributionLevel $distributionLevel)
    {
        $param = $request->get();
        
        //查询商品一级分类
        $categoryOne = $goodsClassify
            ->where(
                [
                    ['parent_id', '=', 0],
                    ['status', '=', 1],
                ]
            )
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
        
        // 平台会员等级名称
        $rankName = $memberRank
            ->where([['member_rank_id', '>', 0]])
            ->field('rank_name,discount')
            ->select();
        
        if (array_key_exists('id', $param) && $param['id']) {
            $goodsData = self::editGoodsShow($param['id'], $goods, $goodsClassify);
            $cateArr = getParCate($goodsData['goods_classify_id'], $goodsClassify);
            $goodsData['cateTitle'] = implode(' / ', array_column($cateArr, 'title'));
            
        }
        
        // 获取店铺商品分类
        $storeGoodsClassify = find_level(
            $classifyModel->where(['status' => 1, 'store_id' => Session::get('client_store_id')])->field(
                'store_goods_classify_id,title,parent_id'
            )->select()->toArray(), 'store_goods_classify_id'
        );
        
        // 商品属性类型数据
        $arrData = $attrType
            ->where(['status' => 1, 'store_id' => Session::get('client_store_id')])
            ->field('attr_type_id,type_name')
            ->order(['update_time' => 'desc'])
            ->select();
        
        // 运费模板数据
        $frightData = $freightExpressClassify->where('store_id', Session::get('client_store_id'))->field(
            'freight_express_classify_id,name'
        )->select();
        
        //查询用户设置过的商品分类缓存
        $cateCache = Cache::get('cateCreateCache_' . $request->loginId) ?: [];
        Env::load(Env::get('app_path') . 'common/ini/.distribution');
        $distribution_status = Env::get('DISTRIBUTION_STATUS', 0);
        $goodsData = isset($goodsData) ? $goodsData : [];
        if (!empty($goodsData)) {
            if ($goodsData['distribution_set']) {
                $goodsData['distribution_set'] = unserialize($goodsData['distribution_set']);
            }
            
            $goodsData['multiple_file_extra_data'] = join(',', $goodsData['multiple_file_extra']);
            $goodsData['multiple_file_data'] = join(',', $goodsData['multiple_file']);
        }
        
        $storeData = Store::where([
            ['store_id', '=', Session::get('client_store_id')],
        ])->find();
        return $this->fetch(
            '', [
                'cateCache' => $cateCache,
                'categoryOne' => $categoryOne,
                'brand' => $brandData,
                'brandFirstChr' => $brandFirstChr,
                'rank_name' => $rankName,
                'goodsData' => isset($goodsData) ? $goodsData : [],
                'storeData' => isset($storeData) ? $storeData : [],
                'arrData' => $arrData,
                'storeGoodsClassify' => $storeGoodsClassify,
                'freight' => $frightData,
                'distribution_status' => $distribution_status,
                'distribution_level' => $distributionLevel
                    ->field('distribution_level_id,level_title,level_weight')
                    ->order('level_weight', 'asc')
                    ->select(),
                'pc_config' => config('user.pc.is_include'),
            ]
        );
    }
    
    /**
     * 编辑商品
     *
     * @param               $goods_id
     * @param GoodsModel $goods
     * @param GoodsClassify $goodsClassify
     *
     * @return array|null|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function editGoodsShow($goods_id, GoodsModel $goods, GoodsClassify $goodsClassify)
    {
        $field = 'goods_id,g.store_id,g.goods_classify_id,store_goods_classify_id,attr_type_id,
                g.brand_id,goods_sn,goods_name,goods_name_style,market_price,goods_weight,
                shop_price,recomme_file,cost_price,goods_number,warn_number,video,file,is_freight,freight_status,freight_id,freight_price,
                multiple_file,is_best,is_hot,is_popularity,store_banner,is_preference,store_particularly_recommend,store_recommend,store_poster,
                express,express_one_city,express_self_lifting,default_express_type,is_group,is_bargain,is_limit,
                is_putaway,is_vip,keyword,g.describe,content,web_content,g.sort,g.distribution_set,g.rebates_type,g.is_distribution,g.is_distributor,Store.is_delivery,Store.is_city,Store.is_shop';
        // 获取商品信息
        $goodsData = $goods
            ->alias('g')
            ->with(
                [
                    'parameter',
                    'spec' => function ($s) {
                        $s->field(
                            'products_id,goods_id,goods_attr,attr_market_price,
                attr_shop_price,attr_cost_price,attr_goods_number,attr_warn_number,
                attr_file,attr_goods_sn'
                        );
                    },
                ]
            )
            ->join('Store Store', 'Store.store_id = g.store_id')
            ->view('brand b', 'brand_name', 'b.brand_id = g.brand_id', 'left')
            ->field($field)
            ->where(['goods_id' => $goods_id])
            ->find();
        
        // 获取商品分类
        $cateGroup = getParCate($goodsData['goods_classify_id'], $goodsClassify);
        // 设置分类标题
        $goodsData['cateTitle'] = implode(' / ', array_column($cateGroup, 'title'));
        // 设置商品分类ID
        $classifyId = array_column($cateGroup, 'goods_classify_id');
        // 商品一级分类ID
        $goodsData['firstCate'] = array_shift($classifyId);
        $goodsData['file_data'] = $goodsData->getData('file');
        // 获取父级分类ID
        $parent = array_column($cateGroup, 'parent_id');
        array_shift($parent);
        // 获取分类列表
        $classify = $goodsClassify
            ->where([['parent_id', 'in', implode(',', $parent)], ['status', '=', 1]])
            ->field('goods_classify_id,parent_id,title')
            ->select();
        
        // 分类数组
        $cateArr = [];
        
        // 原始视频数据
        $goodsData['video_data'] = $goodsData->getData('video');
        
        if ($classify) {
            foreach ($classify as $key => $value) {
                $value['checked'] = in_array($value['goods_classify_id'], $classifyId) ? 1 : 0;
                $cateArr[$value['parent_id']][] = $value;
            }
        }
        
        $goodsData['cateArr'] = array_values($cateArr);
        
        return $goodsData;
    }
    
    /**
     * 检测货号是否重复
     *
     * @param Request $request
     * @param GoodsModel $goods
     *
     * @return array
     */
    public function checkGoodsSn(Request $request, GoodsModel $goods)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                //检测货号是否唯一
                $ret = $goods->checkGoodsSnUnique($param);
                return ['code' => $ret ? -1 : 0, 'message' => config('message.')[$ret ? -6 : 0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }
    }
    
    /**
     * 查看商品是否在活动中
     *
     * @param string $id 商品ID
     * @param GoodsModel $goods
     *
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getActive(Request $request, GoodsModel $goods)
    {
        $param = $request::post();
        
        //看商品是否处于活动中  1未通过  2审核中
        $data = $goods->where([
            ['goods_id', '=', $param['id']],
            ['is_group', 'in', '0,2'],
            ['is_bargain', 'in', '0,2'],
            ['is_limit', 'in', '0,2'],
        ])->find();
        
        if (!empty($data)) {
            return ['code' => 0];  //正常商品
        } else {
            return ['code' => -100];//活动中商品
        }
    }
    
    /**
     * 获取商品规格类型
     *
     * @param AttrType $attrType
     * @param array $ins
     *
     * @return array
     */
    public function getAttrType(AttrType $attrType, $ins = [])
    {
        try {
            //获取商品类型
            $attrWhere = [
                ['status', '=', 1],
                ['store_id', '=', Session::get('client_store_id')],
            ];
            $attr = $attrType
                ->where($attrWhere)
                ->field('attr_type_id,type_name')
                ->order(['update_time' => 'desc'])
                ->select();
            return ['code' => 0, 'message' => '查询成功', 'data' => $attr];
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => config('message.')['-1']];
        }
    }
    
    /**
     * 单修改参数
     *
     * @param Request $request
     * @param GoodsModel $goods
     * @param Products $products
     * @param Store $store
     *
     * @return array
     */
    public function editVal(Request $request, GoodsModel $goods, Products $products, Store $store)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $model = $goods;
                if (array_key_exists('products_id', $param)) {
                    $model = $products;
                }
                if (array_key_exists('switchType', $param)) {
                    $changeVal = [
                        'is_best',
                        'is_hot',
                        'is_popularity',
                        'is_preference',
                        'is_putaway',
                        'store_particularly_recommend',
                        'store_recommend',
                        'store_poster',
                        'store_banner',
                    ];
                    $param[$changeVal[$param['switchType']]] = $param['checked'];
                }
                $model->allowField(true)->isUpdate(true)->save($param);
                $store->countGoodsNum($param['goods_id']);
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }
    }
    
    /**
     * 商品添加处理
     *
     * @param Request $request
     * @param GoodsModel $goods
     * @param Store $store
     * @param GoodsAttr $goodsAttr
     * @param GoodsClassify $goodsClassify
     * @param GoodsParameter $goodsParameterModel
     * @param Cart $cart
     * @param CollectGoods $collectGoods
     *
     * @return array
     */
    public function createAct(Request $request,
                              GoodsModel $goods,
                              Store $store,
                              GoodsAttr $goodsAttr,
                              GoodsClassify $goodsClassify,
                              GoodsParameter $goodsParameterModel,
                              Cart $cart,
                              CollectGoods $collectGoods)
    {
        $ratio = Env::get('RATIO_SET');
        if ($request::isPost()) {
            try {
                $param = $request::post();
                Db::startTrans();
                $_goodsValid = new GoodsValid();
                
                if (isset($param['express']) && $param['express'] == 1 && isset($param['freight_status']) && $param['freight_status'] == 1) {
                    unset($param['freight_price']);
                }
                
                //验证数据
                $validRet = $_goodsValid->scene('create')->check($param);
                if (!$validRet) {
                    Db::rollback();
                    return ['code' => -1, 'message' => $_goodsValid->getError()];
                }
                //处理多图
                if (array_key_exists('picArr', $param) && $param['picArr']) {
                    $param['multiple_file'] = implode(',', $param['picArr']);
                }
                //商品名称转拼音
                $param['spell_goods_name'] = str_replace(
                    ' ', '', app('app\\common\\service\\Spell')->convert(
                    $param['goods_name']
                )
                );
                
                if (array_key_exists('spec', $param) && $param['spec']) {
                    
                    foreach ($param['spec'] as $v) {
                        if (empty($v['attr_goods_number']) && $v['attr_goods_number'] != 0) {
                            Db::rollback();
                            return ['code' => -100, 'message' => '请输入规格(' . $v['goods_attr'] . ')的库存'];
                        }
                        if (empty($v['attr_warn_number'])) {
                            Db::rollback();
                            return ['code' => -100, 'message' => '请输入规格(' . $v['goods_attr'] . ')的库存预警值'];
                        }
                        
                    }
                }
                
                //上传商品
                if (array_key_exists('goods_id', $param) && $param['goods_id']) {
                    $status = true;
                    self::arrivalWarn($goods, $cart, $collectGoods, $param);
                } else {
                    $status = false;
                    
                    // 待审核
                    $param['review_status'] = 2;
                    
                    // 已下架
                    $param['is_putaway'] = 0;
                }
                // 店铺ID
                $param['store_id'] = Session::get('client_store_id');
                if (array_key_exists('spec', $param) && $param['spec']) {
                    // 统一库存(总库存=属性库存和)
                    $param['goods_number'] = array_sum(array_column($param['spec'], 'attr_goods_number'));
                    $param['warn_number'] = array_sum(array_column($param['spec'], 'attr_warn_number'));
                }
                
                if ($param['goods_number'] > 65535) {
                    Db::rollback();
                    // 如果这里客户需要 大于 65535的话 可以在数据库中 ishop_goods表 把 goods_number 类型改为 bigInt
                    return ['code' => -1, 'message' => "商品规格库存和,不能大于65535"];
                }
                if ($param['warn_number'] > 65535) {
                    Db::rollback();
                    // 如果这里客户需要 大于 65535的话 可以在数据库中 ishop_goods表 把 goods_number 类型改为 bigInt
                    return ['code' => -1, 'message' => "商品规格库存预警值和,不能大于65535"];
                }
                
                if (array_key_exists('distribution_set', $param) && $param['distribution_set']) {
                    // 检测比例设置是否低于系统设置值
                    foreach ($param['distribution_set'][1] as $k => $v) {
                        $_pri = 0;
                        foreach ($param['distribution_set'] as $_set) {
                            $_pri += $_set[$k];
                        }
                        if ($param['rebates_type'] == 1) {
                            // 比例
                            if ($_pri > $ratio) {
                                Db::rollback();
                                return ['code' => -1, 'message' => "您设置百分比的值不能大于等于$ratio%"];
                            }
                        } else {
                            Db::rollback();
                            if ($_pri >= $param['shop_price'] / 2) {
                                return ['code' => -1, 'message' => '您设置现金的值不能大于商品半价'];
                            }
                        }
                    }
                    $param['distribution_set'] = serialize($param['distribution_set']);
                }
                
                
                $goods->allowField(true)->isUpdate($status)->save($param);
                $param['goods_id'] = $goods->goods_id;
                $store->countGoodsNum($goods->goods_id);
                //设置商品货号
                if (!$param['goods_sn']) {
                    $goods->setGoodsSn($goods->goods_id);
                }
                //设置商品参数
                if (array_key_exists('parameter_name', $param) && $param['parameter_name']) {
                    $goods->setParameter($param);
                } else {
                    // 删除商品参数
                    $goodsParameterModel->where([['goods_id', '=', $goods->goods_id]])->delete(true);
                }
                //设置商品属性价格
                $goods->setSpec($param);
                //设置商品属性
                $goodsAttr->setGoodsAttr(
                    (array_key_exists('spec_attr', $param) && $param['spec_attr']) ? $param['spec_attr'] : [],
                    $goods->goods_id
                );
                // 设置分类缓存
                $cateClassify = getParCate($param['goods_classify_id'], $goodsClassify);
                $cateCache = [
                    'goods_classify_id' => array_column($cateClassify, 'goods_classify_id'),
                    'goods_classify_info' => $param['goods_classify_info'],
                ];
                if ($status) {
                    $goods->getReduction($param['goods_id'], $param['shop_price'], $param['file']);
                }
                self::setCateCache($cateCache);
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => "/client/goods/index"];
            } catch (ValidateException $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => config('message.')[-1]];
            }
        }
    }
    
    /**
     * 到货提醒
     *
     * @param GoodsModel $goods
     * @param Cart $cart
     * @param CollectGoods $collectGoods
     * @param              $args
     *
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function arrivalWarn(GoodsModel $goods, Cart $cart, CollectGoods $collectGoods, $args)
    {
        // 查询商品原库存剩余量
        $arrivalData = $goods
            ->alias('g')
            ->where([['g.goods_id', '=', $args['goods_id']]])
            ->join('store s', 's.store_id = g.store_id')
            ->field('g.goods_id,g.goods_number,g.warn_number,s.store_name')
            ->with(['arrivalSpec'])
            ->find();
        if ($arrivalData) {
            $where = [];
            // 增加了默认库存
            if ($arrivalData['goods_number'] == 0 && $args['goods_number'] > 0) {
                array_push($where, '');
            }
            if ($arrivalData->arrival_spec && array_key_exists('spec', $args) && $args['spec']) {
                foreach ($arrivalData->arrival_spec as $item) {
                    foreach ($args['spec'] as $_val) {
                        if ($item['attr_goods_number'] == 0 && $_val['attr_goods_number'] > 0) {
                            array_push($where, $_val['goods_attr']);
                        }
                    }
                }
            }
            // 查找购物车和商品收藏夹,查询哪些用户对此商品感兴趣
            if ($where) {
                $cartData = $cart
                    ->where([
                        ['goods_id', '=', $args['goods_id']],
                        ['goods_attr', 'in', implode(',', $where)],
                    ])
                    ->column('member_id');
                $collectGoodsData = $collectGoods
                    ->where([
                        ['goods_id', '=', $args['goods_id']],
                    ])
                    ->column('member_id');
                $memberData = array_unique(array_merge($cartData, $collectGoodsData));
                if ($memberData) {
                    $memberInfo = (new Member())
                        ->where([
                            ['member_id', 'in', implode(',', $memberData)],
                        ])
                        ->field('member_id,web_open_id,subscribe_time,micro_open_id,phone')
                        ->select();
                    $messageData = [];
                    foreach ($memberInfo as $_memberInfo) {
                        array_push($messageData, [
                            'tplKey' => 'goods_state',
                            'openId' => $_memberInfo['web_open_id'],
                            'subscribe_time' => $_memberInfo['subscribe_time'],
                            'microId' => $_memberInfo['micro_open_id'],
                            'phone' => $_memberInfo['phone'],
                            'data' => [1, $arrivalData['store_name'], $args['goods_name']],
                            'inside_data' => [
                                'member_id' => $_memberInfo['member_id'],
                                'type' => 0,
                                'jump_state' => '4',
                                'attach_id' => $args['goods_id'],
                                'file' => $args['file'],
                            ],
                            'sms_data' => [],
                        ]);
                    }
                    // 推送消息[到货提醒][只含站内信]
                    $pushServer = app('app\\interfaces\\behavior\\Push');
                    foreach ($messageData as $_msg) {
                        $pushServer->send($_msg, 3);
                    }
                }
            }
        }
    }
    
    /**
     * 设置创建商品分类缓存
     *
     * @param $cate
     *
     * @return array|mixed
     */
    private function setCateCache($cate)
    {
        $cache = Cache::get('cateCreateCache_' . request()->loginId) ?: [];
        if ($cache) {
            foreach ($cache as $key => $value) {
                if ($value['goods_classify_id'] = $cate['goods_classify_id']) {
                    unset($cache[$key]);
                }
            }
        }
        array_unshift($cache, $cate);
        $cache = array_slice($cache, 0, 20);
        Cache::set('cateCreateCache_' . request()->loginId, $cache);
        return $cache;
    }
    
    /**
     * 智能权重展示与编辑
     *
     * @param Request $request
     * @param GoodsModel $goods
     *
     * @return mixed
     * @throws \Exception
     */
    public function weight(Request $request, GoodsModel $goods)
    {
        try {
            $param = $request::get();
            $data = $goods->alias('g')->where('goods_id', $param['id'])
                ->field('goods_id,store_id,sort,collect_number,sales_volume,goods_name,sales_volume,comments_number')
                ->relation(
                    [
                        'store' => function ($e) {
                            $e->field('store_name,grade');
                        },
                        'ordergoods' => function ($e) {
                            $e->field('IFNULL(sum(case when status=4.3 and status=4.2 then 1 else 0 end),0) as refund');
                            $e->field(
                                'count(distinct case when status=4.1 or status=3.1 or status=2.1 or status=1.1 or status=1.2 then member_id else 0 end) as member_pay'
                            );
                        },
                    ]
                )->find();
            return $this->fetch(
                '', [
                    'data' => $data,
                ]
            );
        } catch (\Exception $e) {
            throw new \Exception(config('message.')['-1']);
        }
    }
    
    /**
     * 第一步获取子类分类
     *
     * @param Request $request
     * @param GoodsClassify $goodsClassify
     *
     * @return array
     */
    public function getSonCate(Request $request, GoodsClassify $goodsClassify)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $where[] = ['parent_id', '=', $param['id']];
                $where[] = ['status', '=', 1];
                //查询商品分类子类
                $sonCate = $goodsClassify
                    ->where($where)
                    ->field('goods_classify_id,parent_id,title')
                    ->order(['sort' => 'asc', 'update_time' => 'desc'])
                    ->select();
                $sonCateHtml = ($param['type'] == 1) ? '<li>请选择二级分类</li>' : '<li>请选择三级分类</li>';
                if ($sonCate->count() > 0) {
                    $flag = 1;
                    foreach ($sonCate as $key => $item) {
                        $inf = 'onclick="step.getSon(' . ($param['type'] + 1) . ',' . $item['goods_classify_id'] . ');"';
                        $sonCateHtml .= '<li ' . $inf . ' id="cate_' . $item['goods_classify_id'] . '"><a href="javascript:void(0);" ><i></i>' . $item['title'] . '</a></li>';
                    }
                } else {
                    $flag = 0;
                    $sonCateHtml .= '<li class="empty">暂无此级分类</li>';
                }
                return ['code' => 0, 'message' => config('message.')[0], 'data' => $sonCateHtml, 'flag' => $flag];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }
    }
    
    /**
     * 获取个人分类历史输入
     *
     * @param Request $request
     * @param GoodsClassify $goodsClassify
     *
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCateHistory(Request $request, GoodsClassify $goodsClassify)
    {
        if ($request::isPost()) {
            $param = $request::post();
            $data = $goodsClassify
                ->where([['parent_id', 'in', implode(',', $param['id'])]])
                ->field('goods_classify_id,parent_id,title,count')
                ->select();
            $title = $goodsClassify
                ->where([['goods_classify_id', 'in', implode(',', $param['id'])]])
                ->column('title');
            
            $html = [];
            
            if ($data->count()) {
                $text = ['<li>请选择二级分类</li>', '<li>请选择三级分类</li>'];
                foreach ($data as $key => $val) {
                    $checked = in_array($val['goods_classify_id'], $param['id']) ? 'cur' : '';
                    $inf = 'onclick="step.getSon(' . ($val['count']) . ',' . $val['goods_classify_id'] . ');"';
                    
                    if (!array_key_exists($val['parent_id'], $html)) {
                        $html[$val['parent_id']] = $text[$val['count'] - 2];
                    }
                    $html[$val['parent_id']] .= '<li ' . $inf . ' class="' . $checked . '" id="cate_' . $val['goods_classify_id'] . '"><a href="javascript:void(0);" ><i></i>' . $val['title'] . '</a></li>';
                }
            }
            
            return ['code' => 0, 'message' => '查询成功', 'data' => array_values($html), 'title' => implode(' / ', $title)];
        }
    }
    
    /**
     * 第四步获取分类
     *
     * @param Request $request
     * @param GoodsClassify $classify
     *
     * @return array
     */
    public function getCate(Request $request, GoodsClassify $classify)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $where = [
                    ['goods_classify_id', '>', 0],
                    ['status', '=', 1],
                ];
                $head = '<li>请选择商品分类</li>';
                $headContent = [];
                if (array_key_exists('id', $param) && $param['id']) {
                    $where[] = ['parent_id', '=', $param['id']];
                    //查询所选项的父级
                    $total = getParCate($param['id'], $classify);
                    if ($total) {
                        $head = '<li><a href="javascript:step.getCate(0,0);">重选</a></li>';
                        foreach ($total as $item) {
                            $head .= '<li>&nbsp;> <a href="javascript:' . (($item['count'] == 3) ? 'void(0)' : 'step.getCate(' . $item['goods_classify_id'] . ',' . $item['count'] . ')') . ';">' . $item['title'] . '</a></li>';
                            $headContent[] = $item['title'];
                        }
                    }
                } else {
                    $where[] = ['parent_id', '=', 0];
                }
                if (array_key_exists('keyword', $param) && $param['keyword']) {
                    $where['title'] = ['like', '%' . $param['keyword'] . '%'];
                }
                $data = $classify
                    ->where($where)
                    ->field('goods_classify_id,title,count')
                    ->order(['sort' => 'desc', 'update_time' => 'desc'])
                    ->select();
                $html = '';
                if ($data->count()) {
                    $count = [1 => 'Ⅰ', 2 => 'Ⅱ', 3 => 'Ⅲ'];
                    foreach ($data as $key => $item) {
                        $html .= '<li title="' . $item['title'] . '"  data-count="' . $item['count'] . '"  value="' . $item['goods_classify_id'] . '" ><em>' . $count[$item['count']] . '</em>' . $item['title'] . '</li>';
                    }
                } else {
                    $html .= '<li class="empty">暂无分类数据</li>';
                }
                return [
                    'code' => 0,
                    'message' => config('message.')[0],
                    'data' => $html,
                    'head' => $head,
                    'headContent' => implode(' > ', $headContent),
                ];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }
    }
    
    
    /**
     * 获取规格列表
     *
     * @param Request $request
     * @param Attr $attr
     *
     * @return array
     */
    public function attrList(Request $request, Attr $attr)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post('id');
                $data = $attr
                    ->where('attr_type_id', $param)
                    ->with('goodsAttr')
                    ->field('attr_type_id,attr_id,attr_name,attr_input_type,attr_value')
                    ->select();
                return ['code' => 0, 'data' => $data];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }
    }
    
    /**
     * 富文本编辑器展示页
     *
     * @return mixed
     */
    public function uEditor()
    {
        return $this->fetch('/goods/uEditor');
    }
    
    
    /**
     * 配送区域城市选择
     *
     * @param Request $request
     * @param Area $area
     *
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function city(Request $request, AreaModel $area)
    {
        // 获取省级城市
        $where = [['deep', '=', 1], ['status', '=', 1]];
        $cityBase = $area->field('area_id,area_name,deep')->order(['area_id' => 'asc']);
        if ($request::isPost()) {
            try {
                $checked = $request::post('checked', '');
                $cityId = $request::post('cityId');
                $deep = $request::post('deep', 1);
                $classStr = $request::post('classStr', '');
                array_shift($where);
                array_unshift($where, ['parent_id', '=', $cityId], ['deep', '=', ++$deep]);
                $city = $cityBase->where($where)->select();
                $html = '<ul class="city-ul">';
                if ($city->count()) {
                    $classArr = explode(' ', $classStr);
                    if ($classArr) {
                        array_shift($classArr);
                        array_push($classArr, $cityId);
                        $classStr = implode(' ', $classArr);
                    }
                    foreach ($city as $key => $item) {
                        $getNext = ($deep == 4) ? ''
                            : '<em title="点击展示' . ($deep == 2 ? '区' : '街道') . '" onclick="getNextCity(' . $item->area_id . ',' . $item->deep . ',\'.city-list\');"> <i class="layui-icon layui-icon-right"></i> </em>';
                        $html .= <<<EXO
<li>
<input type="checkbox" $checked value="$item->area_id" id="$item->area_id" class="$deep $classStr" data-pid="$cityId" lay-filter="$deep" lay-skin="primary" title="$item->area_name">$getNext
</li>
EXO;
                    }
                } else {
                    $html .= '<li class="empty">未发现下级' . ['市', '区', '街道'][$deep - 2] . '</li>';
                }
                return ['code' => 0, 'message' => config('message.')[0], 'data' => $html . '</ul>'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }
        $city = $cityBase->where($where)->select();
        
        return $this->fetch(
            '', [
                'city' => $city,
                'fid' => $request::param('fid'),
            ]
        );
    }
    
    public function searchGoods(Request $request, GoodsModel $goods, GoodsClassifyModel $goodsClassify, BrandModel $brand)
    {
        try {
            $param = $request::get();
            $where = [
                ['g.goods_id', '>', 0],
                ['g.review_status', '=', 1],
                ['g.is_putaway', '=', 1],
                ['g.is_group', '=', 0],
                ['g.is_bargain', '=', 0],
                ['g.is_limit', '=', 0],
            ];
            if (array_key_exists('cateId', $param) && $param['cateId']) {
                array_push($where, ['g.goods_classify_id', '=', $param['cateId']]);
            }
            if (array_key_exists('brandId', $param) && $param['brandId']) {
                array_push($where, ['g.brand_id', '=', $param['brandId']]);
            }
            if (array_key_exists('keyword', $param) && $param['keyword']) {
                array_push(
                    $where,
                    ['g.goods_sn|g.goods_name|g.spell_keyword|g.describe', 'like', '%' . $param['keyword'] . '%']
                );
            }
            $data = $goods
                ->alias('g')
                ->where($where)
                ->where('g.store_id', Session::get('client_store_id'))
                ->view('store s', 'store_name', 's.store_id= g.store_id', 'left')
                ->view('brand b', 'brand_name', 'b.brand_id = g.brand_id', 'left')
                ->field('goods_id,g.goods_classify_id,file,goods_name,shop_price,goods_sn')
                ->order(['g.update_time' => 'desc', 'g.goods_id' => 'asc'])
                ->paginate($goods->pageLimits, false, ['query' => $param]);
            
            //查询商品一级分类
            $categoryOne = $goodsClassify
                ->where([
                    ['parent_id', '=', 0],
                    ['status', '=', 1],
                ])
                ->field('goods_classify_id,parent_id,title,count')
                ->order(['sort' => 'asc', 'update_time' => 'desc'])->select();
            
            //查询全部品牌
            $brandData = $brand->where([['brand_id', '>', 0]])->field('brand_id,brand_name,brand_first_char')
                ->order(['sort' => 'asc', 'update_time' => 'desc'])->select();
            
            //查询全部品牌首字母
            $brandFirstChr = $brand->where([['brand_id', '>', 0]])->distinct(true)->order(['brand_first_char' => 'asc'])
                ->column('brand_first_char');
            
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => config('message.')['-1']];
        }
        return $this->fetch(
            '', [
                'categoryOne' => $categoryOne,
                'data' => $data,
                'type' => $param['type'],
                'brand' => $brandData,
                'brandFirstChr' => $brandFirstChr,
            ]
        );
    }
    
    /**
     * 商品分类排序更新
     *
     * @param Request $request
     * @param GoodsModel $goods
     *
     * @return array
     */
    public function text_update(Request $request, GoodsModel $goods)
    {
        
        if ($request::isPost()) {
            try {
                $goods->clickEdit($request::post());
                return ['code' => 0, 'message' => ''];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }
    }
    
    /**
     * 获取商品规格
     *
     * @param Request $request
     * @param Products $products
     * @param CutGoodsModel $cutGoods
     * @param LimitModel $limit
     * @param GroupGoodsModel $groupGoodsModel
     *
     * @return array
     */
    public function getProducts(Request $request, Products $products, CutGoodsModel $cutGoods, LimitModel $limit, GroupGoodsModel $groupGoodsModel)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                
                $status = '';
                switch ($param['type']) {
                    case 1: //拼团
                        $field = 'products_id,goods_attr,attr_shop_price,attr_group_price,attr';
                        $status = $groupGoodsModel->where(['goods_id' => $param['id']])->value('status');
                        break;
                    case 2: //砍价
                        $field = 'products_id,goods_attr,attr_shop_price,attr_cut_price,attr_single_cut_min,attr_single_cut_max,attr';
                        $status = $cutGoods->where(['goods_id' => $param['id']])->value('status');
                        break;
                    case 3: //限时抢购
                        $field = 'products_id,goods_attr,attr_shop_price,attr_time_limit_price,attr_goods_number,attr_time_limit_number,attr';
                        $status = $limit->where(['goods_id' => $param['id']])->value('status');
                        break;
                    default: //编辑商品
                        $field = 'products_id,goods_id,goods_attr,attr_market_price,attr_shop_price,
                        attr_goods_weight,attr_cost_price,attr_goods_number,attr_warn_number,attr_file,attr_goods_sn,attr';
                        break;
                }
                $data = $products
                    ->where([['goods_id', '=', $param['id']]])
                    ->field($field)
                    ->order(['update_time' => 'desc', 'products_id' => 'desc'])
                    ->select();
                $goods_number = GoodsModel::where([['goods_id', '=', $param['id']]])->value('goods_number');
                $html = '';
                $readonly = '';
                if ($data->count() > 0) {
                    foreach ($data as $key => $val) {
                        switch ($param['type']) {
                            case 1:
                                if ($status == 1) {
                                    $readonly = "readonly";
                                }
                                $html .= <<<EXO
<tr>
<td>$val->goods_attr<input type='hidden' name="products_id[]" value="$val->products_id" form="deniedform" /></td>
<td>$val->attr_shop_price</td>
<td><input type='text' class="batch-2" name="attr_group_price[]" $readonly value="$val->attr_group_price" placeholder='0.00' form="deniedform" /></td>
</tr>
EXO;
                                break;
                            case 2:
                                if ($status == 1) {
                                    $readonly = "readonly";
                                }
                                $html .= <<<EXO
<tr>
<td>$val->goods_attr<input type='hidden' name="products_id[]" value="$val->products_id" form="deniedform" /></td>
<td>$val->attr_shop_price</td>
<td><input type='text' class="batch1-2" name="attr_cut_price[]" $readonly value="$val->attr_cut_price" placeholder='0.00' form="deniedform" /></td>
<td><input type='text' class="batch2-3" name="attr_single_cut_min[]" $readonly value="$val->attr_single_cut_min" placeholder='0.00' form="deniedform" /></td>
<td><input type='text' class="batch-4" name="attr_single_cut_max[]" $readonly value="$val->attr_single_cut_max" placeholder='0.00' form="deniedform" /></td>
</tr>
EXO;
                                break;
                            case 3:
                                if ($status == 1) {
                                    $readonly = "readonly";
                                }
                                $html .= <<<EXO
<tr>
<td>$val->goods_attr<input type='hidden' name="products_id[]" value="$val->products_id" form="deniedform" /></td>
<td>$val->attr_shop_price</td>
<td><input type='text' class="batch-2" name="attr_time_limit_price[]" $readonly value="$val->attr_time_limit_price" placeholder='0.00' form="deniedform" /></td>
<td>$val->attr_goods_number</td> 
<td><input type='text'  class="batch1-4"  onkeyup="check_arrt_number(this,$val->attr_goods_number)" number="$val->attr_goods_number" name="attr_time_limit_number[]" $readonly value="$val->attr_time_limit_number" placeholder='0' form="deniedform" /></td>
</tr>
EXO;
                                break;
                            case 4:
                                $gaClass = implode('#!@!#', explode(',', $val->goods_attr));
                                $faClass = implode('_', explode(',', $val->goods_attr)) . '_img';
                                $html .= <<<EXO
<tr>
<td>
<span class="$gaClass">$val->goods_attr</span>
<input type="hidden" name="spec[$val->goods_attr][goods_attr]" value="$val->goods_attr" form="deniedform" />
<input type="hidden" name="spec[$val->goods_attr][attr]" value="$val->attr" form="deniedform" />
<input type="hidden" name="spec[$val->goods_attr][products_id]" value="$val->products_id" form="deniedform" /> 
</td>
<td>
<input type="text" class="attr-input batch-1" name="spec[$val->goods_attr][attr_market_price]" value="$val->attr_market_price" placeholder="0.00" form="deniedform" />
</td>
<td>
<input type="text" class="attr-input batch-2" name="spec[$val->goods_attr][attr_shop_price]" value="$val->attr_shop_price" placeholder="0.00" form="deniedform" />
</td>
<td>
<input type="text" class="attr-input batch-3" name="spec[$val->goods_attr][attr_cost_price]" value="$val->attr_cost_price" placeholder="0.00" form="deniedform" />
</td>
<td>
<input type="text" class="attr-input batch-4" name="spec[$val->goods_attr][attr_goods_number]" value="$val->attr_goods_number" placeholder="0" form="deniedform" />
</td>
<td> 
<input type="text" class="attr-input batch-5" name="spec[$val->goods_attr][attr_warn_number]" value="$val->attr_warn_number" placeholder="0" form="deniedform" />
</td>
<td>
<input type="text" class="attr-input batch-6" name="spec[$val->goods_attr][attr_goods_weight]" value="$val->attr_goods_weight" placeholder="不填用默认" form="deniedform" />
</td>
<td>
<input type="text" class="attr-input-max" name="spec[$val->goods_attr][attr_goods_sn]" value="$val->attr_goods_sn" placeholder="不填自生成" form="deniedform" />
</td>  
<td><i class="fa fa-plus chose" data-flag="$faClass"></i><input class="layui-upload-file" type="file" accept="image/*" name="attr_file"></td>
<td>
<img src="$val->attr_file_extra" onerror="this.src='/template/master/resource/image/common/imageError.png'" class="spec-img" title="$val->goods_attr" alt="$val->goods_attr" />
<input type="hidden" name="spec[$val->goods_attr][attr_file]" class="attr_file" value="$val->attr_file" form="deniedform" />
</td>
</tr>
EXO;
                                break;
                        }
                    }
                    
                }
                return [
                    'code' => 0,
                    'message' => config('message.')[0],
                    'data' => $html,
                    'goods_number' => $goods_number,
                ];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }
    }
    
    /**
     * 恢复商品数据
     *
     * @param Request $request
     * @param GoodsModel $goods
     *
     * @return array
     */
    public function recover(Request $request, GoodsModel $goods, Store $store)
    {
        if ($request::isPost()) {
            Db::startTrans();
            try {
                $param = $request::post();
                $data = $goods::onlyTrashed()
                    ->where([['goods_id', 'in', $param['id']]])
                    ->field('goods_id')
                    ->select();
                if (count($data) > 0) {
                    foreach ($data as $item) {
                        $item->restore();
                    }
                }
                $store->countGoodsNum($param['id']);
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                Db::rollback();
                dump($e->getMessage());
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }
    }
    
    /**
     * 永久删除商品
     *
     * @param Request $request
     * @param Store $store
     * @param GoodsModel $goods
     *
     * @return array
     */
    public function foreverDestroy(Request $request, Store $store, GoodsModel $goods)
    {
        if ($request::isPost()) {
            try {
                Db::startTrans();
                $param = $request::post();
                Db::name('goods')
                    ->where([['goods_id', 'in', $param['id']]])
                    ->update(['forever_del_status' => 1]);
                $store->countGoodsNum($param['id']);
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }
    }
    
    /**
     * 删除商品
     *
     * @param Request $request
     *
     * @param Store $store
     * @param GoodsModel $goods
     *
     * @return array
     */
    public function destroy(Request $request, Store $store, GoodsModel $goods)
    {
        if ($request::isPost()) {
            try {
                $param = $request::post();
                $data = $goods->where(
                    [
                        ['goods_id', 'in', $param['id']],
                        ['is_group', 'eq', 0],
                        ['is_bargain', 'eq', 0],
                        ['is_limit', 'eq', 0],
                    ]
                )->select();
                if (count($data) == 0) {
                    return ['code' => -100, 'message' => '商品处于活动中，请先删除活动商品'];//活动中商品
                } else {
                    $result = 0;
                    foreach ($data as $k => $v) {
                        if ($v == '') {
                            $result = 1;
                        }
                    }
                    if ($result == 1) {
                        return ['code' => -100, 'message' => '商品处于活动中，请先删除活动商品'];
                    }
                }
                GoodsModel::destroy($param['id']);
                $store->countGoodsNum($param['id']);
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => config('message.')['-1']];
            }
        }
    }
    
    /**
     * 规格库存展示
     *
     * @param Request $request
     * @param Products $products
     *
     * @return mixed
     * @throws \Exception
     */
    public function productShow(Request $request, Products $products)
    {
        try {
            $param = $request::get();
            $data = $products
                ->where(['goods_id' => $param['id']])
                ->field('products_id,goods_attr,attr_shop_price,attr_warn_number,attr_goods_number,attr_goods_sn')
                ->order(['products_id' => 'asc'])
                ->select();
            return $this->fetch(
                'productShow', [
                    'data' => $data,
                ]
            );
        } catch (\Exception $e) {
            throw new \Exception(config('message.')['-1']);
        }
    }
    
    /**
     * 单个修改商品的属性库存stock/价格price
     *
     * @param Request $request
     * @param Products $products
     * @param GoodsModel $goods
     *
     * @return bool
     */
    public function editAttr(Request $request, Products $products, GoodsModel $goods)
    {
        try {
            $param = $request::post();
            Db::startTrans();
            switch ($param['type']) {
                case 'stock': //库存
                    $field = 'attr_goods_number';
                    // 查询当前属性信息
                    $info = $products
                        ->where([['attr_goods_sn', '=', $param['goods_id']]])
                        ->field('products_id,goods_id,attr_goods_number')
                        ->find();
                    $diff = $param['attr_value'] - $info['attr_goods_number'];
                    $goods->allowField(true)
                        ->isUpdate(true)
                        ->save(
                            [
                                'goods_id' => $info['goods_id'],
                                'goods_number' => Db::raw('goods_number + ' . $diff),
                            ]
                        );
                    break;
                case 'price': //价格
                    $field = 'attr_shop_price';
                    break;
                case 'warm': //库存预警
                    $field = 'attr_warn_number';
                    break;
                default: //编辑商品
                    $field = '';
                    break;
            }
            $products->where('attr_goods_sn', $param['goods_id'])->update(["$field" => $param['attr_value']]);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            return false;
        }
        
    }
    
    /**
     * 分销设置
     *
     * @param Request $request
     * @param DistributionLevel $distributionLevel
     * @param GoodsModel $goodsModel
     *
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function distribution(Request $request, DistributionLevel $distributionLevel, GoodsModel $goodsModel)
    {
        $ratio = Env::get('RATIO_SET');
        
        if ($request::isPost()) {
            $param = $request::post();
            // 是否参加分销
            if (!isset($param['is_distribution'])) {
                $param['is_distribution'] = 0;
            }
            // 是否参加分销商
            if (!isset($param['is_distributor'])) {
                $param['is_distributor'] = 0;
            }
            // 分类总数
            $val = [];
            // 分销设置
            if (isset($param['distribution_set'])) {
                // 价格
                $price = $goodsModel->where('goods_id', $param['goods_id'])->value('shop_price') / 2;
                foreach ($param['distribution_set'] as $_param) {
                    if (empty($val)) {
                        $val = $_param;
                    } else {
                        array_walk(
                            $val, function (&$v, $k) use ($_param) {
                            $v += $_param[$k];
                        }
                        );
                    }
                }
                
                foreach ($val as $_val) {
                    // 如果是元 否则是百分比
                    if (empty($param['rebates_type'])) {
                        //                        if ($_val > $price) {
                        //                            return ['code' => -1, 'message' => '您设置百分比的值不能大于商品半价'];
                        //                        }
                    } else {
                        if ($_val > $ratio) {
                            return ['code' => -1, 'message' => "您设置百分比的值不能大于$ratio%"];
                        }
                    }
                }
            }
            $param['distribution_set'] = array_filter($val) ? serialize($param['distribution_set']) : null;
            $goodsModel->allowField(true)->isUpdate(true)->save($param);
            return ['code' => 1, 'message' => config('message.')[0]];
        }
        $item = $goodsModel
            ->where('goods_id', $request::get('goods_id'))
            ->field('is_distribution,is_distributor,rebates_type,distribution_set,is_group,is_bargain,is_limit')
            ->find();
        $item['distribution_set'] = $item['distribution_set'] ? unserialize($item['distribution_set']) : '';
        return $this->fetch(
            '', [
                'item' => $item,
                'distribution_level' => $distributionLevel
                    ->field('distribution_level_id,level_title')
                    ->order('level_weight', 'asc')
                    ->select(),
                'ratio' => $ratio,
            ]
        );
    }
    
    
    /**
     * 批量分销设置
     *
     * @param Request $request
     * @param DistributionLevel $distributionLevel
     * @param GoodsModel $goodsModel
     *
     * @return array|mixed
     * @throws \Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function distributionAll(Request $request, DistributionLevel $distributionLevel, GoodsModel $goodsModel)
    {
        $ratio = Env::get('RATIO_SET');
        if ($request::isPost()) {
            $param = $request::post();
            if (!isset($param['is_distribution'])) {
                $param['is_distribution'] = 0;
            }
            if (!isset($param['is_distributor'])) {
                $param['is_distributor'] = 0;
            }
            // 分类总数
            $val = [];
            if ($param['distribution_set']) {
                if ($param['distribution_set']) {
                    foreach ($param['distribution_set'] as $_param) {
                        if (empty($val)) {
                            $val = $_param;
                        } else {
                            array_walk(
                                $val, function (&$v, $k) use ($_param) {
                                $v += $_param[$k];
                            }
                            );
                        }
                    }
                    foreach ($val as $_val) {
                        if ($_val > $ratio) {
                            return ['code' => -1, 'message' => "您设置百分比的值不能大于$ratio%"];
                        }
                    }
                }
            }
            $param['distribution_set'] = array_filter($val) ? serialize($param['distribution_set']) : null;
            $arr = [];
            foreach (explode(',', rtrim($param['goods_id'])) as $key => $value) {
                $arr[$key]['goods_id'] = $value;
                $arr[$key]['is_distribution'] = $param['is_distribution'];
                $arr[$key]['is_distributor'] = $param['is_distributor'];
                $arr[$key]['rebates_type'] = $param['rebates_type'];
                $arr[$key]['distribution_set'] = $param['distribution_set'];
            }
            $goodsModel->allowField(true)->isUpdate(true)->saveAll($arr);
            return ['code' => 1, 'message' => config('message.')[0]];
        }
        return $this->fetch(
            '', [
                'distribution_level' => $distributionLevel
                    ->field('distribution_level_id,level_title')
                    ->order('level_weight', 'desc')
                    ->select(),
                'ratio' => $ratio,
            ]
        );
        
    }
    
    /**
     * 店铺推荐
     * @param Request $request
     * @param GoodsModel $goodsModel
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function recommend(Request $request, GoodsModel $goodsModel)
    {
        $param = $request::get();
        
        if ($request::isPost()) {
            $param = $request::post();
            try {
                $goodsModel->isUpdate(true)->allowField(true)->save($param);
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/goods/index'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        
        $data = $goodsModel
            ->alias('a')
            ->join('store store', 'store.store_id = a.store_id')
            ->where('a.goods_id', $param['goods_id'])
            ->field('shop,a.goods_id,recomme_file,pc_recomme_file,store_banner,store_particularly_recommend,a.file,store_recommend,is_popularity,is_preference,is_vip')
            ->find();
        $data['recomme_file_data'] = $data['recomme_file_extra'];
        $data['pc_recomme_file_data'] = $data['pc_recomme_file_extra'];
        
        return $this->fetch('', [
            'item' => $data,
            'pc_config' => config('user.pc')['is_include'],
        ]);
    }
    
    /**
     * 活动设置-限时抢购
     * @param Request $request
     * @param Limit $limit
     * @param LimitInterval $limitInterval
     * @param GoodsModel $goods
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function activity_limit(Request $request, Limit $limit, LimitInterval $limitInterval, GoodsModel $goods)
    {
        $param = $request::get();
        
        if ($request::isPost()) {
            Db::startTrans();
            try {
                // 获取数据
                $param = $request::post();
                $param['goods_number'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('goods_number');
                $param['shop_price'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('shop_price');
                // 验证
                if ($param['limit_id'] != '') {
                    $check = $limit->valid($param, 'client_edit');
                    if ($check['code']) return $check;
                    // 更新
                    $limit->allowField(true)->isUpdate(true)->save($param);
                } else {
                    $check = $limit->valid($param, 'create');
                    if ($check['code']) return $check;
                    // 写入
                    $limit->allowField(true)->save($param);
                    $param['limit_id'] = $limit->limit_id;
                }
                
                if (array_key_exists('attr_time_limit_price', $param)) $limit->setSub($param);
                $limit->setGoods($param);
                (new Beanstalk())->put(json_encode(['queue' => 'limitGoodsExpireChangeStatus',
                    'id' => $param['limit_id'], 'time' => date('Y-m-d H:i:s')]),
                    (strtotime($param['down_shelf_time']) - time()));
                
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/goods/index'];
                
            } catch (\Exception $e) {
                
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        
        // 各活动状态
        $activityStatus = $this->checkActivity($param['goods_id'], 'is_limit');
        
        $goods = $goods->where('goods_id', $param['goods_id'])->field('goods_name,shop_price')->find();
        
        $data = $limit->alias('a')
            ->join('goods goods', 'goods.goods_id = a.goods_id', 'left')
            ->where('a.goods_id', $param['goods_id'])
            ->field('a.*,time_limit_price')
            ->find();
        
        return $this->fetch('', [
            'classify_list' => $limitInterval::all(),
            'item' => $data,
            'goods_id' => $param['goods_id'],
            'goods' => $goods,
            'activity_status' => $activityStatus,
        ]);
    }
    
    /**
     * 活动设置-限时抢购拼团
     * @param Request $request
     * @param GoodsModel $goods
     * @param GroupGoods $groupGoods
     * @param GroupClassify $groupClassify
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function activity_group(Request $request, GoodsModel $goods, GroupGoods $groupGoods, GroupClassify $groupClassify)
    {
        $param = $request::get();
        
        if ($request::isPost()) {
            Db::startTrans();
            try {
                // 获取数据
                $param = $request::post();
                $param['goods_number'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('goods_number');
                $param['shop_price'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('shop_price');
                // 验证
                if ($param['group_goods_id'] != '') {
                    $check = $groupGoods->valid($param, 'edit');
                    if ($check['code']) return $check;
                    // 写入
                    $groupGoods->isUpdate(true)->allowField(true)->save($param);
                } else {
                    $check = $groupGoods->valid($param, 'create');
                    if ($check['code']) return $check;
                    // 写入
                    $groupGoods->allowField(true)->save($param);
                    $param['group_goods_id'] = $groupGoods->group_goods_id;
                }
                
                if (array_key_exists('attr_group_price', $param)) {
                    foreach ($param['products_id'] as $key => $value) {
                        $reg = '/(^[1-9](\d+)?(\.\d{1,2})?$)|(^\d\.\d{1,2}$)/';
                        if (!preg_match($reg, $param['attr_group_price'][$key])) return ['code' => -100, 'message' => '属性拼团价为正整数或保留两位小数'];
                        if ((new Products())->where('products_id', $value)->value('attr_shop_price') < $param['attr_group_price'][$key]) return ['code' => -100, 'message' => '属性拼团价小于属性原价'];
                    }
                    $groupGoods->setSub($param);
                }
                $groupGoods->setGoods($param);
                (new Beanstalk())->put(json_encode(['queue' => 'groupGoodsExpireChangeStatus',
                    'id' => $param['group_goods_id'], 'time' => date('Y-m-d H:i:s')]),
                    (strtotime($param['down_shelf_time']) - time()));
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/goods/index'];
                
            } catch (\Exception $e) {
                
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        
        // 各活动状态
        $activityStatus = $this->checkActivity($param['goods_id'], 'is_group');
        
        $goods = $goods->where('goods_id', $param['goods_id'])->field('goods_name,shop_price')->find();
        
        $data = $groupGoods->alias('a')
            ->join('goods goods', 'goods.goods_id = a.goods_id', 'left')
            ->where('a.goods_id', $param['goods_id'])
            ->field('a.*,group_price')
            ->find();
        
        return $this->fetch('', [
            'classify_list' => find_level($groupClassify->field('group_classify_id,title,parent_id')->select()->toArray(), 'group_classify_id'),
            'item' => $data,
            'goods_id' => $param['goods_id'],
            'goods' => $goods,
            'activity_status' => $activityStatus,
        ]);
    }
    
    /**
     * 活动设置-砍价
     * @param Request $request
     * @param GoodsModel $goods
     * @param CutGoods $cutGoods
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function activity_cut(Request $request, GoodsModel $goods, CutGoods $cutGoods)
    {
        $param = $request::get();
        
        if ($request::isPost()) {
            Db::startTrans();
            try {
                // 获取数据
                $param = $request::post();
                $param['goods_number'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('goods_number');
                $param['shop_price'] = GoodsModel::where([['goods_id', '=', $param['goods_id']]])->value('shop_price');
                
                // 验证
                if ($param['cut_goods_id'] != '') {
                    $check = $cutGoods->valid($param, 'edit');
                    if ($check['code']) return $check;
                    // 写入
                    $cutGoods->isUpdate(true)->allowField(true)->save($param);
                } else {
                    $check = $cutGoods->valid($param, 'create');
                    if ($check['code']) return $check;
                    // 写入
                    $cutGoods->allowField(true)->save($param);
                    $param['cut_goods_id'] = $cutGoods->cut_goods_id;
                }
                if (array_key_exists('attr_cut_price', $param)) {
                    foreach ($param['products_id'] as $key => $value) {
                        $reg = '/(^[1-9](\d+)?(\.\d{1,2})?$)|(^\d\.\d{1,2}$)/';
                        if (!preg_match($reg, $param['attr_cut_price'][$key])) return ['code' => -100, 'message' => '属性砍价低价为正整数或保留两位小数'];
                        if (!preg_match($reg, $param['attr_single_cut_min'][$key])) return ['code' => -100, 'message' => '属性砍价单刀最低值为正整数或保留两位小数'];
                        if (!preg_match($reg, $param['attr_single_cut_max'][$key])) return ['code' => -100, 'message' => '属性砍价单刀最高值为正整数或保留两位小数'];
                        
                        if ((new Products())->where('products_id', $value)->value('attr_shop_price') < $param['attr_cut_price'][$key]) return ['code' => -100, 'message' => '属性砍价底价小于属性原价'];
                        if ($param['attr_single_cut_min'][$key] > $param['cut_price']) return ['code' => -100, 'message' => '属性砍价单刀最低值小于等于砍价最低值'];
                        if ($param['attr_single_cut_min'][$key] > $param['attr_single_cut_max'][$key]) return ['code' => -100, 'message' => '属性砍价单刀最低值小于属性砍价单刀最高值'];
                    }
                    
                    $cutGoods->setSub($param);
                }
                $cutGoods->setGoods($param);
                $time = strtotime($param['down_shelf_time']) - time();
                (new Beanstalk())->put(json_encode([
                    'queue' => 'cutGoodsExpireChangeStatus',
                    'id' => $param['cut_goods_id'],
                    'time' => date('Y-m-d H:i:s'),
                ]), $time > 0 ? $time : 5);
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/client/goods/index'];
                
            } catch (\Exception $e) {
                
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        
        // 各活动状态
        $activityStatus = $this->checkActivity($param['goods_id'], 'is_bargain');
        
        $goods = $goods->where('goods_id', $param['goods_id'])->field('goods_name,shop_price')->find();
        
        $data = $cutGoods->alias('a')
            ->join('goods goods', 'goods.goods_id = a.goods_id', 'left')
            ->where('a.goods_id', $param['goods_id'])
            ->field('a.*,cut_price')
            ->find();
        
        return $this->fetch('', [
            'item' => $data,
            'goods_id' => $param['goods_id'],
            'goods' => $goods,
            'activity_status' => $activityStatus,
        ]);
    }
    
    /**
     * 检验其他活动状态
     * @param $goods_id
     * @param $type
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function checkActivity($goods_id, $type)
    {
        $activity = Db::name('goods')
            ->where([
                ['goods_id', 'eq', $goods_id],
            ])
            ->field('goods_id,is_group,is_bargain,is_limit')
            ->find();
        
        if (!empty($activity) && $activity['is_group'] == 1) return ['status' => 1, 'message' => '商品正处于拼团活动中，活动结束才可进行新活动设置'];
        if (!empty($activity) && $activity['is_bargain'] == 1) return ['status' => 1, 'message' => '商品正处于砍价活动中，活动结束才可进行新活动设置'];
        if (!empty($activity) && $activity['is_limit'] == 1) return ['status' => 1, 'message' => '商品正处于限时抢购活动中，活动结束才可进行新活动设置'];
        return ['status' => 0, 'message' => ''];
    }
    
    /**
     * 活动设置-积分商城
     * @param Request $request
     * @param IntegralModel $integral
     * @param IntegralClassifyModel $integralClassify
     * @param GoodsModel $goods
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function activity_integral(Request $request, IntegralModel $integral, IntegralClassifyModel $integralClassify, GoodsModel $goods)
    {
        $param = $request::get();
        
        if ($request::isPost()) {
            
            try {
                // 获取参数
                $param = $request::post();
                
                // 验证
                $check = $integral->valid($param, 'create');
                if ($check['code']) return $check;
                
                //处理多图
                if (array_key_exists('picArr', $param) && $param['picArr'])
                    $param['multiple_file'] = implode(',', $param['picArr']);
                // 写入
                $operation = $integral->allowField(true)->save($param);
                
                if ($operation) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/goods/index'];
                
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        
        $goods = $goods->where('goods_id', $param['goods_id'])->field('goods_name,shop_price,file')->find();
        $goods['file_data'] = $goods->getData('file');
        
        return $this->fetch('', [
            'classify_list' => $integralClassify->where('status', 1)->field('integral_classify_id,parent_id,title,sort')->select(),
            'goods_id' => $param['goods_id'],
            'goods' => $goods,
        ]);
    }
    
    
    /**
     * 查看评论
     * @param Request $request
     * @param GoodsEvaluate $goodsEvaluate
     * @return array|mixed
     */
    public function evaluate(Request $request, GoodsEvaluate $goodsEvaluate)
    {
        $param = $request::get();
        
        try {
            $condition = [];
            if (array_key_exists('star_num', $param) && $param['star_num'] != -1) $condition[] = ['star_num', 'eq', $param['star_num']];
            // 店铺评价星数 0=>未选择
            if (array_key_exists('store_star_num', $param) && $param['store_star_num'] != -1) $condition[] = ['store_star_num', 'eq', $param['store_star_num']];
            // 物流评价星数 0=>未选择
            if (array_key_exists('express_star_num', $param) && $param['express_star_num'] != -1) $condition[] = ['express_star_num', 'eq', $param['express_star_num']];
            
            if (array_key_exists('date', $param) && $param['date']) {
                list($begin, $end) = explode(' - ', $param['date']);
                $end = $end . ' 23:59:59';
                array_push($condition, ['a.create_time', 'between time', [$begin, $end]]);
            }
            
            $data = $goodsEvaluate->alias('a')
                ->join('member member', 'a.member_id = member.member_id')
                ->where($condition)
                ->where('goods_id', $param['goods_id'])
                ->field('a.*,nickname,avatar')
                ->paginate(15, false, ['query' => $param]);
            
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
        
        return $this->fetch('', [
            'data' => $data,
            'goods_id' => $param['goods_id'],
        ]);
    }
    
    /**
     * 查看收藏
     * @param Request $request
     * @param CollectGoods $collectGoods
     * @return array|mixed
     */
    public function collect(Request $request, CollectGoods $collectGoods)
    {
        try {
            $data = $collectGoods->alias('a')
                ->join('member member', 'a.member_id = member.member_id')
                ->where('goods_id', $request::get('goods_id'))
                ->field('a.*,nickname,avatar')
                ->paginate(15, false);
            
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
        
        return $this->fetch('', [
            'data' => $data,
        ]);
    }
    
    /**
     * 删除商品列表
     */
    public function delete()
    {
        $_POST = $this->request->post();
        $_valid = new GoodsValid();
        
        if (!$_valid->scene('goods_id_list')->check($_POST)) {
            return [
                'code' => -100,
                'message' => $_valid->getError(),
            ];
        }
        
        // 保存到数据库的信息
        $_saveData = [
            // 商品
            'GOODS' => [
                // 默认下架
                'is_putaway' => 0,
                // 砍价
                'is_bargain' => 0,
                'cut_price' => 0,
                // 拼团
                'is_group' => 0,
                'group_price' => 0,
                'group_num' => 0,
                // 限时抢购
                'is_limit' => 0,
                'time_limit_price' => 0,
                'time_limit_number' => 0,
            ],
            // 限时抢购
            'LIMIT' => [
                'status' => 0,  // 审核状态
                'reason' => ''   // 审核内容
            ],
            // 拼团
            'GROUP_GOODS' => [
                'status' => 0,  // 审核状态
                'reason' => ''   // 审核内容
            ],
            // 砍价
            'CUT_GOODS' => [
                'status' => 0,  // 审核状态
                'reason' => ''   // 审核内容
            ],
        ];
        
        Db::startTrans();
        try {
            // id列表总数
            $_idListCount = count(explode(',', $_POST['goods_id_list']));
            
            // 查询没有活动的商品
            $_goodsDeleteIdList = GoodsModel::withTrashed()
                ->alias('Goods')
                ->join('GroupGoods GroupGoods', 'Goods.goods_id = GroupGoods.goods_id', 'left')
                ->join('CutGoods CutGoods', 'Goods.goods_id = CutGoods.goods_id', 'left')
                ->join('Limit Limit', 'Goods.goods_id = Limit.goods_id', 'left')
                ->where([
                    ['Goods.goods_id', 'in', $_POST['goods_id_list']],
                ])
                ->group('GroupGoods.status <> 1 AND CutGoods.status <> 1 AND Limit.status <> 1')
                ->column('Goods.goods_id');
            
            // 符合删除要求的商品数量
            $_idResultCount = count($_goodsDeleteIdList);
            
            // 判断查询结果是否有数据
            if ($_idResultCount <= 0) {
                // 如果一条数据都没有
                Db::rollback();
                // 直接返回无法删除
                return [
                    'code' => 0,
                    'message' => "{$_idListCount}个商品操作失败,因为其不符合操作条件",
                ];
            }
            
            // 符合条件的需要删除的商品Id列表
            $_goodsDeleteIdList = join(',', $_goodsDeleteIdList);
            
            // 修改 活动的审核原因
            $_saveData['LIMIT']['reason'] = $_saveData['GROUP_GOODS']['reason'] = $_saveData['CUT_GOODS']['reason'] = '主商品被删除';
            
            // 修改 商品
            GoodsModel::where([
                ['goods_id', '=', $_goodsDeleteIdList],
            ])->update($_saveData['GOODS']);
            
            // 显示抢购 审核未通过
            Limit::where([
                ['goods_id', 'in', $_goodsDeleteIdList],
                ['status', '<>', 0],
            ])->update($_saveData['LIMIT']);
            
            // 修改 拼团 审核未通过
            GroupGoods::where([
                ['goods_id', 'in', $_goodsDeleteIdList],
                ['status', '<>', 0],
            ])->update($_saveData['GROUP_GOODS']);
            
            // 修改 砍价 审核未通过
            CutGoods::where([
                ['goods_id', 'in', $_goodsDeleteIdList],
                ['status', '<>', 0],
            ])->update($_saveData['CUT_GOODS']);
            
            // 软删除商品
            GoodsModel::where([
                ['goods_id', 'in', $_goodsDeleteIdList],
            ])->update([
                'delete_time' => date('Y-m-d H:i:s'),
            ]);
            
            Db::commit();
            
            // 计算 指定删除的数量 和 实际删除的数量 的差
            $_idNumDiff = $_idListCount - $_idResultCount;
            
            // 如果指定的商品全部删除了
            if ($_idNumDiff <= 0) {
                return [
                    'code' => 0,
                    'message' => config('message.')[0],
                ];
            }
            
            // 否则返回其他信息
            return [
                'code' => 0,
                'message' => "成功操作{$_idResultCount}个商品<br>{$_idNumDiff}个商品操作失败,因为其不符合操作条件",
            ];
        } catch (\Exception $e) {
            Db::rollback();
            return [
                'code' => -100,
                //                'message' => config('message.')[-1],
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * 批量商品上架/下架
     * @param Request $request
     * @param GoodsModel $goods
     * @return array
     */
    public function shelves(Request $request)
    {
        if ($request::isPost()) {
            Db::startTrans();
            try {
                // 商品   Goods
                // review_status    审核状态 0 未通过 1 已通过 2 待审核
                // review_content   审核内容
                
                // 限时抢购 Limit
                // status   审核状态 0未通过 1已通过 2待审核
                // reason   审核内容
                
                // 拼团   GroupGoods
                // status   审核状态 0未通过 1已通过 2待审核
                // reason   审核内容
                
                // 砍价   CutGoods
                // status   审核状态 0 未通过 1 已通过 2 待审核
                // reason   审核内容
                
                
                // 商品ID列表 eg:1,2,3,4,5
                $_idList = $this->request->post('id', '');
                
                // 上架/下架状态 0 => 下架 1=> 上架 默认下架
                $_type = $this->request->post('type', 0);
                
                // id列表总数
                $_idListCount = count(explode(',', $_idList));
                
                // 返回的信息
                $_resultMessage = config('message.')[0];
                
                // 保存到数据库的信息
                $_saveData = [
                    // 商品
                    'GOODS' => [
                        // 默认下架
                        'is_putaway' => 0,
                        'is_group' => 0,
                        'is_bargain' => 0,
                        'is_limit' => 0,
                    ],
                    // 限时抢购
                    'LIMIT' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                    // 拼团
                    'GROUP_GOODS' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                    // 砍价
                    'CUT_GOODS' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                ];
                
                switch ($_type) {
                    case 0: // 下架
                        $_saveData['LIMIT']['reason'] = $_saveData['GROUP_GOODS']['reason'] = $_saveData['CUT_GOODS']['reason'] = '主商品被下架';
                        
                        // 获取条件中 上架
                        $_goodsList = GoodsModel::withTrashed()->where([
                            ['goods_id', 'in', $_idList],
                            ['is_putaway', '=', 1]  // 上架的商品
                        ])->column('goods_id');
                        
                        // 查询出来的 结果 的总数
                        $_idResultCount = count($_goodsList);
                        
                        // 如果 都不符合
                        if ($_idResultCount <= 0) {
                            Db::rollback();
                            return [
                                'code' => 0,
                                'message' => "{$_idListCount}个商品操作失败,因为其不符合操作条件",
                            ];
                        }
                        
                        // 前台传过来的商品id 数量 和 查询出来的id 数量 差
                        $_idNumDiff = $_idListCount - $_idResultCount;
                        
                        // Id 结果 以 , 分隔
                        $_idResultJoin = join(',', $_goodsList);
                        
                        // 修改 商品
                        GoodsModel::withTrashed()->where([
                            ['goods_id', 'in', $_idResultJoin],
                        ])->update($_saveData['GOODS']);
                        
                        // 修改 显示抢购
                        Limit::where([
                            ['goods_id', 'in', $_idResultJoin],
                            ['status', '<>', 0],
                        ])->update($_saveData['LIMIT']);
                        
                        // 修改 拼团
                        GroupGoods::where([
                            ['goods_id', 'in', $_idResultJoin],
                            ['status', '<>', 0],
                        ])->update($_saveData['GROUP_GOODS']);
                        
                        // 修改 砍价
                        CutGoods::where([
                            ['goods_id', 'in', $_idResultJoin],
                            ['status', '<>', 0],
                        ])->update($_saveData['CUT_GOODS']);
                        
                        // 如果 前台传过来的商品id 数量 和 查询出来的id 数量 差 大于 0
                        if ($_idNumDiff > 0) {
                            $_resultMessage = "成功操作" . count($_goodsList) . "个商品<br>{$_idNumDiff}个商品操作失败,因为其不符合操作条件";
                        }
                        
                        break;
                    default:    // 上架操作
                        // 操作商品
                        $_result = GoodsModel::withTrashed()->where([
                            ['goods_id', 'in', $_idList],
                            ['review_status', '=', 1],    // 审核已通过
                            ['is_putaway', '<>', 1]     // 已下架
                        ])->update([
                            'is_putaway' => 1,
                        ]);
                        
                        if ($_result <= 0) {
                            Db::rollback();
                            return [
                                'code' => 0,
                                'message' => "{$_idListCount}个商品操作失败,因为其不符合操作条件",
                            ];
                        }
                        // 前台传过来的商品id 数量 和 查询出来的id 数量 差
                        $_idNumDiff = $_idListCount - $_result;
                        // 如果 前台传过来的商品id 数量 和 查询出来的id 数量 差 大于 0
                        if ($_idNumDiff > 0) {
                            $_resultMessage = "成功操作{$_result}个商品<br>{$_idNumDiff}个商品操作失败,因为其不符合操作条件";
                        }
                }
                Db::commit();
                return ['code' => 0, 'message' => $_resultMessage];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
    
    /**
     * 上架/下架全部
     *
     * @return array|mixed
     */
    public function putAwayAll()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $_filterWhere = $this->filterWhere();
                
                $_goodsList = $_filterWhere['GOODS_LIST'];
                $_where = $_filterWhere['WHERE'];
                
                // 查询出来的商品Id列表
                $_goodsIdList = $_goodsList
                    ->alias('Goods')// 定义别名
                    ->join('Store Store', 'Store.store_id = Goods.store_id')// 关联店铺
                    ->where($_where)
                    ->field([
                        'Goods.goods_id' => 'goods_id',
                    ])->column('goods_id');
                
                // 商品   Goods
                // review_status    审核状态 0 未通过 1 已通过 2 待审核
                // review_content   审核内容
                
                // 限时抢购 Limit
                // status   审核状态 0未通过 1已通过 2待审核
                // reason   审核内容
                
                // 拼团   GroupGoods
                // status   审核状态 0未通过 1已通过 2待审核
                // reason   审核内容
                
                // 砍价   CutGoods
                // status   审核状态 0 未通过 1 已通过 2 待审核
                // reason   审核内容
                
                // 商品ID列表 eg:1,2,3,4,5
                $_idList = join(',', $_goodsIdList);
                
                // 上架下架状态 1 => 上架 2=> 下架 默认下架
                $_type = $this->request->post('type', 2);
                
                // id列表总数
                $_idListCount = count($_goodsIdList);
                
                // 返回的信息
                $_resultMessage = config('message.')[0];
                
                // 保存到数据库的信息
                $_saveData = [
                    // 商品
                    'GOODS' => [
                        // 默认下架
                        'is_putaway' => 0,
                        'is_group' => 0,
                        'is_bargain' => 0,
                        'is_limit' => 0,
                    ],
                    // 限时抢购
                    'LIMIT' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                    // 拼团
                    'GROUP_GOODS' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                    // 砍价
                    'CUT_GOODS' => [
                        'status' => 0,  // 审核状态
                        'reason' => ''   // 审核内容
                    ],
                ];
                
                
                // 判断审核状态
                switch ($_type) {
                    case 1: // 上架
                        // 操作商品
                        $_result = GoodsModel::withTrashed()
                            ->where([
                                ['goods_id', 'in', $_idList],
                                ['review_status', '=', 1],    // 审核已通过
                                ['is_putaway', '<>', 1]     // 已下架
                            ])->update([
                                'is_putaway' => 1,
                            ]);
                        
                        if ($_result <= 0) {
                            Db::rollback();
                            return [
                                'code' => 0,
                                'message' => "{$_idListCount}个商品操作失败,因为其不符合操作条件",
                            ];
                        }
                        
                        // 前台传过来的商品id 数量 和 查询出来的id 数量 差
                        $_idNumDiff = $_idListCount - $_result;
                        // 如果 前台传过来的商品id 数量 和 查询出来的id 数量 差 大于 0
                        if ($_idNumDiff > 0) {
                            $_resultMessage = "成功操作{$_result}个商品<br>{$_idNumDiff}个商品操作失败,因为其不符合操作条件";
                        }
                        break;
                    case 2: // 下架
                        $_saveData['LIMIT']['reason'] = $_saveData['GROUP_GOODS']['reason'] = $_saveData['CUT_GOODS']['reason'] = '主商品被下架';
                        
                        // 获取条件中 上架
                        $_goodsList = GoodsModel::withTrashed()
                            ->where([
                                ['goods_id', 'in', $_idList],
                                ['is_putaway', '=', 1]  // 上架的商品
                            ])->column('goods_id');
                        
                        // 查询出来的 结果 的总数
                        $_idResultCount = count($_goodsList);
                        
                        // 如果 都不符合
                        if ($_idResultCount <= 0) {
                            Db::rollback();
                            return [
                                'code' => 0,
                                'message' => "{$_idListCount}个商品操作失败,因为其不符合操作条件",
                            ];
                        }
                        
                        // 前台传过来的商品id 数量 和 查询出来的id 数量 差
                        $_idNumDiff = $_idListCount - $_idResultCount;
                        
                        // Id 结果 以 , 分隔
                        $_idResultJoin = join(',', $_goodsList);
                        
                        // 修改 商品
                        GoodsModel::withTrashed()
                            ->where([
                                ['goods_id', 'in', $_idResultJoin],
                            ])->update($_saveData['GOODS']);
                        
                        // 修改 显示抢购
                        Limit::where([
                            ['goods_id', 'in', $_idResultJoin],
                            ['status', '<>', 0],
                        ])->update($_saveData['LIMIT']);
                        
                        // 修改 拼团
                        GroupGoods::where([
                            ['goods_id', 'in', $_idResultJoin],
                            ['status', '<>', 0],
                        ])->update($_saveData['GROUP_GOODS']);
                        
                        // 修改 砍价
                        CutGoods::where([
                            ['goods_id', 'in', $_idResultJoin],
                            ['status', '<>', 0],
                        ])->update($_saveData['CUT_GOODS']);
                        
                        // 如果 前台传过来的商品id 数量 和 查询出来的id 数量 差 大于 0
                        if ($_idNumDiff > 0) {
                            $_resultMessage = "成功操作" . count($_goodsList) . "个商品<br>{$_idNumDiff}个商品操作失败,因为其不符合操作条件";
                        }
                        break;
                }
                
                
                Db::commit();
                return ['code' => 0, 'message' => $_resultMessage];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        
        return $this->fetch();
    }
    
    
    /**
     * 删除全部
     *
     * @return array|mixed
     */
    public function deleteAll()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                
                $_filterWhere = $this->filterWhere();
                
                $_goodsList = $_filterWhere['GOODS_LIST'];
                $_where = $_filterWhere['WHERE'];
                
                $_goodsIdList = $_goodsList
                    ->alias('Goods')// 定义别名
                    ->join('Store Store', 'Store.store_id = Goods.store_id')// 关联店铺
                    ->where($_where);
                
                // 删除类型
                $_type = $this->request->post('type');
                
                
                if ($_type == 1) {
                    // 删除
                    $_goodsIdList->update([
                        'Goods.delete_time' => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    // 恢复
                    $_goodsIdList->update([
                        'Goods.delete_time' => null,
                    ]);
                }
                
                // 更新商品信息
                $_saveDataList = [];
                foreach ($_goodsIdList as $key => $val) {
                    $_saveDataList[$key] = [
                        'goods_id' => $val,
                        'manage_id' => Session::get('manage_id'),
                        'nickname' => Session::get('manageName'),
                    ];
                    // $_type: 1删除 2恢复
                    // type:    5删除 6恢复
                    $_saveDataList[$key]['type'] = $_type == 1 ? 5 : 6;
                }
                
                (new GoodsOperation())->saveAll($_saveDataList);
                
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
        
        return $this->fetch();
    }
    
    /**
     * 筛选条件
     *
     * 用途
     * 商品搜索
     * 上架全部
     * 下架全部
     * 删除全部
     */
    private function filterWhere()
    {
        // 商品状态 0=>全部 1=>上架商品 2=>下架商品 3=>待审核商品 4=>库存预警商品 5=>商品回收站 6=>审核未通过商品
        $_goodsStatus = (int)$this->request->get('status', 0);
        
        // 商品分类 0=>未选择
        $_goodsClassify = (int)$this->request->get('classify', 0);
        
        // 商品名称
        $_goodsName = (string)$this->request->get('goods_name', '');
        
        // 商品品牌 0=>未选择
        $_goodsBrand = (int)$this->request->get('brand', 0);
        
        // 推荐状态 0=>全部 1=>人气好物推荐 2=>特价优惠推荐 3=>特别推荐 4=>普通推荐 5=>轮播推荐
        $_goodsRecommend = (int)$this->request->get('recommend', 0);
        
        // 活动状态 0=>全部 1=>限时抢购 2=>拼团 3=>砍价
        $_goodsActivity = (int)$this->request->get('activity', 0);
        
        // 分销状态 0=>全部 1=>可分销商品 2=>购买成为分销商
        $_goodsDistribution = (int)$this->request->get('distribution', 0);
        //            $_where
        
        //            0.    控制店铺ID
        //            1.    控制是否上下架
        //            2.    控制是否过审
        //            3.    控制库存预警
        
        //            4.    控制商品分类
        //            5.    控制店铺名称
        //            6.    控制商品名称
        //            7.    控制商品品牌
        //            8.    控制推荐状态
        //            9.    控制活动状态
        //            10.   控制分销状态
        //            11.   店铺ID
        //            12.   永久删除
        $_where = [];
        $_pageConfig = [];
        
        
        // 店铺
        $_where[0] = [
            'Goods.store_id',
            '=',
            Session::get('client_store_id'),
        ];
        
        // 初始化商品模型
        $_goodsList = new GoodsModel;
        
        // 商品状态
        if ($_goodsStatus != 0) {
            $_pageConfig['status'] = $_goodsStatus;
            switch ($_goodsStatus) {
                case 1: // 上架商品
                    // 上架的商品
                    $_where[1] = [
                        'Goods.is_putaway',
                        '=',
                        1,
                    ];
                    $_where[2] = [
                        'Goods.review_status',
                        '=',
                        1,
                    ];
                    break;
                case 2: // 下架商品
                    $_where[1] = [
                        'Goods.is_putaway',
                        '=',
                        0,
                    ];
                    break;
                case 3: // 待审核商品
                    // 待审核状态的商品
                    $_where[2] = [
                        'Goods.review_status',
                        '=',
                        2,
                    ];
                    break;
                case 4: // 库存预警商品
                    // 库存数量少于库存的商品
                    $_where[3] = [
                        'Goods.goods_number',
                        '<',
                        Db::raw('`Goods`.`warn_number`'),
                    ];
                    break;
                case 5: // 商品回收站
                    // 只查询被软删除的商品
                    $_goodsList = GoodsModel::onlyTrashed();
                    break;
                case 6: // 审核未通过商品
                    $_where[2] = [
                        'Goods.review_status',
                        '=',
                        0,
                    ];
                    break;
            }
        }
        
        
        // 商品分类
        if ($_goodsClassify != 0) {
            $_pageConfig['classify'] = $_goodsClassify;
            $_where[4] = [
                'Goods.goods_classify_id',
                '=',
                $_goodsClassify,
            ];
        }
        
        // 商品名称
        if ($_goodsName != '') {
            $_pageConfig['goods_name'] = $_goodsName;
            $_where[6] = [
                'Goods.goods_name',
                'LIKE',
                "%{$_goodsName}%",
            ];
        }
        
        // 商品品牌
        if ($_goodsBrand != 0) {
            $_pageConfig['brand'] = $_goodsBrand;
            $_where[7] = [
                'Goods.brand_id',
                '=',
                $_goodsBrand,
            ];
        }
        
        // 推荐状态
        if ($_goodsRecommend != 0) {
            $_pageConfig['recommend'] = $_goodsRecommend;
            switch ($_goodsRecommend) {
                case 1: // 人气好物推荐
                    $_where[8] = [
                        'Goods.is_popularity',
                        '=',
                        1,
                    ];
                    break;
                case 2: // 特价优惠推荐
                    $_where[8] = [
                        'Goods.is_preference',
                        '=',
                        1,
                    ];
                    break;
                case 3: // 特别推荐
                    $_where[8] = [
                        'Goods.store_particularly_recommend',
                        '=',
                        1,
                    ];
                    break;
                case 4: // 普通推荐
                    $_where[8] = [
                        'Goods.store_recommend',
                        '=',
                        1,
                    ];
                    break;
                case 5: // 轮播推荐
                    $_where[8] = [
                        'Goods.store_poster',
                        '=',
                        1,
                    ];
                    break;
            }
        }
        
        // 活动状态
        if ($_goodsActivity != 0) {
            $_pageConfig['activity'] = $_goodsActivity;
            switch ($_goodsActivity) {
                case 1: // 限时抢购
                    $_where[9] = [
                        'Goods.is_limit',
                        '=',
                        1,
                    ];
                    break;
                case 2: // 拼团
                    $_where[9] = [
                        'Goods.is_group',
                        '=',
                        1,
                    ];
                    break;
                case 3: // 砍价
                    $_where[9] = [
                        'Goods.is_bargain',
                        '=',
                        1,
                    ];
                    break;
            }
        }
        
        // 分销状态
        if ($_goodsDistribution != 0) {
            $_pageConfig['distribution'] = $_goodsDistribution;
            switch ($_goodsDistribution) {
                case 1: // 可分销商品
                    $_where[10] = [
                        'Goods.is_distribution',
                        '=',
                        1,
                    ];
                    $_where[11] = [
                        'Goods.is_group',
                        '=',
                        0,
                    ];
                    $_where[12] = [
                        'Goods.is_bargain',
                        '=',
                        0,
                    ];
                    $_where[13] = [
                        'Goods.is_limit',
                        '=',
                        0,
                    ];
                    break;
                case 2: // 购买成为分销商
                    $_where[10] = [
                        'Goods.is_distributor',
                        '=',
                        1,
                    ];
                    break;
            }
        }
        $_where[12] = ['Goods.forever_del_status', '=', 0];
        
        return [
            'GOODS_LIST' => $_goodsList,
            'WHERE' => $_where,
            'PAGE_CONFIG' => $_pageConfig,
        ];
        
    }
    
    
    /**
     * 标签未选择商品
     * @param Request $request
     * @param GoodsModel $goods
     * @param GoodsClassifyModel $goodsClassify
     * @return array|mixed
     */
    public function tagGoods(Request $request, GoodsModel $goods, GoodsClassifyModel $goodsClassify)
    {
        try {
            $param = $request::get();
            $where = [
                ['g.goods_id', '>', 0],
                ['g.review_status', '=', 1],
                ['g.is_putaway', '=', 1],
                ['g.is_group', '=', 0],
                ['g.is_bargain', '=', 0],
                ['g.is_limit', '=', 0],
                ['tbg.tag_bind_goods_id', 'exp', Db::raw('is null')],
            ];
            if (array_key_exists('cateId', $param) && $param['cateId']) {
                array_push($where, ['g.goods_classify_id', '=', $param['cateId']]);
            }
            
            if (array_key_exists('keyword', $param) && $param['keyword']) {
                array_push(
                    $where,
                    ['g.goods_sn|g.goods_name|g.spell_keyword|g.describe', 'like', '%' . $param['keyword'] . '%']
                );
            }
            $data = $goods
                ->alias('g')
                ->where($where)
                ->where('g.store_id', Session::get('client_store_id'))
                ->view('store s', 'store_name', 's.store_id= g.store_id', 'left')
                ->view('brand b', 'brand_name', 'b.brand_id = g.brand_id', 'left')
                ->view('tag_bind_goods tbg', 'goods_id,tag_bind_goods_id', 'tbg.goods_id = g.goods_id and tbg.tag_id = ' . $param['tag_id'] . ' and tbg.delete_time is null', 'left')
                ->field('g.goods_id,tag_bind_goods_id,s.store_id,g.goods_classify_id,file,goods_name,shop_price,goods_sn')
                ->order(['g.update_time' => 'desc', 'g.goods_id' => 'asc'])
                ->paginate($goods->pageLimits, false, ['query' => $param]);
            
            //查询商品一级分类
            $categoryOne = $goodsClassify
                ->where([
                    ['parent_id', '=', 0],
                    ['status', '=', 1],
                ])
                ->field('goods_classify_id,parent_id,title,count')
                ->order(['sort' => 'asc', 'update_time' => 'desc'])->select();
            
            
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => config('message.')['-1']];
        }
        return $this->fetch(
            '', [
                'categoryOne' => $categoryOne,
                'data' => $data,
                'tag_id' => $param['tag_id'],
            ]
        );
    }
    
    /**
     * 标签选中的商品
     * @param Request $request
     * @param GoodsModel $goods
     * @param GoodsClassifyModel $goodsClassify
     * @return array|mixed
     */
    public function tagChooseGoods(Request $request, GoodsModel $goods, GoodsClassifyModel $goodsClassify)
    {
        try {
            $param = $request::get();
            $where = [
                ['g.goods_id', '>', 0],
                ['g.review_status', '=', 1],
                ['g.is_putaway', '=', 1],
                ['g.is_group', '=', 0],
                ['g.is_bargain', '=', 0],
                ['g.is_limit', '=', 0],
                ['tbg.delete_time', 'exp', Db::raw('is null')],
            ];
            if (array_key_exists('cateId', $param) && $param['cateId']) {
                array_push($where, ['g.goods_classify_id', '=', $param['cateId']]);
            }
            
            if (array_key_exists('keyword', $param) && $param['keyword']) {
                array_push(
                    $where,
                    ['g.goods_sn|g.goods_name|g.spell_keyword|g.describe', 'like', '%' . $param['keyword'] . '%']
                );
            }
            $data = $goods
                ->alias('g')
                ->where($where)
                ->where('g.store_id', Session::get('client_store_id'))
                ->view('store s', 'store_name', 's.store_id= g.store_id', 'left')
                ->view('brand b', 'brand_name', 'b.brand_id = g.brand_id', 'left')
                ->view('tag_bind_goods tbg', 'goods_id,tag_bind_goods_id', 'tbg.goods_id = g.goods_id and tbg.tag_id = ' . $param['tag_id'])
                ->field('g.goods_id,tag_bind_goods_id,s.store_id,g.goods_classify_id,file,goods_name,shop_price,goods_sn')
                ->order(['g.update_time' => 'desc', 'g.goods_id' => 'asc'])
                ->paginate($goods->pageLimits, false, ['query' => $param]);
            //查询商品一级分类
            $categoryOne = $goodsClassify
                ->where([
                    ['parent_id', '=', 0],
                    ['status', '=', 1],
                ])
                ->field('goods_classify_id,parent_id,title,count')
                ->order(['sort' => 'asc', 'update_time' => 'desc'])->select();
            
            
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => config('message.')['-1']];
        }
        return $this->fetch(
            '', [
                'categoryOne' => $categoryOne,
                'data' => $data,
                'tag_id' => $param['tag_id'],
            ]
        );
    }
    
    
    /**
     * 标签设置
     * @param Request $request
     * @param Tag $tag
     * @param TagClassify $tagClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function setTag(Request $request, Tag $tag, TagClassify $tagClassify)
    {
        $param = $request::param();
        $where = [];
        if (array_key_exists('classify', $param) && $param['classify']) {
            array_push($where, ['tag.tag_classify_id', '=', $param['classify']]);
        }
        
        if (array_key_exists('keyword', $param) && $param['keyword']) {
            array_push(
                $where,
                ['tag.name', 'like', '%' . $param['keyword'] . '%']
            );
        }
        
        $data = $tag->alias('tag')
            ->join('TagClassify tag_classify', 'tag.tag_classify_id = tag_classify.tag_classify_id')
            ->join('TagBindGoods tag_bind_goods', 'tag.tag_id = tag_bind_goods.tag_id and tag_bind_goods.goods_id =' . $param['goods_id'] . ' and tag_bind_goods.delete_time is null', 'left')
            ->where($where)
            ->field('tag.tag_id,tag_bind_goods.goods_id,tag_bind_goods.tag_bind_goods_id,tag.name,tag_classify.name as tag_classify_name,tag.content,tag_bind_goods.is_show, if(IFNULL(tag_bind_goods.goods_id,0)=0,2,1) as choose')
            ->order(['choose' => 'asc', 'tag_bind_goods.create_time' => 'desc'])
            ->select();
        
        $tagClassifyData = $tagClassify->field('tag_classify_id,name')->select();
        
        return $this->fetch(
            '', [
                'tag_classify_data' => $tagClassifyData,
                'data' => $data,
            ]
        );
    }
    
    
    /**
     * 商品列表展示
     * @param Request $request
     * @param TagBindGoods $tagBindGoods
     * @return array
     */
    public function is_show(Request $request, TagBindGoods $tagBindGoods)
    {
        
        if ($request::isPost()) {
            try {
                if ($request::post('type') == 'true') {
                    $num = $tagBindGoods->where([['goods_id', '=', $request::post('goods_id')], ['is_show', '=', '1']])->count();
                    if ($num >= 3) {
                        return ['code' => -100, 'message' => '最多可以设置3个标签'];
                    }
                }
                $tagBindGoods->changeIsShow($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
                
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
    
    
    /**
     * 选择标签
     * @param TagBindGoods $tagBindGoods
     * @return array
     */
    public function chooseTag(TagBindGoods $tagBindGoods)
    {
        if ($this->request->isPost()) {
            try {
                $param = Request::param();
                
                $save = ['goods_id' => $param['goods_id'], 'store_id' => Session::get('client_store_id'), 'tag_id' => $param['tag_id']];
                
                $tagBindGoods->allowField(true)->save($save);
                return ['code' => 0, 'message' => '成功'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
    
    /**
     * 移除标签
     */
    public function removeTag()
    {
        if ($this->request->isPost()) {
            try {
                $param = Request::param();
                TagBindGoods::where([
                    ['tag_bind_goods_id', '=', $param['tag_bind_goods_id']],
                ])->update([
                    'delete_time' => date('Y-m-d H:i:s'),
                    'is_show' => 2,
                ]);
                
                return ['code' => 0, 'message' => '成功'];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
}