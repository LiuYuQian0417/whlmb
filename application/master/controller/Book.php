<?php

namespace app\master\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\Book as BookModel;
use app\common\model\BookClass as BookClassModel;
use app\common\model\BookWriter as BookWriterModel;
use app\common\model\BookView as BookViewModel;

class Book extends Controller
{
    /**列表
     * @param Request $request
     * @param BookModel $book
     * @return mixed|string
     */
    public function index(Request $request,BookModel $book,BookClassModel $bookClass){
        try {
            $param = $request::param();
            $where = [];

            if(array_key_exists('keyword',$param) && $param['keyword'] <> ''){
                $where[] = ['title','like','%'.$param['keyword'].'%'];
            }
            if(array_key_exists('class_id',$param) && $param['class_id'] <> '-1'){
                $where[] = ['class_id','=',$param['class_id']];
            }

            $data = $book
                ->field('id,writer_id,title,price,create_time,status,type,image,sort,class_id')
                ->where($where)
                ->order('create_time desc')
                ->paginate([
                    'query' => $param,
                    'list_rows' => 15,
                ]);

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
        $class_list = $bookClass->field('id,title')->select();
        return $this->fetch('', [
            'data' => $data,
            'class_list' => $class_list,
        ]);
    }

    /**添加
     * @param Request $request
     * @param BookModel $book
     * @return array|mixed|string|void
     */
    public function create(Request $request,BookModel $book,BookClassModel $bookClass,BookWriterModel $bookWriter){
        try {
            if($request::isPost()){
                $param = $request::param();
                $param['rotation'] = implode(',',$param['picArr']);
                $param['status'] = '1';
                $check = $book->valid($param,'create');
                if ($check['code']) return ['code' => -100, 'message' => $check];

                $data = $book->isUpdate(false)->allowField(true)->save($param);
                if ($data) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/book/index'];
            }
            $class_list = $bookClass->gettext();
            $writer_list = $bookWriter->select();

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
        return $this->fetch('', [
            'class_list' => $class_list,
            'writer_list' => $writer_list,
        ]);
    }

    /**编辑
     * @param Request $request
     * @param BookModel $book
     * @param BookClassModel $bookClass
     * @param BookWriterModel $bookWriter
     * @return array|mixed
     */
    public function edit(Request $request,BookModel $book,BookClassModel $bookClass,BookWriterModel $bookWriter){
        try {
            if($request::isPost()){
                $param = $request::param();
                $param['rotation'] = implode(',',$param['picArr']);
                $check = $book->valid($param,'edit');
                if ($check['code']) return ['code' => -100, 'message' => $check];

                $data = $book->isUpdate(true)->allowField(true)->save($param);
                if ($data) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/book/index'];
            }

            $info = $book
                ->where('id',$request::param('id',0))
                ->find();

            if(!$info){
                return ['code' => -100, 'message' => '电子书不存在'];
            }

            $info['rotation_extra_data'] = join(',', $info['rotation_extra']);
            $info['rotation_data'] = join(',', $info['rotation']);

            $class_list = $bookClass->gettext();
            $writer_list = $bookWriter->select();
        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('create', [
            'item' => $info,
            'class_list' => $class_list,
            'writer_list' => $writer_list,
        ]);
    }

    /**删除
     * @param Request $request
     * @param BookModel $book
     * @return array|void
     */
    public function destroy(Request $request,BookModel $book){
        if ($request::isPost()) {
            try {
                $book::destroy($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**状态更新
     * @param Request $request
     * @param BookModel $book
     * @return array|void
     */
    public function auditing(Request $request,BookModel $book){
        if ($request::isPost()) {
            try {
                $book->changeStatus($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**章节列表
     * @param Request $request
     * @param BookViewModel $bookView
     * @return array|mixed
     */
    public function viewlist(Request $request,BookViewModel $bookView){
        try {
            $param = $request::param();
            $where = [];
            if(empty($param['book_id'])) return ['code' => -100, 'message' => '电子书不存在'];

            $data = $bookView
                ->where('book_id',$param['book_id'])
                ->where($where)
                ->order('create_time desc')
                ->paginate([
                    'query' => $param,
                    'list_rows' => 15
                ]);

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('', [
            'data' => $data,
            'book_id' => $param['book_id'],
        ]);
    }

    /**
     * @param Request $request
     * @param BookViewModel $bookView
     * @return array|mixed
     */
    public function viewcreate(Request $request,BookViewModel $bookView){
        try {
            $param = $request::param();
            if ($request::isPost()){

                $data = $bookView
                    ->isUpdate(false)
                    ->allowField(true)
                    ->save($param);

                if ($data) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/book/viewlist?book_id='.$param['book_id']];
            }

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
        return $this->fetch('', [
            'book_id' => $param['book_id'],
        ]);
    }

    /**
     * @param Request $request
     * @param BookViewModel $bookView
     * @return array|mixed
     */
    public function viewedit(Request $request,BookViewModel $bookView){
        try {
            $param = $request::param();

            if ($request::isPost()){
                $data = $bookView
                    ->isUpdate(true)
                    ->allowField(true)
                    ->save($param);

                if ($data) return ['code' => 0, 'message' => config('message.')[0], 'url' => '/book/viewlist?book_id='.$param['book_id']];
            }
            $info = $bookView
                ->where('id',$param['id'])
                ->find();

            if(!$info) return ['code' => -100, 'message' => '该章节不存在', 'url' => '/book/index'];

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }
        return $this->fetch('viewcreate', [
            'item' => $info,
            'book_id' => $info['book_id'],
        ]);
    }

    /**
     * @param Request $request
     * @param BookViewModel $bookView
     * @return array|void
     */
    public function viewdestroy(Request $request,BookViewModel $bookView){
        if ($request::isPost()) {
            try {
                $bookView::destroy($request::post('id'));
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**更新
     * @param Request $request
     * @param BookViewModel $bookView
     * @return array|void
     */
    public function viewauditing(Request $request,BookViewModel $bookView){
        if ($request::isPost()) {
            try {
                $bookView->changeStatus($request::post('id'),'is_free');
                return ['code' => 0, 'message' => config('message.')[0]];
            } catch (\Exception $e) {
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 富文本编辑器展示页
     * @return mixed
     */
    public function uEditor()
    {
        return $this->fetch('book/uEditor');
    }

}