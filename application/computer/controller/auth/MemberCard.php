<?php
declare(strict_types=1);

namespace app\computer\controller\auth;

use app\computer\model\Article;
use app\common\model\MemberCard as MemberCardModel;
use app\computer\controller\BaseController;
use mrmiao\encryption\RSACrypt;
use think\facade\Request;
use think\facade\Session;

/**
 * 我的红包
 * Class MemberCoupon
 * @package app\computer\controller\auth
 */
class MemberCard extends BaseController
{

    protected $beforeActionList = [
        //检查是否登录
        'is_login'=>['only' => 'index']
    ];


    /**
     * 银行卡列表
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberCardModel $memberCard
     * @return mixed
     */
    public function index(Request $request, RSACrypt $crypt, MemberCardModel $memberCard)
    {
        try {

            // 获取参数
            $param = $request::instance()->param();
            $member_id = Session::get('member_info')['member_id'];


            // 条件
            $condition[] = ['member_id', '=', $member_id];


            
            // 查询是否已经领取
            $result = $memberCard
                ->where($condition)
                ->field('card_id,card_bank_name,card_bank_owner,card_remark,card_logo,RIGHT(card_number,4) as card_number_enc')
                ->paginate(12, false, ['query' => $param]);

            // dump($result);
        } catch (\Exception $e) {
            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
        }

        return $this->fetch('',['code' => 0, 'result' => $result]);
    }




