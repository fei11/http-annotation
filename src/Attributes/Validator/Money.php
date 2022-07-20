<?php

namespace EasySwoole\HttpAnnotation\Attributes\Validator;

use EasySwoole\HttpAnnotation\Attributes\Param;
use Psr\Http\Message\ServerRequestInterface;

class Money extends AbstractValidator
{
    public ?int $precision; // null 0 1 2

    function __construct(?int $precision = null, ?string $errorMsg = null)
    {
        if (empty($errorMsg)) {
            $errorMsg = "{#name} must be legal amount";
            if ($precision > 0) {
                $errorMsg = $errorMsg . " with {$precision} precision";
            }
        }
        $this->errorMsg($errorMsg);
        $this->precision = $precision;
    }

    protected function validate(Param $param, ServerRequestInterface $request): bool
    {
        $itemData = $param->parsedValue();
        $precision = $this->precision;
        if (is_null($precision) || $precision === 0) {
            $regex = "/^-?(([1-9]\d*)|0)$/";
        } else {
            $regex = "/^-?(([1-9]\d*)|0)\.\d{1,$precision}$/";
        }

        return (bool)preg_match($regex, $itemData);
    }

    function ruleName(): string
    {
        return "Money";
    }
}