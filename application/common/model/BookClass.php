<?php

namespace app\common\model;

use think\model\concern\SoftDelete;

class BookClass extends BaseModel
{
    use SoftDelete;


    /**
     * @return array|\PDOStatement|string|\think\Collection|\think\model\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function gettext(){
        $list = $this
            ->where('parentid',0)
            ->field('id,title')
            ->select();

        foreach ($list as $k=>&$v){
            $v['son'] = $this
                ->where('parentid',$v['id'])
                ->field('id,title')
                ->append(['newtitle'])
                ->select();
        }
        return $list;
    }

    /**
     * @param $value
     * @param $data
     * @return string
     */
    public function getNewtitleAttr($value,$data){
        return '|-'.$data['title'];
    }

    /**
     * @param $class_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getClassArr($class_id){
        $info = $this
            ->where('id',$class_id)
            ->field('id,parentid')
            ->find();
        if($info['parentid']){
            $arr = [];
        }else{
            $arr = $this
                ->where('parentid',$info['id'])
                ->column('id');
        }
        array_push($arr,$class_id);
        return $arr;
    }
}