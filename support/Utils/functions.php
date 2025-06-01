<?php 

const DO_LOGGING = false;

function q_curl_setopt_with_log($handle, $option, $value)
{
	global $__q_remote_log_curls;
	if (is_array($__q_remote_log_curls))
	{
		foreach ($__q_remote_log_curls as $pos => $curl_data)
		{
			if ($curl_data[0] === $handle)
			{
				$__q_remote_log_curls[$pos][2][$option] = $value;
				break;
			}
		}
	}
	return curl_setopt($handle, $option, $value);
}

function q_curl_init_with_log($url = null)
{
	global $__q_remote_log_curls;
	if ($__q_remote_log_curls === null)
		$__q_remote_log_curls = [];

	$curl = curl_init($url);
	
	$__q_remote_log_curls[] = [$curl, null, []]; # handle, url, opts
	
	return $curl;
}

function q_curl_exec_with_log($handle)
{
	// global $__q_remote_log_curls;
	
	// $curl_data = null;
	// $curl_pos = null;
	// foreach ($__q_remote_log_curls ?: [] as $pos => $curl_d)
	// {
	// 	if ($curl_d[0] === $handle)
	// 	{
	// 		$curl_data = $curl_d;
	// 		$curl_pos = $pos;
	// 	}
	// }
	
	// $index = sha1(uniqid("", true));
	
	// $z_data = ['url' => ($curl_data[1] ?: ($curl_data[2][CURLOPT_URL] ?? null))];
	// if ($curl_data[2][CURLOPT_POSTFIELDS] !== null)
	// 	$z_data['post'] = $curl_data[2][CURLOPT_POSTFIELDS];
	// if (isset($curl_data[2]))
	// {
	// 	$z_curl_opts = $curl_data[2];
	// 	unset($z_curl_opts[CURLOPT_POSTFIELDS]);
	// 	$z_data['curl_opts'] = $z_curl_opts;
	// }
	
	// q_remote_log_sub_entry([
	// 	[
	// 		'Index' => $index,
	// 		'Timestamp_ms' => (string)microtime(true),
	// 		'Tags' => ['tag' => 'curl_exec'],
	// 		'Traces' => (new \Exception())->getTraceAsString(),
	// 		'Data' => $z_data,
	// 	]
	// ]);
	
	$rc = curl_exec($handle);
	
	// if ($rc === false)
	// {
	// 	q_remote_log_sub_entry([
	// 		[
	// 			'Index' => $index,
	// 			'Timestamp_ms_end' => (string)microtime(true),
	// 			'Tags' => ['tag' => 'curl_exec', 'error', 'error_code' => curl_errno($handle)],
	// 			'Traces' => (new \Exception())->getTraceAsString(),
	// 			'Is_Error' => true,
	// 			'Data' => ['response' => false, 'curl_getinfo@response' => curl_getinfo($handle), 'curl_error' => curl_error($handle)],
	// 		]
	// 	]);
	// }
	// else
	// {
	// 	q_remote_log_sub_entry([
	// 		[
	// 			'Index' => $index,
	// 			'Timestamp_ms_end' => (string)microtime(true),
	// 			'Tags' => ['tag' => 'curl_exec'],
	// 			'Traces' => (new \Exception())->getTraceAsString(),
	// 			'Data' => ['response' => base64_encode(gzcompress($rc)), 'curl_getinfo@response' => curl_getinfo($handle)],
	// 		]
	// 	]);
	// }
	
	// if ($curl_pos !== null)
	// 	unset($__q_remote_log_curls[$curl_pos]);
	
	return $rc;
}

function q_remote_log_sub_entry(array $data)
{
	
}

/**
 * Better var_dump for objects/model
 * 
 * @return string
 */
function dump()
{
	ob_start();
	$ret = "";
	foreach (func_get_args() as $arg)
		qDebugStackInner($arg, false, false);
	$ret = ob_get_clean();

	echo $ret;
	return $ret;
}

function dd()
{
	ob_start();
	$ret = "";
	foreach (func_get_args() as $arg)
		qDebugStackInner($arg, false, false);
	$ret = ob_get_clean();

	echo $ret;
	die;
}

function qvar_dump()
{
	dump(func_get_args());
}

function qvardump()
{
	dump(func_get_args());
}

function q_url_encode(array $params)
{
	$newParams = [];
	foreach($params as $key=>$value) {
		if(is_bool($value) ){
			$newParams[$key] = ($value) ? 'true' : 'false';
		} else {
			$newParams[$key] = $value;
		}
	}
	$q = http_build_query($newParams);
	return $q;
}


