<?php
declare(strict_types=1);

namespace app\computer\model;

use \app\common\model\Member as MemberModel;
use think\Db;
use think\facade\Env;
use think\facade\Request;

/**
 * 会员模型
 * Class Manage
 *
 * @package app\common\model
 */
class Member extends MemberModel
{
    /**
     * 获取会员等级信息
     * @param $member_id
     * @return array|\PDOStatement|string|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_rank_info($member_id = 0)
    {
        return MemberRank::where(
            'min_points',
            '<=',
            countGrowth($member_id ?: $this['member_id'])
        )
            ->field('rank_name,mark')
            ->order('min_points', 'desc')
            ->find();
    }

    /**
     * 获得用户客服聊天店铺列表
     * @param object $db Mongodb类实例
     * @param string $member_id 会员id
     * @return mixed
     */
    public function getCustomerList($db, $member_id)
    {
        $pid = Request::param('pid', '');
        $data = $db
            ->field(
                [
                    'store_id',
                ],
                TRUE
            )
            ->where(
                ['member_id' => (string)$member_id, 'name' => ['$ne' => 'count'], 'store_id' => ['$ne' => (string)$pid]]
            )
            ->sort(
                ['after_chat_time' => 'desc']
            )->query(
                'member_store'
            )->toArray();

        //如果有店铺id 并且不是平台店铺  判断店铺是否存在
        if ($pid !== '' && ((int)$pid === 0 || ((int)$pid !== 0 && (new Store)->where(
                        [['store_id', '=', $pid], ['status', '=', '4']]
                    )->count())))
        {
            $stdclass = new \stdClass();
            $stdclass->store_id = Request::get('pid');
            array_unshift($data, $stdclass);
        }
        $_store_id = array_column($data, 'store_id');
        $_prefix = config()['database']['prefix'];
        $_new_store_id = implode(',', $_store_id);
        //根据店铺id查出店铺信息
        if (!empty($_new_store_id))
        {
            $sql = "select logo,store_name,shop from {$_prefix}store where store_id in ({$_new_store_id}) order by field(store_id";
            foreach ($_store_id as $v)
            {
                $sql .= ',\'' . $v . '\'';
            }
            $sql .= ')';
            $_store_logo = Db::query($sql);
        }
        //判断当前记录是否有平台客服记录
        $_terrace_id_index = array_search(0, $_store_id);
        if ($_terrace_id_index !== FALSE)
        {
            //读取平台信息
            Env::load(Env::get('APP_PATH') . 'common/ini/.config');
            $_store_logo = $_store_logo ?? [];
            array_splice(
                $_store_logo,
                $_terrace_id_index,
                0,
                [['logo' => Env::get('logo'), 'store_name' => Env::get('title'), 'shop' => 10000]]
            );
        }
        foreach ($data as $k => $v)
        {
            $v->logo = $this->getOssUrl($_store_logo[$k]['logo']);
            $v->store_name = $_store_logo[$k]['store_name'];
            $v->shop = $_store_logo[$k]['shop'];

        }
        return $data;
    }
}