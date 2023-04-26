<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/15 0015
 * Time: 17:12
 */

namespace app\master\controller;

use think\Controller;
use think\Db;
use think\facade\Request;
use app\common\model\ArticleClassify as ArticleClassifyModel;
use app\common\model\Article as ArticleModel;
use app\common\model\GoodsClassify;
use app\common\model\Brand;
use app\common\model\Store;
use app\common\model\Message;
use think\Exception;
use app\common\model\Goods as GoodsModel;
use app\common\model\ArticleAttach as ArticleAttachModel;

/**
 * 文章优惠信息
 *
 * Class Preferential
 * @package app\master\controller
 */
class Preferential extends Controller
{
    /**
     * 文章优惠列表
     * @param Request $request
     * @param ArticleClassifyModel $articleClassifyModel
     * @param ArticleModel $articleModel
     * @return \Exception|mixed
     */
    public function index(Request $request, ArticleClassifyModel $articleClassifyModel, ArticleModel $articleModel)
    {
        try {
            
            // 获取参数
            $param = $request::get();
            
            // 条件定义
            $condition[] = ['article_id', '>', 0];
            $condition[] = ['article_classify_id', 'eq', 4];
            
            // 父ID
            if (!empty($param['classify_id'])) $condition[] = ['article_classify_id', '=', $param['classify_id']];
            if (!empty($param['keyword'])) $condition[] = ['title', 'like', '%' . $param['keyword'] . '%'];
            
            $data = $articleModel
                ->where($condition)
                ->field('author,keyword,describe,file,content,update_time', true)
                ->append(['end_time'])
                ->order(['article_id' => 'desc'])
                ->paginate(15, false, ['query' => $param]);
            
        } catch (\Exception $e) {
            
            return ['code' => -100, 'message' => $e->getMessage()];
            
        }
        
        return $this->fetch('', [
            'classify_list' => find_level($articleClassifyModel->where('article_classify_id', 'neq', $request::get('article_classify_id'))->field('article_classify_id,title,parent_id')->select()->toArray(), 'article_classify_id'),
            'data' => $data
        ]);
    }
    