/**
 * Inner function for qDebugStack
 */
function qDebugStackInner($args, $with_stack = false, $on_shutdown = false, string $title = '', bool $collapsed = false, bool $with_border = true, int $max_depth = 8)
{
	if ($max_depth < 1)
		return;
	
	if ($on_shutdown)
		ob_start();
	
	$css_class = "_dbg_".uniqid();
	
	?><div class="<?= $css_class ?>"><script type="text/javascript">
			if (!window._dbgFuncToggleNext)
			{
				window._dbgFuncToggleNext = function(dom_elem)
				{
					var next = dom_elem ? dom_elem.nextSibling : null;
					// skip until dom element
					while (next && (next.nodeType !== 1))
						next = next.nextSibling;
					if (!next)
						return;
					
					if ((next.offsetWidth > 0) || (next.offsetHeight > 0))
						next.style.display = 'none';
					else
						next.style.display = 'block';
				};
			}
		</script><style type="text/css">
		
		div.<?= $css_class ?> {
			font-family: monospace;
			font-size: 12px;
			<?php if ($with_border): ?>
			padding: 10px;
			margin: 10px;
			border: 2px dotted gray;
			<?php endif; ?>
		}
		
		div.<?= $css_class ?> h4 {
			font-size: 15px;
			margin: 5px 0px 5px 0px;
		}
		
		div.<?= $css_class ?> table {
			border-collapse: collapse;
			border: 1px solid black;
			padding: 3px;
		}
		
		div.<?= $css_class ?> table tr:first-child th {
			background-color: blue;
			color: white;
		}
		
		div.<?= $css_class ?> table th, div.<?= $css_class ?> table td {
			text-align: left;
			padding: 3px;
			border: 1px solid black;
			vertical-align: top;
		}

		div.<?= $css_class ?> table td {
			
		}
		
		div.<?= $css_class ?> ._dbg_params {
			cursor: pointer;
			color: blue;
		}
		
		div.<?= $css_class ?> pre {
			margin: 0;
		}
		
		<?php if ($collapsed): ?>
		div.<?= $css_class ?> pre div {
			display: none;
		}
		<?php else: ?>
		div.<?= $css_class ?> pre div > div {
			display: none;
		}
		<?php endif; ?>
		
		div.<?= $css_class ?> pre span._dbg_expand {
			cursor: pointer;
			color: blue;
		}
		
		div.<?= $css_class ?> pre span._dbg_s {
			color: green;
		}
		
		div.<?= $css_class ?> pre span._dbg_nl {
			color: red;
		}
		
		div.<?= $css_class ?> pre span._dbg_bl {
			color: orange;
		}
		
	</style><?php

	$stack = debug_backtrace();
	// remove this call
	array_shift($stack);
	// and previous
	array_shift($stack);
	
	$stack_1 = end($stack);
	$stack_1_file = $stack_1["file"];
	
	// remove GetStack
	// array_pop($stack);
	
	// $stack = array_reverse($stack);
	$doc_root = $_SERVER["DOCUMENT_ROOT"];
	
	if ($title)
		echo "<h4>{$title}</h4>";
	
	// var_dump(array_keys($args));
	$bag = [];
	qDSDumpVar($args, $max_depth);
	?></div><?php
	
	if ($on_shutdown)
	{
		// AJAX request
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'))
		{
			//QAutoload::$DebugStacks[] = ob_get_clean();
		}
		else
			register_shutdown_function("qDebugStackOutput", ob_get_clean());
	}
}

/**
 * Inner function for qDebugStackInner
 */
