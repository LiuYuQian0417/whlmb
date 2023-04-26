<?php
namespace app\master\controller;

use think\Controller;

class Marketing extends Controller
{
    /**
     * 营销首页
     * @return mixed
     */
    public function index()
    {
        return $this->fetch('');
    }
}