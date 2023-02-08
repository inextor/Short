<?php

namespace APP;

include_once( __DIR__.'/akou/src/LoggableException.php' );
include_once( __DIR__.'/akou/src/Utils.php' );
include_once( __DIR__.'/akou/src/DBTable.php' );
include_once( __DIR__.'/akou/src/RestController.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php' );
include_once( __DIR__.'/akou/src/Image.php' );
include_once( __DIR__.'/SuperRest.php');
include_once( __DIR__.'/schema.php');
include_once( __DIR__.'/akou/src/Curl.php');

use \akou\DBTable;
use \akou\Utils;
use \akou\SystemException;
use \akou\ValidationException;
use \akou\ArrayUtils;
use AKOU\Curl;
use \akou\SessionException;

date_default_timezone_set('UTC');
//error_reporting(E_ERROR | E_PARSE);
Utils::$DEBUG				= TRUE;
Utils::$DEBUG_VIA_ERROR_LOG	= TRUE;
#Utils::$LOG_CLASS			= '\bitacora';
#Utils::$LOG_CLASS_KEY_ATTR	= 'titulo';
#Utils::$LOG_CLASS_DATA_ATTR	= 'descripcion';

class App
{
	const DEFAULT_EMAIL					= '';
	const LIVE_DOMAIN_PROTOCOL			= 'http://';
	const LIVE_DOMAIN					= '';
	const DEBUG							= FALSE;
	const APP_SUBSCRIPTION_COST			= '20.00';

	public static $GENERIC_MESSAGE_ERROR	= 'Please verify details and try again later';
	public static $image_directory		= './user_images';
	public static $attachment_directory = './user_files';
	public static $is_debug				= false;
	public static $endpoint				= 'http://127.0.0.1/Short';
	public static $filename_prefix		= '';
	public static $platform_db_connection = null;
	public static $store_db_connection	= null;

	public static function connect()
	{
		DBTable::$_parse_data_types = TRUE;

		$test_servers = array('127.0.0.1','192.168.0.2','2806:1000:8201:71d:42b0:76ff:fed9:5901');

		$domain = app::getCustomHttpReferer();

		$is_test = in_array($_SERVER['SERVER_ADDR'],$test_servers ) ||
			Utils::startsWith('127.0.',$domain ) ||
			Utils::startsWith('172.16',$domain ) ||
			Utils::startsWith('192.168',$domain ) ||
			in_array( $domain, $test_servers );

		if( $is_test )
		{
				$__user		= 'root';
				$__password	= 'asdf';
				$__db		= 'shortner';
				$__host		= '127.0.0.1';
				$__port		= '3306';

				app::$image_directory = '/var/www/html/PointOfSale/user_images';
				app::$attachment_directory = '/var/www/html/PointOfSale/user_files';
				app::$is_debug	= true;
		}
		else
		{
				Utils::$DEBUG_VIA_ERROR_LOG	= FALSE;
				Utils::$LOG_LEVEL			= Utils::LOG_LEVEL_ERROR;
				Utils::$DEBUG				= FALSE;
				Utils::$DB_MAX_LOG_LEVEL	= Utils::LOG_LEVEL_ERROR;
				app::$is_debug	= false;

				$__user			= 'root';
				$__password		= 'asdf';
				$__db			= 'shortner';
				$__host			= '127.0.0.1';
				$__port			= '3306';

				app::$attachment_directory = './user_files';
				app::$endpoint = 'https://'.$_SERVER['SERVER_ADDR'].'/api';
				app::$image_directory = './user_images';
				app::$is_debug	= false;
		}


		$mysqli_platform =new \mysqli($__host, $__user, $__password, $__db, $__port );

		if( $mysqli_platform->connect_errno )
		{
			echo "Failed to connect to MySQL: (" . $mysqli_platform->connect_errno . ") " . $mysqli_platform->connect_error;
			exit();
		}

		$mysqli_platform->query("SET NAMES 'utf8';");
		$mysqli_platform->query("SET time_zone = '+0:00'");
		$mysqli_platform->set_charset('utf8');

		app::$platform_db_connection = $mysqli_platform;

		$sql_domain = 'SELECT * FROM domain WHERE domain = "'.$mysqli_platform->real_escape_string($domain).'" LIMIT 1';

		$row	= $mysqli_platform->query( $sql_domain );

		if( !$row )
		{
			header("HTTP/1.0 404 Not Found");
			echo'No se encontrol el dominio '.$domain;
			die();
		}
		$domain = null;

		if( !($domain= $row->fetch_object()) )
		{
			header("HTTP/1.0 404 Not Found");
			echo'No se encontrol el dominio '.$domain;
			die();
		}

		$store_result = app::$platform_db_connection->query('SELECT * FROM store WHERE id = "'.app::$platform_db_connection->real_escape_string( $domain->store_id ).'"');
		$store = null;

		if( !($store = $store_result->fetch_object()) )
		{
			header("HTTP/1.0 404 Not Found");
			echo'No se encontrol el dominio '.$domain;
			die();
		}


		$mysqli	= new \mysqli($store->db_server, $store->db_user, $store->db_password, $store->db_name, $store->db_port);

		if( $mysqli->connect_errno )
		{
			header("HTTP/1.0 500 Internal Server Error");
			echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
			exit();
		}

		app::$store_db_connection = $mysqli;
		app::$filename_prefix = $store->db_name;

		date_default_timezone_set('UTC');

		$mysqli->query("SET NAMES 'utf8';");
		$mysqli->query("SET time_zone = '+0:00'");
		$mysqli->set_charset('utf8');

		DBTable::$connection	= $mysqli;
	}

