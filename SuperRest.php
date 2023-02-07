<?php
namespace APP;

include_once( __DIR__.'/app.php' );
include_once( __DIR__.'/akou/src/ArrayUtils.php');

use \akou\Utils;
use \akou\DBTable;
use \akou\ValidationException;
use \akou\LoggableException;
use \akou\SystemException;
use \akou\NotFoundException;


class SuperRest extends \akou\RestController
{
	const LIKE_SYMBOL='~~';
	const CSV_SYMBOL=',';
	const LT_SYMBOL='<';
	const LE_SYMBOL='<~=';
	const GE_SYMBOL='>~=';
	const EQ_SYMBOL='';
	const GT_SYMBOL='>';
	const NOT_NULL_SYMBOL = '@';
	const DIFFERENT_SYMBOL = '!=';
	const ENDS_SYMBOL	= '$';

	public $is_debug = false;

	function options()
	{
		$this->setAllowHeader();
		return $this->defaultOptions();
	}

	function getPagination()
	{
		$page = 0;

		if( !empty( $_GET['page'] ) )
			$page = intval( $_GET['page'] );

		$page_size = 20;

		if( !empty( $_GET['limit'] ) )
		{
			if( $_GET['limit'] != '-1'  )
				$page_size = intval( $_GET['limit'] );
			else
				$page_size = 999999999;
		}

		return	$this->getPaginationInfo($page,$page_size,20);
	}
	function getEqualConstraints($array,$table_name='')
	{
		$constraints = [];
		foreach( $array as $index )
		{
			if( isset( $_GET[$index] ) && $_GET[$index] !== '' )
				$constraints[] =($table_name?'`'.$table_name.'`.':''). $index.'="'.DBTable::escape( $_GET[ $index ]).'"';
		}
		return $constraints;
	}

	function getNullConstraints($parameters, $table_name='')
	{
		$fields_from_request = !empty( $_GET['_NULL'] ) ? explode(',',$_GET['_NULL']) : [];
		$constraints = [];

		foreach($parameters as $field_name )
		{
			if( in_array($field_name, $fields_from_request) )
			{
				$constraints[] =($table_name?$table_name.'.':''). $field_name.' IS NULL';
			}
		}
		return $constraints;

	}
	function getNotNullConstraints($parameters, $table_name='')
	{
		$fields_from_request = !empty( $_GET['_NN'] ) ? explode(',',$_GET['_NN']) : [];
		$constraints = [];

		foreach($parameters as $field_name )
		{
			if( in_array($field_name, $fields_from_request) )
			{
				$constraints[] =($table_name?'`'.$table_name.'`.':''). $field_name.' IS NOT NULL';
			}
		}
		return $constraints;
	}

	function getDifferentConstraints( $array, $table_name)
	{
		$constraints = array();

		foreach( $array as $index )
		{
			if( isset( $_GET[$index.'!'] ) && $_GET[$index.'!'] !== '' )
			{
				$constraints[] = ($table_name?'`'.$table_name.'`.':'').$index.' != "'.DBTable::escape( trim( $_GET[ $index.'!' ] ) ).'%"';
			}
		}
		return $constraints;
	}

	function getStartLikeConstraints( $array, $table_name)
	{
		$constraints = array();

		foreach( $array as $index )
		{
			if( isset( $_GET[$index.'^'] ) && $_GET[$index.'^'] !== '' )
			{
				$constraints[] = ($table_name?'`'.$table_name.'`.':'').$index.' LIKE "'.DBTable::escape( trim( $_GET[ $index.'^' ] ) ).'%"';
			}
		}
		if( count( $constraints ) )
			return array( "(".join(' OR ',$constraints ).")" );
		return array();
	}

	function getEndsWithConstraints( $array, $table_name)
	{
		$constraints = array();

		foreach( $array as $index )
		{
			if( isset( $_GET[$index.'$'] ) && $_GET[$index.'$'] !== '' )
			{
				$constraints[] = ($table_name?'`'.$table_name.'`.':'').$index.' LIKE "%'.DBTable::escape( trim( $_GET[ $index.'$' ] ) ).'"';
			}
		}
		if( count( $constraints ) )
			return array( "(".join(' OR ',$constraints ).")" );
		return array();
	}


