<?php

/**
 * @package Entity - a single database entity, such as a row in a table/view.
 */

namespace phpnanoorm;

class Entity implements \Countable, \Iterator, \ArrayAccess {

    /**
     * @property $__table__
     * @access protected
     * @var string
     * Tells the primary name of the table for this entity.
     */
    protected $__table__ = '';
    /**
     * @property $__saveable__
     * @access protected
     * @var bool
     * Tells if this entity can be saved. If false, no update/delete
     * can be performed on it. This usually applies to read-only views.
     */
    protected $__saveable__ = true;
    /**
     * @property $__schema__
     * @access protected
     * Tells the schema to which the entity does belong.
     * @var string
     */
    protected $__schema__ = 'public';
    /**
     * @property $__primary_name__
     * @access protected
     * Tells the name of the field which is the primary key. If this is
     * an array, then the primary key is considered compound.
     * @var mixed
     */
    protected $__primary_name__ = 'id';
    /**
     * @property $dirty
     * @access protected
     * Tells if the entity was modified. Normally this is determined by the
     * state of its fields, but the entity can also be touch()'ed to force update.
     * @var bool
     */
    protected $dirty = false;
    /**
     * @property $__DATA__
     * @access protected
     * Main data repository for the entity. It only contains fields that can be read
     * or written directly to an entity's table.
     *
     * Note that no introspection is attempted. The user of class is supposed to give it
     * valid fields.
     * @var array
     */
    protected $__DATA__;
    /**
     * @property $__MODIFIEDDATA__
     * @access protected
     *
     * The data that was modified during the object's lifetime. It helps to reduce insert/update
     * statements and to check if the saving is needed at all.
     * @var array
     */
    protected $__MODIFIEDDATA__;
    /**
     * @property $__FOREIGNDATA__
     * @access protected
     * Any object which is referenced by a foreign key field lands there when requested (and gets cached).
     * When the object is being saved, the values from this array take precedence over whatever the respective
     * foreign key fields might contain.
     * @var array
     */
    protected $__FOREIGNDATA__;
    /**
     * @property $__connection__
     * @access protected
     * The current connection handle.
     * @var PDO
     */
    protected $__connection__;

    /**
     * The constructor.
     * @access public
     * @param mixed $pk (default = null) The primary key, to load the object from the database
     *              right away
     * @param PDO $conn (default = null) The PDO connection handle. If omitted, uses ConnectionManager.
     */
    public function __construct($pk = null, \PDO $conn = null) {
        $this->__DATA__ = array();
        $this->__MODIFIEDDATA__ = array();
        if (!is_null($conn))
        $this->setConnection($conn);
        if ($pk) {
            $o = $this->retrieveByPK($pk);
            if ($o)
            $this->setDataDict($o->getDataDict(), false);
        }
    }

    /**
     * retrieveByPK - load object by its primary key.
     *
     * @param mixed $value. If the primary key is compound, treated as an associative array,
     *        keys being field names.
     * @param PDO $conn the PDO handle.
     * @return Entity the entity loaded
     */
    public function retrieveByPK($value, \PDO $conn = null) {
        if (is_null($conn)) {
            $conn = $this->getConnection();
        }
        $coll = $this->getCollection($conn);
        if (is_array($this->__primary_name__)) {
            foreach($this->__primary_name__ as $fld){
                $coll->filterAnyFieldEq(implode('.', array($this->__schema__, $this->__table__, $fld)), $value[$fld]);
            }
        } else {
            $pname = implode('.', array($this->__schema__, $this->__table__, $this->__primary_name__));
            $coll->filterAnyFieldEq($pname, $value);
        }
        return $this->_getFirst($coll);
    }

