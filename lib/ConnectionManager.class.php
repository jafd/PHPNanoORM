<?php

/**
 * @package ConnectionManager
 * 
 * Connection manager - a singleton to provide connection handles
 * to other classes.
 * 
 * Usually, a PDO instance can be passed to any DataEntity/EntityCollection. However,
 * for small projects using a single database handle, this can be fairly cumbersome.
 * That's why ConnectionManager can be used: it provides the default PDO instance unless
 * one is provided explicitly.
 * 
 * @author Yaroslav Fedevych <jaroslaw.fedewicz@gmail.com>
 * @license MIT License; see LICENSE file for details
 * 
 * @todo Expand to support multiple PDO instances
 * @todo Alter connect() so that it supports an externally-instantiated PDO handle
 *
 */

namespace phpnanoorm;

class ConnectionManager {
    protected static $__instance__ = null;
    protected $__handle__ = null;

    private function __construct() {
    }
    private function __clone() {
    }

    public static function getInstance() {
        if (!self::$__instance__) {
            self::$__instance__ = new self();
        }
        return self::$__instance__;
    }

    public function connect($dsn, $u, $p, $options = null) {
        if (is_null($options)) {
            $options = array();
        }
        $this->__handle__ = new \PDO($dsn, $u, $p, $options);
        return $this;
    }

    public function getHandle() {
        if (is_null($this->__handle__)){
            throw new \Exception("Attempt to access the DB handle before connecting!");
        }
        return $this->__handle__;
    }

    public function connected() {
        return $this->__handle__ instanceof \PDO;
    }
}