	function getLikeConstraints($array,$table_name='')
	{
		$constraints = [];
		foreach( $array as $index )
		{
			if( isset( $_GET[$index.static::LIKE_SYMBOL ] ) && $_GET[$index.static::LIKE_SYMBOL] !== '' )
			{
				$constraints[] = ($table_name?'`'.$table_name.'`.':'').$index.' LIKE "%'.DBTable::escape(trim($_GET[ $index.static::LIKE_SYMBOL ]) ) .'%"';
			}
		}
		if( count( $constraints ) )
			return array( "(".join(' OR ',$constraints ).")" );
		return array();
	}

	function getBiggerOrEqualThanConstraints( $array, $table_name )
	{
		$constraints = [];

		foreach( $array as $index )
		{
			if( isset( $_GET[$index.'>~'] ) && $_GET[$index.'>~'] !== '' )
			{
				$constraints[] = ($table_name?'`'.$table_name.'`.':'').$index.' >= "'.DBTable::escape( $_GET[ $index.'>~' ]).'"';
			}
		}


		return $constraints;
	}
	function getBiggerThanConstraints($array ,$table_name='')
	{
		$constraints = [];

		foreach( $array as $index )
		{
			if( isset( $_GET[$index.'>'] ) && $_GET[$index.'>'] !== '' )
			{
				$constraints[] =($table_name?'`'.$table_name.'`.':'').$index.' > "'.DBTable::escape( $_GET[ $index.'>' ]).'"';
			}
		}
		return $constraints;
	}

	function getSmallestThanConstraints($array,$table_name='')
	{
		$constraints = [];
		foreach( $array as $index )
		{
			if( isset( $_GET[$index.'<'] ) && $_GET[$index.'<'] !== '' )
				$constraints[] = ($table_name?'`'.$table_name.'`.':'').$index.' < "'.DBTable::escape( $_GET[ $index.'<' ]).'"';
		}
		return $constraints;
	}
	function getSmallestOrEqualThanConstraints($array,$table_name='')
	{
		$constraints = [];
		foreach( $array as $index )
		{
			if( isset( $_GET[$index.'<~'] ) && $_GET[$index.'<~'] !== '' )
				$constraints[] =($table_name?'`'.$table_name.'`.':'').$index.' <= "'.DBTable::escape( $_GET[ $index.'<~' ]).'"';
		}
		return $constraints;
	}

	function getCsvConstraints($array,$table_name='')
	{
		$constraints = [];
		foreach( $array as $index )
		{
			if( isset( $_GET[$index.','] ) && $_GET[$index.','] !== '' )
				$constraints[] = ($table_name?'`'.$table_name.'`.':'').$index.' IN ('.DBTable::escapeCSV( $_GET[ $index.',' ]).')';
		}
		return $constraints;
	}

	function getSymbolConstraints($symbol, $array,$table_name='',$check_table=false)
	{
		$cmp_array = array
		(
			static::EQ_SYMBOL => "=",
			static::DIFFERENT_SYMBOL => "!=",
			static::CSV_SYMBOL=>'IN',
			static::LIKE_SYMBOL=>"LIKE",
			static::GE_SYMBOL =>">=",
			static::LE_SYMBOL =>"<=",
			static::LT_SYMBOL =>"<",
			static::GT_SYMBOL =>">",
			static::NOT_NULL_SYMBOL =>" IS NOT NULL"
		);

		$cmp = $cmp_array[ $symbol ];

		$constraints = [];
		$tbl = $table_name ? $table_name.'.' : '';
		$like_string = $symbol == static::LIKE_SYMBOL ? '%':'';

		foreach($array as $index )
		{
			$tbl_index = $check_table ? $tbl.$index.$symbol : $index.$symbol;

			$value = '"'.DBTable::escape( $_GET[ $tbl_index ] ).$like_string.'"';

			if( $symbol == static::NOT_NULL_SYMBOL )
			{
				$value = '';
			}
			else if( $symbol == static::CSV_SYMBOL )
			{
				$value = '('.DBTable::escapeArrayValues( $_GET[ $tbl_index ] ).')';
			}

			if( isset( $_GET[ $tbl_index ] ) && $_GET[ $tbl_index ] !== '' )
			{
				$constraints[] = $tbl.$index.' '.$cmp.' '.$value;
			}
		}
		return $constraints;
	}

