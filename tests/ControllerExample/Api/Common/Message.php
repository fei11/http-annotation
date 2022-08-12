<?php

namespace EasySwoole\HttpAnnotation\Tests\ControllerExample\Api\Common;

use EasySwoole\HttpAnnotation\Attributes\Api;
use EasySwoole\HttpAnnotation\Attributes\Example;
use EasySwoole\HttpAnnotation\Attributes\Param;
use EasySwoole\HttpAnnotation\Attributes\Validator\Optional;

class Message extends Base
{
    #[Api(
        apiName: "list",
        requestPath: "api/common/message/list",
        params: [
            new Param(
                name: "token",
                validate: [
                    new Optional()
                ]
            )
        ]
    )]
    function list(){

    }

    #[Api(
        apiName: "unRead",
        requestPath: "api/common/message/unRead",
        params: [],
        successExample: [
            new Example(
                params: [
                    new Param(name: "status"),
                    new Param(
                        name: "result",
                        value: [154,155,156],
                        type: Param::TYPE_LIST
                    ),
                    new Param("message")
                ]
            )
        ]
    )]
    function unRead(){

    }

    #[Api(
        apiName: "detail",
        requestPath: "api/common/message/detail",
        params: [

        ],
        successExample: [
            new Example(
                params: [
                    new Param(name: "status"),
                    new Param(
                        name: "result",
                        value: [
                            new Param(name: "fromId"),
                            new Param(name: "content"),
                            new Param(name: "time"),
                            new Param(
                                name: "author",
                                value: [
                                    new Param(name: "name"),
                                    new Param(name: "email")
                                ],
                                type: Param::TYPE_OBJECT
                            )
                        ],
                        type: Param::TYPE_OBJECT
                    ),
                    new Param("message")
                ]
            )
        ]
    )]
    function detail(){

    }
}