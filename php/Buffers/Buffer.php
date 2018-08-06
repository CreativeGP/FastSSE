<?php
/*
   FastSSE v0.1.1

   Creative GP
   2018/07/29 (yyyy/mm/dd)
 */

namespace FastSSE;

require_once dirname(__FILE__)."/../define.php";
require_once dirname(__FILE__)."/../util.php";

class Buffer
{
	public $file_name;

	private $prev = '';
	public function prev() { return $this->prev; }

	private $now = '';
	public function now() { return $this->now; }

	private $has_changed = false;
	public function has_changed() { return $this->has_changed; }

	function __construct($file_name = FASTSSE_BUF)
	{
		$this->file_name = $file_name;
		if (file_exists($this->file_name))
			$this->prev = file_get_contents($file_name);
	}

	public function write($data, $option=FILE_APPEND)
	{
		file_put_contents($this->file_name, "$data\n", $option);
	}

	public function update()
	{
		$this->prev = $this->now;
		if (file_exists($this->file_name))
			$this->now = file_get_contents($this->file_name);

		$this->has_changed = ($this->prev != $this->prev);
	}

	public function diff()
	{
		$result = substr($this->now, strlen($this->prev));
		return $result;
	}
};