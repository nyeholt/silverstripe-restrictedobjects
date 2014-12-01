<?php

/**
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class RestrictedDataList extends Extension {
	
	public function restrict($perm = 'View') {
		$restrictFilter = function ($item) use ($perm) {
			if ($item->hasExtension('Restrictable') && $item->checkPerm($perm)) {
				return true;
			} else if ($item->hasMethod('can' . $perm)) {
				$method = 'can' . $perm;
				return $item->$method();
			} else {
				return $item->canView();
			}
		};
		
		$unrestrictedCount = $this->owner->count();
		
		$list = $this->owner->filterByCallback($restrictFilter);
		
		$listCount = $list->count();
		
		if ($unrestrictedCount == $listCount) {
			return $list;
		}
		
		// otherwise we need to get the 
		$limitInfo = $this->owner->dataQuery()->getFinalisedQuery()->getLimit();
		if (isset($limitInfo['limit'])) {
			$limit = $limitInfo['limit'];
		} else {
			$limit = 0;
		}
		
		if ($limit && $unrestrictedCount == $limit) {
			$targetNumber = (int) trim($limit);
			$count = $list->count();
			$lastCount = $count;
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
					} else if ($item->canView()) {
						$list->push($item);
					}

					if ($list->count() >= $targetNumber) {
						break;
					}
				}

				$count = $list->count();
				// if we haven't actually increased, we'll just bail here
				if ($lastCount == $count) {
					break;
				}
			}
			
			$list->QueryOffset = $nextOffset;
		}
		
		
		
		return $list;
	}

	public function restrictedByID($id, $perm = 'View') {
		$item = $this->owner->byID($id);
		if (!$item) {
			return null;
		}
		
		if ($item->hasExtension('Restrictable') && $item->checkPerm($perm)) {
			return $item;
		} else if ($item->hasMethod('can' . $perm)) {
			$method = 'can' . $perm;
			return $item->$method() ? $item : null;
		} else {
			return $item->canView() ? $item : null;
		}
	}
}
