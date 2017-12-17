<?php

namespace uuf6429\cli2php;

/**
 * @property string $name Token name (eg; "CONTAINER" or "OPTIONS")
 * @property bool $isOptions If token is one or more options
 * @property bool $isOptional If token is optional (surrounded by brackets)
 * @property bool $isRepeatable If token can be repeated (eg; "name1 name2 nameN")
 * @property string $dataType Data type for token (eg; "string" for container names)
 */
class CliSyntaxToken
{
}
