<?php defined('SYSPATH') OR die('No direct access allowed.');

// TODO: add array to support multiple messages being returned via json

class Game_Controller extends Template_Controller {

	// Disable this controller when Kohana is set to production mode.
	// See http://docs.kohanaphp.com/installation/deployment for more details.
	const ALLOW_PRODUCTION = FALSE;

	// Set the name of the template to use
	public $template = 'templates/default';

	public $board = null;
	public $pieces = null;

	const WHITE_QUEEN = 7;
	const WHITE_ROOK = 6;
	const WHITE_BISHOP = 5;
	const WHITE_KING = 3;
	const WHITE_KNIGHT = 2;
	const WHITE_PAWN = 1;

	const BLACK_QUEEN = -7;
	const BLACK_ROOK = -6;
	const BLACK_BISHOP = -5;
	const BLACK_KING = -3;
	const BLACK_KNIGHT = -2;
	const BLACK_PAWN = -1;

	// abs() the piece before comparing here
	const QUEEN = 7;
	const ROOK = 6;
	const BISHOP = 5;
	const KING = 3;
	const KNIGHT = 2;
	const PAWN = 1;

	public function  __construct()
	{
		if (request::is_ajax())
		{
			$this->auto_render = FALSE;
		}
		parent::__construct();
		$this->session = Session::instance();
	}

	public function changeTemplate($template)
	{
		$this->template = 'templates/'.$template;
		parent::__construct();
	}

	public function view($game='')
	{
		$this->auto_render = true;
		$this->template->content = View::factory('game');
		$this->template->title = 'Game';
		$this->template->content->game = $game;
	}

	public function index()
	{
		$this->auto_render = true;
		$this->template->content = View::factory('game');
		$this->template->title = 'Game';
		$this->template->content->game = '';
	}

