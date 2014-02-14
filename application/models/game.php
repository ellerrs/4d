<?php

class Game_Model extends ORM
{

	const LAST_MOVE = 110;
	const TURN = 94;
	const WHITE_PLAYER_ID = 78;
	const BLACK_PLAYER_ID = 62;
	const WHITE_PLAYER_NAME = 46;
	const BLACK_PLAYER_NAME = 30;

	const W_CASTLE_K = 109;
	const W_CASTLE_Q = 93;
	const B_CASTLE_K = 77;
	const B_CASTLE_Q = 61;
	const FIFTY_MOVE_DRAW = 45;
	const TIME_OF_LAST_MOVE = 29;

	const W_EN_PASSANT = 108;
	const B_EN_PASSANT = 92;
	const GAME_NAME = 76;
	const GAME_ID = 60;
	const GAME_FINISHED = 44;
	const PLY = 28;

	const STATE_INDEX = 107;
	const NUM_STATES = 91;
	const LAST_MOVE_SRC = 75;
	const LAST_MOVE_DST = 59;
	const WHITE_IN_CHECK = 43;
	const BLACK_IN_CHECK = 27;

	const PENDING_DRAW = 106;

	public $state = null;
	public $stateIndex = 0;
	public $numStates = 1;

	protected $has_many = array('states');
	protected $belongs_to = array('state','whiteplayer' => 'user', 'blackplayer' => 'user');

	public function loadState($stateIndex=0)
	{
		$db = new Database();
		$state_query = $db->select('id')->from('states')->where('game_id = '.$this->id.'')->orderby('time','desc')->get();
		if(!isset($state_query[$stateIndex]))
		{
			$stateIndex = 0;
		}
		$state = ORM::factory('state',$state_query[$stateIndex]->id);
		$this->stateIndex = $stateIndex;
		$this->numStates = count($state_query)-1;
		$this->state = $state;
	}

}

?>
