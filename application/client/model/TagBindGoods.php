<?php
declare(strict_types=1);

namespace app\client\model;

use app\common\model\BaseModel;

class TagBindGoods extends BaseModel
{
    protected $pk = 'tag_bind_goods_id';

    /**
     * 更改单信息状态[当前只适用于两种状态切换]
     *
     * @param        $id        string|integer 主键ID值
     * @param string $parameter 参数
     */
    public function changeIsShow($id, $parameter = 'is_show')
    {
        //查询当前状态
        $curStatus = $this->where([$this->pk => $id])->value($parameter);
        //更改当前状态
        $this->isUpdate(TRUE)->where([$this->pk => $id])->update([$parameter => $curStatus === 1 ? 2 : 1]);
    }
}