	public function makeMove($game_id,$src,$dst,$promotion_piece=null)
	{
		$this->auto_render = false;
		$this->verify_logged_in();

		// load game and game state
		$game = ORM::factory('game',$game_id);
		$game->loadState(0);
		$pieces = json_decode($game->state->positions);
		$board = $this->getBoard($game,$pieces);

		// get chess src and dst
		$hex_src = str_pad(dechex($src),2,'0',STR_PAD_LEFT);
		$chess_src_file = format::file($hex_src[1]);
		$chess_src_rank = format::rank($hex_src[0]);
		$chess_src = $chess_src_file.$chess_src_rank;

		$hex_dst = str_pad(dechex($dst),2,'0',STR_PAD_LEFT);
		$chess_dst_file = format::file($hex_dst[1]);
		$chess_dst_rank = format::rank($hex_dst[0]);
		$chess_dst = $chess_dst_file.$chess_dst_rank;

		if(!isset($pieces[$board[$src]])) $this->json_error('Invalid Move','There is no piece to move at '.$chess_src.'!',$game_id);
		
		// remember some stuff
		$piece = $pieces[$board[$src]];		// the piece object at the src square
		$dstpiece = (isset($pieces[$board[$dst]]) ? $pieces[$board[$dst]] : null);	// the piece object at the destination square (if any)
		$capture = (isset($pieces[$board[$dst]]) ? true : false);	// if there is a piece at the destination, $capture is true. 
																	// we'll also set this later for an en passant and unset this later for a castle
		// some defaults:
		$whiteEnPassant = $board[Game_Model::W_EN_PASSANT];
		$blackEnPassant = $board[Game_Model::B_EN_PASSANT];
		$whiteCastleKing = $board[Game_Model::W_CASTLE_K];
		$whiteCastleQueen = $board[Game_Model::W_CASTLE_Q];
		$blackCastleKing = $board[Game_Model::B_CASTLE_K];
		$blackCastleQueen = $board[Game_Model::B_CASTLE_Q];
		$fiftyMoveDraw = $board[Game_Model::FIFTY_MOVE_DRAW];
		$en_passant = false;
		$promotion = false;
		$castle = false;
		$white_in_check = false;
		$black_in_check = false;

		// is one of our own pieces at the dst?
		if(isset($dstpiece) && (($piece->piece < 0 && $dstpiece->piece < 0) || ($piece->piece > 0 && $dstpiece->piece > 0)))
		{
			$this->json_error('Invalid Move','You can\'t attack your own piece!',$game_id);
		}

		// is it our piece?
		if($piece->piece > 0 && $this->session->get('user')!=$game->whiteplayer->id) $this->json_error('Invalid Move','That isn\'t your piece!',$game_id);
		if($piece->piece < 0 && $this->session->get('user')!=$game->blackplayer->id) $this->json_error('Invalid Move','That isn\'t your piece!',$game_id);

		// does the piece match the turn?
		if(($piece->piece > 0 && $board[Game_Model::TURN]==0) || ($piece->piece < 0 && $board[Game_Model::TURN]==1))
		{
			$this->json_error('Invalid Move','It is '.($board[Game_Model::TURN]==1 ? 'White\'s' : 'Black\'s').' turn!',$game_id);
		}

		// src and dst are not the same?
		if($src==$dst) $this->json_error('Invalid Move','You have to actually move!',$game_id);

		// is the dst on the board?
		if(($dst & 0x88) != 0) $this->json_error('Invalid Move','That\'s not a valid square!',$game_id);

		// get the deltas for this piece
		$arrDelta = $this->getDeltas($piece);

		// generate possible moves for this piece
		$valid_destinations = $this->moveGeneration($game_id,$src);
		
		// throw error if $dst is not in list of valid moves
		if(
			!in_array($dst,$valid_destinations['moves']) &&
			!in_array($dst,$valid_destinations['captures']) &&
			!in_array($dst,$valid_destinations['enpassant'])  &&
			!in_array($dst,$valid_destinations['castles']))
		{
			$this->json_error('Invalid Move','You can\'t move there!',$game_id);
		}

		// is this a promotion? (convert to hex, white at < 10, black at >= 70)
		if($piece->piece==self::WHITE_PAWN && dechex($dst) >= 70)
		{
			$promotion=true;
		}
		if($piece->piece==self::BLACK_PAWN && dechex($dst) < 10)
		{
			$promotion=true;
		}
		if($promotion)
		{
			if(!isset($promotion_piece))
			{
				//error, need to select promotion piece!
				$this->json_error('Missing Information','You need to specify a promotion piece!',$game_id);
			}
			//promote this piece
			$new_piece_type = ($piece->piece < 0 ? '-' : '').$promotion_piece;
		}
		else
		{
			//if not a promotion, new_piece_type is the exact same as the original.
			$new_piece_type = $piece->piece;
		}

		// could any other of our (piece->piece) pieces move to the same dst?
		$ambiguousLocations = array();
		$ambiguousPieces = $this->getLocationOfSpecifiedPieces($piece->piece,$pieces);
		foreach($ambiguousPieces as $ambiguousPiece)
		{
			if($ambiguousPiece == $src) continue;
			$controlledSquares = $this->moveGeneration($game_id,$ambiguousPiece,$pieces);
			if(in_array($dst,$controlledSquares['moves']) || in_array($dst,$controlledSquares['captures']))
			{
				$ambiguousLocations[] = $ambiguousPiece;
			}
		}
		$ambiguousNotationSrc = '';
		foreach($ambiguousLocations as $location)
		{
			// get the file and rank of this piece
			$hex_amb = str_pad(dechex($location),2,'0',STR_PAD_LEFT);
			$chess_amb_file = format::file($hex_amb[1]);
			$chess_amb_rank = format::rank($hex_amb[0]);
			// are files identical?
			if($chess_amb_file == $chess_src_file)
			{
				// files are identical. is rank identical?
				if($chess_amb_rank == $chess_src_rank)
				{
					// rank is identical, use file+rank
					$ambiguousNotationSrc = $chess_src;
				}
				// ranks are not identical, so use rank
				{
					$ambiguousNotationSrc = $chess_src_rank;
				}
			}
			// files are not identical, so use file
			else
			{
				$ambiguousNotationSrc = $chess_src_file;
			}
		}

		// Go ahead and move. We've got another check to make, but nothing will be saved yet.
		$pieces[$board[$dst]]->piece = $new_piece_type;
		$pieces[$board[$dst]]->position = $dst;
		unset($pieces[$board[$src]]);
		if($en_passant)	//TODO we're no longer storing the delta used, nor the $en_passant flag. figure this out a different way
		{
			if($delta < 0) unset($pieces[$board[$dst-16]]);
			if($delta > 0) unset($pieces[$board[$dst+16]]);
		}
		$pieces = array_values($pieces);
		// ReCalc board
		$board = $this->getBoard($game,$pieces);

		// put together some data groups of pieces
		if($piece->piece < 0)
		{
			$opponentKingLocation = $this->getLocationOfWhiteKing($pieces);
			$opponentPieceLocations = $this->getLocationOfWhitePieces($pieces);
			$opponentControlledSquares = $this->moveGeneration($game_id,$opponentPieceLocations,$pieces);

			$kingLocation = $this->getLocationOfBlackKing($pieces);
			$pieceLocations = $this->getLocationOfBlackPieces($pieces);
			$controlledSquares = $this->moveGeneration($game_id,$pieceLocations,$pieces);
		}
		else
		{
			$opponentKingLocation = $this->getLocationOfBlackKing($pieces);
			$opponentPieceLocations = $this->getLocationOfBlackPieces($pieces);
			$opponentControlledSquares = $this->moveGeneration($game_id,$opponentPieceLocations,$pieces);

			$kingLocation = $this->getLocationOfWhiteKing($pieces);
			$pieceLocations = $this->getLocationOfWhitePieces($pieces);
			$controlledSquares = $this->moveGeneration($game_id,$pieceLocations,$pieces);
		}

		if(abs($piece->piece)==self::KING && in_array($dst,$valid_destinations['castles']))
		{
			$castle = true;
			if($dst==($src+2))
			{
				// move the king-side rook
				$pieces[$board[($src+1)]]->piece = ($piece->piece > 0 ? 6 : -6);
				$pieces[$board[($src+1)]]->position = ($src+1);
				unset($pieces[$board[($src+3)]]);
				$pieces = array_values($pieces);
				// ReCalc board
				$board = $this->getBoard($game,$pieces);
				// set the notation
				$castle_notation='0-0';
			}
			elseif($dst==($src-2))
			{
				// move the queen-side rook
				$pieces[$board[($src-1)]]->piece = ($piece->piece > 0 ? 6 : -6);
				$pieces[$board[($src-1)]]->position = ($src-1);
				unset($pieces[$board[($src-4)]]);
				$pieces = array_values($pieces);
				// ReCalc board
				$board = $this->getBoard($game,$pieces);
				// set the notation
				$castle_notation='0-0-0';
			}
			else
			{
				// something went wrong, we should never end up here.
				$castle = false;
			}
		}

		// our king cannot be in check after we move
		// check the location of our king in the attacked squares of our opponent's pieces
		if(in_array($kingLocation,$opponentControlledSquares['moves']) || in_array($kingLocation,$opponentControlledSquares['captures']))
		{
			//our king is in check!
			$this->json_error('Invalid Move','Your king will be in check there. You can not move there!',$game_id);
		}

		// increment fiftyMoveDraw count unless pawn moved or piece captured (in which case, reset it)
		// note that this rule does not FORCE a draw, but the program will automatically accept one if one is offered.
		// TODO: should this be set to 1 instead of 0?
		$fiftyMoveDraw += 1;
		if(abs($piece->piece)==self::PAWN || $capture || $en_passant) $fiftyMoveDraw = 0;	//TODO we're no longer storing the $en_passant flag. figure this out a different way

		// if the rook or king is moving, turn off one or both [color]Castle[Side] switches
		if($piece->piece==self::WHITE_KING)
		{
			$whiteCastleKing = 0;
			$whiteCastleQueen = 0;
		}
		if($piece->piece==self::WHITE_ROOK)
		{
			 if($src==0) $whiteCastleQueen = 0;
			 if($src==7) $whiteCastleKing = 0;
		}
		if($piece->piece==self::BLACK_KING)
		{
			$blackCastleKing = 0;
			$blackCastleQueen = 0;
		}
		if($piece->piece==self::BLACK_ROOK)
		{
			 if($src==112) $blackCastleQueen = 0;
			 if($src==119) $blackCastleKing = 0;
		}

		// build the notation
		$notation = '';
		switch(abs($piece->piece))
		{
			case self::PAWN:
				if(($capture || $en_passant) && (!isset($ambiguousNotationSrc) || $ambiguousNotationSrc==''))
				{
					// if a captrue or en_passant and ambiguousNotationSrc has NOT been set, put the file in the notation as the piece notation
					$piece_abbr = format::file($hex_src[1]);
				}
				else
				{
					$piece_abbr = '';
				}
				$friendly_piece_name = 'Pawn';
				break;
			case self::ROOK:
				$piece_abbr = 'R';
				$friendly_piece_name = 'Rook';
				break;
			case self::KNIGHT:
				$piece_abbr = 'N';
				$friendly_piece_name = 'Knight';
				break;
			case self::BISHOP:
				$piece_abbr = 'B';
				$friendly_piece_name = 'Bishop';
				break;
			case self::KING:
				$piece_abbr = 'K';
				$friendly_piece_name = 'King';
				break;
			case self::QUEEN:
				$piece_abbr = 'Q';
				$friendly_piece_name = 'Queen';
				break;
		}

		if($castle)
		{
			$notation.=$castle_notation;
		}
		else
		{
			$notation.=$piece_abbr;
		}
		if(isset($ambiguousNotationSrc) && $ambiguousNotationSrc!='')
		{
			$notation.=$ambiguousNotationSrc;
		}
		if($capture || $en_passant)
		{
			$notation.='x';
		}
		if( ! $castle)
		{
			$notation.=$chess_dst;
		}
		if($en_passant)
		{
			$notation.=' e.p.';
		}
		// promotion needs to be indicated with suffixed "=Q" or "=N".
		if($promotion)
		{
			if($promotion_piece==self::QUEEN)
			{
				$notation.='=Q';
			}
			elseif($promotion_piece==self::KNIGHT)
			{
				$notation.='=N';
			}
		}
		// suffix + for check (later, after checkmate analyzation)
		// check the location of our king and the attacked squares of our pieces
		if(in_array($opponentKingLocation,$controlledSquares['moves']) || in_array($opponentKingLocation,$controlledSquares['captures']))
		{
			if($piece->piece < 0)
			{
				$white_in_check = true;
			}
			else
			{
				$black_in_check = true;
			}
		}

		//check to see if the opponent is in checkmate
		if(($this->session->get('user') == $game->blackplayer_id && $white_in_check) || ($this->session->get('user') == $game->whiteplayer_id && $black_in_check))
		{
			$can_escape_check = false;
			// can opponent's king go anywhere that isn't controlled by opponent?
			$possibleOpponentKingMoves = $this->moveGeneration($game_id,$opponentKingLocation,$pieces);
			foreach($possibleOpponentKingMoves as $possibleOpponentKingMove)
			{
				if(!in_array($possibleOpponentKingMove,$controlledSquares['moves']) && !in_array($possibleOpponentKingMove,$controlledSquares['captures']))
				{
					// king can move out of check
					$can_escape_check = true;
					break;
				}
			}
			// can any pieces block the check? // TODO: figure out best way to do this. for now, just cancel out
			$can_escape_check = true;
			
			if($can_escape_check)
			{
				$notation.='+';
			}
			else
			{
				$game->finished = true;
				$notation.='#';
				if($piece->piece < 0 && $white_in_check)
				{
					$notation.=' 0-1';
					$game->whiteplayer->losses+=1;
					$game->whiteplayer->save();
					$game->blackplayer->wins+=1;
					$game->blackplayer->save();
				}
				if($piece->piece > 0 && $black_in_check)
				{
					$notation.=' 1-0';
					$game->whiteplayer->wins+=1;
					$game->whiteplayer->save();
					$game->blackplayer->losses+=1;
					$game->blackplayer->save();
				}
			}
		}
		//TODO look for stalemate... can any opponent piece move without the king ending up in check?
		else
		{
			$stalemate = false;//replace this with ACTUAL stalemate check
			if($stalemate)
			{
				$game->finished = true;
				$notation.=' 1-2/1-2';
				$game->whiteplayer->draws+=1;
				$game->whiteplayer->save();
				$game->blackplayer->draws+=1;
				$game->blackplayer->save();
			}
		}

		// save the new state, update the game->state_id to equal the new state->id
		$state = ORM::factory('state');
		$state->game_id = $game->id;
		$state->ply = $board[Game_Model::PLY] + 1;
		$state->positions = json_encode($pieces);
		$state->turn = ($board[Game_Model::TURN] ? 0 : 1);
		$state->whiteEnPassant = $whiteEnPassant;
		$state->blackEnPassant = $blackEnPassant;
		$state->whiteCastleKing = $whiteCastleKing;
		$state->whiteCastleQueen = $whiteCastleQueen;
		$state->blackCastleKing = $blackCastleKing;
		$state->blackCastleQueen = $blackCastleQueen;
		$state->fiftyMoveDraw = $fiftyMoveDraw; // we actually need to let this get to 100, since we're counting by ply
		$state->lastMoveSrc = $src;
		$state->lastMoveDst = $dst;
		$state->whiteInCheck = $white_in_check;
		$state->blackInCheck = $black_in_check;
		$state->lastMove = $notation;
		$state->save();

		$game->state_id = $state->id;
		$game->save();

		// send a message to the opponent
		$message = ORM::factory('message');
		$message->to_user_id = ($piece->piece < 0 ? $game->whiteplayer_id : $game->blackplayer_id);
		$message->from_user_id = ($piece->piece > 0 ? $game->whiteplayer_id : $game->blackplayer_id);
		$message->subject = 'New move in Game "<a href="[domain]/4divisions/game/view/'.$game->id.'">'.$game->name.'</a>" ('.$notation.')';
		$message->text = 'A new move has been recorded in Game "'.$game->name.'". '.$friendly_piece_name.' was moved from '.$chess_src.' to '.$chess_dst.' (notation: '.$notation.').';
		$message->sticky = false;
		$message->save();
		$message->push('game/view/'.$game->id);

		$this->json_success('You\'ve Moved!','You moved your '.$friendly_piece_name.' from '.$chess_src.' to '.$chess_dst.' (notation: '.$notation.').',$game_id);
	}

