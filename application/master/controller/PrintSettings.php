<?php

namespace app\master\controller;

use think\Controller;

class PrintSettings extends Controller
{
    public function index()
    {
        return $this->fetch('');
    }
}