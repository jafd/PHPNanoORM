<?php

/**
 * A mutable collection.
 *
 * Similar to Collection except that it can be altered,
 * by adding and removing items from it.
 * Nota bene: inserting an item into a collection will commit it
 * into the database, removing it will delete it from the database.
 *
 */

namespace phpnanoorm;

class MutableCollection extends Collection {
		protected $__autocommit__ = false;
		protected $__DELETED_ITEMS__ = array();

		public function autoCommit($new_value = null) {
				$prev = $this->__autocommit__;
				if (!is_null($new_value))
						$this->__autocommit__ = (bool) $new_value;
				return $prev;
		}

		public function offsetSet($index, $value) {
				if (!($value instanceof $this->__item_class__)) {
						throw new ArgumentException ("Incorrect argument type: {$this->__item_class__}, or a descendant, expected.");
				}
				$this->__ITEMS__[$index] = $value;
				if ($this->__autocommit__) {
						$value->save();
				}
		}

		public function save() {
				$result = parent::save();
				foreach($this->__DELETED_ITEMS__ as $key => $item) {
						$item->delete();
				}
				$this->__DELETED_ITEMS__ = array();
				return $result;
		}

		public function offsetUnset($index) {
				if (!$this->__autocommit__)
						$this->__DELETED_ITEMS__[] = $this->__ITEMS__[$index];
				else
						$this->__ITEMS__[$index]->delete();
				unset($this->__ITEMS__[$index]);
		}

}