	public function getValidMoves($game_id,$src)
	{
		$this->auto_render = false;
		$this->verify_logged_in();
		$validMoves=array('squares'=>array());
		$validMoves['squares'] = $this->moveGeneration($game_id,$src);
		echo json_encode($validMoves);
	}

	public function getHistory($game_id)
	{
		$this->auto_render = false;
		$this->verify_logged_in();
		$db = new Database();
		$state_query = $db->select('lastMove,ply')->from('states')->where(array('game_id = '=>$game_id,'ply > '=>0))->orderby('time','asc')->get();
		$history = array('history'=>array());
		foreach($state_query as $row)
		{
			$history['history'][]=array('turn'=>round($row->ply/2),'move'=>$row->lastMove);
		}
		echo json_encode($history);
	}

	//TODO : only allow undo if latest state->turn/state[color]player_id == logged in user
	public function undo($game_id)
	{
		$this->auto_render = false;
		$this->verify_logged_in();

		// load game
		$game = ORM::factory('game',$game_id);

		// get latest state and delete it
		$state = ORM::factory('state',$game->state_id);

		// find the latest TWO states
		$db = new Database();
		$state_query = $db->select('id')->from('states')->where('game_id = '.$game_id.'')->orderby('time','desc')->limit(2)->get();

		// if there is only one result, do NOT delete any states!
		if(count($state_query)==1)
                {
                    $this->json_error('Nothing to Undo!','There are no more moves to be undone.',$game_id);
                    exit();
                }

		// delete the first state
		$state = ORM::factory('state',$state_query[0]->id)->delete();

		// set the game->state_id to this second state->id
		$game->state_id = $state_query[1]->id;
		$game->save();

                $this->json_success('Success!','State removed. Latest state ID has been reverted to '.$game->state_id.'.',$game_id);
	}

