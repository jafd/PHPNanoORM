<?php

/**
 * @package MTMHelper
 * @author Yaroslav "jafd" Fedevych <jaroslaw.fedewicz@gmail.com>
 *
 *
 * This class is used to fetch a collection of items connected via a helper table, typically when
 * they have the many-to-many relation type. It has no notion that the connecting table might be
 * an entity itself, it just uses the table.
 *
 * It can work both for the case of a one-to-many and many-to-many filtering.
 */
namespace phpnanoorm;
class MTMHelper {

        protected $my_field,
                $my_entity,
                $foreign_field,
                $foreign_entity,
                $via_table,
                $via_my,
                $via_foreign,

                $my_values,
                $foreign_values,
                $mine_is_scalar,
                $foreign_is_scalar,

                $collection;

        /**
         * Initialize the object.
         *
         * @param mixed $my_field The field in "my" table which foreign object is meant to refer to (usually a primary key)
         *        If string, this will be a single field. In the case of a compound key, an array of strings
         *        is accepted.
         *
         * @param string $my_entity The entity name for "my" object. Must name a class.
         * @param mixed $foreign_field The foreign field to which "my" object refers. The rules are the same as for $my_field.
         * @param string $foreign_entity The entity name for the foreign object. Must name a class.
         * @param string $via_table The name of the auxiliary table. Note that this is name of a table, not an entity.
         * @param mixed $via_my The field in the auxiliary table which refers to "my" object. Can be a string if a single field, or array
         *        if compound.
         * @param mixed $via_foreign The field in the auxiliary table which refers to "their" object. Can be a string if a single field, or array
         *        if compound.
         */
        public function __construct($my_field, $my_entity, $foreign_field, $foreign_entity, $via_table, $via_my, $via_foreign) {
                $this->my_field = $my_field;
                $this->my_entity = $my_entity;
                $this->foreign_field = $foreign_field;
                $this->foreign_entity = $foreign_entity;
                $this->via_table = $via_table;
                $this->via_my = $via_my;
                $this->via_foreign = $via_foreign;

                $this->mine_is_scalar = is_scalar($my_field);
                $this->foreign_is_scalar = is_scalar($foreign_field);
        }

        /**
         * Get the relevant collection (foreign-entity-collection) with all necessary filters injected.
         */
        public function getCollection() {
                if (!$this->collection) {
			$cls = $this->foreign_entity;
                        $e = new $cls();
                        $this->collection = $e->getCollection();
                        $this->collection->filterExternal(array($this, 'processFilters'));
                }
                return $this->collection;
        }

        /**
         * Do the actual SQL injection. This function is not meant to be called by the end programmer. It is public
         * solely because it is called by an external entity which is the collection object.
         *
         * Please don't use this method directly.
         *
         */
        public function processFilters(SQL\Select $s) {
                // JOIN <via> ON <foreign_key = via_foreign>
                // JOIN <my> ON <via_my = my_key>
                if ($this->my_values) {
			$cls = $this->my_entity;
                        $mye = new $cls();
                        $my_table = $mye->getTableName();
			$cls = $this->foreign_entity;
                        $fe = new $cls();
                        $foreign_table = $fe->getTableName();

                        if ($this->foreign_is_scalar) {
                                $s->join(sprintf('JOIN %s ON (%s = %s)', $this->via_table, implode('.', array($foreign_table, $this->foreign_field)), implode('.', array($this->via_table, $this->via_foreign))));
                        } else {
                                $buffer = array();
                                foreach($this->foreign_field as $fkey => $fval) {
                                        $buffer[] = sprintf('(%s = %s)', $this->via_table.'.'.$this->via_foreign[$fkey], $foreign_table.'.'.$fval);
                                }
                                $s->join(sprintf('JOIN %s ON (%s)', $this->via_table, implode(' AND ', $buffer)));
                        }

                        if ($this->mine_is_scalar) {
                                $s->join(sprintf('JOIN %s ON (%s = %s)', $my_table, $my_table.'.'.$this->my_field, $this->via_table.'.'.$this->via_my));
                        } else {
                                $buffer = array();
                                foreach($this->my_field as $mkey => $mval) {
                                        $buffer[] = sprintf('(%s = %s)', $this->via_table.'.'.$this->via_my[$mkey], $my_table.'.'.$mval);
                                }
                                $s->join(sprintf('JOIN %s ON (%s)', $my_table, implode(' AND ', $buffer)));
                        }
                        // WHERE

                        $uq = md5(uniqid());
                        $bindprefix = 'mtm_'.$uq.'_'.$mye->getSchema().'_'.$mye->getTable();

                        if ($this->mine_is_scalar) {
                                $bindprefix .= '_'.$this->my_field;
                                if (is_array($this->my_values)) {
                                        // IN
                                        $bindbuffer = array();
                                        foreach($this->my_values as $mykey => $myval) {
                                                $bindp = $bindprefix.'_'.$mykey;
                                                $bindbuffer[] = ':'.$bindp;
                                                $s->bind($bindp, $myval);
                                        }
                                        $s->where(sprintf('%s IN (%s)', $my_table.'.'.$this->my_field, implode(', ', $bindbuffer)));
                                } else {
                                        $s->where(sprintf('%s = :%s', $my_table.'.'.$this->my_field, $bindprefix));
                                        $s->bind($bindprefix, $this->my_values);
                                }
                        } else {
                                // Build a whole scary SQL monster statement, yeah
                                // ((x=a) AND (y=b)) OR ((x=c) AND (y=d)) OR ...
                                $or_buffer = array();
                                $i = 0;
                                foreach ($this->my_values as $record) {
                                        $and_buffer = array();
                                        foreach ($record as $key => $value) {
                                                $and_buffer[] = sprintf('(%s = :%s)', $my_table.'.'.$this->my_fields[$key], $bindprefix.'_'.$this->my_fields[$key].'_'.$i);
                                                $s->bind($bindprefix.'_'.$this->my_fields[$key].'_'.$i, $value);
                                        }
                                        $or_buffer[] = implode(' AND ', $and_buffer);
                                        $i++;
                                }
                                $s->where(implode(' OR ', $or_buffer));
                        }
                }
                if ($this->foreign_values) {
                        // No join required!

                        $uq = md5(uniqid());
                        $bindprefix = 'mtm_'.$uq.'_'.$fe->getSchema().'_'.$fe->getTable();

                        if ($this->foreign_is_scalar) {
                                $bindprefix .= '_'.$this->foreign_field;
                                if (is_array($this->foreign_values)) {
                                        // IN
                                        $bindbuffer = array();
                                        foreach($this->foreign_values as $fkey => $fval) {
                                                $bindp = $bindprefix.'_'.$fkey;
                                                $bindbuffer[] = ':'.$bindp;
                                                $s->bind($bindp, $fval);
                                        }
                                        $s->where(sprintf('%s IN (%s)', $foreign_table.'.'.$this->foreign_field, implode(', ', $bindbuffer)));
                                } else {
                                        $s->where(sprintf('%s = :%s', $foreign_table.'.'.$this->foreign_field, $bindprefix));
                                        $s->bind($bindprefix, $this->foreign_values);
                                }
                        } else {
                                // Build a whole scary SQL monster statement, yeah
                                // ((x=a) AND (y=b)) OR ((x=c) AND (y=d)) OR ...
                                $or_buffer = array();
                                $i = 0;
                                foreach ($this->foreign_values as $record) {
                                        $and_buffer = array();
                                        foreach ($record as $key => $value) {
                                                $and_buffer[] = sprintf('(%s = :%s)', $foreign_table.'.'.$this->foreign_fields[$key], $bindprefix.'_'.$this->foreign_fields[$key].'_'.$i);
                                                $s->bind($bindprefix.'_'.$this->foreign_fields[$key].'_'.$i, $value);
                                        }
                                        $or_buffer[] = implode(' AND ', $and_buffer);
                                        $i++;
                                }
                                $s->where(implode(' OR ', $or_buffer));
                        }
                }
                return $s;
        }


