<?php
declare(strict_types=1);

namespace app\common\command;

use app\common\service\Beanstalk;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Env;

class Consumer extends Command
{
    /* 消费者数量 */
    private $count = 2;
    // 都是延迟队列
    private $queue_name_list = [
        // 未支付订单过期删除(当前默认15m,改变订单状态为已取消)
        'orderExpire',
        // 未支付订单过期前提醒(当前默认5m)
        'orderExpireRemind',
        // 会员积分到期提醒
        'integralExpireRemind',
        // 全部会员积分归零
        'integralClearZero',
        // 优惠券到期提醒(给用户发条即将到期提醒消息)
        'couponExpireRemind',
        // 优惠券过期改变状态(用户未使用的优惠券)
        'couponExpireChangeStatus',
        // 优惠券领取时间到期下架(店铺发布的优惠券到期下架)
        'couponGetExpireChangeStatus',
        // 红包到期提醒
        'packetExpireRemind',
        // 红包过期改变状态
        'packetExpireChangeStatus',
        // 红包领取时间到期下架
        'packetGetExpireChangeStatus',
        // 砍价活动到期状态改变为失败
        'cutExpireChangeStatus',
        // 砍价商品到期下架
        'cutGoodsExpireChangeStatus',
        // 拼团活动到期状态改变为失败
        'groupExpireChangeStatus',
        // 拼团商品到期下架
        'groupGoodsExpireChangeStatus',
        // 限时抢购商品到期下架
        'limitGoodsExpireChangeStatus',
        // 发货后按照系统设置时间为用户自动收货
        'autoCollect',
        // 确认收货后按照系统设置时间自动好评
        'autoEvaluate',
        // 确认收货后按照系统设置时间关闭售后通道
        'autoCloseSaleAfter',
        // 退款退货失败后用户无操作自动回滚到变化状态
        'refundToRedo',
        // 分销商降级自测
        'distributionDowngradeCheck',
        // 推送消息
        'pushMsg',
    ];

    private $dir_base;
    private $log_file;
    private $msg = '';

    /**
     * 命令配置
     */
    protected function configure()
    {
        $this->setName('beanstalk')
            ->setDescription('receive beanstalk message and operate');
    }

    /**
     * 执行命令体
     * @param Input $input
     * @param Output $output
     * @return mixed
     */
    protected function execute(Input $input, Output $output)
    {
        $this->dir_base = Env::get('root_path') . 'public/msg/';
        if (!is_dir($this->dir_base . 'exec')) {
            mkdir($this->dir_base . 'exec', 0755, true);
        }
        $this->log_file = $this->dir_base . 'exec/' . date('Y-m-d') . '.log';
        $this->msg = str_repeat('-', 25) . date('Y-m-d H:i:s') . "[" .
            (microtime(true) - time()) . "]" . str_repeat('-', 25) . PHP_EOL;
        try {
            (new Beanstalk())->receive();
        } catch (\Exception $e) {
            $this->msg .= 'file：' . $e->getFile() . PHP_EOL .
                'line：' . $e->getLine() . PHP_EOL .
                'msg：' . $e->getMessage() . PHP_EOL;
            // 记录日志
            file_put_contents($this->log_file, $this->msg, FILE_APPEND);
        }
    }


}