	public function resign($game_id)
	{
		$this->auto_render = false;
		$this->verify_logged_in();
		
		$game = ORM::factory('game',$game_id);
		$game->loadState(0);

		// determine last move notation.
		if($this->session->get('user') == $game->whiteplayer_id)
		{
			$lastMove = '0-1';
			$opponent = $game->blackplayer_id;
		}
		elseif($this->session->get('user') == $game->blackplayer_id)
		{
			$lastMove = '1-0';
			$opponent = $game->whiteplayer_id;
		}
		// if user does not belong to this game, json_error to that effect.
		else
		{
			$this->json_error('Resignation Failed','You don\'t appear to be associated with this game.',$game->id);
		}

		// mark game as finished, add 1-0/0-1 as lastMove
		$state = ORM::factory('state');
		$state->game_id = $game->id;
		$state->ply = $game->state->ply + 1;
		$state->positions = $game->state->positions;
		$state->turn = ($game->state->turn ? 0 : 1);
		$state->whiteInCheck = $game->state->whiteInCheck;
		$state->blackInCheck = $game->state->blackInCheck;
		$state->lastMove = $lastMove;
		$state->save();

		if($this->session->get('user') == $game->whiteplayer_id)
		{
			$game->whiteplayer->losses+=1;
			$game->whiteplayer->save();
			$game->blackplayer->wins+=1;
			$game->blackplayer->save();
		}
		elseif($this->session->get('user') == $game->blackplayer_id)
		{
			$game->blackplayer->losses+=1;
			$game->blackplayer->save();
			$game->whiteplayer->wins+=1;
			$game->whiteplayer->save();
		}

		$game->finished = true;
		$game->state_id = $state->id;
		$game->save();
		
		// send a message to the opponent
		$message = ORM::factory('message');
		$message->to_user_id = $opponent;
		$message->from_user_id = $this->session->get('user');
		$message->subject = 'Resignation in Game "<a href="[domain]/4divisions/game/view/'.$game->id.'">'.$game->name.'</a>"';
		$message->text = 'Congratulations, Your opponent has resigned. You\'ve won!.';
		$message->sticky = false;
		$message->save();
		$message->push('game/view/'.$game->id);

		$this->json_success('Resignation','You\'ve successfully resigned from Game "'.$game->name.'"',$game->id);
	}

	public function offerDraw($game_id)
	{
		$this->auto_render = false;
		$this->verify_logged_in();
		$game = ORM::factory('game',$game_id);
		$game->loadState(0);

		// get opponent
		if($this->session->get('user') == $game->whiteplayer_id)
		{
			$opponent = $game->blackplayer_id;
		}
		elseif($this->session->get('user') == $game->blackplayer_id)
		{
			$opponent = $game->whiteplayer_id;
		}
		// if user does not belong to this game, json_error to that effect.
		else
		{
			$this->json_error('Draw Offer Failed','You don\'t appear to be associated with this game.',$game->id);
		}
		
		// actions for fiftyMoveDraw rule
		$additional_text = '';
		if($game->state->fiftyMoveDraw >= 100)
		{
			$additional_text .= 'Due to the Fifty Move Draw rule, this draw has been automatically accepted.';
			$message_text = 'A draw has been offered and, due to the Fifty Move Draw rule, automatically accepted.';
			// automatically accept the draw: mark game as finished, add 1/2-1/2 as lastMove
			$state = ORM::factory('state');
			$state->game_id = $game->id;
			$state->ply = $game->state->ply + 1;
			$state->positions = $game->state->positions;
			$state->turn = ($game->state->turn ? 0 : 1);
			$state->whiteInCheck = $game->state->whiteInCheck;
			$state->blackInCheck = $game->state->blackInCheck;
			$state->lastMove = '1/2-1/2';
			$state->save();

			$game->whiteplayer->draws+=1;
			$game->whiteplayer->save();
			$game->blackplayer->draws+=1;
			$game->blackplayer->save();

			$game->finished = true;
			$game->state_id = $state->id;
			$game->save();
		}
		else
		{
			// mark pending draw and save
			$game->state->pendingDraw = 1;
			$game->state->save();

			$message_text = 'A draw has been offered by your opponent. Please visit the game to accept or decline this draw';
		}

		// send a message to the opponent
		$message = ORM::factory('message');
		$message->to_user_id = $opponent;
		$message->from_user_id = $this->session->get('user');
		$message->subject = 'Draw Offer in Game "<a href="[domain]/4divisions/game/view/'.$game->id.'">'.$game->name.'</a>"';
		$message->text = $message_text;
		$message->sticky = false;
		$message->save();
		$message->push('game/view/'.$game->id);

		$this->json_success('Draw Offered','You\'ve offered a draw to your opponent on Game "'.$game->name.'".'.$additional_text,$game->id);
	}

	public function declineDraw($game_id)
	{
		$this->auto_render = false;
		$this->verify_logged_in();
		$game = ORM::factory('game',$game_id);
		$game->loadState(0);

		// get opponent
		if($this->session->get('user') == $game->whiteplayer_id)
		{
			$opponent = $game->blackplayer_id;
		}
		elseif($this->session->get('user') == $game->blackplayer_id)
		{
			$opponent = $game->whiteplayer_id;
		}
		// if user does not belong to this game, json_error to that effect.
		else
		{
			$this->json_error('Draw Offer Failed','You don\'t appear to be associated with this game.',$game->id);
		}

		if( ! $game->state->pendingDraw)
		{
			$this->json_error('Draw could not be declined','A draw has not been offered for this game!.',$game->id);
		}

		// unmark pending draw and save
		$game->state->pendingDraw = 0;
		$game->state->save();

		// send a message to the opponent
		$message = ORM::factory('message');
		$message->to_user_id = $opponent;
		$message->from_user_id = $this->session->get('user');
		$message->subject = 'Draw Declined in Game "<a href="[domain]/4divisions/game/view/'.$game->id.'">'.$game->name.'</a>"';
		$message->text = 'Your offer of a draw has been declined.';
		$message->sticky = false;
		$message->save();
		$message->push('game/view/'.$game->id);

		$this->json_success('Draw declined in Game "'.$game->name.'"','You\'ve declined the tendered draw on Game "'.$game->name.'"',$game->id);
	}

	public function acceptDraw($game_id)
	{
		$this->auto_render = false;
		$this->verify_logged_in();
		$game = ORM::factory('game',$game_id);
		$game->loadState(0);

		// get opponent
		if($this->session->get('user') == $game->whiteplayer_id)
		{
			$opponent = $game->blackplayer_id;
		}
		elseif($this->session->get('user') == $game->blackplayer_id)
		{
			$opponent = $game->whiteplayer_id;
		}
		// if user does not belong to this game, json_error to that effect.
		else
		{
			$this->json_error('Draw could not be accepted','You don\'t appear to be associated with this game.',$game->id);
		}

		if( ! $game->state->pendingDraw)
		{
			$this->json_error('Draw could not be accepted','A draw has not been offered for this game!.',$game->id);
		}

		// mark game as finished, add 1/2-1/2 as lastMove
		$state = ORM::factory('state');
		$state->game_id = $game->id;
		$state->ply = $game->state->ply + 1;
		$state->positions = $game->state->positions;
		$state->turn = ($game->state->turn ? 0 : 1);
		$state->whiteInCheck = $game->state->whiteInCheck;
		$state->blackInCheck = $game->state->blackInCheck;
		$state->lastMove = '1/2-1/2';
		$state->save();

		$game->whiteplayer->draws+=1;
		$game->whiteplayer->save();
		$game->blackplayer->draws+=1;
		$game->blackplayer->save();

		$game->finished = true;
		$game->state_id = $state->id;
		$game->save();

		// send a message to the opponent
		$message = ORM::factory('message');
		$message->to_user_id = $opponent;
		$message->from_user_id = $this->session->get('user');
		$message->subject = 'Draw Accepted in Game "<a href="[domain]/4divisions/game/view/'.$game->id.'">'.$game->name.'</a>"';
		$message->text = 'Congratulations, Your offer of a draw has been accepted.';
		$message->sticky = false;
		$message->save();
		$message->push('game/view/'.$game->id);

		$this->json_success('Draw accepted in Game "'.$game->name.'"','You\'ve accepted the tendered draw on Game "'.$game->name.'"',$game->id);
	}

