//TODO: all calls to gritter should run loop on data.messages to support multiple messages

var messenger =
{

	init : function()
	{
		$.extend($.gritter.options, { fade_out_speed: 100 });
		// look in queue for existing messages,
		// add each to message system
		$('#message_queue div.message').each(function(){
			class_name=null;
			if($(this).hasClass('error')) class_name = 'error';
			if($(this).hasClass('notify')) class_name = 'notify';
			if($(this).hasClass('success')) class_name = 'success';
			$.gritter.add({
				title: $(this).attr('title') != '' ? $(this).attr('title') : ' ',
				text:  $(this).html(),
				class_name: class_name
			});
		});
	}
}

var uidebug =
{
	string : '',
	capturing : false,

	init : function()
	{
		// bind show/hide debug key
		$(document).keyup(
		function(event){
			if (event.keyCode == 192) {
				if(uidebug.capturing)
				{
					//disable debug data and stop capturing
					$('div#debug_info').hide();
					uidebug.capturing = false;
					uidebug.string = '';
				}
				else
				{
					//start capturing
					uidebug.capturing = true;
					uidebug.string = '';
				}
			}
			if(uidebug.capturing)
			{
				uidebug.string+=event.keyCode;
				if(uidebug.string == '192161718')
				{
					$('div#debug_info').show();
					uidebug.string = '';
				}
				if(uidebug.string.length > 9)
				{
					uidebug.capturing = false;
					uidebug.string = '';
				}
				return false;
			}
		}
		);
	}
}

var logger = function(data)
{
	if(typeof(console)!='undefined' && console.log)
	{
		console.log(data);
	}
}

var format =
{
	file : function(i)
	{
		return "ABCDEFGH".charAt(i)
	},
	rank : function(i)
	{
		return Math.abs(i-8);
	},
	fuzzytime : function(time)
	{
		var date = new Date((time || "").replace(/-/g,"/").replace(/[TZ]/g," "));
		var diff = (((new Date()).getTime() - date.getTime()) / 1000);
		var day_diff = Math.floor(diff / 86400);

		if ( isNaN(day_diff) || day_diff < 0 ) 
		{
			return time;
		}

		return day_diff == 0 && (
				diff < 60 && "just now" ||
				diff < 120 && "about 1 minute ago" ||
				diff < 3600 && "about " + Math.floor( diff / 60 ) + " minutes ago" ||
				diff < 7200 && "about 1 hour ago" ||
				diff < 86400 && "about " + Math.floor( diff / 3600 ) + " hours ago") ||
			day_diff == 1 && "Yesterday" ||
			day_diff < 7 && day_diff + " days ago" ||
			day_diff >= 7 && day_diff <=10 && " last week" ||
			day_diff >=11 && day_diff <=13 && " nearly 2 weeks ago" ||
			day_diff < 31 && Math.ceil( day_diff / 7 ) + " weeks ago" || 
			day_diff < 40 && "about 1 month ago" || 
			day_diff >= 40 && Math.floor( day_diff / 30 ) + " months ago" ;
	},
	localtime : function(time)
	{
		//TODO: this should convert gmt to local
		return time;
	}
}

// ### chess specific ##########################################################

var move =
{

	selectedPromotionPiece : null,
	promotionSelection : null,

	piece : null,
	src : null,
	dst : null,
	confirmation : null,
	tmp_game : null,
	move_count : 0,
	
	free_move_mode : false,

	//click_moving : 0,

	confirmationAdditionalText : '',

	selectPromotionPiece : function()
	{
		move.promotionSelection = $.gritter.add({
			sticky : true,
			title : 'Promotion Selection for '+$(move.piece).attr('title') + ' at ' + $(move.dst).attr('title'),
			text : 'Select a promotion piece:<br /><div class="modal-input"><input type="button" id="confirm_Q" value="Queen me, please!" /> <input type="button" id="confirm_N" value="Let me get a Knight!" /></div>',
			class_name : 'notify'
		});
		$('input#confirm_Q').click(function(){
			move.selectedPromotionPiece = '7';
			move.confirmationAdditionalText += '<br />That piece is eligible for promotion. You\'ve chosen to promote to a Queen.';
			move.removePromotionSelection();
			move.confirmMove();
		});
		$('input#confirm_N').click(function(){
			move.selectedPromotionPiece = '2';
			move.confirmationAdditionalText += '<br />That piece is eligible for promotion. You\'ve chosen to promote to a Knight.';
			move.removePromotionSelection();
			move.confirmMove();
		})
	},
	removePromotionSelection : function()
	{
		if(move.promotionSelection != null)
		{
			$.gritter.remove(move.promotionSelection);
		}
	},
	confirmMove : function()
	{
		src = $(move.src).attr('id').replace('square_','');
		dst = $(move.dst).attr('id').replace('square_','');

		move.removeConfirmation();
		move.confirmation = $.gritter.add({
			sticky : true,
			title : 'Confirm Move: '+$(move.piece).attr('title') + ' from ' + $(move.src).attr('title') + ' to ' + $(move.dst).attr('title'),
			text : 'Would you like to move your '+$(move.piece).attr('title') + ' from ' + $(move.src).attr('title') + ' to ' + $(move.dst).attr('title')+'? '+move.confirmationAdditionalText+'<br />You may continue moving pieces and accept or decline this whenever you are ready.<div class="modal-input"><input type="button" id="confirm_y" value="Yes, Move." /> <input type="button" id="confirm_n" value="No, I don\'t like this move" /></div>',
			class_name : 'notify'
		});
		$('input#confirm_y').click(function(){
			move.makeMove(src,dst,move.selectedPromotionPiece);
			move.removeConfirmation();
		});
		$('input#confirm_n').click(function(){
			move.clear();
			game.resetPositions();
			move.removeConfirmation();
		})
	},
	freeMoveResetDialog : function()
	{
		move.confirmation = $.gritter.add({
			sticky : true,
			title : 'FREE MOVE MODE',
			text : 'When finished, hit "RESET" to reset the board.<div class="modal-input"><input type="button" id="reset" value="Reset Board." /></div>',
			class_name : 'notify'
		});
		$('input#reset').click(function(){
			move.disableFreeMove();
			move.removeConfirmation();
		})
	},
	removeConfirmation : function()
	{
		if(move.confirmation != null)
		{
			$.gritter.remove(move.confirmation);
		}
	},
	clear : function()
	{
		move.src = null;
		move.dst = null;
		move.piece = null;
		move.move_count = 0;
		move.tmp_game = null;
	},
	enableFreeMove : function()
	{
		move.tmp_game = jQuery.extend(true,{},game);
		game.setAllPiecesDraggable();
		move.free_move_mode = true;
		$('a#free_move').text('Disable Free Move');
		if(window.console && console.log) console.log('Free Move Enabled');
	},
	disableFreeMove : function()
	{
		move.clear();
		game.resetPositions();
		move.free_move_mode = false;
		$('a#free_move').text('Enable Free Move');
		if(window.console && console.log) console.log('Free Move Disabled');
	},
	tryMove : function()
	{
		move.selectedPromotionPiece = null;
		move.confirmationAdditionalText = '';

		src = $(move.src).attr('id').replace('square_','');
		dst = $(move.dst).attr('id').replace('square_','');

		if(src==dst) return;	// dropped piece on the square they picked it up from

		if($(move.dst).children('div.piece').length > 0)
		{
			if($(move.src).children('div.piece').hasClass('color_1') && $(move.dst).children('div.piece').hasClass('color_1'))
			{
				//can't attack own piece!
				$.gritter.add({ title:'Invalid Move',class_name:'error',text:'You can\'t attack your own piece!' });
				game.resetPositions();
				return;
			}
			else if($(move.src).children('div.piece').hasClass('color_0') && $(move.dst).children('div.piece').hasClass('color_0'))
			{
				//can't attack own piece!
				$.gritter.add({ title:'Invalid Move',class_name:'error',text:'You can\'t attack your own piece!' });
				game.resetPositions();
				return;
			}
		}

		promotion = false;
		// promotion: if piece being moved is a WHITE PAWN and dst >= 112...
		if($(move.src).children('div.piece').hasClass('piece_type_1') && dst >= 112)
		{
			promotion = true;
			move.selectPromotionPiece();
		}
		// promotion: if piece being moved is a BLACK PAWN and dst <= 7...
		if($(move.src).children('div.piece').hasClass('piece_type_-1') && dst <= 7)
		{
			promotion = true;
			move.selectPromotionPiece();
		}

		// confirmMove will always be shown if a promotion. It is called elsewhere, so skip this if promoting...
		if(!promotion)
		{
			if(move.move_count == 0)
			{
				if(move.free_move_mode)
				{
					move.freeMoveResetDialog();
				}
				else
				{
					if(user.confirmBeforeMove)
					{
						move.confirmMove();
					}
					else
					{
						move.makeMove(src,dst,move.selectedPromotionPiece);
						return;
					}
				}
			}
		}

		if(move.tmp_game==null)
		{
			move.tmp_game = jQuery.extend(true,{},game);
		}

		// move old piece off the board
		if(typeof(move.tmp_game.pieces[move.tmp_game.board[dst]])!='undefined')
		{
			move.tmp_game.pieces[move.tmp_game.board[dst]].position = game.TRASH;
		}
		// change board[dst] to value of board[src] (piece key)
		move.tmp_game.board[dst] = move.tmp_game.board[src];
		// change position of moved piece to dst
		move.tmp_game.pieces[move.tmp_game.board[src]].position = dst;

		move.move_count++;

		game.placePieces(move.tmp_game.pieces);
		$('div.piece').unbind('click');
	},
	makeMove : function(src,dst,extra)
	{
		jQuery.getJSON('/game/makeMove/'+game.board[game.GAME_ID]+'/'+src+'/'+dst+(extra?'/'+extra:''),function(data){
			if(data.message_type == 'error')
			{
				game.resetPositions();
			}
			$.gritter.add({ title:data.message_title,class_name:data.message_type,text:data.message_text });
			move.clear();
			getObject.game(game.board[game.GAME_ID]);
			updatedGames.restartTimer();
		});
	}
}

