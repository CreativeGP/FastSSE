<?php
/*
   FastSSE v0.1.1

   Creative GP
   2018/07/29 (yyyy/mm/dd)
 */

namespace FastSSE;

require_once dirname(__FILE__)."/../define.php";
require_once dirname(__FILE__)."/../util.php";
require_once dirname(__FILE__)."/ServerInterface.php";
require_once dirname(__FILE__)."/../Buffers/ConnectionBuffer.php";


class TagDistributor extends ServerInterface
{
	function __construct($connbuf_filename = FASTSSE_CONNBUF)
	{
		// q(add|join) id t(wanted tags) and p(passwords)
		// They can't contain newlines.
		$args = $this->get_args(['q', 'id', 't', 'p']);
		if (!is_array($args))
			$this->bad_request($args);

		// if (!ctype_digit($args['id'])) 
		// 	$this->bad_request("`id` is incorrect.");

		$connbuf = new ConnectionBuffer($connbuf_filename);
		$wanted_tags = explode(',', $args['t']);
		$passes = explode(',', $args['p']);

		// Check if the id is valid.
		if (!$connbuf->verify_id($args['id']))
			$this->bad_request("No user found.");
		
		if (count($wanted_tags) != count($passes)) 
			$this->bad_request("The number of tags and passwords don't match.");

		for ($i = 0; $i < count($wanted_tags); $i += 1)
		{
			if (!$connbuf->join_tag($args['id'], $wanted_tags[$i], $passes[$i]))
				$this->bad_request("Passwords incorrect.");
		}

		$connbuf->upload();
	}
};