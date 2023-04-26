<?php
declare(strict_types = 1);

namespace app\interfaces\controller;

/**
 * 控制基类
 * Class BaseController
 * @package app\interfaces\controller
 */
class BaseController
{
    public static $type;
    public static $oneOrMore;
    public static $oneStoreId;
    public static $storeAuthSql;
    public static $goodsAuthSql;
    public static $express;
    
    public function __construct()
    {
        // 当前平台类型0精简1豪华
        self::$type = config('user.pattern');
        // 当前0单店铺还是1多店铺
        self::$oneOrMore = config('user.one_more');
        // 单店铺id
        self::$oneStoreId = config('user.one_store_id');
        // 店铺是否有效sql
        self::$storeAuthSql = 's.delete_time is null and s.status = 4 and if(s.end_time,unix_timestamp(s.end_time) > unix_timestamp(),1)';
        // 单店铺拼接单店铺id
        if (!self::$oneOrMore) {
            self::$storeAuthSql .= ' and s.store_id = ' . self::$oneStoreId;
        }
        // 商品是否有效sql
        self::$goodsAuthSql = 'g.is_putaway = 1 and g.review_status = 1 and g.goods_number > 0';
    }
}