	public function getGameSummary($gameId)
	{
		$this->auto_render = false;
		$this->verify_logged_in();
		echo json_encode($this->gameSummary($gameId));
	}

	public function getUpdatedGames($secondsSinceLastUpdate=0)
	{
		$this->auto_render = false;
		$this->verify_logged_in();
		$db = new Database();
		$states = $db->select('game_id')->from('states')->join('games','games.state_id','states.id')->where('(whiteplayer_id = '.$this->session->get('user').' or blackplayer_id = '.$this->session->get('user').') and time >= date_sub(now(),INTERVAL '.$secondsSinceLastUpdate.' SECOND)')->orderby('time','desc')->get();

		$game_array = array('collection'=>array());
		foreach($states as $state)
		{
			//$updatedGames['gameIds'][]=$state->game_id;
			$game_array['collection'][] = $this->gameSummary($state->game_id);
		}
		echo json_encode($game_array);
	}

	public function createGame($name,$opponent,$playas)
	{
		$this->auto_render = false;
		$this->verify_logged_in();
		$game = ORM::factory('game');

                if($opponent==0)
                {
                    $this->json_error('Gosh. I am SO sorry.','Open games are not displayed on the dashboard yet. Nobody will be able to join your open game... so I am not going to let you create one. Sorry!',9999);
                }

		if($playas==1)
		{
			$white_player = $this->session->get('user');
			$black_player =	($opponent==0 ? null : $opponent);
		}
		else
		{
			$white_player = ($opponent==0 ? null : $opponent);
			$black_player =	$this->session->get('user');
		}

		$game->name = $name;
		$game->whiteplayer_id = $white_player;
		$game->blackplayer_id = $black_player;
		$game->state_id = null;
		$game->save();

		$state = ORM::factory('state');
		$state->game_id = $game->id;
		$state->positions = State_Model::startingState();
		$state->save();

		$game->state_id = $state->id;
		$game->save();

		// send a message to the opponent
		if($opponent != 0)
		{
			$message = ORM::factory('message');
			$message->to_user_id = ($opponent);
			$message->from_user_id = $this->session->get('user');
			$message->subject = 'You\'ve been challenged! Game "<a href="[domain]/4divisions/game/view/'.$game->id.'">'.$game->name.'</a>" is waiting for you.';
			$message->text = 'A new Game ("'.$game->name.'") has been created. You are playing as '.($opponent==$white_player ? 'white' : 'black');
			$message->sticky = false;
			$message->save();
			$message->push('game/view/'.$game->id);
		}

		echo json_encode(array('message_type'=>'success','message_title'=>'Game ('.$name.') Created!','message_text'=>'Your game was created. It should be in your "My Current Games" list on your dashboard.','object_id'=>$game->id));
	}

	public function getGame($id=0,$state_index=0)
	{
		$this->auto_render = false;
		$this->verify_logged_in();

		// load game and game state
		$game = ORM::factory('game',$id);

		$game->loadState($state_index);

		$pieces = json_decode($game->state->positions);
		$board = $this->getBoard($game,$pieces);

		// construct game and send the output
		$game = array('board'=>$board,'pieces'=>$pieces);
		echo json_encode($game);
	}

	public function gameNames()
	{
		$this->auto_render = false;
		$this->verify_logged_in();
		$db = new Database();
		$result = $db->query('select name from games');
		foreach($result as $row)
		{
			$game_names['list'][] = $row->name;
		}
		echo json_encode($game_names);
	}

	//##########################################################################
	//PRIVATE METHODS

	private function getLocationOfSpecifiedPieces($pieceTypes,$pieces)
	{
		$this->auto_render = false;
		$locations = array();
		if(!is_array($pieceTypes)) $pieceTypes = array($pieceTypes);
		foreach($pieces as $piece)
		{
			if(in_array($piece->piece,$pieceTypes))
			{
				$locations[] = $piece->position;
			}
		}
		return $locations;
	}

