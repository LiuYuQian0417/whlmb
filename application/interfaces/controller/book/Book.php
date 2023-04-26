<?php
declare(strict_types = 1);

namespace app\interfaces\controller\book;
use app\interfaces\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Env;
use app\common\model\Book as BookModel;
use app\common\model\BookClass as BookClassModel;

class Book extends BaseController
{


    public function __construct()
    {
        parent::__construct();
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
    }


    /**
     * @param RSACrypt $crypt
     * @param BookModel $book
     * @param BookClassModel $bookClass
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(RSACrypt $crypt,BookModel $book,BookClassModel $bookClass){
        $param = $crypt->request();
        $param['member_id'] = request()->mid ?? '';
        $where = [
            ['status','=','1'],//审核通过
            ['type','=','1'],//已上架
        ];

        //关键词
        if(array_key_exists('keyword',$param) && $param['keyword'] <> ''){
            $where[] = ['title','like','%'.$param['keyword'].'%'];
        }
        //分类
        if(array_key_exists('class_id',$param) && $param['class_id']){
            $where[] = ['class_id','in',$bookClass->getClassArr($param['class_id'])];
        }

        $data = $book
            ->where($where)
            ->order('sort desc')
            ->paginate(20,false);


        return $crypt->response([
            'code' => 0,
            'message' => '查询成功',
            'data' => $data,
        ], true);
    }
}