    /**
     * 文章优惠新增
     * @param Request $request
     * @param ArticleClassifyModel $articleClassifyModel
     * @param ArticleModel $articleModel
     * @param GoodsClassify $goodsClassify
     * @param Brand $brand
     * @param Store $store
     * @param Message $message
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(Request $request, ArticleClassifyModel $articleClassifyModel, ArticleModel $articleModel, GoodsClassify $goodsClassify, Brand $brand, Store $store, Message $message)
    {
        
        if ($request::isPost()) {
            
            try {
                
                // 获取参数
                $param = $request::post();
                
                // 验证
                $check = $articleModel->valid($param, 'pref_create');
                if ($check['code']) return $check;
                
                $state = $articleModel->allowField(true)->save($param);
                if ($state) {
                    // 推送消息[只含站内信][优惠文章新增]
                    $pushServer = app('app\\interfaces\\behavior\\Push');
                    $pushServer->send([
                        'tplKey' => 'article',
                        'openId' => '',
                        'subscribe_time' => '',
                        'microId' => '',
                        'phone' => '',
                        'data' => [$articleModel->title??'', $articleModel->describe??''],
                        'inside_data' => [
                            'member_id' => 0,
                            'type' => 2,
                            'attach_id' => $articleModel->article_id,
                            'content' => $articleModel->content??'',
                            'web_content' => $articleModel->web_content??'',
                            'end_time' => $param['end_time'],
                            'status' => $param['status'],
                            'jump_state' => '5',
                            'file' => $param['file'],
                        ],
                        'sms_data' => [],
                    ], 3);
                    return ['code' => 0, 'message' => config('message.')[0], 'url' => '/preferential/index'];
                }
            } catch (\Exception $e) {
                
                return ['code' => -100, 'message' => $e->getMessage()];
                
            }
        }
        
        //查询商品一级分类
        $categoryOne = $goodsClassify
            ->where([
                ['parent_id', '=', 0],
                ['status', '=', 1]
            ])
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
        $storeList = $store
            ->where(['status' => 1, 'shop' => 0])
            ->field('store_id,store_name')
            ->select();
        return $this->fetch('', [
            'classify_list' => find_level($articleClassifyModel->field('article_classify_id,title,parent_id')->select()->toArray(), 'article_classify_id'),
            'categoryOne' => $categoryOne,
            'brand' => $brandData,
            'brandFirstChr' => $brandFirstChr,
            'storeList' => $storeList,
            'pc_config' => config('user.pc')['is_include'],
        ]);
    }
    
    /**
     * 文章优惠分类编辑
     * @param Request $request
     * @param Store $store
     * @param ArticleClassifyModel $articleClassifyModel
     * @param ArticleModel $articleModel
     * @param GoodsClassify $goodsClassify
     * @param Brand $brand
     * @param ArticleAttachModel $articleAttach
     * @param Message $message
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request, Store $store, ArticleClassifyModel $articleClassifyModel, ArticleModel $articleModel, GoodsClassify $goodsClassify, Brand $brand, ArticleAttachModel $articleAttach, Message $message)
    {
        
        if ($request::isPost()) {
            
            try {
                
                // 获取参数
                $param = $request::post();
                
                // 验证
                $check = $articleModel->valid($param, 'pref_edit');
                if ($check['code']) return $check;
                
                $articleModel->allowField(true)->isUpdate(true)->save($param);
                $message_data = [
                    'title' => $param['title'],
                    'describe' => $param['describe'],
                    'content' => $param['content']??'',
                    'web_content' => $param['web_content']??'',
                    'end_time' => $param['end_time'],
                    'status' => $param['status'],
                ];
                if (!empty($param['file'])) $message_data['file'] = $param['file'];
                
                $message->save($message_data, ['attach_id' => $articleModel->article_id]);
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/preferential/index'];
                
            } catch (\Exception $e) {
                
                return ['code' => -100, 'message' => $e->getMessage()];
                
            }
        }
        
        //查询商品一级分类
        $categoryOne = $goodsClassify
            ->where([
                ['parent_id', '=', 0],
                ['status', '=', 1]
            ])
            ->field('goods_classify_id,parent_id,title,count')
            ->order(['sort' => 'asc', 'update_time' => 'desc'])
            ->select();
        //查询全部品牌
        $brandData = $brand
            ->where([['brand_id', '>', 0]])
            ->field('brand_id,brand_name,brand_first_char')
            ->order(['sort' => 'asc', 'update_time' => 'desc'])
            ->select();
        $storeList = $store
            ->where(['status' => 1, 'shop' => 0])
            ->field('store_id,store_name')
            ->select();
        //查询全部品牌首字母
        $brandFirstChr = $brand
            ->where([['brand_id', '>', 0]])
            ->distinct(true)
            ->order(['brand_first_char' => 'asc'])
            ->column('brand_first_char');
        
        $data = $articleModel->get($request::get('article_id'));
        $data['file_data'] = $data->getData('file');
        return $this->fetch('create', [
            'classify_list' => find_level($articleClassifyModel->where('article_classify_id', 'neq', $request::get('article_classify_id'))->field('article_classify_id,title,parent_id')->select()->toArray(), 'article_classify_id'),
            'item' => $data,
            'goods' => $articleAttach->alias('a')->join('goods g', 'a.goods_id = g.goods_id')->field('g.goods_id,g.goods_name')->where(['a.article_id' => $request::get('article_id')])->select(),
            'categoryOne' => $categoryOne,
            'brand' => $brandData,
            'brandFirstChr' => $brandFirstChr,
            'article_id' => $request::get('article_id'),
            'storeList' => $storeList,
            'pc_config' => config('user.pc')['is_include'],
        ]);
    }
    
    /**
     * 选择商品
     * @param Request $request
     * @param GoodsModel $goods
     * @param GoodsClassify $goodsClassify
     * @param Brand $brand
     * @return string
     * @throws Exception
     */
    public function selectGoods(Request $request, GoodsModel $goods, GoodsClassify $goodsClassify, Brand $brand)
    {
        try {
            $param = $request::param();
            $map = [];
            if (array_key_exists('cateId', $param) && $param['cateId'])
                $map['goods_classify_id'] = $param['cateId'];
            if (array_key_exists('brandId', $param) && $param['brandId'])
                $map['brand_id'] = $param['brandId'];
            if (array_key_exists('keyword', $param) && $param['keyword'])
                $map['goods_name|spell_keyword|describe'] = ['like', '%' . $param['keyword'] . '%'];
            if (array_key_exists('search', $param) && $param['search']) {
                //查找商品
                $data = $goods->where($map)->order(['sort' => 'asc'])->field('goods_id,goods_name')->select();
            } else {
                $data = [];
            }
            
            $tr = '';
            foreach ($data as $key => $value) {
                $tr .= '<li style="padding-left: 20px;">' .
                    '<input type="checkbox"  class="chk" lay-filter="chk" title="' . $value['goods_name'] . '" value="' . $value['goods_id'] . '" lay-skin="primary">' .
                    '<div class="layui-unselect layui-form-checkbox" lay-skin="primary"><span>' . $value['goods_name'] . '</span><i class="layui-icon layui-icon-ok"></i></div>' .
                    '</li>';
            };
            
            $info = '<ul>' .
                $tr .
                '</ul>';
            
            return $info;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    
    /**
     * 右侧商品选择
     * @param Request $request
     * @param GoodsModel $goods
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \Exception
     */
    public function getGoods(Request $request, GoodsModel $goods)
    {
        try {
            $param = $request::param();
            $data = $goods->where('goods_id', 'in', $param['id'])->field('goods_id,goods_name')->select();
            return $data;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
    
    /**
     * 删除文章分类
     * @param Request $request
     * @param ArticleModel $articleModel
     * @return array
     */
    public function destroy(Request $request, ArticleModel $articleModel, Message $message)
    {
        if ($request::isPost()) {
            
            Db::startTrans();
            try {
                $message->where([
                    'type' => 2,
                    'attach_id' => $request::post('id')
                ])->update(['delete_time' => date('Y-m-d H:i:s')]);
                
                $articleModel::destroy($request::post('id'));
                
                // 提交事务
                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0]];
                
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
                
            }
        }
    }
    
    /**
     * 文章分类显示状态更新
     * @param Request $request
     * @param ArticleModel $articleModel
     * @return array
     */
    public function auditing(Request $request, ArticleModel $articleModel)
    {
        
        if ($request::isPost()) {
            try {
                $articleModel->changeStatus($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
    
    /**
     * 文章分类排序更新
     * @param Request $request
     * @param ArticleModel $articleModel
     * @return array
     */
    public function text_update(Request $request, ArticleModel $articleModel)
    {
        
        if ($request::isPost()) {
            try {
                $articleModel->clickEdit($request::post());
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }
}