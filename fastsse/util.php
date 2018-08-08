<?php
/*
   FastSSE v0.1.1

   Creative GP
   2018/07/29 (yyyy/mm/dd)
 */

namespace FastSSE;

function encode_nl($s)
{
	return str_replace("\n", "%\\n", $s);
}