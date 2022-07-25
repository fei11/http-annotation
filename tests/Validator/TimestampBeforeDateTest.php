<?php

namespace EasySwoole\HttpAnnotation\Tests\Validator;

use EasySwoole\Http\Request;
use EasySwoole\HttpAnnotation\Attributes\Param;
use EasySwoole\HttpAnnotation\Attributes\Validator\TimestampBeforeDate;
use PHPUnit\Framework\TestCase;

class TimestampBeforeDateTest extends TestCase
{
    /*
    * 合法
    */
    public function testValidCase()
    {
        $request = new Request();
        $request->withQueryParams([
            "date" => time() - 1
        ]);

        $param = new Param("date");
        $param->parsedValue($request);

        $rule = new TimestampBeforeDate(date: date('YmdHis', time()));
        $this->assertEquals(true, $rule->execute($param, $request));

        // func
        $request = new Request();
        $request->withQueryParams([
            "date" => time() - 1
        ]);

        $param = new Param("date");
        $param->parsedValue($request);

        $rule = new TimestampBeforeDate(date: function () {
            return date('YmdHis', time());
        });
        $this->assertEquals(true, $rule->execute($param, $request));
    }

    /*
     * 默认错误信息
     */
    public function testDefaultErrorMsgCase()
    {
        $request = new Request();
        $request->withQueryParams([
            "date" => time() + 1
        ]);

        $param = new Param("date");
        $param->parsedValue($request);

        $rule = new TimestampBeforeDate(date('YmdHis', time()));
        $this->assertEquals(false, $rule->execute($param, $request));

        // func
        $request = new Request();
        $request->withQueryParams([
            "date" => "2022-06-30"
        ]);

        $param = new Param("date");
        $param->parsedValue($request);

        $time = date('YmdHis', time());
        $rule = new TimestampBeforeDate(date: function () use ($time) {
            return $time;
        });
        $this->assertEquals(false, $rule->execute($param, $request));

        $this->assertEquals("date must be timestamp before {$time}", $rule->errorMsg());
    }

    /*
     * 自定义错误信息
     */
    public function testCustomErrorMsgCase()
    {
        $request = new Request();
        $request->withQueryParams([
            "date" => time() + 1
        ]);

        $param = new Param("date");
        $param->parsedValue($request);

        $rule = new TimestampBeforeDate(date: date('YmdHis', time()), errorMsg: '无效时间戳');
        $this->assertEquals(false, $rule->execute($param, $request));
        $this->assertEquals("无效时间戳", $rule->errorMsg());
    }
}
