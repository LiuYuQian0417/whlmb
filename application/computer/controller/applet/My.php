<?php
/**
 * 关于个人信息.
 * User: Heng
 * Date: 2019/2/23
 * Time: 13:46
 */

namespace app\computer\controller\applet;

use think\facade\Request;
use mrmiao\encryption\RSACrypt;
use app\computer\model\Member as MemberModel;
use app\computer\model\MemberTask as MemberTaskModel;
use app\computer\model\IntegralTask as IntegralTaskModel;
use app\computer\model\CutActivityAttach as CutActivityAttachModel;
use app\computer\controller\BaseController;

class My extends BaseController
{
    /**
     * 是否绑定微信
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberModel $memberModel
     * @return mixed
     */
    public function judgeBindingWechat(Request $request, RSACrypt $crypt, MemberModel $memberModel)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;

                return $crypt->response(['code' => 0, 'result' => $memberModel->where('member_id', $param['member_id'])->value('wechat_open_id') ? 1 : 0]);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }


    /**
     * 是否绑定QQ
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberModel $memberModel
     * @return mixed
     */
    public function judgeBindingQq(Request $request, RSACrypt $crypt, MemberModel $memberModel)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;

                return $crypt->response(['code' => 0, 'result' => $memberModel->where('member_id', $param['member_id'])->value('qq_open_id') ? 1 : 0]);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }


    /**
     * 绑定微信号
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberModel $memberModel
     * @param CutActivityAttachModel $activityAttach
     * @param MemberTaskModel $memberTask
     * @param IntegralTaskModel $integralTask
     * @return mixed
     */
    public function bindingWechat(Request $request, RSACrypt $crypt, MemberModel $memberModel, CutActivityAttachModel $activityAttach, MemberTaskModel $memberTask, IntegralTaskModel $integralTask)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;
                // 主账号id
                $member = $memberModel->where('wechat_open_id', $param['wechat_open_id'])->field('member_id,phone')->find();

                // 如果已经绑定
                if ($member['phone']) return $crypt->response(['code' => -1, 'message' => config('message.')[-1][-5]], true);

                if ($member['member_id']) {

                    // 更新微信唯一ID
                    $memberModel->save(['wechat_open_id' => $param['unionId']], ['member_id' => $member['member_id']]);

                    // 删除三方member_id
                    $memberModel::destroy($param['member_id']);

                    // 合并砍价member_id
                    $activityAttach->save(['helper' => $member['member_id']], ['helper' => $param['member_id']]);

                    // 合并任务
                    merge($member['member_id']);

                } else {
                    $memberModel->allowField(true)->isUpdate(true)->save($param);
                }

                $memberTask->save(['third_party_state' => 1], ['member_id' => $param['member_id']]);

                $integralTask->save(['third_party_state' => 1], ['member_id' => $param['member_id']]);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0], 'member_id' => $member['member_id'] ? $member['member_id'] : $param['member_id']]);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }


    /**
     * 解除绑定微信
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberModel $memberModel
     * @param MemberTaskModel $memberTask
     * @param IntegralTaskModel $integralTask
     * @return mixed
     */
    public function relieveBindingWechat(Request $request, RSACrypt $crypt, MemberModel $memberModel, MemberTaskModel $memberTask, IntegralTaskModel $integralTask)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;
                // 解除
                $memberModel->save(['wechat_open_id' => NULL], ['member_id' => $param['member_id']]);

                $memberTask->save(['third_party_state' => 0], ['member_id' => $param['member_id']]);

                $integralTask->save(['third_party_state' => 0], ['member_id' => $param['member_id']]);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }


    /**
     * 绑定QQ号
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberModel $memberModel
     * @param CutActivityAttachModel $activityAttach
     * @param MemberTaskModel $memberTask
     * @param IntegralTaskModel $integralTask
     * @return mixed
     */
    public function bindingQq(Request $request, RSACrypt $crypt, MemberModel $memberModel, CutActivityAttachModel $activityAttach, MemberTaskModel $memberTask, IntegralTaskModel $integralTask)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;
                // 主账号id
                $member = $memberModel->where('qq_open_id', $param['qq_open_id'])->field('member_id,phone')->find();

                // 如果已经绑定
                if ($member['phone']) return $crypt->response(['code' => -1, 'message' => config('message.')[-1][-5]], true);

                if ($member['member_id']) {

                    // 更新微信唯一ID
                    $memberModel->save(['qq_open_id' => $param['open_id']], ['member_id' => $member['member_id']]);

                    // 删除三方member_id
                    $memberModel::destroy($param['member_id']);

                    // 合并砍价member_id
                    $activityAttach->save(['helper' => $member['member_id']], ['helper' => $param['member_id']]);

                    // 合并任务
                    merge($member['member_id']);

                } else {
                    $memberModel->allowField(true)->isUpdate(true)->save($param);
                }

                $memberTask->save(['third_party_state' => 1], ['member_id' => $param['member_id']]);

                $integralTask->save(['third_party_state' => 1], ['member_id' => $param['member_id']]);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0], 'member_id' => $member['member_id'] ? $member['member_id'] : $param['member_id']]);


            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }


    /**
     * 接触绑定QQ号
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberModel $memberModel
     * @param MemberTaskModel $memberTask
     * @param IntegralTaskModel $integralTask
     * @return mixed
     */
    public function relieveBindingQq(Request $request, RSACrypt $crypt, MemberModel $memberModel, MemberTaskModel $memberTask, IntegralTaskModel $integralTask)
    {
        if ($request::isPost()) {
            try {
                // 获取参数
                $param = $crypt->request();
                $param['member_id'] = request(0)->mid;
                // 解除
                $memberModel->save(['qq_open_id' => NULL], ['member_id' => $param['member_id']]);

                $memberTask->save(['third_party_state' => 0], ['member_id' => $param['member_id']]);

                $integralTask->save(['third_party_state' => 0], ['member_id' => $param['member_id']]);

                return $crypt->response(['code' => 0, 'message' => config('message.')[0][0]]);

            } catch (\Exception $e) {
                return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()]);
            }
        }
    }
}