var square =
{

	size : 50,
	id : 0,
	cssclass : 'square',
	bordersize : 1,
	coordinates : Array(0,0),
	$container : null,
	color : 0,
	title : '',
	contents : '',

	markAsSource : function(e,ui)
	{
		$('div.selected_src').removeClass('selected_src');
		$(ui.helper).parent().addClass('selected_src');

		move.src = $(ui.helper).parent();
		move.piece = $(ui.helper);
	},

	markAsDestination : function(e,ui)
	{
		if($(move.src).attr('id') == $(this).attr('id'))
		{
			$(this).removeClass('selected_src');
			move.src = null;
			move.piece = null;
			return;
		}

		$('div.selected_dst').removeClass('selected_dst');
		$(this).addClass('selected_dst');

		$(this).children('div.piece').not(move.piece).hide();

		move.dst = $(this);
		move.tryMove()
		game.renderInfo();
		$('div.square').removeClass('validDestination');
	},

	getSquareSizeArray : function()
	{
		return Array((square.size+square.bordersize),(square.size+square.bordersize));
	},

	renderSquare : function()
	{
		//reset defaults
//		square.cssclass = 'square';
//		square.color = 1^((square.coordinates[0].toString(2) ^ square.coordinates[1].toString(2)) % 2);
//		if(game.flip)
//		{
//			top = (((game.square_count-1)-square.coordinates[0])*(square.size+square.bordersize));
//			left = (((game.square_count-1)-square.coordinates[1])*(square.size+square.bordersize));
//		}
//		else
//		{
//			top = (square.coordinates[0]*(square.size+square.bordersize));
//			left = (square.coordinates[1]*(square.size+square.bordersize));
//		}
//		if(square.id==game.board[game.LAST_MOVE_SRC]) square.cssclass+= ' lastmove_src';
//		if(square.id==game.board[game.LAST_MOVE_DST]) square.cssclass+= ' lastmove_dst';
//		square.$container = jQuery('<div id="square_'+square.id+'" class="'+square.cssclass+' color_'+square.color+'" title="'+square.title+'">'+square.contents+'</div>').css({ 'position':'absolute' , 'top':(top)+'px' , 'left':(left)+'px' , 'width':square.size+'px' , 'line-height':square.size+'px' , 'height':square.size+'px' });
//		square.$container.droppable({
//			drop : square.markAsDestination
//		});
//		return square.$container;
	}

}

var user =
{

	renderUserInfo : function()
	{
		if(!user.id) return;

		$('#userinfo #username').text(user.username);
		$('#userinfo #email').text(user.email);
		$('#userinfo #notifications').text((user.notifications ? 'ON' : 'OFF'));
		$('#userinfo #wins').text(user.wins);
		$('#userinfo #losses').text(user.losses);
		$('#userinfo #draws').text(user.draws);
	}

}

