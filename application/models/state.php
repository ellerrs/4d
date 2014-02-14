<?php

class State_Model extends ORM
{

	protected $belongs_to = array('game');
	protected $has_one = array('game');

	public static function startingState()
	{
		return '[{"piece":6,"position":0},{"piece":2,"position":1},{"piece":5,"position":2},{"piece":7,"position":3},{"piece":3,"position":4},{"piece":5,"position":5},{"piece":2,"position":6},{"piece":6,"position":7},{"piece":-6,"position":112},{"piece":-2,"position":113},{"piece":-5,"position":114},{"piece":-7,"position":115},{"piece":-3,"position":116},{"piece":-5,"position":117},{"piece":-2,"position":118},{"piece":-6,"position":119},{"piece":1,"position":16},{"piece":1,"position":17},{"piece":1,"position":18},{"piece":1,"position":19},{"piece":1,"position":20},{"piece":1,"position":21},{"piece":1,"position":22},{"piece":1,"position":23},{"piece":-1,"position":96},{"piece":-1,"position":97},{"piece":-1,"position":98},{"piece":-1,"position":99},{"piece":-1,"position":100},{"piece":-1,"position":101},{"piece":-1,"position":102},{"piece":-1,"position":103}]';
	}
}

?>