        protected function _hasScalarsOnly($array) {
                foreach ($array as $row) {
                        if (!is_scalar($row))
                                return false;
                }
                return true;
        }

        protected function _hasArraysOnly($array) {
                foreach ($array as $row) {
                        if (!is_array($row))
                                return false;
                }
                return true;
        }

        protected function _matchesKeys($keydef, $valuedef) {
                $kk = array_keys($keydef);
                sort($kk);
                $vk = array_keys($valuedef);
                sort($vk);
                return  $kk == $vk;
        }

        protected function _keyValueSanityCheck($values, $dbkey) {
                if (!is_array($dbkey)) {
                        // The key is scalar. So any array must be of scalars only.
                        if (is_array($values)) {
                                foreach($values as $key => $value) {
                                        if (!is_scalar($value)) {
                                                throw new \RuntimeException ("An array of scalars expected, but found a non-scalar at position $key.");
                                        }
                                }
                        } elseif (!is_scalar($values)) {
                                throw new \RuntimeException ("Only scalars and arrays of scalars are accepted as my fields.");
                        }
                } else {
                        // The key is compound. So an array is a must. It must fulfill the following:
                        // 1. The array is made only of scalars and its keys match the keys in my_fields.
                        // 2. The array is made only of arrays all of which correspond to the p.1.
                        if (!is_array($values)) {
                                throw new \RuntimeException ("The key is compound but non-array was passed.");
                        }
                        if (!$this->_hasScalarsOnly($values) && !$this->_hasArraysOnly($values)) {
                                throw new \RuntimeException ("The values cannot comprise both arrays and scalars at a single time.");
                        }
                        if ($this->_hasScalarsOnly($values) && !$this->_matchesKeys($dbkey, $values)) {
                                throw new \RuntimeException ("The values do not match field definition.");
                        }
                        if ($this->_hasArraysOnly($values)) {
                                foreach($values as $row) {
                                        if (!$this->_matchesKeys($dbkey, $row)) {
                                                throw new \RuntimeException ("The values do not match field definition.");
                                        }
                                }
                        }
                }
        }

        /**
         * Filter values of "my" primary keys.
         *
         * @param mixed $values Depending on key type: a scalar or an array of scalars if non-compound key, or an array of arrays of scalars
         *        if the key is compound.
         * @return MTMHelper The modified object, useful in expressions and chaining.
         */
        public function filterMyRecords($values) {
                $this->_keyValueSanityCheck($values, $this->my_field);
                $this->my_values = $values;
                return $this;
        }

        /**
         * Filter values of "foreign" record keys.
         *
         * @param mixed $values Depending on key type: a scalar or an array of scalars if non-compound key, or an array of arrays of scalars
         *        if the key is compound.
         * @return MTMHelper The modified object, useful in expressions and chaining.
         */
        public function filterForeignRecords($values) {
                $this->_keyValueSanityCheck($values, $this->foreign_field);
                $this->foreign_values = $values;
                return $this;
        }


}