    /**
     * _getForeignKeys - return foreign keys description
     * @return array
     *
     * Override this method if you have foreign keys. It must return an array of the following
     * structure:
     *
     * 'fieldName' => array(
     *   'entity' => 'ClassName', // foreign entity's class name, an Entity descendant
     *   'foreign_field' => 'field_name', // the name of key field in the foreign table
     *   'my_field' => 'field_name', // The field name in our table that references foreign entity
     *   'collection' => true/false // true if multiple records can be referenced
     *   'via' => array( // Present if this is a many-to-many relationship
     *     'table' => 'via_table', // The table name for intermediary table
     *     'this' => 'key_name', // The key field in 'via' table referencing our primary key field
     *     'other' => 'key_name', // The key field in 'via' table referencing others' primary key field
     *   )
     * )
     *
     * After that, fieldName or getFieldName will return referenced entity or collections of
     * referenced entities.
     *
     */
    protected function _getForeignKeys() {
        return array();
    }

    protected function _getLinkedData($key) {
        $fkeys = $this->_getForeignKeys();
        if (isset($fkeys[$key]['via'])) {
            $via = $fkeys[$key]['via'];
            $mh = new MTMHelper($this->__primary_name__, get_class($this), $fkeys[$key]['foreign_field'], $fkeys[$key]['entity'], $via['table'], $via['this'], $via['other']);
            $mh->filterMyRecords($this->offsetGet($fkeys[$key]['my_field']));
            $collection = $mh->getCollection();
        } else {
            $entity = new $fkeys[$key]['entity']();
            $collection = $entity->getCollection();
            $collection->filterAnyFieldEq($fkeys[$key]['foreign_field'], $this->offsetGet($fkeys[$key]['my_field']));
        }
        if (isset($fkeys[$key]['collection']) && !$fkeys[$key]['collection']) {
            return $this->_getFirst($collection);
        }
        return $collection;
    }

    protected function _getFirst($coll) {
        return isset($coll[0]) ? $coll[0] : null;
    }


    /**
     * Returns current connection handle.
     *
     * @return PDO
     */
    public function getConnection() {
        if (!$this->__connection__ instanceof \PDO)
        $this->__connection__ = ConnectionManager::getInstance()->getHandle();
        return $this->__connection__;
    }

    public function setConnection(\PDO $connection) {
        $this->__connection__ = $connection;
        return $this;
    }

    protected function _genericGet($attr, $default = null) {
        if (in_array($attr, array_keys($this->_getForeignKeys()))) {
            if (!isset($this->__FOREIGNDATA__[$attr]))
            $this->__FOREIGNDATA__[$attr] = $this->_getLinkedData($attr);
            return $this->__FOREIGNDATA__[$attr];
        } elseif (in_array($attr, array_keys($this->__DATA__))) {
            return $this->__DATA__[$attr];
        } else {
            return $default;
        }
    }

    protected function _genericSet($attr, $value) {
        $fk = $this->_getForeignKeys();
        if (in_array($attr, array_keys($this->_getForeignKeys())) &&
        $value instanceof $fk[$attr]['entity']) {
            $this->__FOREIGNDATA__[$attr] = $value;
            $this[$fk[$attr]['my_field']] = $value[$fk[$attr]['foreign_field']];
        } else {
            $this[$attr] = $value;
        }
        return $this;
    }

    /**
     * Prepares foreign data before saving the object. Updates all foreign
     * references to those of actual foreign objects. This is done right before
     * saving, so that foreign data might be updated before being saved once.
     *
     * Parent relations are updated prior to saving.
     * @return void
     */
    protected function _updateParentForeignData() {
        $fkeys = $this->_getForeignKeys();
        if ($this->__FOREIGNDATA__) {
            foreach ($this->__FOREIGNDATA__ as $attribute => $value) {
                $fk = $fkeys[$attribute];
                if ($fk['collection']) {
                    continue;
                }
                $value->save();
                $this[$fk['my_field']] = $value[$fk['foreign_field']];
            }
        }
    }

    protected function _updateChildForeignData() {
        $fkeys = $this->_getForeignKeys();
        if ($this->__FOREIGNDATA__) {
            foreach ($this->__FOREIGNDATA__ as $attribute => $value) {
                $fk = $fkeys[$attribute];
                if (!$fk['collection']) {
                    continue;
                }
                foreach ($value as $item) {
                    $item[$fk['foreign_field']] = $this[$fk['my_field']];
                }
            }
        }
    }

