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
require_once dirname(__FILE__)."/../Buffers/Buffer.php";
require_once dirname(__FILE__)."/../Buffers/ConnectionBuffer.php";


class Receiver extends ServerInterface
{
	function __construct(
		$file_name = FASTSSE_BUF,
		$connbuf_filename = FASTSSE_CONNBUF)
	{
		// NOTE(crium): Password is already checked in TagDistributor.

		// id {int}
		// t {base64} tags
		// d {base64} datas
		// They can't contain newlines.
		$args = $this->get_args(['id', 't', 'd']);
		if (!is_array($args)) 
			$this->bad_request("get_args()");

		if (strpos($args['d'], "%\\n") !== false) 
			$this->bad_request("newline in data.");
		// if (!ctype_digit($_GET['id'])) 
		// 	$this->bad_request();

		// Say hello to ConnectionBuffer and get id.
		$connbuf = new ConnectionBuffer($connbuf_filename);

		// Check all the wanted tag is registered by this user.
		$registered_tags = $connbuf->id2tags($args['id']);
		if ($registered_tags == -1)
			$this->bad_request();
		$wanted_tags = explode(',', $args['t']);
		foreach ($wanted_tags as $tagname)
		{
			if (!in_array($tagname, $registered_tags, true))
				$this->bad_request("tag `$tagname` is not registered.");
		}

		$buf = new Buffer($file_name);
		$tags = str_replace(',', ' ', $args['t']);
		$data = base64_encode($args['d']);
		$buf->write("$tags $data");
	}
};