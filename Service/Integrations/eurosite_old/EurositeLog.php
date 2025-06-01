<?php

namespace Omi\TF;
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of EurositeLog
 * 
 * @storage.table Eurosite_LOG
 *
 * @author Mihaita
 */
class EurositeLog extends \QModel
{
	use EurositeLog_GenTrait;
	/**
	 * @var int
	 */
	public $Id;
	/**
	 * @var string
	 */
	public $RequestMethod;
	/**
	 * @var string
	 */
	public $RequestParams;
	/**
	 * @storage.type LONGTEXT
	 * @var string
	 */
	public $Request;
	/**
	 * @var datetime
	 */
	public $RequestSentAt;
	/**
	 * @storage.type LONGTEXT
	 * 
	 * @var string
	 */
	public $Response;
	/**
	 * @var datetime
	 */
	public $ResponseReceivedAt;
	/**
	 * @var float
	 */
	public $RequestTook;
}