var piece =
{

	// "CONSTANTS"
	PAWN : 1,
	KNIGHT : 2,
	KING : 3,
	BISHOP : 5,
	ROOK : 6,
	QUEEN : 7,

	WHITE_PAWN : 1,
	BLACK_PAWN : -1,
	WHITE_KNIGHT : 2,
	BLACK_KNIGHT : -2,
	WHITE_KING : 3,
	BLACK_KING : -3,
	WHITE_BISHOP : 5,
	BLACK_BISHOP : -5,
	WHITE_ROOK : 6,
	BLACK_ROOK : -6,
	WHITE_QUEEN : 7,
	BLACK_QUEEN : -7,

	piece : 0,
	position : 0,
	color : 0,
	cssclass : 'piece',
	$container : null,

	init : function()
	{
		piece = 0;
		position = 0;
		color = 0;
		cssclass = 'piece';
		$container = null;
	},
	getScore : function(piece_type)
	{
		if(!piece_type) return 0;
		var p=Math.abs(piece_type);
		if		(p==piece.PAWN) return 1;
		else if	(p==piece.KNIGHT) return 3;
		else if	(p==piece.BISHOP) return 3.1;
		else if	(p==piece.ROOK) return 5;
		else if	(p==piece.QUEEN) return 9;
		else return 0;
	},
	renderPiece : function()
	{
		piece.cssclass = 'piece';
		piece.color = 0;
		if(piece.piece > 0) piece.color = 1;
		if((piece.piece == -3 && game.board[game.BLACK_IN_CHECK]) || (piece.piece == 3 && game.board[game.WHITE_IN_CHECK]))
		{
			piece.cssclass += ' check';
		}
		piece.$container = jQuery('<div id="piece_'+piece.position+'" class="draggable '+piece.cssclass+' piece_type_'+piece.piece+' color_'+piece.color+'" title="'+piece.pieceName()+'"><span class="piece_type" title="'+piece.piece+'">'+piece.pieceUnicode()+'</span></div>');
		piece.$container.css({ height : '100%' , width : '100%' });
		if(game.board[game.STATE_INDEX]==0)
		{
			piece.$container.click(function(){	//TODO: test this on mouseover when using a remote client
				$('div.square').removeClass('validDestination').removeClass('validCapture').removeClass('validEnPassant').removeClass('validCastle');
				validMoves.squares = {};
				getObject.validMoves(game.board[game.GAME_ID],$(this).attr('id').replace('piece_',''));
			});
		}
		piece.$container.mouseout(function(){
			$('div.square').removeClass('validDestination').removeClass('validCapture').removeClass('validEnPassant').removeClass('validCastle');
		})
		gridArray = square.getSquareSizeArray();
		return piece.$container;
	},
	pieceUnicode : function()
	{
		if(!piece.piece) return;
		var p=piece.piece;
		if		(p==piece.WHITE_PAWN) return '&#9817;';
		else if	(p==piece.BLACK_PAWN) return '&#9823;';
		else if	(p==piece.WHITE_KNIGHT) return '&#9816;';
		else if	(p==piece.BLACK_KNIGHT) return '&#9822;';
		else if	(p==piece.WHITE_KING) return '&#9812;';
		else if	(p==piece.BLACK_KING) return '&#9818;';
		else if	(p==piece.WHITE_BISHOP) return '&#9815;';
		else if	(p==piece.BLACK_BISHOP) return '&#9821;';
		else if	(p==piece.WHITE_ROOK) return '&#9814;';
		else if	(p==piece.BLACK_ROOK) return '&#9820;';
		else if	(p==piece.WHITE_QUEEN) return '&#9813;';
		else if	(p==piece.BLACK_QUEEN) return '&#9819;';
		else return piece.piece;
	},
	pieceName : function()
	{
		if(!piece.piece) return;
		var p=Math.abs(piece.piece);
		if		(p==piece.PAWN) return 'Pawn';
		else if	(p==piece.KNIGHT) return 'Knight';
		else if	(p==piece.KING) return 'King';
		else if	(p==piece.BISHOP) return 'Bishop';
		else if	(p==piece.ROOK) return 'Rook';
		else if	(p==piece.QUEEN) return 'Queen';
		else return piece.piece;
	}
}

var validMoves =
{
	squares : {
		moves : {},
		captures : {},
		enpassant : {},
		castles : {}
	},
	init : function()
	{
		squares = { moves : {}, captures : {}, enpassant : {}, castles : {} }
	},
	mark : function()
	{
		for(i=0;typeof(validMoves.squares.moves[i])!='undefined';i++)
		{
			$('#square_'+validMoves.squares.moves[i]).addClass('validDestination');
		}
		for(i=0;typeof(validMoves.squares.captures[i])!='undefined';i++)
		{
			$('#square_'+validMoves.squares.captures[i]).addClass('validCapture');
		}
		for(i=0;typeof(validMoves.squares.enpassant[i])!='undefined';i++)
		{
			$('#square_'+validMoves.squares.enpassant[i]).addClass('validEnPassant');
		}
		for(i=0;typeof(validMoves.squares.castles[i])!='undefined';i++)
		{
			$('#square_'+validMoves.squares.castles[i]).addClass('validCastle');
		}
	}
}