	private function getLocationOfBlackKing($pieces)
	{
		$this->auto_render = false;
		foreach($pieces as $piece)
		{
			if($piece->piece == -3)
			{
				return $piece->position;
			}
		}
	}
	private function getLocationOfWhiteKing($pieces)
	{
		$this->auto_render = false;
		foreach($pieces as $piece)
		{
			if($piece->piece == 3)
			{
				return $piece->position;
			}
		}
	}
	private function getLocationOfWhiteNonKingPieces($pieces)
	{
		$this->auto_render = false;
		$arrWhiteNonKingPieceLocations = array();
		foreach($pieces as $piece)
		{
			if($piece->piece > 0 && $piece->piece != 3)
			{
				$arrWhiteNonKingPieceLocations[] = $piece->position;
			}
		}
		return $arrWhiteNonKingPieceLocations;
	}
	private function getLocationOfBlackNonKingPieces($pieces)
	{
		$this->auto_render = false;
		$arrBlackNonKingPieceLocations = array();
		foreach($pieces as $piece)
		{
			if($piece->piece < 0 && $piece->piece != -3)
			{
				$arrBlackNonKingPieceLocations[] = $piece->position;
			}
		}
		return $arrBlackNonKingPieceLocations;
	}
	private function getLocationOfWhitePieces($pieces)
	{
		$this->auto_render = false;
		$arrWhitePieceLocations = array();
		foreach($pieces as $piece)
		{
			if($piece->piece > 0)
			{
				$arrWhitePieceLocations[] = $piece->position;
			}
		}
		return $arrWhitePieceLocations;
	}
	private function getLocationOfBlackPieces($pieces)
	{
		$this->auto_render = false;
		$arrBlackPieceLocations = array();
		foreach($pieces as $piece)
		{
			if($piece->piece < 0)
			{
				$arrBlackPieceLocations[] = $piece->position;
			}
		}
		return $arrBlackPieceLocations;
	}
	private function getPieceName($piece)
	{
		switch($piece->piece)
		{
			case self::WHITE_PAWN:
				return 'WHITE_PAWN';
				break;
			case self::BLACK_PAWN:
				return 'BLACK_PAWN';
				break;
			case self::WHITE_ROOK:
				return 'WHITE_ROOK';
				break;
			case self::BLACK_ROOK:
				return 'BLACK_ROOK';
				break;
			case self::WHITE_KNIGHT:
				return 'WHITE_KNIGHT';
				break;
			case self::BLACK_KNIGHT:
				return 'BLACK_KNIGHT';
				break;
			case self::WHITE_BISHOP:
				return 'WHITE_BISHOP';
				break;
			case self::BLACK_BISHOP:
				return 'BLACK_BISHOP';
				break;
			case self::WHITE_KING:
				return 'WHITE_KING';
				break;
			case self::BLACK_KING:
				return 'BLACK_KING';
				break;
			case self::WHITE_QUEEN:
				return 'WHITE_QUEEN';
				break;
			case self::BLACK_QUEEN:
				return 'BLACK_QUEEN';
				break;
			default:
				return 'UNKNOWN';
				break;
		}
	}
	public function generateAllPieceMoves($game_id=0,$pieces=null)
	{
		$this->auto_render = false;
		if($game_id==0)
		{
			$game = ORM::factory('game');
			$game->state = ORM::factory('state');
			$game->state->positions = State_Model::startingState();
		}
		else
		{
			$game = ORM::factory('game',$game_id);
			$game->loadState(0);
		}
		if(!isset($pieces))
		{
			$pieces = $game->state->positions;
		}
		$pieces = json_decode($pieces);
		$board = $this->generateBoardPositions($pieces);
		$new_pieces = array();
		$this->auto_render = false;
		foreach($pieces as $piece)
		{
			$new_piece = $this->generatePieceMoves($game,$board,$pieces,$piece);
			array_push($new_pieces,$new_piece);
		}
		print('<pre>');print_r($new_pieces);print('</pre>');
	}
	private function generatePieceMoves($game,$board,$pieces,$piece)
	{		
		$validMoves = array('moves'=>array(),'captures'=>array(),'enpassant'=>array(),'castles'=>array());	// holds valid destinations for this piece
		$arrDelta = $this->getDeltas($piece); // get the deltas for this piece
		$pieceName = $this->getPieceName($piece);
		
		// this is a sliding piece (bishop(5),rook(6),queen(7)
		if((abs($piece->piece) & 4) != 0)
		{
			foreach($arrDelta as $delta)
			{
				for($i=(int)$piece->position;($i & 0x88)==0;$i+=$delta)
				{
					if(isset($pieces[$board[$i]]->piece) && $i!=$piece->position)
					{
						if(($piece->piece > 0 && $pieces[$board[$i]]->piece > 0) || ($piece->piece < 0 && $pieces[$board[$i]]->piece < 0))
						{
							//one of our pieces is here, stop calculating along this delta
							break;
						}
						if(($piece->piece > 0 && $pieces[$board[$i]]->piece < 0) || ($piece->piece < 0 && $pieces[$board[$i]]->piece > 0))
						{
							//one of our opponent's pieces is here, add this to validMOves as a capture, then stop calculating along this delta
							$validMoves['captures'][] = $i;
							break;
						}
					}
					if($i!=$piece->position) $validMoves['moves'][] = $i;
				}
			}
		}
		// this is a NON-sliding piece (knight(2),king(3),pawn(1))
		else
		{
			// only traverse one delta iteration (no inner loop)
			foreach($arrDelta as $delta)
			{
				$i = $piece->position+$delta;
				if(($i & 0x88)==0)
				{
					if(isset($pieces[$board[$i]]->piece) && $i!=$piece->position && (($piece->piece > 0 && $pieces[$board[$i]]->piece > 0) || ($piece->piece < 0 && $pieces[$board[$i]]->piece < 0)))
					{
						//one of our pieces is here, don't add this as a valid destination
					}
					else
					{
						if(abs($piece->piece) == self::PAWN && isset($pieces[$board[$i]]->piece) && $i!=$piece->position)
						{
							//if this piece is a pawn, it cannot move forward if there are ANY piece in the way
						}
						else
						{
							//if there is an opponent piece here, it is a capture
							if(isset($pieces[$board[$i]]->piece) && (($piece->piece > 0 && $pieces[$board[$i]]->piece < 0) || ($piece->piece < 0 && $pieces[$board[$i]]->piece > 0)))
							{
								$validMoves['captures'][] = $i;
							}
							//otherwise, valid move.
							else
							{
								$validMoves['moves'][] = $i;
							}
						}
					}
				}
			}
			// if a king, also check castles
			$arrCastleDelta = array();
			if( $piece->piece == self::WHITE_KING && ! $game->state->whiteInCheck)
			{
				if( $game->state->whiteCastleKing )
				{
					$arrCastleDelta[] = 1;
				}
				if( $game->state->whiteCastleQueen )
				{
					$arrCastleDelta[] = -1;
				}
			}
			if( $piece->piece == self::BLACK_KING && ! $game->state->blackInCheck)
			{
				if( $game->state->blackCastleKing )
				{
					$arrCastleDelta[] = 1;
				}
				if( $game->state->blackCastleQueen )
				{
					$arrCastleDelta[] = -1;
				}
			}
			if(count($arrCastleDelta) > 0)
			{
				// get opponent controlled squares...
				if($piece->piece < 0)
				{
					$opponentPieceLocations = $this->getLocationOfWhiteNonKingPieces($pieces);
					$opponentControlledSquares = $this->moveGeneration($game->id,$opponentPieceLocations,$pieces);
				}
				else
				{
					$opponentPieceLocations = $this->getLocationOfBlackNonKingPieces($pieces);
					$opponentControlledSquares = $this->moveGeneration($game->id,$opponentPieceLocations,$pieces);
				}

				// iterate twice over each $arrCastleDelta.
				foreach($arrCastleDelta as $delta)
				{
					$castle_iterator = 0;
					for($i=(int)$piece->position;($i & 0x88)==0;$i+=$delta)
					{
						//if $i would be a check or contains one of our pieces (exempting $src) then bail out.
						if(		in_array($i,$opponentControlledSquares['moves']) ||
								in_array($i,$opponentControlledSquares['captures']) ||
								(isset($pieces[$board[$i]]->piece) && $i!=$piece->position && (($piece->piece > 0 && $pieces[$board[$i]]->piece > 0) || ($piece->piece < 0 && $pieces[$board[$i]]->piece < 0)))
						)
						{
							break;
						}
						else
						{
							if($castle_iterator==2)
							{
								$validMoves['castles'][]=$i;
							}
							elseif($castle_iterator>2)
							{
								break;
							}
						}
						$castle_iterator++;
					}
				}
			}

			// if a pawn, also check a couple special deltas used to figure captures and en passants and initial 2-square moves
			if($piece->piece == self::WHITE_PAWN)
			{
				$arrCapturingDelta = array( 15, 17 );
				$arrFirstMoveDelta = array( 32 );
			}
			if($piece->piece == self::BLACK_PAWN)
			{
				$arrCapturingDelta = array( -15, -17 );
				$arrFirstMoveDelta = array( -32 );
			}
			if(isset($arrCapturingDelta))
			{
				foreach($arrCapturingDelta as $delta)
				{
					// if calculated index is on the board, and either contains a piece we can capture or is a valid en passant
					$i = $piece->position+$delta;
					if(($i & 0x88)==0)
					{
						if(isset($pieces[$board[$i]]) && (($pieces[$board[$i]]->piece > 0 && $piece->piece < 0) || ($pieces[$board[$i]]->piece < 0 && $piece->piece > 0)))
						{
							$validMoves['captures'][] = $i;
						}
						elseif(
							($delta < 0 && isset($pieces[$board[$i+16]]) && $board[Game_Model::B_EN_PASSANT]==$i+16) ||
							($delta > 0 && isset($pieces[$board[$i-16]]) && $board[Game_Model::W_EN_PASSANT]==$i-16)
							)
						{
							$validMoves['enpassant'][] = $i;
						}
					}
				}
				foreach($arrFirstMoveDelta as $delta)
				{
					// if pawn is still on its home rank (($src & 0x60)==96 [white] or ($src & 0x60)==0 [black]), it hasn't moved and may move forward TWO squares
					if(($delta < 0 && ($piece->position & 0x60)==96) || ($delta > 0 && ($piece->position & 0x60)==0))
					{
						if(isset($pieces[$board[$piece->position+$delta]]->piece) || isset($pieces[$board[$piece->position+($delta/2)]]))
						{
							//piece cannot move forward if there are ANY pieces in the way
						}
						else
						{
							$validMoves['moves'][] = $piece->position+$delta;
						}
					}
				}
			}
		}
		return array('name'=>$pieceName,'piece'=>$piece->piece,'position'=>$piece->position,'deltas'=>$arrDelta,'validMoves'=>$validMoves);
	}
	
