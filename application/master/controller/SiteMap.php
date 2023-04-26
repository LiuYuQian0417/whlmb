<?php
// 生成网站地图
declare(strict_types = 1);

namespace app\master\controller;

use think\Controller;
use app\common\service\SiteMapGenerator;
use think\facade\Env;

class SiteMap extends Controller
{
    const LENGTH = 1024*20;

    public function index()
    {
        $filename =  Env::get('root_path').'public/sitemap/sitemap.xml';
        $data = self::xmlToArray($filename);


        return $this->fetch('',[
           'data'=>$data
        ]);
    }


    /**
     * 获取站点地图
     */
    public function create()
    {
        $sitemap = new SitemapGenerator("https://lhy666.cn/");

        //添加url，如果你的url是通过程序生成的，这里就可以循环添加了。
        $sitemap->addUrl("https://lhy666.cn/mobile/login/index.html",'登录');
        $sitemap->addUrl("https://lhy666.cn/mobile/forget/phone.html",'注册');
        $sitemap->addUrl("https://lhy666.cn/mobile/index/index.html",'主页');
        //创建sitemap
        $sitemap->createSitemap();

        //生成sitemap文件
        $sitemap->writeSitemap();

        //更新robots.txt文件
        $sitemap->updateRobots();

        return ['code' => 0, 'message' => '操作成功'];
        // //提交sitemap到搜索引擎
        // $sitemap->submitSitemap();
    }


    /**
     * 读取站点地图
     */
    private static function xmlToArray($filename)
    {
        $xmlParse = xml_parser_create('utf-8');
        xml_parser_set_option($xmlParse, XML_OPTION_CASE_FOLDING, 1);
        xml_parser_set_option($xmlParse, XML_OPTION_SKIP_WHITE, 1);
        $fp = fopen($filename ,'r');
        $xmlData = fread($fp,self::LENGTH);

        xml_parse_into_struct($xmlParse,$xmlData,$xmlArr);
        $xmlFinalArr = [];

        foreach ($xmlArr as $key => $item){
            if (array_key_exists('value',$item)&&array_key_exists('tag',$item)){
               if ($item['tag']=='LOC'){
                   $xmlFinalArr[] = [
                       'name'=>$xmlArr[$key+1]['value'],
                       'url'=>$item['value'],
                   ];
               }

            }

        }
        fclose($fp);
        if ($xmlFinalArr)
            foreach ($xmlFinalArr as &$item){
                if (is_array($item)){
                    $item = array_change_key_case($item,CASE_LOWER);
                }
            }
        return $xmlFinalArr;
    }
}