var game =
{

	// "CONSTANTS"
	LAST_MOVE : 110,
	TURN : 94,
	WHITE_PLAYER_ID : 78,
	BLACK_PLAYER_ID : 62,
	WHITE_PLAYER_NAME : 46,
	BLACK_PLAYER_NAME : 30,

	W_CASTLE_K : 109,
	W_CASTLE_Q : 93,
	B_CASTLE_K : 77,
	B_CASTLE_Q : 61,
	FIFTY_MOVE_DRAW : 45,
	TIME_OF_LAST_MOVE : 29,

	W_EN_PASSANT : 108,
	B_EN_PASSANT : 92,
	GAME_NAME : 76,
	GAME_ID : 60,
	GAME_FINISHED : 44,
	PLY : 28,

	STATE_INDEX : 107,
	NUM_STATES : 91,
	LAST_MOVE_SRC : 75,
	LAST_MOVE_DST : 59,
	WHITE_IN_CHECK : 43,
	BLACK_IN_CHECK : 27,

	PENDING_DRAW : 106,

	TRASH : 105,

	boardsize : 0,
	square_count : 8,
	bordersize : 1,
	$boardcontainer : null,
	move_src : null,
	move_dst : null,
	move_piece : null,

	include_coordinates : true,

	whitePieces : Array(),
	blackPieces : Array(),

	flip : false,

	init : function()
	{
		game.$boardcontainer = null;
		game.flip =false;
		game.whitePieces = Array();
		game.blackPieces = Array();
		game.pieces = null;
		game.move_src = null;
		game.move_dst = null;
		game.move_piece = null;
		game.whiteScore = 0;
		game.blackScore = 0;
	},

	renderBoard : function()
	{
		$('div#game_board').attr('id','game_board_'+game.board[game.GAME_ID]);
		$('div.square.lastmove_src').removeClass('lastmove_src');
                $('div.square.lastmove_dst').removeClass('lastmove_dst');
                $('div.game_board div.board').removeClass('flip');

		//flip the board if the black player is logged in AND (the black player is not the white player OR it is black's turn)
		if(view.user==game.board[game.BLACK_PLAYER_ID] && (game.board[game.BLACK_PLAYER_ID] != game.board[game.WHITE_PLAYER_ID] || game.board[game.TURN]==0)) game.flip = true;

		if(game.flip)
		{
			$('div.game_board div.board').removeClass('regular').addClass('flipped');
		}
		else
		{
			$('div.game_board div.board').removeClass('flipped').addClass('regular');
		}
		
		$('div#square_'+game.board[game.LAST_MOVE_SRC]).addClass('lastmove_src');
		$('div#square_'+game.board[game.LAST_MOVE_DST]).addClass('lastmove_dst');

		$('div.square').each(function(){
			$(this).droppable({
				drop : square.markAsDestination
			});
		});
	},

	renderLink : function()
	{
		var game_name = game.board[game.GAME_NAME];
		var game_id = game.board[game.GAME_ID];
		$('#navigation ul li a#game_link_'+game_id).remove();
		$('#navigation ul li.info').before('<li><a class="gamelink" href="/game/view/'+game_id+'" id="game_link_'+game_id+'">Game <span>'+game_name+'</span></a></li>');
	},

	placePieces : function(pieces)
	{
		game.blackPieces = Array();
		game.whitePieces = Array();

		pieces = (!pieces ? game.pieces : pieces);
		$('#pieceScore').remove();
		$('div.piece').remove();
		for(x=0;x<pieces.length;x++)
		{
			this_piece = pieces[x];
			if(this_piece.piece < 0)
			{
				game.blackPieces.push(this_piece);
				game.blackScore += (+piece.getScore(this_piece.piece));
			}
			else if(this_piece.piece > 0)
			{
				game.whitePieces.push(this_piece);
				game.whiteScore += (+piece.getScore(this_piece.piece));
			}
			piece.position = this_piece.position;
			piece.piece = this_piece.piece;
			$piece = piece.renderPiece();				// return the piece html
			$('#square_'+piece.position).append($piece);// insert the html

			if(game.board[game.STATE_INDEX]==0)
			{

				if(move.move_count > 0)
				{
					game.setAllPiecesDraggable();
				}
				else
				{
					// if it is white's turn and this is the white user
					if(game.board[game.TURN]==1 && view.user==game.board[game.WHITE_PLAYER_ID])
					{
						game.setWhitePiecesDraggable();
					}
					// if it is black's turn and this is the black user
					else if(game.board[game.TURN]==0 && view.user==game.board[game.BLACK_PLAYER_ID])
					{
						game.setBlackPiecesDraggable();
					}
				}
			}
		}
		// append piece scores to footer
		$('div#footer').append(' <span id="pieceScore">('+game.whiteScore.toFixed(1)+'/'+game.blackScore.toFixed(1)+')</span>' );
	},
	setAllPiecesDraggable : function()
	{
		game.setWhitePiecesDraggable();
		game.setBlackPiecesDraggable();
	},
	setWhitePiecesDraggable : function()
	{
		$('div.piece.color_1').draggable(
		{
			containment:$('div.board'),
			zIndex:'9999',
			grid:gridArray,
			start:square.markAsSource
			}
		);
		//$('div.piece.color_1').click(function(event){
		//	if(move.click_moving===1) square.clickForSource(event,event.target)
		//	event.stopPropagation();
		//});
	},
	setBlackPiecesDraggable : function()
	{
		$('div.piece.color_0').draggable(
		{
			containment:$('div.board'),
			zIndex:'9999',
			grid:gridArray,
			start:square.markAsSource
			}
		);
		//$('div.piece.color_0').click(function(event){
		//	if(move.click_moving===1) square.clickForSource(event,event.target)
		//	event.stopPropagation();
		//});
	},
	disableDraggable : function()
	{
		$('div.piece.draggable').draggable('disable');
		//$('div.piece').unbind('click');
	},
	enableDraggable : function()
	{
		$('div.piece.draggable').draggable('enable');
		//$('div.piece').click(function(event){
		//	if(move.click_moving===1) square.clickForSource(event,event.target)
		//	event.stopPropagation();
		//});
	},
	renderInfo : function()
	{
		var name =			game.board[game.GAME_NAME];
		var id =			game.board[game.GAME_ID];
		var last_move =		game.board[game.LAST_MOVE];
		var turn =			game.board[game.TURN];
		var whitePlayerName = game.board[game.WHITE_PLAYER_NAME];
		var blackPlayerName = game.board[game.BLACK_PLAYER_NAME];
		var timeOfLastMove = format.localtime(game.board[game.TIME_OF_LAST_MOVE]);
		var pendingDraw =	game.board[game.PENDING_DRAW];

		var stateIndex =	game.board[game.STATE_INDEX];
		var numStates =		game.board[game.NUM_STATES];

		$('div#game_info').css({ marginLeft : $('div#game_board_'+id).css('width') ,  marginTop:(square.size/2)+'px' })
		$('div#game_info').css({ marginRight : '42px' })
		$('#game_name').html('<span class="gameName">' + name + '</span> <span class="gamePlayers">' + whitePlayerName + ' vs ' + blackPlayerName + '</span>'+' <span class="gamePlys">(showing ply: '+(numStates-stateIndex)+'/'+numStates+')</span>');
		$('#game_statenav').html((stateIndex<numStates ? '<a href="/game/view/'+id+'/'+((+stateIndex)+1)+'" class="mimicbutton" id="stateBack">&larr; Previous Ply</a> ' : '') + (stateIndex>0 ? ' <a href="/game/view/'+id+'/'+((+stateIndex)+1)+'" class="mimicbutton" id="stateForward">Next Ply &rarr;</a>' : ''));
		if(stateIndex>=numStates && stateIndex<=0) $('#game_statenav').hide();

		$('#game_lastmove').html( (last_move ? 'Last move was <span class="lastMove">'+last_move+'</span> &mdash; <span class="timeOfLastMove fuzzy" title="'+timeOfLastMove+'">'+format.fuzzytime(timeOfLastMove)+'</span>' : 'No moves have been made') );
		$('#game_turn').addClass('turn_'+turn).html( 'It is <span class="playersTurn">' + (turn ? ''+whitePlayerName+'\'s Turn' : ''+blackPlayerName+'\'s Turn') + '</span>' );
		$('a#stateBack').click(function(){
			getObject.game(game.board[game.GAME_ID],(+game.board[game.STATE_INDEX])+1);
			return false;
		});
		$('a#stateForward').click(function(){
			getObject.game(game.board[game.GAME_ID],(+game.board[game.STATE_INDEX])-1);
			return false;
		})
		if(pendingDraw)
		{
			accept_draw_request = $.gritter.add({
					sticky : true,
					title : 'Accept Draw for Game "'+game.board[game.GAME_NAME]+'":',
					text : 'Your opponent has offered a draw. <div class="modal-input"><input type="button" id="confirm_y" value="Accept Draw." /> <input type="button" id="confirm_n" value="Decline Draw" /></div>',
					class_name : 'notify'
				});
				$('input#confirm_y').click(function(){
					UI.loading.show($('#gameName span.gameName'));
					jQuery.getJSON('/game/acceptDraw/'+game.board[game.GAME_ID],function(data){
						$.gritter.add({ title:data.message_title,class_name:data.message_type,text:data.message_text });
						UI.loading.hide($('#gameName span.gameName'));
						getObject.game(game.board[game.GAME_ID]);
					});
					$.gritter.remove(accept_draw_request);
				});
				$('input#confirm_n').click(function(){
					UI.loading.show($('#gameName span.gameName'));
					jQuery.getJSON('/game/declineDraw/'+game.board[game.GAME_ID],function(data){
						$.gritter.add({ title:data.message_title,class_name:data.message_type,text:data.message_text });
						UI.loading.hide($('#gameName span.gameName'));
						getObject.game(game.board[game.GAME_ID]);
					});
					$.gritter.remove(accept_draw_request);
				})
				// we prevent pieces from being moved while pendingDraw is set??
				game.disableDraggable();
		}
	},
	resetPositions : function()
	{
		$('div.square.selected_src').removeClass('selected_src');
		$('div.square.selected_dst').removeClass('selected_dst');
		game.placePieces();
	}

}

