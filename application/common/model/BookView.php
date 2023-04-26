<?php

namespace app\common\model;

class BookView extends BaseModel
{
    public function getBookNameAttr($value, $data){
        return (new Book())->where('id',$data['book_id'])->value('title')?:'未设置';
    }
}