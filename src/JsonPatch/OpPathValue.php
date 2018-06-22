<?php

namespace Odanijel\JsonDiff\JsonPatch;


abstract class OpPathValue extends OpPath
{
    public $value;

    public function __construct($path = null, $value = null)
    {
        parent::__construct($path);
        $this->value = $value;
    }

}
