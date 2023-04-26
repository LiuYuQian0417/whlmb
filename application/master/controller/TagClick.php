<?php


namespace app\master\controller;


use app\master\model\TagClassify as TagClassifyModel;
use app\master\model\TagClick as TagClickModel;
use think\Controller;

class TagClick extends Controller
{
    public function index()
    {
        $_GET = $this->request->get();

        // sql条件
        $_where = [
            ['Store.shop', '=', 0]
        ];

        // 商品分类ID
        if (isset($_GET['tag_classify_id']) && !empty($_GET['tag_classify_id'])) {

            $_tagClassifyIdList = explode('-', $_GET['tag_classify_id']);

            switch ($_tagClassifyIdList[0]) {
                case 'c':
                    $_where[] = [
                        'TagClassify.tag_classify_id', '=', $_tagClassifyIdList[1]
                    ];
                    break;
                case 't':
                    $_where[] = [
                        'Tag.tag_id', '=', $_tagClassifyIdList[1]
                    ];
                    break;
            }
        }

        // 标签名称
        if (isset($_GET['tag_name'])) {
            $_where[] = [
                'Tag.name', 'like', "%{$_GET['tag_name']}%"
            ];
        }

        // 商品名称
        if (isset($_GET['goods_name'])) {
            $_where[] = [
                'Goods.goods_name', 'like', "%{$_GET['goods_name']}%"
            ];
        }

        $_tagClickModel = (new TagClickModel());

        $_tagClickList = $_tagClickModel
            ->alias('TagClick')
            ->join('TagBindGoods TagBindGoods', 'TagClick.tag_bind_goods_id = TagBindGoods.tag_bind_goods_id')
            ->join('Tag Tag', 'Tag.tag_id = TagBindGoods.tag_id')
            ->join('TagClassify TagClassify', 'TagClassify.tag_classify_id = Tag.tag_classify_id')
            ->join('Goods Goods', 'Goods.goods_id = TagBindGoods.goods_id')
            ->join('Store Store', 'Store.store_id = Goods.store_id')
            ->join('Member Member', 'Member.member_id = TagClick.member_id', 'left')
            ->where($_where)
            ->field([
                'Tag.name' => 'tag_name',
                'TagClassify.name' => 'tag_classify_name',
                'Store.store_name' => 'store_name',
                'Goods.file' => 'goods_file',
                'Goods.goods_name' => 'goods_name',
                'TagClick.create_time' => 'create_time',
                'Member.phone' => 'member_phone',
                'Member.nickname' => 'member_nickname',
                'Member.avatar' => 'member_avatar'
            ])
            ->order('TagClick.create_time DESC')
            ->paginate(20, false, [
                'query' => $_GET
            ]);

        $_dataList = [];

        if (!$_tagClickList->isEmpty()) {
            foreach ($_tagClickList as $tagClick) {
                $_dataList[] = [
                    'tag_name' => $tagClick['tag_name'],
                    'tag_classify_name' => $tagClick['tag_classify_name'],
                    'store_name' => $tagClick['store_name'],
                    'goods_file' => $_tagClickModel->getOssUrl($tagClick['goods_file']),
                    'goods_name' => $tagClick['goods_name'],
                    'create_time' => $tagClick['create_time'],
                    'member_phone' => $tagClick['member_phone'] ?? '-',
                    'member_nickname' => $tagClick['member_nickname'] ?? '游客',
                    'member_avatar' => stripos($tagClick['member_avatar'], 'http') !== false ? $tagClick['member_avatar'] : $_tagClickModel->getOssUrl($tagClick['member_avatar']),
                ];
            }
        }

        // 标签分类列表
        $_tagClassifyList = TagClassifyModel::withTrashed()->with('tagWSD')->select();

        $this->assign('dataList', $_dataList);
        $this->assign('tagClassifyList', $_tagClassifyList);
        $this->assign('tagClickList', $_tagClickList);

        return $this->fetch();
    }
}