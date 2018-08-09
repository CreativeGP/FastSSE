<?php

require_once "../fastsse/SI/Sender.php";

// $my_tags = explode(',', $_GET['t']);

$sender = new FastSSE\Sender();

$sender->dam();
