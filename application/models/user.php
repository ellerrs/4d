<?php

class User_Model extends ORM
{

	//protected $has_one = array('game');
	//protected $has_many = array( 'whiteplayer'=>'games','blackplayer'=>'games' );
	protected $has_many = array('games'=>array('model'=>'games','foreign_key'=>array('whiteplayer_id','blackplayer_id')),'messages'=>array('model'=>'messages','foreign_key'=>'to_user_id'));

}

?>