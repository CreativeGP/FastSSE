<?php
/*
   FastSSE v0.1.1

   Creative GP
   2018/07/29 (yyyy/mm/dd)
 */

namespace FastSSE;

require_once dirname(__FILE__)."/../Buffers/Buffer.php";

class ServerInterface
{
	protected function bad_request($msg="")
	{
		echo "Bad request =X";
		echo $msg;
		exit();
	}

	protected function get_args($args)
	{
		$result = [];

		$n = count($args);
		if (count($_GET) != $n)
			return 'Invalid number arguments.';
		
		foreach ($args as $arg)
		{
			if (!isset($_GET[$arg]))
				return 'Invalid number arguments.';
			if (strpos($_GET[$arg], "\n") !== false)
				return 'Invalid character.';

			// NOTE(cgp): `p` is used for passwords which can be null.
			if ($_GET[$arg] == "" && $arg != 'p')
				return 'Null arguments.';
			
			$result[$arg] = $_GET[$arg];
		}

		return $result;
	}
};