	function getAllConstraints($key_constraints,$table_name='')
	{
		$like_constraints = $this->getLikeConstraints( $key_constraints, $table_name );
		$equal_constrints = $this->getEqualConstraints( $key_constraints, $table_name );
		$bigger_than_constraints = $this->getBiggerThanConstraints( $key_constraints, $table_name );
		$different_than_constraints = $this->getDifferentConstraints( $key_constraints, $table_name );
		$ge_constraints = $this->getBiggerOrEqualThanConstraints( $key_constraints, $table_name );
		$smallest_than_constraints = $this->getSmallestThanConstraints( $key_constraints, $table_name );
		$le_than_constraints = $this->getSmallestOrEqualThanConstraints( $key_constraints, $table_name );
		$csv_constraints	= $this->getCsvConstraints( $key_constraints, $table_name );
		$start_constraints			= $this->getStartLikeConstraints( $key_constraints, $table_name );
		$not_null_constrints	= $this->getNotNullConstraints( $key_constraints, $table_name );
		$null_constraints		= $this->getNullConstraints( $key_constraints, $table_name );
		$ends_constraints		= $this->getEndsWithConstraints( $key_constraints, $table_name );

		return array_merge
		(
			$like_constraints,
			$equal_constrints,
			$bigger_than_constraints,
			$different_than_constraints,
			$ge_constraints,
			$smallest_than_constraints,
			$le_than_constraints,
			$csv_constraints,
			$start_constraints,
			$ends_constraints,
			$null_constraints,
			$not_null_constrints
		);
	}

	function getSessionErrors($usuario,$roles = NULL )
	{
		if(empty( $usuario_session ))
		{
			return $this->sendStatus( 401 )->json(array('error'=>'Por favor inicia sesion'));
		}

		if( $roles !== NULL && !in_array( $usuario->tipo, $roles) )
		{
			return $this->sendStatus( 403 )->json(array('error'=>'Permiso denegado se necesita alguno de los siguientes permisos '.join(',', $roles)));
		}
		return NULL;
	}

	function isAssociativeArray(array $array)
	{
		return count(array_filter(array_keys($array), 'is_string')) > 0;
	}

	function debug($label, $array, $json=TRUE)
	{
		if( $json )
			error_log( $label.' '.json_encode( $array, JSON_PRETTY_PRINT ));
		else
			error_log( $label.' '.print_r( $array, true ) );
	}

	function debugArray($label, $array,$json=TRUE )
	{
		return $this->debug($label,$array,$json);
	}

	function saveReplay()
	{
		//$replay				= new replay();
		//$replay->url		= $_SERVER['REQUEST_URI'];
		//$replay->method		= $_SERVER['REQUEST_METHOD'];

		//$replay->get_params	= json_encode( $_GET );
		//$replay->post_params = json_encode( $this->getMethodParams() );
		//$replay->headers	= json_encode( getallheaders() );

		//if( !$replay->insert() )
		//{

		//}
	}

	function getGenericGetConstraintsString($table_name,$extra_constraints=array())
	{
		$class_name = "APP\\$table_name";
		$constraints = $this->getAllConstraints( $class_name::getAllProperties(), $table_name );
		$all_constraints = array_merge($constraints, $extra_constraints );
		return count( $all_constraints ) > 0 ? join(' AND ',$all_constraints ) : '1';
	}


