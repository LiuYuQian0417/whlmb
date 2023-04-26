<?php
declare(strict_types=1);

namespace app\computer\controller\home;

use app\computer\model\Member;
use app\computer\model\Message as MessageModel;
use app\computer\model\MessageExamine;
use app\computer\model\Article;
use app\computer\model\ArticleAttach;
use app\computer\model\CollectArticle;
use app\computer\model\IntegralRecord;
use app\computer\model\MemberGrowthRecord;
use app\computer\controller\BaseController;
use think\facade\Env;
use think\facade\Request;
use think\facade\Session;

/**
 * 消息
 * Class Home
 * @package app\computer\controller\home
 */
class Message extends BaseController
{


    protected $beforeActionList = [
        //检查是否登录
        'is_login'=>['except' => '']
    ];

    /**
     * 消息列表
     * @param Request $request
     * @param Member $member
     * @param MessageModel $message
     * @param MessageExamine $messageExamine
     * @return mixed
     */
    public function index(Request $request, Member $member, MessageModel $message, MessageExamine $messageExamine)
    {
        try
        {
            // 获取参数
            $param = $request::get();
            $param['member_id']= Session::get('member_info')['member_id'];
            $condition = [['status', '=', 1], ['member_id', ['=', $param['member_id']], ['=', 0], 'or']];
            if ($param['type'] ?? 0 <> NULL)
            {
                $condition[] = ['type', '=', $param['type'] ?? 0];
            }
            if($param['type'] == 0){
                $condition[] = ['jump_state', 'not in', '0,6'];
            }
            if ($param['type'] == 2)
            {
                $condition[] = [
                    'create_time',
                    '>=',
                    $member->where('member_id', $param['member_id'])->value('register_time'),
                ];
            }
            $message_examine_id = $messageExamine
                ->where(
                    [
                        ['member_id', '=', $param['member_id']],
                        ['type', '=', $param['type']],
                    ]
                )
                ->value('message_examine_id');
            if ($message_examine_id)
            {
                $messageExamine->allowField(TRUE)->save($param, ['message_examine_id' => $message_examine_id]);
            } else
            {
                $messageExamine->allowField(TRUE)->save($param);
            }
            $result = $message
                ->where($condition)
                ->field(
                    'content,title,`describe`,file,express_value,express_number,express_type,jump_state,
                    attach_id,end_time,DATE_FORMAT(create_time,"%Y-%m-%d") as date_time,type'
                )
                ->order('create_time', 'desc')
                ->append(['current_time', 'order_title'])
                ->paginate(4,false,['query'=>$param]);
            foreach (['common', 'express', 'activity'] as $key => $value)
            {
                $statistics[$value] = $param['member_id'] ? $message
                    ->where(
                        [
                            ['type', '=', $key],
                            ['status', '=', 1],
                            ['member_id', '=', ($value == 'activity') ? 0 : $param['member_id']],
                            [
                                'create_time',
                                '>',
                                $messageExamine->where(
                                    [['member_id', '=', $param['member_id']], ['type', '=', $key]]
                                )->value('create_time') ?: $member->where('member_id', $param['member_id'])->value(
                                    'register_time'
                                ),
                            ],
                        ]
                    )
                    ->count() : 0;

            }
        } catch (\Exception $e)
        {
            halt($e->getMessage());
        }
//        dump($result);
        return $this->fetch('', ['result' => $result, 'statistics' => $statistics]);
    }

    /**
     * 优惠信息查看
     * @param Request $request
     * @param Article $article
     * @param ArticleAttach $articleAttach
     * @param CollectArticle $collectArticle
     * @param IntegralRecord $integralRecord
     * @param MemberGrowthRecord $memberGrowthRecord
     * @param Member $member
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function discounts_examine(Request $request,Article $article, ArticleAttach $articleAttach, CollectArticle $collectArticle, IntegralRecord $integralRecord, MemberGrowthRecord $memberGrowthRecord, Member $member){
        Env::load(Env::get('APP_PATH') . 'common/ini/.config');
        $article_id=$request::get('article_id');
        $param['member_id']=Session::get('member_info')['member_id'];
        $result = $article
            ->where('article_id', $article_id)
            ->field('article_id,title,create_time,content,file')
            ->find();
        // 关注状态
        $result['attention_state'] = $collectArticle
            ->where([
                        ['article_id', '=', $article_id],
                        ['member_id', '=',$param['member_id']]
                    ])
            ->value('collect_article_id');
        // 商品状态
        $result['goods'] = $articleAttach->alias('article_attach')
            ->join('goods goods', 'goods.goods_id = article_attach.goods_id')
            ->where('article_id', $article_id)
            ->field('goods.goods_id,goods_name,file,shop_price')
            ->select();

        $article->where('article_id', $article_id)->setInc('hits');

        // 积分广告记录查询
        $integral_record_id = $integralRecord
            ->where([
                        ['member_id', '=',$param['member_id']],
                        ['type', '=', 0],
                        ['origin_point', '=', 8],
                        ['parameter', '=', $article_id],
                    ])
            ->value('integral_record_id');

        if (empty($integral_record_id) &&$param['member_id']) {
            // 插入积分记录
            $integralRecord->save([
                                      'member_id'    =>$param['member_id'],
                                      'type'         => 0,
                                      'origin_point' => 8,
                                      'parameter'    => $article_id,
                                      'integral'     => Env::get('integral_adv'),
                                      'describe'     => '浏览了' . $result['title'] . '广告',
                                      'create_time'  => date('Y-m-d H:i:s')
                                  ]);

            // 增加积分
            $member->where('member_id',$param['member_id'])->setInc('pay_points', (int)Env::get('integral_adv'));
        }

        // 成长值广告记录查询
        $member_growth_record_id = $memberGrowthRecord
            ->where([
                        ['member_id', '=',$param['member_id']],
                        ['type', '=', 0],
                        ['describe', '=', '浏览了' . $result['title'] . '_' . $article_id . '广告']
                    ])
            ->value('member_growth_record_id');

        if (empty($member_growth_record_id) &&$param['member_id']) {

            // 插入成长值记录
            $memberGrowthRecord->save([
                                          'type'         => 0,
                                          'member_id'    =>$param['member_id'],
                                          'growth_value' => Env::get('growth_adv'),
                                          'describe'     => '浏览了' . $result['title'] . '_' . $article_id . '广告',
                                          'create_time'  => date('Y-m-d H:i:s')
                                      ]);

        }
        return $this->fetch('',['data'=>$result]);
    }
}