<?php


namespace app\common\service;


use think\facade\Config;

class OssImage
{
    private $prefix = '';

    public function __construct()
    {
        $this->prefix = Config::get('oss.prefix');
    }

    /**
     * 构建oss地址
     *
     * @param $path
     * @return array
     */
    function buildOssUrl($path)
    {
        $_data = [
            'path' => $path,
            'url'  => '',
        ];

        $_data['url'] = !empty($_data['path'])?$_data['url'] = $this->prefix . $_data['path']:$_data['url'] = $path;

        return $_data;
    }
}