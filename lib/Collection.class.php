<?php

/**
 * @package Collection
 * @author Yaroslav "jafd" Fedevych <jaroslaw.fedewicz@gmail.com>
 *
 * This class is the common ancestor for classes which represent sets of entities.
 * It has the means to compose an efficient enough SQL statement, to efficiently
 * enough fetch the data and "hydrate" it into actual entity objects.
 *
 * Each Entity descendant must have a correspondent Collection descendant,
 * or upleasant consequences might unleash. (Entity::getCollection() is expected to
 * return a valid class name, for one.)
 *
 * The starter kit is very scarce on filters, but you are free to invent your own. To do that,
 * you need to define and implement:
 *
 *  * a public method which accepts filtering values. It is a convention that its name starts
 *    with 'filter' and that it returns $this.
 *  * your public method must file the values accepted into the __filters__ array under some unique key.
 *  * a protected method with the name starting with '_filter' and accepting a Select. Based
 *    on what is recorded in __filters__, it modifies the statement and must return it back.
 *
 * That is all. Your protected method will be called automatically when needed. Note that the method is always
 * called, so it must take care of the question whether or not to mangle the statement if, say, there
 * was no value passed and hence no need for it.
 *
 * Not only filters (which are meant usually to mangle WHERE, HAVING and GROUP BY clauses) are alterable.
 * You can redefine even the SQL statement which is used as the base. The basic form is
 *
 *     SELECT * FROM <primary table>
 *
 * This can be altered by overriding the initStatement method. Refer to its documentation for details.
 *
 * Also note:
 *
 *  * the collections are accessible as arrays and iterators (so, foreach will work, as will next and friends)
 *  * actual database interactions are done on demand, there's no way to force them out of the context.
 */

namespace phpnanoorm;

class Collection implements \ArrayAccess, \Iterator, \Countable {

    protected $__ITEMS__;
    protected static $__item_class__ = 'Entity';
    protected $__filters__, $__options__, $__connection__;
    protected $dirty = true;
    public $totalRows = 0;

