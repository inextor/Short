<?php
namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');

use akou\ArrayUtils;

class Service extends SuperRest
{
	function get()
	{
		App::connect();
		$this->setAllowHeader();
		return $this->genericGet("table");
	}

	function post()
	{
		return $this->genericPost("table");
	}
}

$l = new Service();
$l->execute();
