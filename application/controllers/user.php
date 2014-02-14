<?php defined('SYSPATH') OR die('No direct access allowed.');


class User_Controller extends Template_Controller {

	// Disable this controller when Kohana is set to production mode.
	// See http://docs.kohanaphp.com/installation/deployment for more details.
	const ALLOW_PRODUCTION = FALSE;

	// Set the name of the template to use
	public $template = 'templates/default';

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

	public function login()
	{
		if($this->input->post('username') != '' && $this->input->post('password') != '')
		{
			$db = new Database();
			$user = $db->select('id')
				->from('users')
				->where('( lower(username) = "'.strtolower($this->input->post('username')).'" or lower(email) = "'.strtolower($this->input->post('username')).'") and password = "'.md5($this->input->post('password')).'"')
				->get();

			if(count($user) == 1)
			{
				$this->session->set('user',$user[0]->id);
				url::redirect('/dashboard/');
			}
			else
			{
				$this->template->messages = '<div class="message error" title="Error Logging In">Invalid username or password</div>';
			}
			//TODO: find all read messages older than 30 days and remove them
		}
	}
	public function logout()
	{
		$this->session->set('user',null);
		url::redirect('/dashboard/');
	}

	public function isLoggedIn()
	{
		$this->auto_render = true;
		$user = $this->session->get('user');
		if(!isset($user) || $user=='')
		{
			$logged_in = 0;
		}
		else
		{
			$logged_in = 1;
		}
		echo $logged_in;
	}

	public function index()
	{
		$this->auto_render = true;
		$this->verify_logged_in();
		$this->template->content = new View('user');
		$this->template->title = 'User';
	}

	public function setEmail($email)
	{
		$this->auto_render = false;
		$this->verify_logged_in();

		$user = ORM::factory('user',$this->session->get('user'));
		$user->email = $email;
		$user->save();

		$this->json_success('Email Address Updated','You\'ve successfully changed your email address to '.$email.'',$user->id);
	}

	public function setNotifications($notifications)
	{
		$this->auto_render = false;
		$this->verify_logged_in();

		$user = ORM::factory('user',$this->session->get('user'));
		$user->notifications = $notifications;
		$user->save();

		$this->json_success('Email Address Updated','You\'ve successfully changed turned your notifications '.($notifications ? 'ON' : 'OFF').'.',$user->id);
	}

	public function setPassword($password)
	{
		$this->auto_render = false;
		$this->verify_logged_in();

		$user = ORM::factory('user',$this->session->get('user'));
		$user->password = md5($password);
		$user->save();

		$this->json_success('Password Updated','You\'ve successfully changed your password.',$user->id);
	}

	public function getUser()
	{
		$this->auto_render = false;
		$this->verify_logged_in();

		$user = ORM::factory('user',$this->session->get('user'));
		$user_games = $user->games;
		$user_unread_messages = ORM::factory('message')->where(array('to_user_id'=>$user->id,'read'=>0))->find_all();
		
		$user_output = array(
			'id'=>					$user->id,
			'username'=>			$user->username,
			'email'=>				$user->email,
			'active'=>				$user->active,
			'wins'=>				$user->wins,
			'losses'=>				$user->losses,
			'draws'=>				$user->draws,
			'notifications'=>		$user->notifications,
			'confirmBeforeMove'=>	$user->confirmBeforeMove,
			'newMessageCount'=>		count($user_unread_messages)
		);
		foreach($user_games as $game)
		{
			$user_output['current_games'][] = $game->id;
		}
		echo json_encode($user_output);
	}

	public function getUnreadMessages()
	{
		$this->auto_render = false;
		$this->verify_logged_in();
		$user_unread_messages = ORM::factory('message')->where(array('to_user_id'=>$this->session->get('user'),'read'=>0))->orderby('time','desc')->find_all();
		$messages = array('messages'=>array());
		foreach($user_unread_messages as $message)
		{
			$messages['messages'][] = $message->as_array();
		}
		echo json_encode($messages);
	}

	public function getMessages()
	{
		$this->auto_render = false;
		$this->verify_logged_in();
		$user_unread_messages = ORM::factory('message')->where(array('to_user_id'=>$this->session->get('user')))->orderby('time','desc')->find_all();
		$messages = array('messages'=>array());
		foreach($user_unread_messages as $message)
		{
			$messages['messages'][] = $message->as_array();
		}
		echo json_encode($messages);
	}

	public function markMessageRead($message_id=null)
	{
		$this->auto_render = false;
		$this->verify_logged_in();
		if(!$message_id) return json_encode(array());
		$message = ORM::Factory('message',$message_id);
		if($message->to_user_id != $this->session->get('user'))
		{
			$this->json_error('Invalid Message','The specified message ('.$message_id.') does not belong to you',$message_id);
		}
		$message->read = true;
		$message->save();
		$this->json_success('Message Read','The specified message ('.$message_id.') has been marked as read',$message_id);
	}

	public function getUserList()
	{
		$this->auto_render = false;
		$this->verify_logged_in();
		$users = ORM::factory('user')->where( array('active'=>1) )->find_all();
		$user_list = array('list'=>array());
		foreach($users as $user)
		{
			$user_list['list'][] = array( "id"=>$user->id, "username"=>$user->username);
		}
		echo json_encode($user_list);
	}

	//TODO only allow this for logged in user
	public function edit($user_id=0)
	{
		$this->auto_render = false;
		$this->verify_logged_in();
		$user = ORM::factory('user',$user_id);
		if($this->input->post('submit')=='submit' || request::is_ajax())
		{
			if($this->input->post('username')!='') $user->username = $this->input->post('username');
			if($this->input->post('password')!='') $user->password = md5($this->input->post('password'));
			if($this->input->post('email')!='') $user->email = $this->input->post('email');
			$user->save();
		}
		$this->json_success('Account Updated!','Your account has been updated successfully.',$user_id);
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
