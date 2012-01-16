<?php
namespace phpnanoorm\SQL;

class Update extends BaseStatement {
    protected $__values__ = array();
    protected $__only__ = false;
    protected $__table__ = '';

    public function table($value) {
        $this->__table__ = $value;
        return $this;
    }

    public function set($values) {
        foreach($values as $column => $value) {
            if ($value instanceof SQLLiteral) {
                $value = strval($value);
                $this->__values__[] = $this->_quote_identifier($column)." = $value";
            } else {
                $u = md5(uniqid());
                $ncol = preg_replace('/[^A-Za-z0-9_]/', '_', $column).'_'.$u;
                $this->binding(":$ncol", $value);
                $this->__values__[] = $this->_quote_identifier($column)." = :$ncol";
            }
        }
        return $this;
    }

    public function only($value = true) {
        $this->__only__ = (bool)$value;
        return $this;
    }


    public function __toString() {
        if (!$this->__table__) {
            throw new RuntimeException('UPDATE statement must contain a table');
        }
        $table = $this->_quote_identifier($this->__table__);
        $set = implode(', ', $this->__values__);
        $only = $this->__only__ ? 'ONLY' : '';
        $tables = $this->_aliases_to_sql($this->__from__);
        $where = implode(" AND ", $this->__where__);
        $returning = $this->_aliases_to_sql($this->__returning__);
        if ($tables) {
            $where = "FROM $tables";
        }
        if ($where) {
            $where = "WHERE $where";
        }
        if ($returning) {
            $returning = "RETURNING $returning";
        }
        $result = "UPDATE {$only} {$table} SET {$set} {$tables} {$where} {$returning}";
        return $result;
    }
}