	function getResultGeneric($table_name,$extra_constraints=array(),$extra_joins='',$extra_sort=array(),$extra_fields=array(),$group_by='')
	{
		$class_name = "APP\\$table_name";

		if( isset( $_GET['id'] ) && !empty( $_GET['id'] ) )
		{
			$obj_inst = $class_name::get( $_GET['id'] );

			if( $obj_inst )
			{
				if( method_exists($this,'getInfo') )
				{
					$result = $this->{'getInfo'}( array( $obj_inst->toArray() ) );
					return $this->sendStatus( 200 )->json( $result[0] );
				}
				else
				{
					return $this->sendStatus( 200 )->json( $obj_inst->toArray() );
				}
			}
			return $this->sendStatus( 404 )->json(array('error'=>'El elemento no se enconntro'));
		}

		$constraints = $this->getAllConstraints( $class_name::getAllProperties(), $table_name );

		$all_constraints = array_merge($constraints, $extra_constraints );
		$constraints_str = count( $all_constraints ) > 0 ? join(' AND ',$all_constraints ) : '1';
		$pagination	= $this->getPagination();
		$sort_string	= empty( $_GET['_sort'] ) ? '' : $this->getSortOrderString($_GET['_sort'], $table_name, $extra_sort);

		$extra_fields_str = empty($extra_fields) ? '' : ','.implode(',',$extra_fields);

		$offset_string = $pagination->offset == 0 ? '' : ' OFFSET '.$pagination->offset;

		$group_by_str = '';
		if( !empty( trim($group_by) ) )
		{
			$group_by_str = ' GROUP BY '.$group_by.' ';
		}

		$sql	= 'SELECT DISTINCT SQL_CALC_FOUND_ROWS `'.$table_name.'`.*'.$extra_fields_str.'
			FROM `'.$table_name.'`
			'.$extra_joins.'
			WHERE '.$constraints_str.'
			'.$group_by_str.'
			'.$sort_string.'
			LIMIT '.$pagination->limit.' '.$offset_string;

		if( $this->is_debug )
		{
			error_log('GENERIC SQL '.$sql );
		}

		$info	= DBTable::getArrayFromQuery( $sql );
		$total	= DBTable::getTotalRows();

		if( method_exists($this,'getInfo') )
		{
			$result = $this->{'getInfo'}( $info );

			return array("total"=>$total,"data"=>$result);
		}

		return array("total"=>$total, "data"=>$info);
	}

	function genericGet($table_name,$extra_constraints=array(),$extra_joins='',$extra_sort=array(),$extra_fields=array(),$group_by='')
	{
		$result = $this->getResultGeneric($table_name,$extra_constraints,$extra_joins,$extra_sort,$extra_fields, $group_by);
		return $this->sendStatus( 200 )->json( $result );
	}

	function getSortOrderString($sort_value, $table_name, $extra_sort=array() )
	{
		if( empty( trim( $sort_value) ) )
			return '';

		$class_name = 'APP\\'.$table_name;

		$properties = $class_name::getAllProperties();
		$elements = explode(',', $_GET['_sort'] );

		$sort_array = array();

		foreach($elements as $sort_field)
		{
			$tokens = explode('_', $sort_field);

			$parts = explode('_', $sort_field);
			$last = array_pop($parts);
			$tokens = array(implode('_', $parts), $last);

			if( count( $tokens) !== 2 )
				continue;

			$direction = $tokens[1];

			if( $direction === 'ASC' || $direction ==='DESC')
			{
				$property = $tokens[0];
				if(in_array( $property, $properties, TRUE ) )
				{
					$sort_array[] = '`'.$table_name.'`.'.$property.' '.$direction;
				}
				else if( in_array($property, $extra_sort ) )
				{
					$sort_array[] = $property.' '.$direction;
				}
			}
		}
		if( empty( $sort_array ) )
			return '';

		return ' ORDER BY '.join(',',$sort_array).PHP_EOL;
	}

