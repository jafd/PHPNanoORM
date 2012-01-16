<?php

/**
 * @package Select
 *
 * This is a class used to build SELECT statements, assembling it from parts.
 *
 * @author jafd
 *
 */

namespace phpnanoorm\SQL;

class Select extends BaseStatement {

    protected $__select__, $__from__, $__joins__, $__where__, $__group__, $__having__, $__order__, $__bindings__, $__orwhere__ = array();
    protected $__offset__, $__limit__, $__primary_table__;

    public function __construct() {
        $this->__select__ =
        $this->__from__ =
        $this->__joins__ =
        $this->__where__ =
        $this->__group__ =
        $this->__having__ =
        $this->__order__ = array();
        $this->__primary_table__ = '';
    }

    public function select($item, $alias = '') {
        if ($alias == '')
        $this->__select__[] = $item;
        else
        $this->__select__[$alias] = $item;
        return $this;
    }

    public function join($sql, $alias = null) {
        if (is_null($alias)) {
            $alias = $sql;
        }
        $this->__joins__[$alias] = $sql;
        return $this;
    }

    public function order($by) {
        $this->__order__[$by] = $by;
        return $this;
    }

    public function group($by) {
        $this->__group__[] = $by;
        return $this;
    }

    protected function _add_having($cond, $operator) {
        $this->__having__[] = $this->_format_conditions($cond, $operator);
        return $this;
    }

    public function having($cond) {
        return $this->_add_having($cond, "AND");
    }

    public function orhaving($cond) {
        return $this->_add_having($cond, "OR");
    }

    public function offset($int) {
        $this->__offset__ = "$int";
        return $this;
    }

    public function limit($int) {
        $this->__limit__ = "$int";
        return $this;
    }

    public function __toString() {
        $sel = "*";
        if (count($this->__select__)) {
            $sel = $this->_aliases_to_sql($this->__select__);
        }
        $tables = $this->_aliases_to_sql($this->__from__);
        $joins = implode(" ", $this->__joins__);
        $order = implode(", ", $this->__order__);
        $group = implode(", ", $this->__group__);
        $where = implode(" AND ", $this->__where__);
        $having = implode(" AND ", $this->__having__);
        $limit = $this->__limit__;
        $offset = $this->__offset__;
        if ($limit)
        $limit = "LIMIT $limit";
        if ($offset)
        $offset = "OFFSET $offset";
        if ($where)
        $where = "WHERE $where";
        if ($having)
        $having = "HAVING $having";
        if ($group)
        $group = "GROUP BY $group";
        if ($order)
        $order = "ORDER BY $order";
        $result = "SELECT {$sel} FROM {$tables} {$joins} {$where} {$group} {$having} {$order} {$offset} {$limit}";
        return $result;
    }

}

