<?php
namespace app\master\controller;

use think\Controller;
use think\facade\Request;
use app\common\model\Message as MessageModel;
use think\Db;

class Message extends Controller
{
    /**
     * 消息列表
     * @param Request $request
     * @param MessageModel $message
     * @return array|mixed
     */
    public function index(Request $request, MessageModel $message)
    {
        try {
            // 获取数据
            $param = $request::post();

            // 筛选条件
            $condition[] = ['type', 'eq', 2];
            if (!empty($param['keyword'])) $condition[] = ['title', 'like', '%' . $param['keyword'] . '%'];

            // 获取数据
            $data = $message
                ->where($condition)
                ->order(['create_time' => 'desc'])
                ->paginate(10, false, ['query' => $param]);

        } catch (\Exception $e) {
            return ['code' => -100, 'message' => $e->getMessage()];
        }

        return $this->fetch('', [
            'data' => $data
        ]);
    }

    /**
     * 消息添加
     * @param Request $request
     * @param MessageModel $message
     * @return array|mixed
     */
    public function create(Request $request, MessageModel $message)
    {
        if ($request::isPost()) {
            Db::startTrans();
            try {
                // 获取数据
                $param = $request::post();

                // 验证
                $check = $message->valid($param, 'create');
                if ($check['code']) return $check;

                // 写入
                $message->allowField(true)->save($param);

                Db::commit();
                return ['code' => 0, 'message' => config('message.')[0], 'url' => '/message/index'];

            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return ['code' => -100, 'message' => $e->getMessage()];
            }
        }

        return $this->fetch('', [

        ]);
    }
}