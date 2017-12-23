<?php

namespace uuf6429\cli2php;

class CliOption
{
    /**
     * @var string Short argument, if available (eg; "-a")
     */
    public $short;

    /**
     * @var string Long argument (eg; "--append")
     */
    public $long;

    /**
     * @var string Argument name (eg; "append")
     */
    public $name;

    /**
     * @var string Following args, if available (eg; "text")
     */
    public $args;

    /**
     * @var string Description (eg; "Append some text")
     */
    public $desc;

    /**
     * @var string Variable name (eg; "$append")
     */
    public $varName;
}
