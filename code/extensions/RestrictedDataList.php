<?php

/**
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class RestrictedDataList extends Extension
{
    
    /**
     * How many 'next' pages we look for new items before 
     * assuming there's no more to find
     */
    const MAX_FETCH_DEPTH = 10;
    
    public function restrict($perm = 'View')
    {
        $restrictFilter = function ($item) use ($perm) {
            if ($item->hasExtension('Restrictable') && $item->checkPerm($perm)) {
                return true;
            } elseif ($item->hasMethod('can' . $perm)) {
                $method = 'can' . $perm;
                return $item->$method();
            } else {
                return $item->canView();
            }
        };
        
        $unrestrictedCount = $this->owner->count();
        
        $list = $this->owner->filterByCallback($restrictFilter);
        
        $listCount = $list->count();
        
        // otherwise we need to get the 
        $limitInfo = $this->owner->dataQuery()->getFinalisedQuery()->getLimit();
        if (isset($limitInfo['limit'])) {
            $limit = $limitInfo['limit'];
        } else {
            $limit = 0;
        }

        if ($limit) {
            $list->QueryOffset = $limitInfo['start'] + $limit;
        }

        if ($unrestrictedCount == $listCount) {
            return $list;
        }

        if ($limit && $unrestrictedCount == $limit) {
            $targetNumber = (int) trim($limit);
            $count = $list->count();
            $lastCount = $count;
            $numSearches = 0;
            $newOffset = $limitInfo['start'];
            while ($count < $targetNumber) {
                $nextOffset = $newOffset = $newOffset + $targetNumber;
                $nextList = $this->owner->limit($targetNumber, $newOffset);
                foreach ($nextList as $item) {
                    $nextOffset++;
                    if ($item->hasExtension('Restrictable')) {
                        if ($item->checkPerm($perm)) {
                            $list->push($item);
                        }
                    } elseif ($item->canView()) {
                        $list->push($item);
                    }

                    if ($list->count() >= $targetNumber) {
                        break;
                    }
                }

                $count = $list->count();
                // if we haven't actually increased, we'll just bail here
                if ($lastCount == $count && $numSearches++ > self::MAX_FETCH_DEPTH) {
                    break;
                }
                $lastCount = $count;
            }
            
            $list->QueryOffset = $nextOffset;
        }
        
        return $list;
    }

    public function restrictedByID($id, $perm = 'View')
    {
        $item = $this->owner->byID($id);
        if (!$item) {
            return null;
        }
        
        if ($item->hasExtension('Restrictable') && $item->checkPerm($perm)) {
            return $item;
        } elseif ($item->hasMethod('can' . $perm)) {
            $method = 'can' . $perm;
            return $item->$method() ? $item : null;
        } else {
            return $item->canView() ? $item : null;
        }
    }
}