function qDSDumpVar($var, $max_depth = 8, &$bag = null, $depth = 0, $accessModifier = null, $wasSet = null)
{
	if ($max_depth < 0)
		return;
	
	$ty = gettype($var);
	
	if (!$bag)
		$bag = array();
	
	if ($depth === 0)
		echo "<pre>\n";
	
	$pad = str_repeat("\t", $depth);
	
	switch ($ty)
	{
		case "string":
		{
			echo "[string(".strlen($var).")]".($accessModifier ? "[{$accessModifier}]" : "").($wasSet ? "[set]" : "").": ";
			echo "<span class='_dbg_s'>";
			// wordwrap ( string $str [, int $width = 75 [, string $break = "\n" [, bool $cut = false ]]] )
			if (strlen($var) > (1024 * 1024))
			{
				// very big !
				echo '"'.preg_replace(['/\\r/us', '/\\n/us'], ["\\r", "\n"], htmlspecialchars(substr($var, 0, 1024*1024))).' [... truncated ...]"';
			}
			else
				echo '"'.preg_replace(['/\\r/us', '/\\n/us'], ["\\r", "\n"], htmlspecialchars($var)).'"';
			echo "</span>";
			break;
		}
		case "NULL":
		{
			echo ($accessModifier ? "[{$accessModifier}]" : "").($wasSet ? "[set]" : "").": <span class='_dbg_nl'>[null]</span>";
			break;
		}
		case "integer":
		{
			echo "[int]".($accessModifier ? "[{$accessModifier}]" : "").($wasSet ? "[set]" : "").": ";
			echo $var;
			break;
		}
		case "double":
		{
			echo "[float]".($accessModifier ? "[{$accessModifier}]" : "").($wasSet ? "[set]" : "").": ";
			echo $var;
			break;
		}
		case "boolean":
		{
			echo "[bool]".($accessModifier ? "[{$accessModifier}]" : "").($wasSet ? "[set]" : "").": <span class='_dbg_bl'>";
			echo $var ? "true" : "false";
			echo "</span>";
			break;
		}
		case "array":
		{
			echo "<span class='_dbg_expand' onclick='_dbgFuncToggleNext(this);'>[array(".count($var).")]:</span>\n";
			echo "<div>";
			foreach ($var as $k => $v)
			{
				echo $pad."\t<b>".((is_string($k) && (strlen($k) === 0)) ? "''" : htmlspecialchars($k))."</b>";
				if ($max_depth)
					qDSDumpVar($v, $max_depth - 1, $bag, $depth + 1, $accessModifier, $wasSet);
				else
					echo "<span class='_dbg_nl'>*** too deep</span>";
				echo "\n";
			}
			echo "</div>";
			break;
		}
		case "object":
		{
			$obj_class = get_class($var);
			if (substr($obj_class, 0, strlen('class@anonymous')) === 'class@anonymous')
			{
				echo "#class@anonymous";
				break;
			}
			
			if ($obj_class === 'Generator')
			{
				echo "#Generator";
				break;
			}
			
			if ($obj_class === 'Closure')
			{
				echo "#Closure";
				break;
			}
			
			$ref_id = array_search($var, $bag, true);
			if ($ref_id === false)
			{
				end($bag);
				$ref_id = key($bag);
				$ref_id = ($ref_id === null) ? 0 : $ref_id + 1;
				
				$ref_id++;
				
				$bag[] = $var;
			}
			else
			{
				$ref_id++;
	
				echo "[{$obj_class}#{$ref_id}".(isset($var->_id) ? 
					"; id:".$var->_id : 
					(isset($var->Id) ? 
						"; Id:".$var->Id : 
						""))."]: <span class='_dbg_expand'>#ref</span>";
				return;
			}

			echo "<span class='_dbg_expand' onclick='_dbgFuncToggleNext(this);'>[{$obj_class}";
			if ($var instanceof \Closure)
				echo "]";
			else
				echo ("")."#{$ref_id}".(
						isset($var->_id) && $var->_id ? 
						"; id:".  $var->_id : 
						(isset($var->Id) && $var->Id ? 
							"; Id:".$var->Id : 
							""))."]".($accessModifier ? "[{$accessModifier}]" : "");
			echo ":</span>\n";
			echo "<div>";

			$_isqm = false;
			$props = (array)$var; # $_isqm ? $var->getModelType()->properties : $var;
			
			$_refCls = $_isqm ? $var->getModelType()->getReflectionClass() : null;

			$null_props = [];
			
			if ($_isqm)
			{
				if ($var->_ts !== null)
				{
					echo $pad."\t<b>_ts: </b>";
					echo "<span class='_dbg_nl'>{$var->_ts}</span>";
					echo "\n";
				}
				if ($var->_tsp !== null)
				{
					echo $pad."\t<b>_tsp: </b>";
					echo "<span class='_dbg_nl'>". json_encode($var->_tsp)."</span>";
					echo "\n";
				}
				if ($var->_tsx !== null)
				{
					echo $pad."\t<b>_tsx: </b>";
					echo "<span class='_dbg_nl'>". json_encode($var->_tsx)."</span>";
					echo "\n";
				}
				if ($var->_wst !== null)
				{
					echo $pad."\t<b>_wst: </b>";
					echo "<span class='_dbg_nl'>". json_encode($var->_wst)."</span>";
					echo "\n";
				}
				if ($var->_rowi !== null)
				{
					echo $pad."\t<b>_rowi: </b>";
					echo "<span class='_dbg_nl'>". json_encode($var->_rowi)."</span>";
					echo "\n";
				}
			}
			
			foreach ($props as $_k => $v)
			{
				$p_name = $_k;
				if ($_k[0] === "\x00")
				{
					if (substr($_k, 0, 3) === "\x00*\x00")
					{
						$p_name = substr($_k, 3);
						$k = $_isqm ? $p_name : $p_name."(protected)";
					}
					else if (substr($_k, 0, 2 + strlen($obj_class)) === "\x00{$obj_class}\x00")
					{
						$p_name = substr($_k, 2 + strlen($obj_class));
						$k = $_isqm ? $p_name : $p_name."(private)";
					}
					else
						$p_name = $k = trim($_k, "\x00");
				}
				else
					$p_name = $k = $_k;
				
				if ($_isqm && (($p_name === "_typeIdsPath") || ($p_name === "_qini") || ($p_name === "_ty") || ($p_name === "_id") || ($p_name === "_wst") || ($p_name === "_ts") || ($p_name === "_tsx") || ($p_name === "_sc") || ($p_name === "Del__")))
					continue;
				
				$accessModifier = null;
				$wasSet = $_isqm ? $var->wasSet($k) : null;
				if ($_isqm && ($refP = $_refCls->hasProperty($p_name) ? $_refCls->getProperty($p_name) : null))
				{
					$accessModifier = $refP->isPublic() ? "public" : ($refP->isPrivate() ? "private" : ($refP->isProtected() ? "protected" : null));
				}

				if ($v !== null)
				{
					# echo $pad."\t<b>".((is_string($k) && (strlen($k) === 0)) ? "''" : htmlspecialchars($k))."</b>";
					echo $pad."\t<b>".((is_string($k) && (strlen($k) === 0)) ? "''" : htmlspecialchars($k))."</b>";
					if ($max_depth)
					{
						qDSDumpVar($v, $max_depth - 1, $bag, $depth + 1, $accessModifier, $wasSet);
					}
					else
						echo "<span class='_dbg_nl'>*** too deep</span>";
					echo "\n";
				}
				else
					$null_props[$p_name] = $p_name;
			}
			
			if ($null_props)
			{
				ksort($null_props);
				echo $pad."\t<b>Null props: ".implode(", ", $null_props)."</b>";
			}
			echo "</div>";
			break;
		}
		case "resource":
		{
			echo get_resource_type($var)." #".intval($var);
			break;
		}
		case "function":
		{
			echo "#Closure";
			break;
		}
		default:
		{
			// unknown type
			break;
		}
	}
	
	if ($depth === 0)
		echo "</pre>\n";
}