	static function getPasswordHash( $password, $timestamp )
	{
		return sha1($timestamp.$password.'sdfasdlfkjasld');
	}

	/* https://stackoverflow.com/questions/40582161/how-to-properly-use-bearer-tokens */

	static function getAuthorizationHeader(){
		$headers = null;
		if (isset($_SERVER['Authorization'])) {
			$headers = trim($_SERVER["Authorization"]);
		}
		else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
			$headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
		} elseif (function_exists('apache_request_headers')) {
			$requestHeaders = apache_request_headers();
			// Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
			$requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
			//print_r($requestHeaders);
			if (isset($requestHeaders['Authorization'])) {
				$headers = trim($requestHeaders['Authorization']);
			}
		}
		return $headers;
	}


	/**
	* get access token from header
	* */
	static function getBearerToken() {
		$headers = App::getAuthorizationHeader();
		// HEADER: Get the access token from the header
		if (!empty($headers)) {
			if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
				return $matches[1];
			}
		}
		return null;
	}

	//static function getOrganizationFromDomain()
	//{
	//	$returned_var = app::getCustomHttpReferer();
	//	$domain_url		= parse_url( $returned_var );

	//	$domain_name	= $domain_url[ 'host' ];

	//	$domain = domain::searchFirst(array('name'=>$domain_name) );

	//	if( $domain )
	//		return organization::get( $domain->organization_id);

	//	return null;

	//}

	static function getUserFromSession()
	{
		$token = App::getBearerToken();
		if( $token == null )
			return null;

		$platform_pos = strpos($token,'Platform',0);

		if( $platform_pos === 0 )
		{
			$sql = 'SELECT *
				FROM session
				WHERE id = "'.app::$platform_db_connection->real_escape_string( $token ).'"
				LIMIT 1';

			$result = app::$platform_db_connection->query( $sql );
			if( !$result )
			{
				return null;
			}

			$session = $result->fetch_assoc();

			if( !$session )
			{
				return null;
			}

			$user = user::searchFirst(array('platform_client_id'=>$session['platform_client_id']));

			if( $user )
			{
				return $user;
			}

			$platform_client_sql = 'SELECT *
				FROM platform_client
				WHERE id = "'.app::$platform_db_connection->real_escape_string( $session['platform_client_id'] ).'"
				LIMIT 1';

			$pc_result = app::$platform_db_connection->query( $platform_client_sql );

			if( !$pc_result )
			{
				return null;
			}

			$platform_client = $pc_result->fetch_object();

			if( !$platform_client )
			{
				return null;
			}

			$user						= new user();
			$user->platform_client_id	= $platform_client->id;
			$user->name					= $platform_client->name;
			$user->email				= $platform_client->email;
			$user->phone				= $platform_client->phone;
			$user->price_type_id		= 1;
			$user->type					= 'CLIENT';

			if( !$user->insertDb() )
			{
				throw new SystemException('Ocurrio un error por favor intente mas tarde '.$user->getError());
			}
			$user->load();

			return $user;
		}

		return App::getUserFromToken( $token );
	}

	static function getRandomString($length)
	{
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);

		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}

	static function getUserFromToken($token)
	{
		if( $token == null )
			return null;

		$user	= new user();
		$session		= new session();
		$session->id	= $token;
		//$session->estatus = 'SESION_ACTIVA';
		$session->setWhereString();


		if( $session->load() )
		{
			$user = new user();
			$user->id = $session->user_id;

			if( $user->load(true) )
			{
				return $user;
			}
		}
		return null;
	}

	static function getCustomHttpReferer()
	{
		$return_var	= FALSE;


		if( !empty( $_GET['domain'] ) )
		{
			$return_var = 'http://'.$_GET['domain'];
		}
		else if( isset( $_SERVER['HTTP_HOST'] ) )
		{
			$return_var = $_SERVER['HTTP_HOST'];
		}
		else if( isset( $GLOBALS['domain'] ) )
		{
			if
			(
				isset( $GLOBALS['domain']['scheme'] )
				&&
				isset( $GLOBALS['domain']['host'] )
				&&
				isset( $GLOBALS['domain']['path'] )
			)
			{
				$return_var = $GLOBALS['domain']['scheme'] .
				'://' .
				$GLOBALS['domain'].
				$GLOBALS['domain']['path'];
			}
			else
			{
			}
		}
		else if( isset( $_SERVER['HTTP_REFERER'] ) )
		{
			$return_var = $_SERVER['HTTP_REFERER'];
		}
		else if( isset( $_SERVER['HTTP_ORIGIN'] ) )
		{
			$return_var = $_SERVER['HTTP_ORIGIN'];
		}



		if( !empty( $return_var ) )
		{
			$not_valie = array('https://','http://','www.');
			$valid = array('','','');
			$return_var = str_replace( $not_valie, $valid, $return_var );
		}

		$return_var = 'http://'.$return_var;

		$parse = parse_url( $return_var );
		return $parse['host'];
	}
}
