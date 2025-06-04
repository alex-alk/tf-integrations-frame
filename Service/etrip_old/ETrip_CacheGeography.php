<?php

namespace Omi\TF;

/**
 * 
 */
trait ETrip_CacheGeography
{
	/**
	 * @param type $config
	 * @return type
	 */
	public function cacheTOPCountries($config = [], $topCountries = null)
	{
		\Omi\TF\TOInterface::markReportStartpoint($config, 'countries');

		$force = $config["force"] ?: false;
		$callMethod = "GetGeography";
		$callKeyIdf = null;
		$callParams = [];
		// we need to make sure that we have all data in order (countries and continents)
		$refThis = $this;
		list($return, $alreadyProcessed, $callRequest, $callResponse, $callTopRequestTiming) = $this->getResponseAndProcessingStatus(function (string $method, array $params = []) use ($refThis) {
			$reqEX = null;
			try
			{
				$return = $refThis->SOAPInstance->request($method, $params);
			}
			catch (\Exception $ex)
			{
				$reqEX = $ex;
			}
			return [$return, $refThis->SOAPInstance->client->__getLastRequest(), $refThis->SOAPInstance->client->__getLastResponse(), $reqEX];
		}, $callMethod, $callParams, $callKeyIdf, $force);
		
		if ((!$return) || ($alreadyProcessed && (!$force)))
		{
			if ($this->TrackReport)
			{
				if (!$return)
					$this->TrackReport->_noResponse = true;
				if ($alreadyProcessed)
					$this->TrackReport->_responseNotChanged = true;
			}
			echo $alreadyProcessed ? "Nothing changed from last request!" : "No return";
			return;
		}

		$t1 = microtime(true);

		$retCountries = $this->SOAPInstance->getGeography_Rec($return);

		$mappedCountries = [
			"Suedia"					=> "Sweden",
			"USA"						=> "United States",
			"United States Of America"	=> "United States",
			"Cape Verde"				=> "Republica Capului Verde",
			"Great Britain"				=> "United Kingdom",
			"Bosnia and Herzegovina"	=> "Bosnia Herzegovina",
			"Filipine"					=> "Philippines",
			"Finlanda"					=> "Finland",
			'Iordania'					=> 'Jordan'
		];
		
		$countries = new \QModelArray();
		foreach ($retCountries as $cd)
		{
			if (!trim($cd->IntName))
				continue;

			$cd->IntName = $mappedCountries[$cd->IntName] ?: $cd->IntName;
			$country = $this->FindExistingItem("Countries", $cd->Id, "Name, InTourOperatorsIds.{TourOperator, Identifier}");
			if (!$country)
			{
				$__cb = [];
				if (($_bind = trim($cd->IntName)))
					$__cb[] = $_bind;

				if (($_bind = trim($cd->Name)))
					$__cb[] = $_bind;

				$qctrs = \QQuery("Countries.{Name, Alias, InTourOperatorsIds.{TourOperator, Identifier} WHERE (Name=? OR Alias=?)}", $__cb)->Countries;
				$country = (count($qctrs) == 1) ? reset($qctrs) : null;

				if (!$country)
				{
					echo "<div style='color: red;'>Country [{$cd->Id}] {$cd->IntName} {$cd->Name} not added</div>";
					continue;
				}

				if (!$country->InTourOperatorsIds)
					$country->InTourOperatorsIds = new \QModelArray();
				$apiStorageId = new \Omi\TF\ApiStorageIdentifier();
				$apiStorageId->setTourOperator($this->TourOperatorRecord);
				$apiStorageId->setFromTopAddedDate(date("Y-m-d H:i:s"));
				$apiStorageId->setIdentifier($cd->Id);
				if ($this->TrackReport)
				{
					if (!$this->TrackReport->NewCountries)
						$this->TrackReport->NewCountries = 0;
					$this->TrackReport->NewCountries++;
				}
				$apiStorageId->__added_to_system__ = true;
				echo "<div style='color: green;'>Country " . ($country->Code . "|" . $country->Name) . " identifier added</div>";
				$country->InTourOperatorsIds[] = $apiStorageId;
			}
			else if ($country->Name != $cd->IntName)
			{				
				echo "<div style='color: orange;'>Country name changed in tour operator for code [{$country->Name}]: " 
					. ($country->Name . " ~ " . $cd->IntName) . " - to be revised</div>";
			}
			
			$countries[$cd->Id] = $country;
		}

		$this->setupSoapResponseAndProcessingStatus($callMethod, $callRequest, $callResponse, $callKeyIdf, $callTopRequestTiming, (microtime(true) - $t1));
		
		\Omi\TF\TOInterface::markReportEndpoint($config, 'countries');
		return $countries;
	}

	/**
	 * Cache regions
	 * 
	 * The algorythm
	 *	Load existing top countries
	 *  Load existing top regions
	 *  Get all regions from top - the part with getting them all in one go will be solved on the top implementation
	 *  Go through all regions and setup system regions (setup ids also)
	 *  Flag the regions that we no longer have them in the top api response
	 * 
	 * 
	 * @return type
	 */
	public function cacheTOPRegions($config = [], $allCountiesResp = null)
	{
		\Omi\TF\TOInterface::markReportStartpoint($config, 'regions');
		\Omi\TF\TOInterface::markReportData($config, 'tour operator regions are added when caching top hotels');
		\Omi\TF\TOInterface::markReportEndpoint($config, 'regions');
	}
	/**
	 * Cache cities
	 * 
	 * The algorythm
	 *	Load existing top countries
	 *  Load existing top regions
	 *  Load existing top cities
	 *  Get all cities from top - the part with getting them all in one go will be solved on the top implementation
	 *  Go through all cities and setup system cities (setup ids also)
	 *  Flag the cities that we no longer have them in the top api response
	 * 
	 * 
	 * @return type
	 */
	public function cacheTOPCities($config = [], $allCitiesResp = null)
	{
		\Omi\TF\TOInterface::markReportStartpoint($config, 'cities');
		\Omi\TF\TOInterface::markReportData($config, 'tour operator regions are added when caching top hotels');
		\Omi\TF\TOInterface::markReportEndpoint($config, 'cities');
	}
}