	function genericPost($table_name)
	{
		$this->setAllowHeader();
		$params = $this->getMethodParams();
		app::connect();
		DBTable::autocommit(false );
		try
		{
			$user = app::getUserFromSession();
			if( $user == null )
				throw new ValidationException('Please login');

			$is_assoc	= $this->isAssociativeArray( $params );
			$result		= $this->genericInsert( $is_assoc  ? array($params) : $params, $table_name );

			if( method_exists($this,'getInfo') )
			{
				$info = $this->{'getInfo'}( $result );
			}
			else
			{
				$info = $result;
			}

			DBTable::commit();
			return $this->sendStatus( 200 )->json( $is_assoc ? $info[0] : $info );
		}
		catch(LoggableException $e)
		{
			DBTable::rollback();
			return $this->sendStatus( $e->code )->json(array("error"=>$e->getMessage()));
		}
		catch(\Exception $e)
		{
			DBTable::rollback();
			return $this->sendStatus( 500 )->json(array("error"=>$e->getMessage()));
		}
	}

	function genericPut($table_name,$insert_with_ids, $optional_values=array(),$system_values=array(),$banned_values=array())
	{
		$this->setAllowHeader();
		$params = $this->getMethodParams();
		app::connect();
		DBTable::autocommit(false );
		try
		{
			$user = app::getUserFromSession();
			if( $user == null )
				throw new ValidationException('Please login');

			$is_assoc	= $this->isAssociativeArray( $params );
			$result		= $this->genericUpdate($params, $table_name, $insert_with_ids, $optional_values,$system_values,$banned_values);

			if( method_exists($this,'getInfo') )
			{
				$info = $this->{'getInfo'}( $result );
			}
			else
			{
				$info = $result;
			}

			DBTable::commit();
			return $this->sendStatus( 200 )->json( $is_assoc ? $info[0] : $info );
		}
		catch(LoggableException $e)
		{
			DBTable::rollback();
			return $this->sendStatus( $e->code )->json(array("error"=>$e->getMessage()));
		}
		catch(\Exception $e)
		{
			DBTable::rollback();
			return $this->sendStatus( 500 )->json(array("error"=>$e->getMessage()));
		}
	}


	function genericInsert($array, $table_name, $optional_values=array(), $system_values=array(), $banned_values=null)
	{
		$class_name = "APP\\$table_name";
		$results = array();

		$user = app::getUserFromSession();

		$except = $banned_values;

		if( $except == $banned_values )
			$except = array('id','created','updated','tiempo_creacion','tiempo_actualizacion','updated_by_user_id','created_by_user_id');

		$properties = $class_name::getAllPropertiesExcept( $except );

		foreach($array as $params )
		{
			$obj_inst = new $class_name;

			if( !empty( $optional_values ) )
				$obj_inst->assignFromArray( $optional_values );

			$obj_inst->assignFromArray( $params, $properties );

			if( !empty( $system_values ) )
				$obj_inst->assignFromArray( $system_values );

			$obj_inst->unsetEmptyValues( DBTable::UNSET_BLANKS );

			if( $user )
			{
				$user_array = array('updated_by_user_id'=>$user->id,'created_by_user_id'=>$user->id);
				$obj_inst->assignFromArray( $user_array );
			}

			if( !$obj_inst->insert() )
			{
				throw new ValidationException('An error Ocurred please try again later'.$obj_inst->getError() );
			}

			$results [] = $obj_inst->toArray();
		}

		return $results;

	}