var gameSummary =
{

	init : function()
	{

	},
	render : function()
	{
		var upd = false;
		var yrMove = false;
		var game_id = gameSummary.board[game.GAME_ID];

		if($('#game_row_'+game_id).length > 0)
		{
			//if this game is finished, remove it from the user_games_summary table
			if(gameSummary.board[game.GAME_FINISHED])
			{
				$('#game_row_'+game_id).remove();
				return;
			}
			//otherwise, set that user_games_summary row to the current game row
			$game_row = $('#game_row_'+game_id);
			upd = true;
		}
		else
		{
			// if the game is finished, bail out now.
			if(gameSummary.board[game.GAME_FINISHED]) return;
			
			//we don't have a game_row for this game. make one.
			$game_row = jQuery('<tr title="click to play this game ('+game_id+')" class="game_row" id="game_row_'+game_id+'"></tr>');
		}
		//add game summary data to the game row
		$game_row.html('');
		$game_row.removeClass('attention');
		var timeOfLastMove = format.localtime(gameSummary.board[game.TIME_OF_LAST_MOVE]);
		yrMove = ( ((gameSummary.board[game.TURN]==1 && view.user==gameSummary.board[game.WHITE_PLAYER_ID]) || (gameSummary.board[game.TURN]==0 && view.user==gameSummary.board[game.BLACK_PLAYER_ID])) ? true : false );
		if(yrMove)
		{
			$game_row.addClass('attention');
		}
		$game_row_data = jQuery('	<td class="game_row_name">'+gameSummary.board[game.GAME_NAME]+' ('+game_id+')</td>\n\
									<td class="game_row_players">'+gameSummary.board[game.WHITE_PLAYER_NAME]+' vs. '+gameSummary.board[game.BLACK_PLAYER_NAME]+(yrMove ? '<br /><span class="attention">it\'s your turn!</span>' : '' )+'</td>\n\
									<td class="game_row_lastmove">'+gameSummary.board[game.LAST_MOVE]+' - <span class="timeOfLastMove fuzzy" title="'+timeOfLastMove+'">'+format.fuzzytime(timeOfLastMove)+'</span></td>');
		$game_row.append($game_row_data);
		
		$game_row.children('td').bind('click',function()
		{
			target_game = $(this).parent('tr').attr('id').replace('game_row_','');
			window.location.href = '/game/view/'+target_game;
			return false;
		});
		if(!upd) $('#user_games_summary tbody').append($game_row);
		gameSummary.sort();
	},
	sort : function()
	{
		var order = [];
		$('#user_games_body tr').each(function(){

			tr_a = $(this);
			ts_a = $(tr_a).children('td.game_row_lastmove').children('span.timeOfLastMove').attr('title');
			tr_a_id = $(tr_a).attr("ID");
			order.push(ts_a);
		});
		order.sort();
		for(var i=0,ol=order.length;i<ol;i++)
		{
			$('#user_games_body tr').each(function(){
				tr_a = $(this);
				ts_a = $(tr_a).children('td.game_row_lastmove').children('span.timeOfLastMove').attr('title');
				tr_a_id = $(tr_a).attr("ID");
				if(ts_a==order[i])
				{
					order[i] = tr_a_id;
					$(tr_a).clone(true).prependTo('#user_games_body');
					$(tr_a).remove();
				}
			});
		}
	},
	sortCompare : function(a,b)
	{
		if(a < b) return false;
		return true;
	}

}

