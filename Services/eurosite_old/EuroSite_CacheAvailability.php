<?php

namespace Omi\TF;

/**
 * 
 */
trait EuroSite_CacheAvailability
{
	public function cacheChartersDepartures($config = [])
	{
		if (!file_exists(($lockFile = \Omi\App::GetLogsDir('locks') . "cacheChartersDepartures_on_" . $this->TourOperatorRecord->Handle . "_Lock.txt")))
			file_put_contents($lockFile, "cacheChartersDepartures lock");

		if (!($lock = \QFileLock::lock($lockFile, 1)))
			throw new \Exception("Sincronizarea de date plecare charter este inca in procesare - " . $lockFile);

		try
		{
			$this->saveCharters($config, ($config["force"] ?: false));
		}
		catch (\Exception $ex)
		{
			throw $ex;
		}
		finally
		{
			if ($lock)
				$lock->unlock();
		}
	}

	public function cacheToursDepartures($config = [])
	{
		if (!file_exists(($lockFile = \Omi\App::GetLogsDir('locks') . "cacheToursDepartures_on_" . $this->TourOperatorRecord->Handle . "_Lock.txt")))
			file_put_contents($lockFile, "cacheToursDepartures lock");

		if (!($lock = \QFileLock::lock($lockFile, 1)))
			throw new \Exception("Sincronizarea de date plecare circuite este inca in procesare - " . $lockFile);

		try
		{
			$this->saveTours($config, ($config["force"] ?: false));
		}
		catch (\Exception $ex)
		{
			throw $ex;
		}
		finally
		{
			if ($lock)
				$lock->unlock();
		}
	}
}