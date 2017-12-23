<?php

namespace uuf6429\cli2php;

class MethodReturn
{
    /**
     * @var string PHP expression
     */
    public $expr;

    /**
     * @var string PHPDoc data type (pipe-separated when multiple)
     */
    public $type;

    /**
     * @var string PHPDoc return description
     */
    public $desc;
}