    public function __call($name, $args) {
        if (preg_match('/^get/', $name)) {
            return $this->_genericGet(Util::decamelize(Util::unprefix($name)), isset($args[0])?$args[0]:null);
        } elseif (preg_match('/^set/', $name)) {
            return $this->_genericSet(Util::decamelize(Util::unprefix($name)), $args[0]);
        }
    }

    public function __set($name, $value) {
        return $this->_genericSet($name, $value);
    }

    public function __get($name) {
        return $this->_genericGet($name);
    }

    public function __isset($name) {
        return array_key_exists($name, $this->__DATA__);
    }

    public function getTableName($param = '') {
        if ($this->__table__ != '') {
            return (empty($this->__schema__)?'':($this->__schema__.'.')).$this->__table__;
        }
        return Util::decamelize(Util::unprefix(get_class($this)));
    }

    protected function _prepareInput($data) {
        $result = array();
        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 't' : 'f';
            }
            $result[$key] = $value;
        }
        return $result;
    }

    /**
     * Tells if the save operation will have any work to do.
     *
     * @return bool
     */
    protected function dirty() {
        //
        // Check if there is any work to do.
        //
        if ((!count($this->__MODIFIEDDATA__)) || (!count($this->__DATA__))) {
            return false;
        }
        //
        // If we were forcibly touched, make sure there is work to do.
        //
        if (!count($this->__MODIFIEDDATA__)) {
            $this->__MODIFIEDDATA__ = $this->__DATA__;
        }
        return true;
    }

    protected function _doInsert(\PDO $pdo) {
        $sql = new SQL\Insert();
        if (is_array($this->__primary_name__)) {
            // Compound primary key
            $sql->returning($this->__primary_name__);
        } else {
            $sql->returning(array($this->__primary_name__));
        }
        $sql->table($this->getTableName('insert'));
        $sql->set($this->__MODIFIEDDATA__);
        $stmt = $pdo->prepare(strval($sql));
        if($result = $stmt->execute($sql->getBindings())) {
            $row = $stmt->fetch();
            foreach($row as $key => $value)
            $this[$key] = $value;
        } else {
            throw new Exception("Failed to INSERT. ".implode(" ", $pdo->errorInfo()));
        }
        return true;
    }

    protected function _doUpdate(\PDO $pdo) {
        $sql = new SQL\Update();
        $sql->table($this->getTableName('update'));
        $set = $where = '';
        $bindings = array();
        foreach(array_keys($this->__MODIFIEDDATA__) as $field) {
            if ((!is_array($this->__primary_name__) && ($field != $this->__primary_name__)) ||
            (is_array($this->__primary_name__) && (!in_array($field, $this->__primary_name__)))) {
                $sql->set(array($field => $this->__MODIFIEDDATA__[$field]));
            }
        }
        if (is_array($this->__primary_name__)) {
            foreach($this->__primary_name__ as $field) {
                $where[] = "($field = :$field)";
                $bindings[":$field"] = $this->__DATA__[$field];
            }
        } else {
            $pname = implode('.', array($this->__schema__, $this->__table__, $this->__primary_name__));
            $pbind = implode('_', array($this->__schema__, $this->__table__, $this->__primary_name__));
            $where[] = "({$pname} = :{$pbind})";
            $bindings[":$pbind"] = $this->__DATA__[$this->__primary_name__];
        }
        $sql->where($where);
        $sql->bindMultiple($bindings);
        $stmt = $pdo->prepare(strval($sql));
        if ($result = $stmt->execute($sql->getBindings())) {
            return $stmt->rowCount();
        }
        throw new Exception("Failed to UPDATE (".strval($sql)."). ".implode("\n", $pdo->errorInfo()));
    }

    protected function beforeSave() {
        return true;
    }

    protected function afterSave() {
        return true;
    }

    public function save() {
        if (!$this->beforeSave()) // validate, maybe
        return false;
        if (!$this->dirty()) {
            return true;
        }
        // Prepare primary keys
        if (is_array($this->__primary_name)) {
            foreach($this->__primary_name__ as $key) {
                if (isset($this[$key]) && is_null($this[$key])) {
                    unset($this[$key]);
                }
            }
        } else {
            if (isset($this[$this->__primary_name__]) && is_null($this[$this->__primary_name__])) {
                unset($this[$this->__primary_name__]);
            }
        }
        //
        $this->_updateParentForeignData();
        $pdo = $this->getConnection();
        if (is_array($this->__primary_name__) || !empty($this->__DATA__[$this->__primary_name__])) {
            // Do update-then-insert
            if ($this->_doUpdate($pdo) == 0)
            $this->_doInsert($pdo);
        } else {
            // Do insert
            $this->_doInsert($pdo);
        }
        $this->_updateChildForeignData();
        $this->__MODIFIEDDATA__ = array();
        $this->dirty = false;
        return $this->afterSave();
    }

    public function touch() {
        $this->dirty = true;
        return $this;
    }

    public function getDataDict() {
        return $this->__DATA__;
    }

    public function setDataDict(array $data, $touch = true) {
        $this->__DATA__ = $data;
        if ($touch)
        $this->__MODIFIEDDATA__ = $data;
        reset($this->__DATA__);
    }

    public function delete($preserve_primary = false) {
        $where = $primaries = array();
        if (is_array($this->__primary_name__)) {
            foreach($this->__primary_name__ as $field) {
                $where[] = "($field = :$field)";
                $primaries[$field] = $this->$field;
            }
        } else {
            $where[] = "({$this->__primary_name__} = :{$this->__primary_name__})";
            $primaries[$this->__primary_name__] = $this->{$this->__primary_name__};
        }
        $stmt = $this->getConnection()->prepare(sprintf("DELETE FROM %s WHERE ".implode(', ', $where), $this->getTableName('update')));
        if(!$stmt->execute($primaries))
        throw new Exception("Error while deleting: ".implode(' ', $this->getConnection()->errorInfo()));
        if(!$preserve_primary) {
            if (is_array($this->__primary_name__)) {
                foreach($this->__primary_name__ as $field) {
                    $this->$field = null;
                }
            } else {
                $this->{$this->__primary_name__} = null;
            }
        }
    }

    /**
     * Returns the name for collection class correspondent to this entity class.
     * In this implementation, this is the class name + 'Collection'. If your naming
     * convention differs, please override this method.
     *
     * @return string
     */
    protected function _getCollectionName() {
        return get_class($this).'Collection';
    }

    /**
     * Returns an instance of collection correspondent to this entity class.
     *
     * @return EntityCollection
     */
    public function getCollection() {
        $cname = $this->_getCollectionName();
        return new $cname($this->getConnection());
    }

    //
    // Countable Implementation
    //

    /**
     * Returns the count of items (fields) in this object.
     *
     * @return int
     */
    public function count() {
        return count($this->__DATA__);
    }

    //
    // ArrayAccess
    //

    public function offsetGet($offset) {
        return $this->__DATA__[$offset];
    }

    public function offsetSet($offset, $value) {
        $this->__DATA__[$offset] = $value;
        $this->__MODIFIEDDATA__[$offset] = $value;
    }

    public function offsetUnset($offset) {
        unset($this->__DATA__[$offset]);
        unset($this->__MODIFIEDDATA__[$offset]);
    }

    public function offsetExists($offset) {
        return array_key_exists($offset, $this->__DATA__);
    }

    //
    // Iterator
    //

    public function rewind() {
        return reset($this->__DATA__);
    }

    public function current() {
        return current($this->__DATA__);
    }

    public function key() {
        return key($this->__DATA__);
    }

    public function next() {
        return next($this->__DATA__);
    }

    public function valid() {
        $keys = array_keys($this->__DATA__);
        $last = $keys[count($keys)-1];
        return key($this->__DATA__) !== $last;
    }
}

