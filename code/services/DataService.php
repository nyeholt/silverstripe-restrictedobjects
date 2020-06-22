<?php

/**
 * A wrapper around DataObject:: static methods that perform proper security checks
 * when loading objects
 *
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class DataService
{

    /**
     * Shortcut for loading objects.
     *
     * Instead of $data->get('Type', 'Filter');
     *
     * you can simply call
     *
     * $data->Type('filter');
     *
     * @param type $method
     * @param type $arguments
     */
    public function __call($method, $arguments)
    {
        $type = null;
        $call = null;

        if (strpos($method, 'getOne') === 0) {
            $call = 'getOne';
        } elseif (strpos($method, 'getAll') === 0) {
            $call = 'getAll';
        } elseif (strpos($method, 'ById') > 0) {
            $call = 'ById';
        }

        if ($call) {
            $type = ucfirst(str_replace($call, '', $method));
        }

        if ($type && class_exists($type)) {
            array_unshift($arguments, $type);
            return call_user_func_array(array($this, lcfirst($call)), $arguments);
        }
        throw new Exception("Cannot get objects of unknown type in $method");
    }

    /**
     *
     * Retrieve a list of data objects
     *
     * @see DataObject::get()
     *
     * @param type $callerClass
     * @param string|array $filter
     *
     * @param type $sort
     * @param type $join
     * @param type $limit
     * @param type $containerClass
     * @return DataObjectSet
     */
    public function getAll($callerClass, $filter = null, $sort = "", $join = "", $limit = "", $requiredPerm = 'View')
    {
        return $this->loadObjects($callerClass, $filter, $sort, $join, $limit, $requiredPerm);
    }

    /**
     * Gets a single object
     *
     * @param type $callerClass
     * @param type $filter
     * @param type $cache
     * @param type $orderby
     * @return DataObject
     */
    public function getOne($callerClass, $filter = "", $cache = true, $orderby = "", $requiredPerm = 'View')
    {
        $items = $this->getAll($callerClass, $filter, $orderby, null, null, $requiredPerm);
        if ($items && count($items)) {
            return $items[0];
        }
    }

    /**
     * Return the given element, searching by ID
     *
     * @param string $callerClass The class of the object to be returned
     * @param int $id The id of the element
     * @param boolean $cache See {@link get_one()}
     *
     * @return DataObject The element
     */
    public function byId($callerClass, $id, $cache = true, $requiredPerm = 'View')
    {
        $id = (int) $id;
        $item = DataObject::get_by_id($callerClass, $id, $cache);
        if ($item && $item->hasExtension('Restrictable') && $item->checkPerm($requiredPerm)) {
            return $item;
        }

        if ($item && !$item->hasExtension('Restrictable') && $item->canView()) {
            return $item;
        }
    }

    /**
     * A reimplementation of DataObject::instance_get
     *
     * @param string $filter A filter to be inserted into the WHERE clause.
     * @param string $sort A sort expression to be inserted into the ORDER BY clause.  If omitted, self::$default_sort will be used.
     * @param string $join A single join clause.  This can be used for filtering, only 1 instance of each DataObject will be returned.
     * @param string $limit A limit expression to be inserted into the LIMIT clause.
     *
     * @return mixed The objects matching the filter, in the class specified by $containerClass
     */
    public function loadObjects($type, $filter = "", $sort = "", $join = "", $limit="", $requiredPerm = 'View')
    {
        if (!DB::isActive()) {
            throw new Exception("DataObjects have been requested before the database is ready. Please ensure your database connection details are correct, your database has been built, and that you are not trying to query the database in _config.php.");
        }

        $list = DataList::create($type);
        if ($filter) {
            if (is_array($filter)) {
                $list = $list->filter($filter);
            } else {
                $list = $list->where($filter);
            }
        }
        if ($sort) {
            $list = $list->sort($sort);
        }
        if ($limit) {
            if (is_string($limit)) {
                $limit = explode(',', $limit);
            }
            $list = $list->limit($limit[1], $limit[0]);
        }
        if ($join) {
            if (isset($join['table'])) {
                $join = array($join);
            }
            foreach ($join as $joinVal) {
                $list = $list->innerJoin($joinVal['table'], $joinVal['clause']);
                if (isset($joinVal['where'])) {
                    $list = $list->where($joinVal['where']);
                }
            }
        }

        $unrestrictedCount = $list->count();

        $ret = $this->filterList($list, $requiredPerm);

        // properly recalculate the offset that we had to use by recursively calling loadObjects
        // with the next page of info until we have enough, then return the actual offset used in the
        // list of objects
        if (isset($limit[1]) && $unrestrictedCount == $limit[1]) {
            $targetNumber = (int) trim($limit[1]);
            $count = $ret->count();
            $lastCount = $count;
            $newOffset = $limit[0];
            $nextOffset = $newOffset + $targetNumber;
            while ($count < $targetNumber) {
                $nextOffset = $newOffset = $newOffset + $targetNumber;
                $list = $list->limit($targetNumber, $newOffset);
                foreach ($list as $item) {
                    $nextOffset++;
                    if ($item->hasExtension('Restrictable')) {
                        if ($item->checkPerm($requiredPerm)) {
                            $ret->push($item);
                        }
                    } elseif ($item->canView()) {
                        $ret->push($item);
                    }

                    if ($ret->count() >= $targetNumber) {
                        break;
                    }
                }

                $count = $ret->count();
                // if we haven't actually increased, we'll just bail here
                if ($lastCount == $count) {
                    break;
                }
            }

            $ret->QueryOffset = $nextOffset;
        }

        return $ret;
    }

    /**
     * Filter the given list to return the accessible objects
     *
     * @param type $list
     */
    protected function filterList($list, $perm)
    {
        $ret = $list->filterByCallback(function ($item) use ($perm) {
            if ($item->hasExtension('Restrictable')) {
                return $item->checkPerm($perm);
            }
            return $item->canView();
        });
        return $ret;
    }
}


if (!function_exists('lcfirst')) {
    function lcfirst($str)
    {
        if (strlen($str) > 0) {
            return strtolower($str[0]) . substr($str, 1);
        }
        return $str;
    }
}