	private function moveGeneration($game_id,$src=0,$pieces=null)
	{

		$this->auto_render = false;

		// load game and game state
		$game = ORM::factory('game',$game_id);
		$game->loadState(0);
		if(!isset($pieces))
		{
			$pieces = json_decode($game->state->positions);
		}
		$board = $this->getBoard($game,$pieces);

		$validMoves = array('moves'=>array(),'captures'=>array(),'enpassant'=>array(),'castles'=>array());	// holds valid destinations for this piece

		if($src===0)
		{
			$srcs_to_check = array_keys($pieces);
		}
		else
		{
			if(!is_array($src))
			{
				$srcs_to_check = array($src);
			}
			else
			{
				$srcs_to_check = $src;
			}
		}

		foreach($srcs_to_check as $src)
		{
			$arrCapturingDelta = array();
			$arrFirstMoveDelta = array();
		
			if(!isset($src)) continue;
			// remember some stuff
			$piece = (isset($pieces[$board[$src]]) ? $pieces[$board[$src]] : null);		// the piece object at the src square
			if($piece===null) continue;

			// get the deltas for this piece
			$arrDelta = $this->getDeltas($piece);

			// this is a sliding piece (bishop(5),rook(6),queen(7)
			if((abs($piece->piece) & 4) != 0)
			{
				foreach($arrDelta as $delta)
				{
					for($i=(int)$src;($i & 0x88)==0;$i+=$delta)
					{
						if(isset($pieces[$board[$i]]->piece) && $i!=$src)
						{
							if(($piece->piece > 0 && $pieces[$board[$i]]->piece > 0) || ($piece->piece < 0 && $pieces[$board[$i]]->piece < 0))
							{
								//one of our pieces is here, stop calculating along this delta
								break;
							}
							if(($piece->piece > 0 && $pieces[$board[$i]]->piece < 0) || ($piece->piece < 0 && $pieces[$board[$i]]->piece > 0))
							{
								//one of our opponent's pieces is here, add this to validMOves as a capture, then stop calculating along this delta
								$validMoves['captures'][] = $i;
								break;
							}
						}
						if($i!=$src) $validMoves['moves'][] = $i;
					}
				}
			}
			// this is a NON-sliding piece (knight(2),king(3),pawn(1))
			else
			{
				// only traverse one delta iteration (no inner loop)
				foreach($arrDelta as $delta)
				{
					$i = $src+$delta;
					if(($i & 0x88)==0)
					{
						if(isset($pieces[$board[$i]]->piece) && $i!=$src && (($piece->piece > 0 && $pieces[$board[$i]]->piece > 0) || ($piece->piece < 0 && $pieces[$board[$i]]->piece < 0)))
						{
							//one of our pieces is here, don't add this as a valid destination
						}
						else
						{
							if(abs($piece->piece) == self::PAWN && isset($pieces[$board[$i]]->piece) && $i!=$src)
							{
								//if this piece is a pawn, it cannot move forward if there are ANY piece in the way
							}
							else
							{
								//if there is an opponent piece here, it is a capture
								if(isset($pieces[$board[$i]]->piece) && (($piece->piece > 0 && $pieces[$board[$i]]->piece < 0) || ($piece->piece < 0 && $pieces[$board[$i]]->piece > 0)))
								{
									$validMoves['captures'][] = $i;
								}
								//otherwise, valid move.
								else
								{
									$validMoves['moves'][] = $i;
								}
							}
						}
					}
				}
				// if a king, also check castles
				$arrCastleDelta = array();
				if( $piece->piece == self::WHITE_KING && ! $game->state->whiteInCheck)
				{
					if( $game->state->whiteCastleKing )
					{
						$arrCastleDelta[] = 1;
					}
					if( $game->state->whiteCastleQueen )
					{
						$arrCastleDelta[] = -1;
					}
				}
				if( $piece->piece == self::BLACK_KING && ! $game->state->blackInCheck)
				{
					if( $game->state->blackCastleKing )
					{
						$arrCastleDelta[] = 1;
					}
					if( $game->state->blackCastleQueen )
					{
						$arrCastleDelta[] = -1;
					}
				}
				if(count($arrCastleDelta) > 0)
				{
					// get opponent controlled squares...
					if($piece->piece < 0)
					{
						$opponentPieceLocations = $this->getLocationOfWhiteNonKingPieces($pieces);
						$opponentControlledSquares = $this->moveGeneration($game_id,$opponentPieceLocations,$pieces);
					}
					else
					{
						$opponentPieceLocations = $this->getLocationOfBlackNonKingPieces($pieces);
						$opponentControlledSquares = $this->moveGeneration($game_id,$opponentPieceLocations,$pieces);
					}

					// iterate twice over each $arrCastleDelta.
					foreach($arrCastleDelta as $delta)
					{
						$castle_iterator = 0;
						for($i=(int)$src;($i & 0x88)==0;$i+=$delta)
						{
							//if $i would be a check or contains one of our pieces (exempting $src) then bail out.
							if(		in_array($i,$opponentControlledSquares['moves']) ||
									in_array($i,$opponentControlledSquares['captures']) ||
									(isset($pieces[$board[$i]]->piece) && $i!=$src && (($piece->piece > 0 && $pieces[$board[$i]]->piece > 0) || ($piece->piece < 0 && $pieces[$board[$i]]->piece < 0)))
							)
							{
								break;
							}
							else
							{
								if($castle_iterator==2)
								{
									$validMoves['castles'][]=$i;
								}
								elseif($castle_iterator>2)
								{
									break;
								}
							}
							$castle_iterator++;
						}
					}
				}

				// if a pawn, also check a couple special deltas used to figure captures and en passants and initial 2-square moves
				if($piece->piece == self::WHITE_PAWN)
				{
					$arrCapturingDelta = array( 15, 17 );
					$arrFirstMoveDelta = array( 32 );
				}
				if($piece->piece == self::BLACK_PAWN)
				{
					$arrCapturingDelta = array( -15, -17 );
					$arrFirstMoveDelta = array( -32 );
				}
				if(isset($arrCapturingDelta))
				{
					foreach($arrCapturingDelta as $delta)
					{
						// if calculated index is on the board, and either contains a piece we can capture or is a valid en passant
						$i = $src+$delta;
						if(($i & 0x88)==0)
						{
							if(isset($pieces[$board[$i]]) && (($pieces[$board[$i]]->piece > 0 && $piece->piece < 0) || ($pieces[$board[$i]]->piece < 0 && $piece->piece > 0)))
							{
								$validMoves['captures'][] = $i;
							}
							elseif(
								($delta < 0 && isset($pieces[$board[$i+16]]) && $board[Game_Model::B_EN_PASSANT]==$i+16) ||
								($delta > 0 && isset($pieces[$board[$i-16]]) && $board[Game_Model::W_EN_PASSANT]==$i-16)
								)
							{
								$validMoves['enpassant'][] = $i;
							}
						}
					}
					foreach($arrFirstMoveDelta as $delta)
					{
						// if pawn is still on its home rank (($src & 0x60)==96 [white] or ($src & 0x60)==0 [black]), it hasn't moved and may move forward TWO squares
						if(($delta < 0 && ($src & 0x60)==96) || ($delta > 0 && ($src & 0x60)==0))
						{
							if(isset($pieces[$board[$src+$delta]]->piece) || isset($pieces[$board[$src+($delta/2)]]))
							{
								//piece cannot move forward if there are ANY pieces in the way
							}
							else
							{
								$validMoves['moves'][] = $src+$delta;
							}
						}
					}
				}
			}
		}
		return $validMoves;
	}