/**
 * Parses a string into a associative array that would describe an entity
 * ex: Orders.*,Orders.Items.{Date,Product,Quantity},Orders.DeliveryAddresses.*
 * The {} can be used to nest properties relative to the parent
 * 
 * @param string $str
 * @param boolean $mark
 * 
 * @return array
 */
function qParseEntity(string $str, $mark = false, $expand_stars = false, $start_class = null, bool $for_listing = false)
{
	if ($for_listing)
		# only split on dot (`.`) if followed by `{` - whitespace accepted
		$tokens = preg_split("/(\s+|\,|\.(?=\\s*\\{)|\:|\{|\})/us", $str, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
	else
		$tokens = preg_split("/(\s+|\,|\.|\:|\{|\})/us", $str, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
	
	$entity = array();

	$ctx_ent = &$entity;
	$ctx_prev = null;
	$ctx_sel = &$entity;
	$selected = null;

	// . => go deeper
	// , => reset to last `level` in this context
	// { => start a new context
	// } => exit current context
	$has_star = false;

	foreach ($tokens as $tok)
	{
		$frts = $tok[0];
		switch ($frts)
		{
			case " ":
			case "\t":
			case "\n":
			case "\r":
			case "\v":
				break;
			case ".":
			{
				// there is nothing to do tbh
				break;
			}
			case ",":
			{
				$ctx_sel = &$ctx_ent;
				if ($selected !== null)
				{
					$selected[] = true;
					// make sure you unset and not assign to null as it is a reference
					unset($selected);
				}
				break;
			}
			case "{":
			{
				// creates a new context
				$ctx_prev = array(&$ctx_ent, $ctx_prev);
				$ctx_ent = &$ctx_sel;
				break;
			}
			case "}":
			{
				// closes the current context
				$ctx_ent = &$ctx_prev[0];
				$ctx_prev = &$ctx_prev[1];
				if ($selected !== null)
				{
					$selected[] = true;
					// make sure you unset and not assign to null as it is a reference
					unset($selected);
				}
				break;
			}
			default:
			{
				// identifier
				if ($expand_stars && (!$has_star) && (($tok === '*') || ($frts === '@')))
					$has_star = true;
				($ctx_sel[$tok] !== null) ? null : ($ctx_sel[$tok] = array());
				$ctx_sel = &$ctx_sel[$tok];
				$mark ? ($selected = &$ctx_sel) : null;
				break;
			}
		}
	}
	
	if ($selected !== null)
	{
		$selected[] = true;
		// make sure you unset and not assign to null as it is a reference
		unset($selected);
	}
	
	if ($expand_stars && $start_class && (($entity["*"] !== null) || $has_star))
		qExpandStars($entity, $start_class);
	
	return $entity;
}

function q_filter_by_selector($data, $selector = true, array $skip_props = ['_ty', '_id'])
{
    if (is_string($selector))
        $selector = qParseEntity($selector);
    else if (!is_array($selector))
        throw new \Exception("Bad selector");

    $copy_to = null;
    # data must be object or array or QModelArray
    if (is_array($data))
    {
        if ($copy_to === null)
            $copy_to = [];
        foreach ($data as $dk => $d)
            $copy_to[$dk] = q_filter_by_selector($d, $selector);

        return $copy_to;
    }
    else if (is_object($data))
    {
        if ($copy_to === null)
            $copy_to = new stdClass();

        # foreach ($data as $k => $v)
        foreach ((is_array($selector) ? $selector : $data) as $k => $s_k)
        {
            if (in_array($k, $skip_props))
                continue;
            
            $v = $data->$k;
            if (($v === null) || ($s_k === null))
            {
                # nothing
            }
            else if (is_array($v) || is_object($v))
            {
                $copy_to->$k = q_filter_by_selector($v, $s_k);
            }
            else if (is_scalar($v))
            {
                $copy_to->$k = $v;
            }
        }

        return $copy_to;
    }
    else if (is_scalar($data))
        return $data;
}
function qExpandStars(&$entity, $class)
{
	$add = [];
	if (is_array($class) && $class)
	{
		// multiple
		$types = [];

	}
	else if ($class)
	{
		// single

	}
	foreach ($entity as $k => &$sub)
	{
		if ($k === "*")
		{
			if (is_array($types))
			{
				foreach ($types as $ty)
				{
					foreach ($ty->properties as $k => $v)
						$add[$k] = [];
				}
			}
			else
			{

			}
		}
		else if ($sub && ($sub_types = qMixedTypes($types, $k)))
		{
			qExpandStars($sub, $sub_types);
		}
	}
	
	if ($add)
	{
		unset($entity["*"]);
		foreach ($add as $k => $v)
		{
			if ($entity[$k] === null)
			$entity[$k] = [];
		}
	}
	return $entity;
}

function qMixedTypes($types, $property)
{
	$ret = [];
	if (is_array($types))
	{
		foreach ($types as $ty)
		{
			$prop = $ty->properties[$property];
			if ($prop)
			{
				if (($ref_types = $prop->getReferenceTypes()))
				{
					if (!$ret)
						$ret = $ref_types;
					else
					{
						foreach ($ref_types as $k => $v)
							$ret[$k] = $v;
					}
				}

				if ((($coll = $prop->getCollectionType())) && ($ref_types = $coll->getReferenceTypes()))
				{
					if (!$ret)
						$ret = $ref_types;
					else
					{
						foreach ($ref_types as $k => $v)
							$ret[$k] = $v;
					}
				}
			}
		}
	}
	else
	{
		$prop = $types->properties[$property];
		if ($prop)
		{
			if (($ref_types = $prop->getReferenceTypes()))
			{
				if (!$ret)
					$ret = $ref_types;
				else
				{
					foreach ($ref_types as $k => $v)
						$ret[$k] = $v;
				}
			}

			if ((($coll = $prop->getCollectionType())) && ($ref_types = $coll->getReferenceTypes()))
			{
				if (!$ret)
					$ret = $ref_types;
				else
				{
					foreach ($ref_types as $k => $v)
						$ret[$k] = $v;
				}
			}
		}	
	}
	return $ret ?: null;
}

function json_encode_pretty(mixed $value): bool|string
{
	return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE, JSON_INVALID_UTF8_SUBSTITUTE);
}

function qmkdir(string $dir)
{
	return mkdir($dir);
}

function q_count($arr)
{
	return count($arr);
}

function q_reset(&$var)
{
	return reset($var);
}
