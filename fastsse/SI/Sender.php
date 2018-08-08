<?php
/*
   FastSSE v0.1.1

   Creative GP
   2018/07/29 (yyyy/mm/dd)
 */

namespace FastSSE;

require_once dirname(__FILE__)."/../define.php";
require_once dirname(__FILE__)."/../util.php";
require_once dirname(__FILE__)."/../Buffers/Buffer.php";
require_once dirname(__FILE__)."/../Buffers/ConnectionBuffer.php";

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
		echo "retry: 2000\n\n";

		$id = $this->connbuf->welcome();
		
		echo "event: server\n";
		echo "data: $id\n\n";

		// $this->connbuf->add_tag($id, 'general');

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
						echo "event: d\n";
						echo "data: $contents\n\n";

						ob_flush();
						flush();
						$counter = 0;
					}
				}
			}
			else
			{
				if ($counter % $this->ping_freq == $this->ping_freq-1) {
					echo "data: a\n\n";
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