var updatedGames =
{

	timer : null,
	running : false,
	delay : 31000,		//milliseconds 30000

	collection : null,

	reloadConfirmation : null,

	init : function()
	{

	},
	markForUpdate : function()
	{
		if(!updatedGames.collection) return;

		for(x=0;x<updatedGames.collection.length;x++)
		{
			var alerted = false;

			jQuery.extend(true,gameSummary,updatedGames.collection[x]);

			//look for this game in any game_rows
			if($('#game_row_'+gameSummary.board[game.GAME_ID]).length>0)
			{
				gameSummary.render();
				UI.markRow($('#game_row_'+gameSummary.board[game.GAME_ID]));
				alerted = true;
			}

			//look for game links with this ID
			if($('#game_link_'+gameSummary.board[game.GAME_ID]).length>0)
			{
				UI.markLink($('#game_link_'+gameSummary.board[game.GAME_ID]));
				alerted = true;
			}

			//look for game boards with this ID (if reloadConfirmation isn't already set)
			if($('#game_board_'+gameSummary.board[game.GAME_ID]).length>0 && updatedGames.reloadConfirmation == null)
			{
				updatedGames.reloadConfirmation = $.gritter.add({
					sticky : true,
					title : 'Activity in Game: '+ gameSummary.board[game.GAME_NAME]+' ('+gameSummary.board[game.LAST_MOVE]+' - '+format.fuzzytime(gameSummary.board[game.TIME_OF_LAST_MOVE])+')',
					text : 'Would you like to reload this game?<div class="modal-input"><input type="button" id="confirm_y" value="Yes, Reload." /> <input type="button" id="confirm_n" value="No, I\'m kind of busy right now" /></div>',
					class_name : 'notify'
				});
				$('input#confirm_y').click(function(){
					move.clear();
					getObject.game(gameSummary.board[game.GAME_ID]);
					$.gritter.remove(updatedGames.reloadConfirmation);
				});
				$('input#confirm_n').click(function(){
					$.gritter.remove(updatedGames.reloadConfirmation);
				});
				alerted = true;
			}

			//OTHERWISE, just let them know that one of their other boards (in dashboard view) has activity.
			if(alerted) continue;

			strTurn = (gameSummary.board[game.TURN]==1 ? 'White' : 'Black');
			strYourTurn = '';
			if(
				(gameSummary.board[game.TURN]==1 && gameSummary.board[game.WHITE_PLAYER_ID]==view.user) ||
				(gameSummary.board[game.TURN]==0 && gameSummary.board[game.BLACK_BLAYER_ID]==view.user)
			) { strYourTurn = ' (hey, that\'s you!) '; }

			$.gritter.add({
				sticky : false,
				title : 'Activity in Game: <a href="/game/view/'+gameSummary.board[game.GAME_ID]+'">'+ gameSummary.board[game.GAME_NAME] +'</a>',
				text : 'The last move was "'+gameSummary.board[game.LAST_MOVE]+'". It is '+strTurn+'\'s' + strYourTurn  + ' turn.',
				class_name : 'notify'
			});

		}
	},

	startTimer : function()
	{
		updatedGames.timer = setInterval( updatedGames.intervalAction , updatedGames.delay );
		updatedGames.running = true;
		//updatedGames.showTimerControl();
	},

	showTimerControl : function()
	{
		$('input#timerFrob').remove();
		$('div#footer').append('<input type="button" id="timerFrob" value="timer running. click to stop" />');
		$('input#timerFrob').click(function(){
			if(updatedGames.running == true)
			{
				updatedGames.stopTimer();
				$('input#timerFrob').val('timer stopped. click to start');
			}
			else
			{
				updatedGames.startTimer();
				$('input#timerFrob').val('timer running. click to stop');
			}
		});
	},

	stopTimer : function()
	{
		clearInterval(updatedGames.timer);
		updatedGames.running = false;
	},

	restartTimer : function()
	{
		updatedGames.stopTimer();
		updatedGames.startTimer();
	},

	intervalAction : function()
	{
		getObject.isLoggedIn();
		getObject.updatedGames((updatedGames.delay/1000)-1);
		getObject.unreadMessages();
		UI.updateFuzzyTimes();
	}

}

var userList =
{

	list : null,

	renderOpponentSelection : function()
	{
		if(!userList.list) return;
		for(x=0;x<userList.list.length;x++)
		{
			if(userList.list[x].id >= 1000) $('select#opponent').append('<option value="'+userList.list[x].id+'">'+userList.list[x].username+'</option>');
		}
	},
	getUserName : function(userid)
	{
		for(x=0;x<userList.list.length;x++)
		{
			if(userList.list[x].id == userid) return userList.list[x].username;
		}
		return 'unknown user';
	}
}
var gameNames =
{

	list : {},

	init : function()
	{
		jQuery.getJSON('/game/gameNames/',function(data){
			if(data)
			{
				gameNames.list = data.list;
			}
			$('#form_creategame input#name').blur(function(){ gameNames.checkGameName(); });
		});
	},

	checkGameName : function()
	{
		for(x=0;x<gameNames.list.length;x++)
		{
			if($('#form_creategame input#name').val()==gameNames.list[x])
			{
				$('#isNameAvailable').text('This name is already taken');
				$('#form_creategame input#name').removeClass('valid').addClass('invalid');
				return;
			}
			else
			{
				$('#isNameAvailable').text('');
				$('#form_creategame input#name').removeClass('invalid').addClass('valid');
			}
		}
	}
}

var messages =
{

	messages : null,
	gritter_messages : Array(),

	notify : function()
	{
		if(messages.messages.length > 0)
		{
			// display message indicator to user
			if($('#navigation ul a#message_link').length <= 0)
			{
				$('#navigation ul').append('<li><a class="messagelink" href="/dashboard/" id="message_link">New Messages <span><strong class="message_count">0</strong> unread</span></a></li>');
				$('#navigation ul a#message_link').click(function(){
					for(x=0;x<messages.gritter_messages.length;x++)
					{
						$.gritter.remove(messages.gritter_messages[x]);
					}
					messages.display();
					return false;
				});
			}
			$('#navigation ul a#message_link strong.message_count').text(messages.messages.length)
			UI.markLink($('#navigation ul a#message_link'));
		}
		else
		{
			$('#navigation ul a#message_link').remove();
		}
	},
	display : function()
	{
		for(i=0;i<messages.messages.length;i++)
		{
			timesent = format.localtime(messages.messages[i].time);
			gritter_message = $.gritter.add({
				sticky : true,
				title : '('+messages.messages[i].id+') '+messages.messages[i].subject,
				text : 'Message from '+userList.getUserName(messages.messages[i].from_user_id)+' (sent <span class="timeStamp fuzzy" title="'+timesent+'">'+format.fuzzytime(timesent)+'</span>)<br />'+messages.messages[i].text,
				gritter_item_title : messages.messages[i].id,
				class_name : 'notify message',
				before_close : function(el)
				{
					//get message id from title attribute
					var element_title = el.find('span.gritter-title').text();
					var message_id = element_title.substring(1,5);
					messages.markAsRead(message_id);
					getObject.unreadMessages();
				}
			});
			messages.gritter_messages.push(gritter_message);
			if(i==4 && messages.messages.length > 5)
			{
				$.gritter.add({
					sticky : false,
					title : 'Message Display Limit.',
					text : 'There are '+messages.messages.length+' unread messages. Only 5 unread messages will be displayed at a time.',
					class_name : 'error'
				});
				break;
			}
		}
	},
	markAsRead : function(message_id)
	{
		if(typeof(message_id)=='undefined') return;
		jQuery.getJSON('/user/markMessageRead/'+message_id,function(data){
			if(!data) return;
			if(data.message_type=='error')
			{
				$.gritter.add({ title:data.message_title,class_name:data.message_type,text:data.message_text });
			}
		});
	}

}

var gameHistory =
{

	history : null,

	render : function()
	{
		$('#game_history_container').html('').hide();
		last_history = 0;
		for(i=0;i<gameHistory.history.length;i++)
		{
			if(last_history != gameHistory.history[i].turn)
			{
				$('#game_history_container').append(' <strong>'+gameHistory.history[i].turn+'.</strong>&nbsp;');
				last_history = gameHistory.history[i].turn;
			}
			else
			{
				$('#game_history_container').append('&nbsp;');
			}
			$('#game_history_container').append(gameHistory.history[i].move+'');
		}
		$('#game_history_container').show();
	}

}