	function genericUpdate($array, $table_name, $insert_with_ids, $optional_values=array(),$system_values=array(),$banned_values=array())
	{
		$class_name = "APP\\$table_name";

		$results = array();
		$user = app::getUserFromSession();

		$except = array_merge($banned_values,array('id','created','updated','tiempo_creacion','tiempo_actualizacion','updated_by_user_id','created_by_user_id'));

		$properties = $class_name::getAllPropertiesExcept( $except );

		foreach($array as $index=>$params )
		{
			$obj_inst = $class_name::createFromArray( $params );

			if( $insert_with_ids )
			{
				if( !empty( $obj_inst->id ) )
				{
					if( $obj_inst->load(true) )
					{
						$obj_inst->assignFromArray( $params, $properties );
						$obj_inst->unsetEmptyValues( DBTable::UNSET_BLANKS );


						if( $user )
						{
							$user_array = array('updated_by_user_id'=>$user->id);
							if( property_exists($obj_inst,'updated_by_user_id') )
								$obj_inst->assignFromArray( $user_array );
						}

						if( !$obj_inst->update($properties) )
						{
							throw new ValidationException('It fails to update element #'.$obj_inst->id);
						}

						if( $this->is_debug )
						{
							error_log('GENERIC Update SQL' .$obj_inst->getLastQuery() );
						}

						$results[] = $obj_inst->toArray();
					}
					else
					{
						if( $user )
						{
							if( property_exists($obj_inst,'updated_by_user_id') || property_exists($obj_inst,'created_by_user_id') )
							{
								$user_array = array('updated_by_user_id'=>$user->id,'created_by_user_id'=>$user->id);
								$obj_inst->assignFromArray( $user_array );
							}
						}

						if( !$obj_inst->insertDb() )
						{
							throw new ValidationException('It fails to update element at index #'.$index);
						}
						$results[] = $obj_inst->toArray();
					}
				}
			}
			else
			{
				if( !empty( $obj_inst->id ) )
				{
					$obj_inst->setWhereString( true );

					$except = array('id','created','updated','tiempo_creacion','tiempo_actualizacion','updated_by_user_id','created_by_user_id');
					$properties = $class_name::getAllPropertiesExcept( $except );
					$obj_inst->unsetEmptyValues( DBTable::UNSET_BLANKS );

					if( $user )
					{
						$user_array = array('updated_by_user_id'=>$user->id);
						$obj_inst->assignFromArray( $user_array );
					}

					if( !$obj_inst->updateDb( $properties ) )
					{
						error_log($obj_inst->getLastQuery());
						throw new ValidationException('An error Ocurred please try again later'.$obj_inst->getError() );
					}

					if( $this->is_debug )
					{
						error_log('GENERIC Update SQL' .$obj_inst->getLastQuery() );
					}

					$obj_inst->load(true);

					$results [] = $obj_inst->toArray();
				}
				else
				{
					if( $user )
					{
						$user_array = array('updated_by_user_id'=>$user->id,'created_by_user_id'=>$user->id);
						$obj_inst->assignFromArray( $user_array );
					}

					$obj_inst->unsetEmptyValues( DBTable::UNSET_BLANKS );
					if( !$obj_inst->insert() )
					{
						throw new ValidationException('An error Ocurred please try again later'.$obj_inst->getError() );
					}

					$results [] = $obj_inst->toArray();
				}
			}
		}

		return $results;
	}

	function genericDelete($table_name)
	{
		$class_name = "APP\\$table_name";
		try
		{
			app::connect();
			DBTable::autocommit( false );

			$user = app::getUserFromSession();

			if( $user == null )
				throw new ValidationException('Please login');

			if( empty( $_GET['id'] ) )
			{
				throw new ValidationException('El id del objeto no puede estar vacio');
			}
			$obj_inst = new $class_name;
			$obj_inst->id = $_GET['id'];

			if( !$obj_inst->load(true) )
			{
				throw new NotFoundException('The element was not found');
			}

			if( !$obj_inst->deleteDb() )
			{
				throw new SystemException('An error occourred, please try again later');
			}

			DBTable::commit();
			return $this->sendStatus( 200 )->json( $obj_inst->toArray() );
		}
		catch(LoggableException $e)
		{
			DBTable::rollback();
			return $this->sendStatus( $e->code )->json(array("error"=>$e->getMessage()));
		}
		catch(\Exception $e)
		{
			DBTable::rollback();
			return $this->sendStatus( 500 )->json(array("error"=>$e->getMessage()));
		}
	}
	function execute()
	{
		try
		{
			parent::execute();
		}
		catch(LoggableException $e)
		{
			return $this->sendStatus( $e->code )->json(array("error"=>$e->getMessage()));
		}
		catch(\Exception $e)
		{
			if( DBTable::$connection )
			{
				error_log( DBTable::$connection->error );
			}
			return $this->sendStatus( 500 )->json(array("error"=>$e->getMessage()));
		}
	}
}
