<?php

namespace Omi\TF;

/**
 * 
 */
trait ETrip_CacheAvailability
{
	public function saveCharters($config, $force = false)
	{
		#return $this->cacheTransports([], 'charter');
		$this->saveCachedData(false, $force, $config);
	}
	
	public function saveTours($config, $force = false)
	{
		#return $this->cacheTransports([], 'tour');
		$this->saveCachedData(true, $force, $config);
	}

	public function cacheChartersDepartures($config = [])
	{		
		$this->saveCachedData(false, $config["force"] ?: false, $config);
	}
	
	public function cacheToursDepartures($config = [])
	{
		$this->saveCachedData(true, $config["force"] ?: false, $config);
	}
}