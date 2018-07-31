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

	public function write($data, $option = FILE_APPEND)
	{
		file_put_contents($this->file_name, "$data\n");
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
	private $map_ip2id = [];
	private $map_id2tags = [];

	function __construct($file_name = FASTSSE_CONNBUF)
	{
		parent::__construct($file_name);
		$this->sync();
	}

	public function taginfo($tagname)
	{
		if (isset($this->tags[$tagname]))
			return $this->tags[$tagname];
		else return false;
	}

	public function ip2id($ip)
	{
		if (isset($this->map_ip2id[$ip]))
			return $this->map_ip2id[$ip];
		else return -1;
	}

	public function id2tags($id)
	{
		if (isset($this->map_id2tags[$id]))
			return $this->map_id2tags[$id];
		else return -1;
	}

	public function add_tag($id, $tagname, $password='')
	{
		if (!in_array($map_id2tags, $id, true)) return;
		if (in_array($tagname, $this->tags, true)) return;

		$this->tags[$tagname] = new Tag($tagname, hash('sha256', $password), [$id]);
	}

	public function ask_tag($id, $tagname, $password)
	{
		if (!in_array($tagname, $this->tags, true)) return;
		if (!in_array($map_id2tags, $id, true)) return;
		if ($this->tags[$tagname]->pass != ""
		&& hash('sha256', $password) != $this->tags[$tagname]->pass) return;

		array_push($this->tags[$tagname]->member, $id);
	}

	public function upload()
	{
		$data = serialize([
			"f"=>$this->tags,
			"g"=>$this->map_ip2id,
			"h"=>$this->map_id2tags]);
		parent::write($data, 0);
		$this->sync();
	}

	public function sync()
	{
		$this->update();
		$tmp = unserialize($this->now());

		$this->tags = $tmp["f"];
		$this->map_ip2id = $tmp["g"];
		$this->map_id2tags = $tmp["h"];
	}

	public function welcome()
	{
		$id = count($this->map_id2tags);
		$this->map_ip2id[$_SERVER['REMOVE_ADDR']] = $id;
		$this->map_id2tags[$id] = [];

		$this->upload();

		return $id;
	}

	public function farewell()
	{
		$id = $this->ip2id($_SERVER['REMOVE_ADDR']);
		unset($this->$map_id2tags[$id]);
		unset($this->map_ip2id[$_SERVER['REMOVE_ADDR']]);

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

class TagDistributor
{
	function __construct($connbuf_filename = FASTSSE_CONNBUF)
	{
		// q(add|join) t(wanted tags) and p(passwords)
		// They can't contain newlines.
		if (count($_GET) != 3 || !isset($_GET['q'])|| !isset($_GET['t']) || !isset($_GET['p'])) exit;
		if (strpos($_GET['t'], "\n") !== false) exit;
		if ($_GET['t'] == "") exit;
		if (strpos($_GET['p'], "\n") !== false) exit;

		$connbuf = new ConnectionBuffer($connbuf_filename);
		$wanted_tags = explode(',', $_GET['t']);
		$passes = explode(',', $_GET['p']);

		if (count($wanted_tags) != count($passes)) exit;

		for ($i = 0; $i < count($wanted_tags); $i += 1)
		{
			$connbuf->ask_tag(
				$connbuf->ip2id($_SERVER['REMOVE_ADDR']),
				$wanted_tags[i], $passes[i]);
		}

		$connbuff->upload();
	}
};

class Receiver
{
	function __construct(
		$file_name = FASTSSE_BUF,
		$connbuf_filename = FASTSSE_CONNBUF)
	{
		if (count($_GET) != 2 || !isset($_GET['q']) || !isset($_GET['t'])) exit;
		if (strpos($_GET['t'], "\n") !== false) exit;
		if (strpos(base64_decode($_GET['q']), "%\\n") !== false) exit;
		if (base64_decode($_GET['q']) == "") exit;
		if ($_GET['t'] == "") exit;

		// Say hello to ConnectionBuffer and get id.
		$connbuf = new ConnectionBuffer($connbuf_filename);
		$id = $connbuf->ip2id($_SERVER['REMOVE_ADDR']);

		// If the connection is not registered, abort.
		if ($id == -1) exit;

		// Check all the wanted tag is registered by this user.
		$registered_tags = $connbuf->id2tags($connbuf->ip2id($_SERVER['REMOVE_ADDR']));
		$wanted_tags = explode(',', $_GET['t']);
		foreach ($wanted_tags as $tagname)
		{
			if (!in_array($tagname, $registered_tags, true))
				exit;
		}

		$buf = new Buffer($file_name);
		$tags = str_replace(',', ' ', $_GET['t']);
		$buf->write("$tags {$_GET['q']}");
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
				$this->connbuf->farewell();
				exit();
			}

			++$counter;
			sleep(1);
		}
	}
};
