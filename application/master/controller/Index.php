<?php
declare(strict_types = 1);
namespace app\master\controller;

use think\Controller;

class Index extends Controller
{

    public function index()
    {
        return $this->fetch('');
    }
}