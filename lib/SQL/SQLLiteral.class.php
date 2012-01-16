<?php
namespace phpnanoorm\SQL;

class SQLLiteral {
    protected $value;
    public function __construct($value) {
        $this->value = $value;
    }
    public function __toString() {
        return strval($this->value);
    }
}

function literal($value) {
    return new SQLLiteral($value);
}

