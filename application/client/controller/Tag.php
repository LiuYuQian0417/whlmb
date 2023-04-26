<?php
//标签管理
namespace app\client\controller;

use think\Controller;
use think\Exception;
use think\facade\Request;
use app\client\model\TagClassify;
use app\client\model\Tag as TagModel;
use app\client\model\TagClick;
use app\client\model\TagBindGoods;
use think\facade\Session;

class Tag extends Controller
{

    /**
     * 标签分类
     * @param Request $request
     * @param TagClassify $tagClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function classify_index(Request $request,TagClassify $tagClassify)
    {
        $param = $request::param();

        $condition = [];

        if (!empty($param['keyword'])){
            $condition[] = ['name', 'like', '%' . $param['keyword'] . '%'];
         }
        $data = $tagClassify->where($condition)->field('tag_classify_id,name')->select();

        return $this->fetch('',[
            'data' => $data
        ]);

    }

    /**
     * 标签管理
     * @param Request $request
     * @param TagModel $tag
     * @param TagClassify $tagClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(Request $request,TagModel $tag,TagClassify $tagClassify)
    {
        $param = $request::param();

        $condition = [];

        if(!empty($param['keyword'])){
            $condition[] = ['tag.name', 'like', '%' . $param['keyword'] . '%'];
        }
        if(!empty($param['classify'])){
            $condition[] = ['tag.tag_classify_id', '=', $param['classify']];
        }

        $data = $tag->alias('tag')
            ->join('TagClassify tag_classify','tag.tag_classify_id = tag_classify.tag_classify_id')
            ->where($condition)
            ->field('tag.tag_id,tag.name,tag.tag_classify_id,tag_classify.name as tag_classify_name,tag.content')
            ->paginate(10,FALSE);

        $tagClassifyData = $tagClassify->field('tag_classify_id,name')->select();

        return $this->fetch('',[
            'data' => $data,
            'tag_classify_data' => $tagClassifyData
        ]);
    }


    /**
     * 标签查看
     * @param Request $request
     * @param TagModel $tag
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create(Request $request, TagModel $tag)
    {
        $param = $request::param();

        $data = $tag->alias('tag')
            ->join('TagClassify tag_classify','tag.tag_classify_id = tag_classify.tag_classify_id')
            ->where([['tag.tag_id', '=', $param['tag_id']]])
            ->field('tag.tag_id,tag.name,tag.tag_classify_id,tag_classify.name as tag_classify_name,tag.content')
            ->find();
        return $this->fetch('',[
            'item' => $data
        ]);
    }

    /**
     * 标签点击记录
     * @param Request $request
     * @param TagClick $tagClick
     * @param TagClassify $tagClassify
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function log_index(Request $request,TagClick $tagClick,TagClassify $tagClassify)
    {
        $param = $request::param();

        $condition = [];

        if(!empty($param['keyword'])){
            $condition[] = ['tag.name', 'like', '%' . $param['keyword'] . '%'];
        }
        if(!empty($param['classify'])){
            $condition[] = ['tag.tag_classify_id', '=', $param['classify']];
        }
        if(!empty($param['goods_name'])){
            $condition[] = ['goods.goods_name','like', '%' . $param['goods_name'] . '%'];
        }

        $data = $tagClick->alias('tag_click')
            ->join('TagBindGoods tag_bind_goods','tag_click.tag_bind_goods_id = tag_bind_goods.tag_bind_goods_id')
            ->join('Tag tag','tag_bind_goods.tag_id = tag.tag_id')
            ->join('TagClassify tag_classify','tag.tag_classify_id = tag_classify.tag_classify_id')
            ->join('Member member','tag_click.member_id = member.member_id')
            ->join('Goods goods','tag_bind_goods.goods_id = goods.goods_id')
            ->join('Store store','tag_bind_goods.store_id = store.store_id')
                ->where($condition)
                ->field('tag_click.tag_click_id,tag.name as tag_name,tag_classify.name as tag_classify_name,store.store_name,goods.goods_name,goods.file,member.nickname,member.phone,tag_click.create_time')
            ->order('tag_click.create_time','desc')
            ->paginate(10,FALSE);

        $tagClassifyData = $tagClassify->field('tag_classify_id,name')->select();

        return $this->fetch('',[
            'data' => $data,
            'tag_classify_data' => $tagClassifyData
        ]);
    }


    /**
     * 选中商品
     * @param TagBindGoods $tagBindGoods
     * @return array
     */
    public function choose(TagBindGoods $tagBindGoods)
    {
        if ($this->request->isPost())
        {
            try{
                $param = Request::param();

                $array = explode(',', $param['goods_id_list']);

                $save = [];
                foreach ($array as $value)
                {
                    $save[] = ['goods_id' => $value, 'store_id' => Session::get('client_store_id'), 'tag_id' => $param['tag_id']];
                }
                $tagBindGoods->allowField(TRUE)->saveAll($save);
                return ['code' => 0, 'message' => '成功'];
            }catch (\Exception $e){
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }


    /**
     * 移除商品
     * @return array
     */
    public function remove()
    {
        if ($this->request->isPost())

        {
            try
            {
                $param = Request::param();
                TagBindGoods::where([
                                        ['tag_bind_goods_id', 'in', $param['tag_bind_goods_id']],
                                    ])->update([
                                                   'delete_time' => date('Y-m-d H:i:s'),
                                               ]);

                return ['code' => 0, 'message' => '成功'];
            } catch (\Exception $e)
            {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

}