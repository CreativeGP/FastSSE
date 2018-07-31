<?php

require_once "fastsse/php/lib.php";

// $my_tags = explode(',', $_GET['t']);

$sender = new FastSSE\Sender();

$sender->dam();
