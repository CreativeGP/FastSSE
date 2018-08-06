<?php
/*
   FastSSE v0.1.1

   Creative GP
   2018/07/29 (yyyy/mm/dd)
 */

namespace FastSSE;

require_once dirname(__FILE__)."/../define.php";
require_once dirname(__FILE__)."/../util.php";
require_once dirname(__FILE__)."/Buffer.php";

class Tag
{
	public $name;
	public $password;
	public $member;

	function __construct($name, $password, $member)
	{
		$this->name = $name;
		$this->password = $password;
		$this->member = $member;
	}
}

class ConnectionBuffer extends Buffer
{
	public function people_num() { return $this->people_num; }

	// Holds tags and their passwords (hash).
	private $tags = [];
	private $map_id2tags = [];

	function __construct($file_name = FASTSSE_CONNBUF)
	{
		parent::__construct($file_name);
		$this->sync();
	}

	public function taginfo($tagname)
	{
		if (array_key_exists($tagname, $this->tags))
			return $this->tags[$tagname];
		else return false;
	}

	public function verify_id($id)
	{
		return array_key_exists($id, $this->map_id2tags);
	}

	public function id2tags($id)
	{
		if (array_key_exists($id, $this->map_id2tags))
			return $this->map_id2tags[$id];
		else return -1;
	}

	public function add_tag($id, $tagname, $password='')
	{
		if (!array_key_exists($id, $this->map_id2tags)) return;
		if (array_key_exists($tagname, $this->tags)) return;

		$this->tags[$tagname] = new Tag($tagname, hash('sha256', $password), [$id]);

		$this->upload();
	}

	public function join_tag($id, $tagname, $password)
	{
		if (!array_key_exists($id, $this->map_id2tags)) return false;
		if (!array_key_exists($tagname, $this->tags)) return false;
		if ($this->tags[$tagname]->password != "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
			&& hash('sha256', $password) != $this->tags[$tagname]->password)
			return false;

		// Already joined.
		if (in_array($id, $this->tags[$tagname]->member, true))
			return true;

		array_push($this->tags[$tagname]->member, $id);
		array_push($this->map_id2tags[$id], $tagname);
		return true;
	}

	public function upload()
	{
		$data = serialize([
			"f"=>$this->tags,
			"g"=>$this->map_id2tags]);
		parent::write($data, 0);
		$this->sync();
	}

	public function sync()
	{
		$this->update();
		$tmp = unserialize($this->now());

		$this->tags = $tmp["f"];
		$this->map_id2tags = $tmp["g"];
	}

	public function welcome()
	{
		$id = uniqid();
		$this->map_id2tags[$id] = [];

		$this->upload();

		return $id;
	}

	public function farewell($id)
	{
		unset($this->map_id2tags[$id]);

		foreach ($this->tags as $name => $t)
		{
			if (($key = array_search($id, $t->member)) !== false)
			{
				unset($this->tags[$name]->member[$key]);
			}
		}

		$this->upload();
	}
};