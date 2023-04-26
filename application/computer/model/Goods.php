<?php
/**
 * Created by PhpStorm.
 * User: Faith
 * Date: 2019/4/17
 * Time: 10:30
 */

namespace app\computer\model;

use \app\common\model\Goods as GoodsModel;
use app\computer\controller\BaseController;
use think\facade\Env;
use think\facade\Session;

class Goods extends GoodsModel
{

    /**
     * 获得商品跳转手机二维码
     * @param $value
     * @param $data
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getMobileGoodsCodeAttr($value, $data)
    {
        $_file_name = 'goods_pc_' . $data['goods_id'];
        $_file_path = 'qr_code/goods/';
        if (is_file(Env::get('root_path') . 'public/' . $_file_path . $_file_name . '.png'))
        {
            unlink(Env::get('root_path') . 'public/' . $_file_path . $_file_name . '.png');
        }
        $_code_data = config('user.')['mobile']['mobile_domain'] . '/GoodDetail?goods_id=' . $data['goods_id'];
        
        if (!class_exists('QrCode'))
        {
            require(Env::get('root_path') . 'extend/phpcode/QrCode.php');
        }
        \common\lib\phpcode\QrCode::getQrCode($_file_name, $_code_data, $_file_path);

        return request()->domain() . '/' . $_file_path . $_file_name . '.png';
    }

    /**
     * 获取配送方式
     * @param $value
     * @param $data
     * @return array|null|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getGoodsDistributionAttr($value, $data)
    {

        return (new Store())
            ->where(
                [
                    ['store_id', '=', $data['store_id']],
                ]
            )
            ->field('is_city,is_shop,is_express,is_pay_delivery,is_delivery')
            ->find();

    }

    /**
     * 获取店铺名称
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getStoreNameAttr($value, $data)
    {

        return (new Store())->where('store_id', $data['store_id'])->value('store_name');

    }

    /**
     * 获取商品当前状态实际价格
     * @param string $ALIAS
     * @return string
     * @throws \Exception
     */
    public function getGoodsRealPriceSql($ALIAS = '')
    {
        if ($ALIAS == '')
        {
            exception('商品表别名不能为空');
        }
        $ALIAS = $ALIAS === '' ? '' : $ALIAS . '.';
        Env::load(Env::get('app_path') . 'common/ini/.config');
        $_PREFIX = config('database.')['prefix'] ?? '';
        $_TIME = date('Y-m-d');
        $_THIS_TIME_H = date('H');
        $_SQL = '';
        if (Env::get('is_group') == 1)
        {
            $_SQL .= "WHEN  {$ALIAS}is_group = 1 and (select count(*) from {$_PREFIX}group_goods c_g_g where c_g_g.goods_id = {$ALIAS}goods_id and c_g_g.delete_time is null and c_g_g.status = 1 and c_g_g.up_shelf_time <= '{$_TIME}' and c_g_g.down_shelf_time >= '{$_TIME}') > 0 THEN {$ALIAS}group_price ";
        }
        if (Env::get('is_cut') == 1)
        {
            $_SQL .= "WHEN  {$ALIAS}is_bargain = 1 and (select count(*) from {$_PREFIX}cut_goods c_c_g where c_c_g.goods_id = {$ALIAS}goods_id and c_c_g.delete_time is null and c_c_g.status = 1 and c_c_g.up_shelf_time <= '{$_TIME}'and c_c_g.down_shelf_time >= '{$_TIME}') > 0 THEN {$ALIAS}cut_price ";
        }
        if (Env::get('is_limit') == 1)
        {
            $_SQL .= "WHEN  {$ALIAS}is_limit = 1 and INSTR(concat(',',(select c_l.interval_id from {$_PREFIX}limit c_l where c_l.goods_id = {$ALIAS}goods_id and c_l.status = 1 and c_l.exchange_num > 0 and c_l.up_shelf_time <= {$_TIME} and c_l.down_shelf_time >= {$_TIME} and ISNULL(c_l.delete_time) limit 1),','),(select c_l_i.limit_interval_id from {$_PREFIX}limit_interval c_l_i where c_l_i.start_time <= {$_THIS_TIME_H} and end_time > {$_THIS_TIME_H} limit 1)) > 0 THEN {$ALIAS}time_limit_price";
        }
        return $_SQL == '' ? "{$ALIAS}shop_price goods_real_price" : "(CASE {$_SQL}  ELSE {$ALIAS}shop_price END) goods_real_price";
    }


