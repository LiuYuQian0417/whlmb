<?php
/**
 * Created by PhpStorm.
 * User: LD
 * Date: 2019-02-19
 * Time: 16:22
 */

namespace app\computer\controller\agreement;

use think\Controller;
use think\facade\View;

class Index extends Controller {
    public function index(){
        return View::fetch('agreement/index');
    }
}