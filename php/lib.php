<?php
/*
   FastSSE v0.1.1

   Creative GP
   2018/07/29 (yyyy/mm/dd)
 */

namespace FastSSE;

define(FASTSSE_BUF, 'buf');
define(FASTSSE_CONNBUF, 'connbuf');


function encode_nl($s)
{
	return str_replace("\n", "%\\n", $s);
}

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

class Sender
{
	// main buffer.
	private $buf;
	private $connbuf;

	// The frequency of pinging (senconds).
	private $ping_freq = 1;

	function __construct(
		$buf_filename = FASTSSE_BUF,
		$connbuf_filename = FASTSSE_CONNBUF)
	{
		$this->buf = new Buffer($buf_filename);
		$this->connbuf = new ConnectionBuffer($connbuf_filename);
	}

	public function dam()
	{
		ignore_user_abort(true);

		header('Content-Type: text/event-stream');
		header('Cache-Control: no-cache');

		echo ":" . str_repeat(" ", 2048) . "\n"; // 2 kB padding for IE
		echo "retry: 2000\n";

		$id = $this->connbuf->welcome();
		
		echo "server: $id";

		$this->connbuf->add_tag($id, 'general');

		$counter = 0;

		while (true)
		{
			$this->buf->update();
			$this->connbuf->sync();

			if ($this->buf->diff() != "")
			{
				$diff = $this->buf->diff();

				$data = array_diff(explode("\n", $diff), [end(explode("\n", $diff))]);

				foreach ($data as $d)
				{
					$tags = array_values(array_diff(explode(' ', $d), [end(explode(' ', $d))]));
					$contents = base64_decode(end(explode(' ', $d)));

					$contents = encode_nl($contents);

					$go = false;
					foreach ($tags as $t)
					{
						if (in_array($t, $this->connbuf->id2tags($id), true))
						{
							$go = true;
							break;
						}
					}

					if ($go)
					{
						echo "data: $contents \n\n";

						ob_flush();
						flush();
						$counter = 0;
					}
				}
			}
			else
			{
				if ($counter % $this->ping_freq == $this->ping_freq-1) {
					echo "ping: a";
					ob_flush();
					flush();
				}
			}

			if (connection_aborted())
			{
				$this->connbuf->farewell($id);
				exit();
			}

			++$counter;
			sleep(1);
		}
	}
};