    /**
     * Initialize the object.
     */
    public function __construct(\PDO $conn = null) {
        $this->__ITEMS__ = array();
        if (!is_null($conn))
        $this->setConnection($conn);
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


    /**
     * Get the primary table's name.
     */
    public function  getTableName($param = '') {
        $entity = $this->getEntity();
        return $entity->getTableName($param);
    }

    /**
     * Save all collection items, done in a single transaction.
     */
    public function save() {
        $conn = nblConnectionManager::getInstance()->getHandle();
        try {
            $conn->beginTransaction();
            foreach($this as $item)
            $item->save();
            $conn->commit();
        }
        catch (Exception $e) {
            $conn->rollBack();
        }
    }

    protected function _filterTableName(SQL\Select $s) {
        if (!$this->getTableName('select')){
            $s->from($this->getTableName('select'));
            return $s;
        }
        $i = $this->getEntity();
        $tbl = $i->getTableName('select');
        $s->from(array($tbl));
        return $s;
    }

    protected function _filterAnyFieldEq(SQL\Select $s) {
        if (isset($this->__filters__['__anyfield__']) && is_array($this->__filters__['__anyfield__'])) {
            foreach($this->__filters__['__anyfield__'] as $field => $value) {
                $binding = preg_replace('/\./', '_', $field);
                $s->where("$field = :$binding"."_eq");
                $s->binding($binding."_eq", $value);
            }
        }
        return $s;
    }

    protected function _filterAnyFieldNotEq(SQL\Select $s) {
        if (isset($this->__filters__['__anyfield!__']) && is_array($this->__filters__['__anyfield!__'])) {
            foreach($this->__filters__['__anyfield!__'] as $field => $value) {
                $binding = preg_replace('/\./', '_', $field);
                $s->where("$field <> :$binding"."_ne");
                $s->binding($binding."_ne", $value);
            }
        }
        return $s;
    }

    protected function _filterOrderField(SQL\Select $s) {
        if (isset($this->__filters__['__order__'])) {
            foreach($this->__filters__['__order__'] as $item) {
                if (empty($item[1]) || in_array(strtolower($item[1]), array('asc', 'desc')))
                $s->order(implode(' ', $item));
            }
        }
        return $s;
    }

    protected function _filterExternal(SQL\Select $s) {
        if (isset($this->__filters__['__externals__']) && (count($this->__filters__['__externals__']) > 0)) {
            foreach($this->__filters__['__externals__'] as $value) {
                if (is_callable($value)) {
                    $result = call_user_func($value, $s);
                    if (!($result instanceof SQL\Select)) {
                        throw new \RuntimeException ("External filters must return a SQL\Select!");
                    }
                    $s = $result;
                } else {
                    var_dump($value);
                    throw new \RuntimeException ("External filters must be callable!");
                }
            }
        }
        return $s;
    }

    public function filterExternal($callable) {
        if (!isset($this->__filters__['__externals__'])) {
            $this->__filters__['__externals__'] = array($callable);
        }
        $this->__filters__['__externals__'][] = $callable;
    }

    public function filterAnyFieldEq($fieldname, $value) {
        $this->dirty = true;
        $this->__filters__['__anyfield__'][$fieldname] = $value;
        return $this;
    }

    public function filterAnyFieldNotEq($fieldname, $value) {
        $this->dirty = true;
        $this->__filters__['__anyfield!__'][$fieldname] = $value;
        return $this;
    }

    public function filterOrderField($fieldnames) {
        $this->dirty = true;
        $this->__filters__['__order__'] = $fieldnames;
        return $this;
    }

    public function filterOffset($value) {
        $this->dirty = true;
        $this->__filters__['offset'] = $value;
        return $this;
    }

    public function filterLimit($value) {
        $this->dirty = true;
        $this->__filters__['limit'] = $value;
        return $this;
    }

    /**
     * Returns class name for the entity correspondent to this collection.
     * In this implementation, it is the class name sans 'Collection'. If your
     * entity/collection pair follows a different scheme, please override this
     * method.
     *
     * @return string
     */
    protected function _getEntityName() {
        return preg_replace('/Collection$/', '', get_class($this));
    }

    /**
     * Return an instance of the entity correspondent to this collection class.
     *
     * @return Entity
     */
    public function getEntity() {
        $name = $this->_getEntityName();
        return new $name(null, $this->getConnection());
    }

    protected function getFilterMethods() {
        $cls = new \ReflectionClass(get_class($this));
        $res = $cls->getMethods(\ReflectionMethod::IS_PROTECTED);
        $result = array();
        foreach ($res as $method) {
            if (preg_match('/^_filter[A-Z0-9]/', $method->name))
            $result[] = $method->name;
        }
        return $result;
    }

    /**
     * This method returns base SQL statement object to begin with. If SELECT * is
     * not what you want, you are free to override this method. If your entities are built in a compound
     * way, this is probably what you want. Please note that once elements are added into the statement,
     * there is really no way to remove them later. So the returned statement must contain everything
     * essential, and everything optional must be added with filters.
     */
    protected function initStatement() {
        $stmt = new SQL\Select ();
        return $stmt;
    }

    protected function assembleStatement() {
        $statement = $this->initStatement();
        foreach ($this->getFilterMethods() as $methodname) {
            $statement = $this->$methodname($statement);
        }
        return $statement;
    }

    protected function load() {
        $conn = ConnectionManager::getInstance()->getHandle();
        $statement = $this->assembleStatement();
        if (!isset($this->__filters__['offset']) && !isset($this->__filters__['limit'])) {
            $stmt = $conn->prepare($statement->__toString());
            if (!$stmt->execute($statement->getBindings())) {
                throw new \Exception("SQL Error (".$statement->__toString()."): ".implode("\n", $conn->errorInfo()));
            }
            $this->__ITEMS__ = $this->hydrate($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } else {
            $offset = isset($this->__filters__['offset']) ? (int)$this->__filters__['offset'] : 0;
            $limit = isset($this->__filters__['limit']) ? (int)$this->__filters__['limit'] : 0;
            $conn->beginTransaction();
            $stmt = $conn->prepare ("DECLARE sel_c CURSOR FOR ".strval($statement));
            if (!$stmt->execute($statement->getBindings())) {
                throw new \Exception("SQL Error (".$statement->__toString()."): ".implode("\n", $conn->errorInfo()));
            }
            $conn->query ("MOVE FORWARD $offset IN sel_c");
            $r = $conn->query ("FETCH $limit FROM sel_c");
            if (!$r) {
                throw new \Exception("SQL Error (".$statement->__toString()."): ".implode("\n", $conn->errorInfo()));
            }
            $this->__ITEMS__ = $this->hydrate($r->fetchAll(\PDO::FETCH_ASSOC));
            $this->totalRows = count($this->__ITEMS__) + $conn->exec("MOVE FORWARD ALL IN sel_c");
            $conn->rollBack();
        }
        $this->dirty = false;
    }

    protected function hydrate($collection) {
        $result = array();
        foreach ($collection as $item) {
            $newitem = $this->getEntity();
            $newitem->setDataDict($item, $this->getConnection());
            $result[] = $newitem;
        }
        return $result;
    }

    public function __set($name, $value) {
        throw new \Exception ("The attribute {$name} cannot be set from within collection.");
    }

    public function __get($name) {
        throw new \Exception ("The attribute {$name} cannot be queried from within collection.");
    }

    public function __call($name, $args) {
        if (preg_match('/^filter[A-Z0-9]/', $name)) {
            $this->__filters__[Util::decamelize(Util::unprefix($name))] = $args[0];
            $this->dirty = true;
        } else
        throw new \Exception("Cannot call nonexistent method $name.");
    }

    //
    // Countable
    //

    public function count() {
        if ($this->dirty) $this->load();
        return count($this->__ITEMS__);
    }

    //
    // ArrayAccess
    //

    public function offsetGet($offset) {
        if ($this->dirty) {
            $this->load();
        }
        return $this->__ITEMS__[$offset];
    }

    public function offsetSet($offset, $value) {
        if ($this->dirty) { 
            $this->load();
        }
        throw new \Exception("Collections are read-only!");
    }

    public function offsetUnset($offset) {
        if ($this->dirty) { 
            $this->load();
        }
        throw new \Exception("Collections are read-only!");
    }

    public function offsetExists($offset) {
        if ($this->dirty) $this->load();
        return isset($this->__ITEMS__[$offset]);
    }

    //
    // Iterator
    //

    public function rewind() {
        if ($this->dirty) {
            $this->load();
        }
        return reset($this->__ITEMS__);
    }

    public function current() {
        if ($this->dirty) {
            $this->load();
        }
        return current($this->__ITEMS__);
    }

    public function key() {
        if ($this->dirty) {
            $this->load();
        }
        return key($this->__ITEMS__);
    }

    public function next() {
        if ($this->dirty) {
            $this->load();
        }
        return next($this->__ITEMS__);
    }

    public function valid() {
        if ($this->dirty) {
            $this->load();
        }
        return $this->current() !== false;
    }

}
