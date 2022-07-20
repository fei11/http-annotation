<?php

namespace EasySwoole\HttpAnnotation\Attributes\Validator;

use EasySwoole\HttpAnnotation\Attributes\Param;
use Psr\Http\Message\ServerRequestInterface;

class NotEmpty extends AbstractValidator
{
    function __construct(?string $errorMsg = null)
    {
        if(empty($errorMsg)){
            $errorMsg = "{#name} is notEmpty";
        }
        $this->errorMsg($errorMsg);
    }

    protected function validate(Param $param,ServerRequestInterface $request): bool
    {
        if((!$param->isNullData()) && ($param->parsedValue() === null)){
            return false;
        }
        return true;
    }

    function ruleName(): string
    {
        return "NotEmpty";
    }
}