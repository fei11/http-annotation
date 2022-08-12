<?php

namespace EasySwoole\HttpAnnotation\Tests\ControllerExample;

use EasySwoole\HttpAnnotation\Attributes\Api;
use EasySwoole\HttpAnnotation\Attributes\RequestParam;
use EasySwoole\HttpAnnotation\Attributes\Description;
use EasySwoole\HttpAnnotation\Attributes\Example;
use EasySwoole\HttpAnnotation\Attributes\Param;
use EasySwoole\HttpAnnotation\Attributes\Validator\MaxLength;
use EasySwoole\HttpAnnotation\Attributes\Validator\Required;

class Index extends Base
{
    #[Api(
        apiName: "home",
        allowMethod:Api::GET,
        requestPath: "/test/index.html",
        requestParam: new RequestParam(
            params: [
                new Param(name:"account",from: [Param::GET],validate: [
                    new Required(),
                    new MaxLength(maxLen: 15),
                ],description: new Description("用户登录的账户Id,这个参数一定要有啊"))
            ]
        ),
        description: new Description("这是一个接口说明啊啊啊啊")
    )]
    function index(string $account){
        $this->writeJson(200,null,"account is {$account}");
    }
}