<?php

namespace uuf6429\cli2php;

class CliSyntaxToken
{
    /**
     * @var string Token name (eg; "CONTAINER" or "OPTIONS")
     */
    public $name;

    /**
     * @var bool If token is one or more options
     */
    public $isOptions;

    /**
     * @var bool If token is optional (surrounded by brackets)
     */
    public $isOptional;

    /**
     * @var bool If token can be repeated (eg; "name1 name2 nameN")
     */
    public $isRepeatable;

    /**
     * @var string Data type for token (eg; "string" for container names)
     */
    public $dataType;

    /**
     * @var string Variable name (eg; "$container")
     */
    public $varName;
}