    /**
     * 银行卡列表
     * @param Request $request
     * @param RSACrypt $crypt
     * @param MemberCardModel $memberCard
     * @return mixed
     */
    public function add(Request $request, RSACrypt $crypt, MemberCardModel $memberCard)
    {
        if($request::isPost()){
            $param =  $request::instance()->param();
            $param['member_id'] = Session::get('member_info')['member_id'];

            // $param['member_id'] = 10;
            $param['create_time'] = date('Y-m-d H:i:s');
            if(!empty($param['member_id'])&&!empty($param['card_bank_name'])&&!empty($param['card_bank_owner'])&&!empty($param['card_number'])){
                // ?cardNo={$param['card_number']}&cardBinCheck=true
                //  获取银行编码 
                $url = "https://ccdcapi.alipay.com/validateAndCacheCardInfo.json?_input_charset=utf-8&cardNo={$param['card_number']}&cardBinCheck=true"; 
                $curl = curl_init();
                // 设置你需要抓取的URL
                curl_setopt($curl, CURLOPT_URL, $url);
                // 设置header
                curl_setopt($curl, CURLOPT_HEADER, 0);
                // 设置cURL 参数，要求结果保存到字符串中还是输出到屏幕上。
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                // https请求 不验证证书 其实只用这个就可以了
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
                //https请求 不验证HOST
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
                //执行速度慢，强制进行ip4解析
                curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                // 运行cURL，请求网页
                $bankMessage = json_decode(curl_exec($curl));
                // 关闭URL请求
                curl_close($curl);
                //  银行信息
                $banks = '{"CDB":"国家开发银行","ICBC":"中国工商银行","ABC":"中国农业银行","BOC":"中国银行","CCB":"中国建设银行","PSBC":"中国邮政储蓄银行","COMM":"交通银行","CMB":"招商银行","SPDB":"上海浦东发展银行","CIB":"兴业银行","HXBANK":"华夏银行","GDB":"广东发展银行","CMBC":"中国民生银行","CITIC":"中信银行","CEB":"中国光大银行","EGBANK":"恒丰银行","CZBANK":"浙商银行","BOHAIB":"渤海银行","SPABANK":"平安银行","SHRCB":"上海农村商业银行","YXCCB":"玉溪市商业银行","YDRCB":"尧都农商行","BJBANK":"北京银行","SHBANK":"上海银行","JSBANK":"江苏银行","HZCB":"杭州银行","NJCB":"南京银行","NBBANK":"宁波银行","HSBANK":"徽商银行","CSCB":"长沙银行","CDCB":"成都银行","CQBANK":"重庆银行","DLB":"大连银行","NCB":"南昌银行","FJHXBC":"福建海峡银行","HKB":"汉口银行","WZCB":"温州银行","QDCCB":"青岛银行","TZCB":"台州银行","JXBANK":"嘉兴银行","CSRCB":"常熟农村商业银行","NHB":"南海农村信用联社","CZRCB":"常州农村信用联社","H3CB":"内蒙古银行","SXCB":"绍兴银行","SDEB":"顺德农商银行","WJRCB":"吴江农商银行","ZBCB":"齐商银行","GYCB":"贵阳市商业银行","ZYCBANK":"遵义市商业银行","HZCCB":"湖州市商业银行","DAQINGB":"龙江银行","JINCHB":"晋城银行JCBANK","ZJTLCB":"浙江泰隆商业银行","GDRCC":"广东省农村信用社联合社","DRCBCL":"东莞农村商业银行","MTBANK":"浙江民泰商业银行","GCB":"广州银行","LYCB":"辽阳市商业银行","JSRCU":"江苏省农村信用联合社","LANGFB":"廊坊银行","CZCB":"浙江稠州商业银行","DYCB":"德阳商业银行","JZBANK":"晋中市商业银行","BOSZ":"苏州银行","GLBANK":"桂林银行","URMQCCB":"乌鲁木齐市商业银行","CDRCB":"成都农商银行","ZRCBANK":"张家港农村商业银行","BOD":"东莞银行","LSBANK":"莱商银行","BJRCB":"北京农村商业银行","TRCB":"天津农商银行","SRBANK":"上饶银行","FDB":"富滇银行","CRCBANK":"重庆农村商业银行","ASCB":"鞍山银行","NXBANK":"宁夏银行","BHB":"河北银行","HRXJB":"华融湘江银行","ZGCCB":"自贡市商业银行","YNRCC":"云南省农村信用社","JLBANK":"吉林银行","DYCCB":"东营市商业银行","KLB":"昆仑银行","ORBANK":"鄂尔多斯银行","XTB":"邢台银行","JSB":"晋商银行","TCCB":"天津银行","BOYK":"营口银行","JLRCU":"吉林农信","SDRCU":"山东农信","XABANK":"西安银行","HBRCU":"河北省农村信用社","NXRCU":"宁夏黄河农村商业银行","GZRCU":"贵州省农村信用社","FXCB":"阜新银行","HBHSBANK":"湖北银行黄石分行","ZJNX":"浙江省农村信用社联合社","XXBANK":"新乡银行","HBYCBANK":"湖北银行宜昌分行","LSCCB":"乐山市商业银行","TCRCB":"江苏太仓农村商业银行","BZMD":"驻马店银行","GZB":"赣州银行","WRCB":"无锡农村商业银行","BGB":"广西北部湾银行","GRCB":"广州农商银行","JRCB":"江苏江阴农村商业银行","BOP":"平顶山银行","TACCB":"泰安市商业银行","CGNB":"南充市商业银行","CCQTGB":"重庆三峡银行","XLBANK":"中山小榄村镇银行","HDBANK":"邯郸银行","KORLABANK":"库尔勒市商业银行","BOJZ":"锦州银行","QLBANK":"齐鲁银行","BOQH":"青海银行","YQCCB":"阳泉银行","SJBANK":"盛京银行","FSCB":"抚顺银行","ZZBANK":"郑州银行","SRCB":"深圳农村商业银行","BANKWF":"潍坊银行","JJBANK":"九江银行","JXRCU":"江西省农村信用","HNRCU":"河南省农村信用","GSRCU":"甘肃省农村信用","SCRCU":"四川省农村信用","GXRCU":"广西省农村信用","SXRCCU":"陕西信合","WHRCB":"武汉农村商业银行","YBCCB":"宜宾市商业银行","KSRB":"昆山农村商业银行","SZSBK":"石嘴山银行","HSBK":"衡水银行","XYBANK":"信阳银行","NBYZ":"鄞州银行","ZJKCCB":"张家口市商业银行","XCYH":"许昌银行","JNBANK":"济宁银行","CBKF":"开封市商业银行","WHCCB":"威海市商业银行","HBC":"湖北银行","BOCD":"承德银行","BODD":"丹东银行","JHBANK":"金华银行","BOCY":"朝阳银行","LSBC":"临商银行","BSB":"包商银行","LZYH":"兰州银行","BOZK":"周口银行","DZBANK":"德州银行","SCCB":"三门峡银行","AYCB":"安阳银行","ARCU":"安徽省农村信用社","HURCB":"湖北省农村信用社","HNRCC":"湖南省农村信用社","NYNB":"广东南粤银行","LYBANK":"洛阳银行","NHQS":"农信银清算中心","CBBQS":"城市商业银行资金清算中心","HRBANK":"哈尔滨银行"}';
                
                $bankArr = json_decode($banks);
                // dump($bankArr);
                //   判断银行卡号
                if($bankMessage->validated){
                    $param['card_remark'] = '未识别';
                    // dump($bankMessage->bank);
                    $key =$bankMessage->bank;
                    // dump($bankArr->$key);
                    if(!empty($bankArr->$key)) $param['card_remark'] = $bankArr->$key;
                    $param['card_logo'] = "https://apimg.alipay.com/combo.png?d=cashier&t={$key}";
                }else{
                     return $crypt->response([
                        'code'    => -1,
                        'message' => '银行卡号错误，请重新输入',
                        ], TRUE);
                }

                $dis = $memberCard->allowField(true)->save($param);
                if($dis){
                    $message = '添加成功';
                }else{
                    $message = '添加失败';
                }
            }else{
                 return $crypt->response([
                    'code'    => -1,
                    'message' => '信息不能为空',
                ], TRUE);
            }

            return $crypt->response([
                'code'    => 0,
                'message' => $message,
            ], TRUE);
        }

        return $this->fetch('',['code' => 0]);
    }
  
    /**
     * 删除银行卡
     *
     */
   public function destroy(Request $request, RSACrypt $crypt, MemberCardModel $memberCard)
    {
        try {

            // 获取参数
            $param = $request::instance()->param();
            

            $memberCard->destroy($param['card_id']);
             return $crypt->response([
                        'code'    => 1,
                        'message' => '操作成功',
                        ], TRUE);
            
        } catch (\Exception $e) {
            return $crypt->response(['code' => -100, 'message' => self::$errMsg ?: $e->getMessage()], true);
        }
    }



}