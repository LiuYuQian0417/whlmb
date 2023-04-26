<?php

return [
    'app_init'=>[
//        'app\\master\\behavior\\Config',
    ],
    'action_begin' => [
        'app\\master\\behavior\\Config',
    ],
    'app_end'   =>  [
        //记录管理员操作日志
        'app\\master\\behavior\\LogRecord',        // 前置
    ],
];