var getObject =
{

	isLoggedIn : function()
	{
		jQuery.getJSON('/user/isLoggedIn/',function(data){
			if(data!=1)
			{
				//not logged in, bounce to login form
				$.gritter.add({ title:'Not Logged In' , class_name:'error' , text:'Uh-oh! You\re not logged in. Please log in!' });
				return;
			}
		});
	},
	user : function()
	{
		jQuery.getJSON('/user/getUser/',function(data){
			jQuery.extend(true,user,data);
			user.renderUserInfo();
			if(!data.current_games) return;
			for(x=0;x<data.current_games.length;x++)
			{
				getObject.gameSummary(data.current_games[x]);
			}
		});
	},
	unreadMessages : function()
	{
		jQuery.getJSON('/user/getUnreadMessages/',function(data){
			if(!data) return;
			messages.messages = null;
			jQuery.extend(true,messages,data);
			messages.notify();
		});
	},
	userList : function()
	{
		jQuery.getJSON('/user/getUserList/',function(data){
			if(!data) return;
			jQuery.extend(true,userList,data);
			userList.renderOpponentSelection();
		});
	},
	game : function(gameId,stateIndex)
	{
		if(!gameId) return;
		stateIndex = (!stateIndex?0:stateIndex);
		UI.loading.show($('#gameName span.gameName'));
		jQuery.getJSON('/game/getGame/'+gameId+'/'+stateIndex,function(data){
			game.init();
			jQuery.extend(true,game,data);
			game.renderBoard();
			game.renderInfo();
			game.placePieces();
			game.renderLink();
			UI.loading.hide($('#gameName span.gameName'));
		});
	},
	gameSummary : function(gameId)
	{
		if(!gameId) return;
		jQuery.getJSON('/game/getGameSummary/'+gameId,function(data){
			gameSummary.init();
			jQuery.extend(true,gameSummary,data);
			gameSummary.render();
		});
	},
	gameHistory : function(gameId)
	{
		if(!gameId) return;
		UI.loading.show($('#gameName span.gameName'));
		jQuery.getJSON('/game/getHistory/'+gameId,function(data){
			gameHistory.history = null;
			jQuery.extend(true,gameHistory,data);
			gameHistory.render();
			UI.loading.hide($('#gameName span.gameName'));
		});
	},
	updatedGames : function(secondsSinceLastUpdate)
	{
		if(!secondsSinceLastUpdate) return;
		UI.loading.show($('#user_games_header'));
		jQuery.getJSON('/game/getUpdatedGames/'+secondsSinceLastUpdate,function(data){
			updatedGames.init();
			jQuery.extend(true,updatedGames,data);
			updatedGames.markForUpdate();
			UI.loading.hide($('#user_games_header'));
		});
	},
	validMoves : function(gameId,src)
	{
		UI.loading.show($('#gameName span.gameName'));
		jQuery.getJSON('/game/getValidMoves/'+gameId+'/'+src,function(data){
			jQuery.extend(true,validMoves.squares,data.squares);
			validMoves.init();
			validMoves.mark();
			UI.loading.hide($('#gameName span.gameName'));
		});
	}

}

