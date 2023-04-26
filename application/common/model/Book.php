<?php

namespace app\common\model;

use think\model\concern\SoftDelete;

class Book extends BaseModel
{
    use SoftDelete;


    /**
     * @param $id
     * @param $parameter
     * @return void
     */
    public function changeStatus($id, $parameter = 'type')
    {
        parent::changeStatus($id, $parameter); // TODO: Change the autogenerated stub
    }

    /**分类名称
     * @param $value
     * @param $data
     * @return mixed|string
     */
    public function getClassNameAttr($value, $data){
        return (new BookClass())->where('id',$data['class_id'])->value('title')?:'暂未设置';
    }

    /**作者名称
     * @param $value
     * @param $data
     * @return mixed|string
     */
    public function getWriterNameAttr($value,$data){
        return (new BookWriter())->where('id',$data['writer_id'])->value('name')?:'暂未设置';
    }

    /**审核状态文本
     * @param $value
     * @param $data
     * @return string
     */
    public function getStatusTextAttr($value,$data){
        $arr = [
            '0' => '待审核',
            '1' => '已通过',
            '2' => '未通过',
        ];
        return $arr[$data['status']];
    }

    /**
     * @param $value
     * @param $data
     * @return array|false|string[]
     */
    public function getImageDataAttr($value, $data)
    {
        return $data['image'];
    }

    public function getImageAttr($value,$data){
        return $this->getOssUrl($data['image']);
    }

    public function getRotationAttr($value, $data){
        if (!empty($data['rotation'])) {
            $arr = explode(',', $data['rotation']);
            return $this->getOssUrl($arr);
        }
        return [];
    }

    public function getRotationExtraAttr($value, $data){
        return $data['rotation'] ? explode(',', $data['rotation']) : [];
    }


}