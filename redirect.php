<?php
namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');
include_once( __DIR__.'/SuperRest.php');

use akou\ArrayUtils;
use akou\NotFoundException;

class Service extends SuperRest
{
	function get()
	{
		App::connect();
		$this->setAllowHeader();

		if( empty( $_GET['id'] ) )
		{
			throw new NotFoundException('Link Not Found');
		}

		$id = base_convert($_GET['id'], 36, 10);
		$link  = link::get( $id );

		if( empty( $link ) )
		{
			throw new NotFoundException('Link Not Found');
		}

		$bot = (isset($_SERVER['HTTP_USER_AGENT']) && preg_match("/(bot|crawl)/i", $_SERVER['HTTP_USER_AGENT']));

		if( $bot && $link->description )
		{

			return $this->raw( $link->description );
		}


		$link->clicks += 1;

		if( !$link->update('clicks') )
		{
			error_log('It fails to update clicks');
		}

  		header("Location: " . $link->url);
	}
}

$l = new Service();
$l->execute();
