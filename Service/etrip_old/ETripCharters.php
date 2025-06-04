<?php

namespace Omi\TF;

trait ETripCharters
{
	public function saveCharters__DEPRECTATED($force = false, $config = null)
	{
		$this->saveCachedData(false, $force, $config);
	}
	/**
	 * @api.enable
	 * 
	 * @param type $request
	 * @param type $handle
	 * @param type $params
	 * @param type $_nights
	 */
	public static function PullChartersHotels($request, $handle, $params, $_nights, $do_cleanup = true, $skip_cache = false, $force = false)
	{
		return static::PullCacheData($request, $handle, $params, $_nights, false, $do_cleanup, $skip_cache, $force);
	}
}
