<?php
namespace phpnanoorm\SQL;

class Insert extends BaseStatement {
    protected $__columns__ = array();
    protected $__values__ = array();
    protected $__table__ = '';

    public function table($value) {
        $this->__table__ = $value;
        return $this;
    }

    public function set($values) {
        foreach($values as $column => $value) {
            if ($value instanceof SQLLiteral) {
                $value = strval($value);
                $this->__values__[$column] = $value;
            } else {
                $u = md5(uniqid());
                $ncol = preg_replace('/[^A-Za-z0-9_]/', '_', $column).'_'.$u;
                $this->binding(":$ncol", $value);
                $this->__values__[$column] = ":$ncol";
            }
        }
        return $this;
    }

    public function __toString() {
        if (!$this->__table__) {
            throw new RuntimeException('INSERT statement must contain a table');
        }
        $table = $this->_quote_identifier($this->__table__);
        $columns = array();
        $values = array();
        foreach($this->__values__ as $column => $value) {
            $columns[] = $this->_quote_identifier($column);
            $values[] = $value;
        }
        $set = implode(', ', $this->__values__);
        $returning = $this->_aliases_to_sql($this->__returning__);
        if ($returning) {
            $returning = "RETURNING $returning";
        }
        $result = "INSERT INTO {$table} (".implode(', ', $columns).") VALUES (".implode(', ', $values).") {$returning}";
        return $result;
    }
}