	private function getDeltas($piece)
	{
		$arrDelta = array(  );

		// this is a sliding piece (bishop(5),rook(6),queen(7)
		if((abs($piece->piece) & 4) != 0)
		{
			if((abs($piece->piece) & 1) != 0)
			{
				// this piece moves diagonally
				$arrDelta = array_merge($arrDelta,array( 17, 15, -17, -15 ));
			}
			if((abs($piece->piece) & 2) != 0)
			{
				// this piece moves vertically/horizontally
				$arrDelta = array_merge($arrDelta,array( 1, 16, -1, -16 ));
			}
		}
		// this is a NON-sliding piece (knight(2),king(3),pawn(1))
		else
		{
			// special rules apply for these pieces, delta's are set but these pieces may only traverse one delta iteration.
			if(isset($piece->piece))
			{
				switch ($piece->piece)
				{
					case self::WHITE_PAWN :
						$arrDelta = array_merge($arrDelta,array( 16 ));
						break;
					case self::BLACK_PAWN :
						$arrDelta = array_merge($arrDelta,array( -16 ));
						break;
					case self::WHITE_KING :
					case self::BLACK_KING :
						$arrDelta = array_merge($arrDelta,array( 1, 16, -1, -16, 17, 15, -17, -15 ));
						break;
					case self::WHITE_KNIGHT :
					case self::BLACK_KNIGHT :
						$arrDelta = array_merge($arrDelta,array( 31, 33, 18, 14, -31, -33, -18, -14 ));
						break;
				}
			}
		}
		return $arrDelta;
	}

	private function gameSummary($gameId)
	{
		$this->auto_render = false;

		// load game and game state
		$game = ORM::factory('game',$gameId);
		$game->loadState(0);
		$board = $this->getBoard($game);

		// construct game and send the output
		$game_array = array('board'=>$board);
		return $game_array;
	}

	private function getBoard($game,$pieces=null)
	{
		if(isset($pieces))
		{
			$board = $this->generateBoardPositions($pieces);
		}
		else
		{
			$board = array();
		}

		// game data
		$board[Game_Model::LAST_MOVE] = $game->state->lastMove;			// last move (str)
		$board[Game_Model::TURN] =		$game->state->turn;				// turn (1==white,0==black)
		$board[Game_Model::WHITE_PLAYER_ID] =	($game->whiteplayer->id == '' ? 0 : $game->whiteplayer->id);		// id of white player
		$board[Game_Model::WHITE_PLAYER_NAME] = ($game->whiteplayer->username == '' ? '[open]' : $game->whiteplayer->username);	// name of white player
		$board[Game_Model::BLACK_PLAYER_ID] =	($game->blackplayer->id == '' ? 0 : $game->blackplayer->id);		// id of black player
		$board[Game_Model::BLACK_PLAYER_NAME] = ($game->blackplayer->username == '' ? '[open]' : $game->blackplayer->username);	// name of black player

		$board[Game_Model::W_CASTLE_K] = $game->state->whiteCastleKing;		// can white castle king-side?
		$board[Game_Model::W_CASTLE_Q] = $game->state->whiteCastleQueen;			// can white castle queen-side?
		$board[Game_Model::B_CASTLE_K] = $game->state->blackCastleKing;			// can black castle king-side?
		$board[Game_Model::B_CASTLE_Q] = $game->state->blackCastleQueen;			// can black castle queen-side
		$board[Game_Model::FIFTY_MOVE_DRAW] = $game->state->fiftyMoveDraw;		// where do we stand on the fifty move draw?
		$board[Game_Model::TIME_OF_LAST_MOVE] = $game->state->time;		// time since last move

		$board[Game_Model::W_EN_PASSANT] = $game->state->whiteEnPassant;	// what piece of white's (if any) is eligible to be captured en passant?
		$board[Game_Model::B_EN_PASSANT] = $game->state->blackEnPassant;	// what piece of black's (if any) is eligible to be captured en passant?
		$board[Game_Model::GAME_NAME] = $game->name;					// name of this game
		$board[Game_Model::GAME_ID] = $game->id;						// id of this game
		$board[Game_Model::GAME_FINISHED] = $game->finished;	// is the game over?
		$board[Game_Model::PLY] = $game->state->ply;			// what ply is it?

		$board[Game_Model::STATE_INDEX] = $game->stateIndex;
		$board[Game_Model::NUM_STATES] = $game->numStates;
		$board[Game_Model::LAST_MOVE_SRC] = $game->state->lastMoveSrc;
		$board[Game_Model::LAST_MOVE_DST] = $game->state->lastMoveDst;
		$board[Game_Model::WHITE_IN_CHECK] = $game->state->whiteInCheck;
		$board[Game_Model::BLACK_IN_CHECK] = $game->state->blackInCheck;

		$board[Game_Model::PENDING_DRAW] = $game->state->pendingDraw;

		return $board;
	}

	private function generateBoardPositions($pieces)
	{
		// initialize board
		$board = array();
		for($i=0;$i<128;$i++)
		{
			$board[$i] = null;
		}
		// place pieces
		foreach($pieces as $key=>$piece)
		{
			$board[$piece->position] = $key;
		}
		//return result
		return $board;
	}

	public function __call($method, $arguments)
	{
		// Disable auto-rendering
		$this->auto_render = FALSE;

		// By defining a __call method, all pages routed to this controller
		// that result in 404 errors will be handled by this method, instead of
		// being displayed as "Page Not Found" errors.
		echo 'This text is generated by __call. If you expected the index page, you need to use: welcome/index/'.substr(Router::$current_uri, 8);
	}

}

?>
