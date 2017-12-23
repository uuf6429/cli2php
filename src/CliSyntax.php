<?php

namespace uuf6429\cli2php;

class CliSyntax
{
    /**
     * @var string Original syntax line (eg; "docker-machine active [OPTIONS] [arg...]")
     */
    public $source;

    /**
     * @var string Syntax line without commands (eg; "active [OPTIONS] [arg...]")
     */
    public $plain;

    /**
     * @var CliSyntaxToken[] Parsed tokens
     */
    public $tokens = [];
}