    /**
     * 获取店铺是否支持开增值发票
     */
    public function getIsAddedValueTaxAttr($value, $data)
    {

        if (BaseController::$oneOrMore !== TRUE)
        {
            Env::load(Env::get('app_path') . 'common/ini/.config');
            $is_added_value_tax = Env::get('is_added-value_tax', 0);
        } else
        {
            $store = (new Store());
            if (in_array('is_added_value_tax', $store->getTableFields()))
            {
                $is_added_value_tax = Store::where(
                    [
                        ['store_id', '=', $data['store_id']],
                    ]
                )
                    ->value('is_added_value_tax', 0);
            } else
            {
                $is_added_value_tax = 0;
            };
        }
        return $is_added_value_tax;
    }


    /**
     * PC用 获得商品实际价格
     * @param $value
     * @param $data
     * @return float
     */
    public function getGoodsPriceAttr($value, $data)
    {
//        $data['is_group'] . $data['is_bargain'] . $data['is_limit']
        //如果获取当前方法传进来的值就不会走模型对应获取器   修改成从当前调用模型调取属性
        switch ($this->is_group . $this->is_bargain . $this->is_limit)
        {
            //拼团
            case '100':
                $value = $data['group_price'];
                break;
            //砍价
            case '010':
                $value = $data['cut_price'];
                break;
            //限时抢购
            case '001':
                $value = $data['time_limit_price'];
                break;
            default :
                $value = $data['shop_price'];
                break;
        }
        return is_string($value) ? $value : number_format($value, 2);
    }


    /**
     * 获取商品当前实际金额(带属性)
     */

    public function getPayGoodsPriceAttr()
    {
        //判断当前商品所处活动并取对应价格字段
        switch ($this['is_group'] . $this['is_bargain'] . $this['is_limit'])
        {
            //拼团
            case '100':
                $goods_price_field = 'group_price';
                break;
            //砍价
            case '010':
                $goods_price_field = 'cut_price';
                break;
            //限时抢购
            case '001':
                $goods_price_field = 'time_limit_price';
                break;
            default :
                $goods_price_field = 'shop_price';
                break;
        }

        //默认取商品价格  如果有属性则取第一组属性价格
        $goods_price_array = [
            'shop_price'       => number_format($this['shop_price'], 2, '.', ''),
            'time_limit_price' => number_format($this['time_limit_price'], 2, '.', ''),
            'cut_price'        => number_format($this['cut_price'], 2, '.', ''),
            'group_price'      => number_format($this['group_price'], 2, '.', ''),
        ];
        //判断当前商品是否有属性
        if (!empty($this['attr']))
        {

            $goods_attr = '';
            foreach ($this['attr'] as $v)
            {
                $goods_attr .= $v['goods_attr'][0]['attr_value'] . ',';
            }
            $price_info = Products::where(
                [
                    ['goods_attr', '=', trim($goods_attr, ',')],
                    ['goods_id', '=', $this['goods_id']],
                ]
            )
                ->field('products_id,attr_shop_price,attr_group_price,attr_cut_price,attr_time_limit_price')
                ->find();
            if (!empty($price_info))
            {
                $goods_price_array = [
                    'shop_price'       => number_format($price_info['attr_shop_price'], 2, '.', ''),
                    'time_limit_price' => number_format($price_info['attr_time_limit_price'], 2, '.', ''),
                    'cut_price'        => number_format($price_info['attr_cut_price'], 2, '.', ''),
                    'group_price'      => number_format($price_info['attr_group_price'], 2, '.', ''),
                ];
            }
        }
        //当前商品所处状态价格   带属性
        $goods_price_array['reality_price'] = number_format($goods_price_array[$goods_price_field], 2, '.', '');
        return $goods_price_array;
    }

    /**
     * 获取图片oss地址
     * @param $value
     * @return mixed
     */
    public function getPcRecommeFileAttr($value)
    {
        return $this->getOssUrl($value);
    }
}