var view =
{
	user : null,
	game : null,

	init : function()
	{
		if(!$('body').hasClass('log_in'))
		{
			getObject.isLoggedIn();
		}
		$.ajaxSetup({
			error:function(XMLHttpRequest, textStatus, errorThrown){
				$.gritter.add({ title:'API Error' , class_name:'error' , text:'There was a problem communicating with the API ('+textStatus+'). I\'ll keep trying. With luck, it will resolve itself!' });
				return;
			}
		});

		this.determineUser();
		this.determineGame();
		if(this.user)
		{
			getObject.user();
			getObject.unreadMessages();
		}
		getObject.userList();

		if(this.game) getObject.game(this.game);

		if($('body.dashboard').length > 0) this.dashboard.init();
		if($('body.game').length > 0) this.game_view.init();

		$('form#form_login #username').focus();
	},
	determineUser : function()
	{
		var user = $('#user_id').attr('title');
		if(user == '') user = null;
		this.user = user
	},
	determineGame : function()
	{
		var game = $('#game_id').attr('title');
		if(game == '') game = null;
		this.game = game
	},
	game_view : {

		init: function()
		{
			view.game_view.bindGameHistory();
			view.game_view.bindResign();
			view.game_view.bindOfferDraw();
			view.game_view.bindManualMoving();
			view.game_view.bindFreeMove();
		},

		bindGameHistory : function()
		{
			$('a#game_history').click(function(){
				if($(this).attr('title')=='Show Game History')
				{
					getObject.gameHistory(view.game);
					$(this).attr('title','Hide Game History').text('Hide this Game\'s History');
				}
				else
				{
					$('#game_history_container').hide();
					$(this).attr('title','Show Game History').text('View this Game\'s History');
				}
				return false;
			});
		},

		bindManualMoving : function()
		{
			$('#submit-move').click(function(){

				$('div.selected_src').removeClass('selected_src');
				$('div.selected_dst').removeClass('selected_dst');

				var src = null;
				var dst = null;
				var src_chess_coord = $('#game_manual_move input#src').val();
				var dst_chess_coord = $('#game_manual_move input#dst').val();
				//find the square with these coordinates
				$('div.square').each(function(){
					if($(this).attr('title').toLowerCase()==src_chess_coord.toLowerCase())
					{
						src = $(this);
						$(this).addClass('selected_src');
					}
					if($(this).attr('title').toLowerCase()==dst_chess_coord.toLowerCase())
					{
						dst = $(this);
						$(this).addClass('selected_dst');
					}
				});
				if(src!==null && dst!==null)
				{
					move.src = src;
					move.dst = dst;
					move.tryMove();
					game.renderInfo();
					$('div.square').removeClass('validDestination');
				}
				else
				{
					$.gritter.add({ title:'Invalid Move',class_name:'error',text:'I couldn\'t make heads nor tails of that input. Try again?' });
				}
			});
		},

		bindResign : function()
		{
			$('a#resign').click(function(){
				resign_confirmation = $.gritter.add({
					sticky : true,
					title : 'Confirm Resignation from Game "'+game.board[game.GAME_NAME]+'":',
					text : 'Are you absolutely, positively, SURE that you wish to resign from this game? <div class="modal-input"><input type="button" id="confirm_y" value="Yes, I suck." /> <input type="button" id="confirm_n" value="No! Eye of the Tiger!" /></div>',
					class_name : 'notify'
				});
				$('input#confirm_y').click(function(){
					UI.loading.show($('#gameName span.gameName'));
					jQuery.getJSON('/game/resign/'+game.board[game.GAME_ID],function(data){
						$.gritter.add({ title:data.message_title,class_name:data.message_type,text:data.message_text });
						UI.loading.hide($('#gameName span.gameName'));
						getObject.game(game.board[game.GAME_ID]);
					});
					$.gritter.remove(resign_confirmation);
				});
				$('input#confirm_n').click(function(){
					$.gritter.remove(resign_confirmation);
				});
				return false;
			});
		},

		bindOfferDraw : function()
		{
			$('a#offer_draw').click(function(){
				draw_offer = $.gritter.add({
					sticky : true,
					title : 'Draw Offer for Game "'+game.board[game.GAME_NAME]+'":',
					text : 'Are you sure you want to offer a draw to your opponent? <div class="modal-input"><input type="button" id="confirm_y" value="Yes, I\'ve had enough." /> <input type="button" id="confirm_n" value="No! Don\'t stop believin!" /></div>',
					class_name : 'notify'
				});
				$('input#confirm_y').click(function(){
					UI.loading.show($('#gameName span.gameName'));
					jQuery.getJSON('/game/offerDraw/'+game.board[game.GAME_ID],function(data){
						$.gritter.add({ title:data.message_title,class_name:data.message_type,text:data.message_text });
						UI.loading.hide($('#gameName span.gameName'));
						getObject.game(game.board[game.GAME_ID]);
					});
					$.gritter.remove(draw_offer);
				});
				$('input#confirm_n').click(function(){
					$.gritter.remove(draw_offer);
				});
				return false;
			});
		},
		
		bindFreeMove : function()
		{
			$('a#free_move').click(function(){
				if(move.free_move_mode)
				{
					move.disableFreeMove();
				}
				else
				{
					move.enableFreeMove();
				}
				return false;
			});
		}

	},
	dashboard : {

		init : function()
		{
			$('#form_userinfo_email').hide();
			$('#form_userinfo_password').hide();
			$('#form_userinfo_notifications').hide();

			$('#edit_userinfo_email').show().click(function(){
				if($('#form_userinfo_email:hidden').length>0)
				{
					$('#form_userinfo_email').show();
					$('#input_email').focus();
					$('#edit_userinfo_email').text('cancel change');
				}
				else
				{
					$('#form_userinfo_email').hide();
					$('#form_userinfo_email input[type="text"]').val('');
					$('#edit_userinfo_email').text('change');
				}
				return false;
			});
			$('#edit_userinfo_password').show().click(function(){
				if($('#form_userinfo_password:hidden').length>0)
				{
					$('#form_userinfo_password').show();
					$('#input_password').focus();
					$('#edit_userinfo_password').text('cancel change');
				}
				else
				{
					$('#form_userinfo_password').hide();
					$('#form_userinfo_password input[type="password"]').val('');
					$('#edit_userinfo_password').text('change');
				}
				return false;
			});
			$('#edit_userinfo_notifications').show().click(function(){
				user.notifications = (user.notifications ? 0 : 1);
				jQuery.getJSON('/user/setNotifications/'+user.notifications,function(data){
					$.gritter.add({ title:data.message_title,class_name:data.message_type,text:data.message_text });
					getObject.user(view.user);
				});
				return false;
			});

			//TODO bind to email_submit and password_submit

			gameNames.init();
			view.dashboard.bindCreateGame();
			view.dashboard.bindSetPassword();
			view.dashboard.bindSetEmail();
		},
		bindSetEmail : function()
		{
			$('#email-submit').click(function(){
				jQuery.getJSON('/user/setEmail/'+$('#input_email').val(),function(data){
					$.gritter.add({ title:data.message_title,class_name:data.message_type,text:data.message_text });
					getObject.user(view.user);
				});
				return false;
			})
		},
		bindSetPassword : function()
		{
			$('#password-submit').click(function(){
				if($('#input_password').val() != $('#input_password2').val())
				{
					$.gritter.add({ title:'Password Do Not Match',class_name:'error',text:'Passwords must match! Please try again.' });
					return;
				}
				jQuery.getJSON('/user/setPassword/'+$('#input_password').val(),function(data){
					$.gritter.add({ title:data.message_title,class_name:data.message_type,text:data.message_text });
					getObject.user(view.user);
				});
				return false;
			})
		},
		bindCreateGame : function()
		{
			$('#form_creategame').submit(function(){
				var game_name = $('#form_creategame #name').val();
				var opponent = $('#form_creategame #opponent').val();
				var playas = $('#form_creategame #playas').val();
				jQuery.getJSON('/game/createGame/'+game_name+'/'+opponent+'/'+playas,function(data){
					$.gritter.add({ title:data.message_title,class_name:data.message_type,text:data.message_text });
					getObject.gameSummary(data.object_id);
				});
				return false;
			});
		}

	}

}

var UI =
{
	markLink : function($link)
	{
		$link.pulse({
			textColors: ['#ccccbb','#111100'],
			runLength: 10
		});
	},
	markRow : function($row,text)
	{
		text=(typeof(text)=='undefined'?'this game has been updated':text);
		$row.removeClass('attention').addClass('attention');
		$row.children('td.game_row_lastmove').append('<br /><span class="attention">'+text+'</span>');
	},

	loading :
	{

		show : function($parent)
		{
			UI.loading.hide($parent);
			$parent.append('<img class="smallloading" src="/_public/img/small_loading.gif" />');
		},
		hide : function($parent)
		{
			$parent.children('img.smallloading').remove();
		}

	},

	updateFuzzyTimes : function()
	{
		$('span.fuzzy').each(function(){
			if($(this).attr('title').length > 0) $(this).html(format.fuzzytime($(this).attr('title')));
		});
	}

}

$(document).ready(function()
{

	// initialize messenger object
	messenger.init();

	// initialize debug
	uidebug.init();

	view.init();

	if(view.user) updatedGames.startTimer();

	var invalid_func=function(){ $.gritter.add({ title:'Form Error',class_name:'error',text:'Please correct the invalid form values (outlined in red), then re-submit'}); }
	validator = $.fn.t9validator;
	validator.setcallback(invalid_func);
	validator.bind($('form'));
	
	// navigation fixed - fadeout
	$(function() {
		$(window).scroll(function()
		{
			var scrollTop = $(window).scrollTop();
			if (scrollTop != 0)
			{
				$('#navigation ul').stop().animate({'opacity':'0.2'},400);
			}
			else
			{
				$('#navigation ul').stop().animate({'opacity':'1'},400);
			}
		});
		
		$('#navigation ul').hover(
			function(e) 
			{
				var scrollTop = $(window).scrollTop();
				if (scrollTop != 0)
				{
					$('#navigation ul').stop().animate({'opacity':'1'},400);
				}
			},
			function(e) 
			{
				var scrollTop = $(window).scrollTop();
				if (scrollTop != 0)
				{
					$('#navigation ul').stop().animate({'opacity':'0.2'},400);
				}
			}
		);
	});

});
