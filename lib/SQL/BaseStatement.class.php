<?php

namespace phpnanoorm\SQL;

class BaseStatement {
    protected $__bindings__ = array();
    protected $__from__ = array();
    protected $__where__ = array();
    protected $__returning__ = array();
    
    protected function _quote_identifier($identifier) {
        if (preg_match('/^[^A-Za-z_]|[^A-Za-z0-9_\.]/', $identifier)) {
            if (preg_match('/\./', $identifier)) {
                $input = explode('.', $identifier);
            } else {
                $input = array($identifier);
            }
            $buffer = array();
            foreach($input as $i) {
                if (!preg_match('/^".+"$/', $i)) {
                    $buffer[] = '"'.str_replace('"', '""', $i).'"';
                } else {
                    $buffer[] = $i;
                }
            }
            $output = implode('.', $buffer);
        } else {
            $output = $identifier;
        }
        return $output;
    }
    
    protected function _aliases_to_sql($al) {
        $buffer = array();
        foreach ($al as $key => $value) {
            if ($value instanceof BaseStatement) {
                $value = "(".strval($value).")";
            }
            if (is_int($key) || ($key == $value)) {
                $buffer[] = $value;
            } else {
                $key = $this->_quote_identifier($key);
                $buffer[] = "$value AS $key";
            }
        }
        return implode(', ', $buffer);
    }
    
    public function binding($name, $value) {
        $this->__bindings__[$name] = $value;
        return $this;
    }
    
    public function bind($name, $value) {
        return $this->binding($name, $value);
    }
    
    public function bindMultiple($arr) {
        $this->__bindings__ = array_merge($this->__bindings__, $arr);
        return $this;
    }
    
    public function getBindings() {
        return $this->__bindings__;
    }

    protected function _format_conditions($cond, $operator) {
        $conds = array();
        if (is_array($cond)) {
            foreach($cond as $c) {
                $conds[] = "($c)";
            }
        } else {
            $conds = array("($cond)");
        }
        return implode(" $operator ", $conds);
    }
    
    protected function _add_where($cond, $operator) {
        $this->__where__[] = $this->_format_conditions($cond, $operator);
        return $this;
    }
    
    public function where($cond) {
        return $this->_add_where($cond, 'AND');
    }
    
    public function orwhere($cond) {
        return $this->_add_where($cond, 'OR');
    }
    
    public function from($fromlist) {
        $this->__from__ = array_merge($this->__from__, $fromlist);
        return $this;
    }
    
    public function returning($returninglist) {
        $this->__returning__ = array_merge($this->__returning__, $returninglist);
        return $this;
    }

}