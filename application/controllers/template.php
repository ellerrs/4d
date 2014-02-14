<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Allows a template to be automatically loaded and displayed. Display can be
 * dynamically turned off in the controller methods, and the template file
 * can be overloaded.
 *
 * To use it, declare your controller to extend this class:
 * `class Your_Controller extends Template_Controller`
 *
 * $Id: template.php 3769 2008-12-15 00:48:56Z zombor $
 *
 * @package    Core
 * @author     Kohana Team
 * @copyright  (c) 2007-2008 Kohana Team
 * @license    http://kohanaphp.com/license.html
 */
abstract class Template_Controller extends Controller {

	// Template view name
	public $template = 'template';

	// Default to do auto-rendering
	public $auto_render = TRUE;

	/**
	 * Template loading and setup routine.
	 */
	public function __construct()
	{
		parent::__construct();

		// Load the template
		$this->template = new View($this->template);

		if ($this->auto_render == TRUE)
		{
			// Render the template immediately after the controller method
			Event::add('system.post_controller', array($this, '_render'));
		}
		$this->session = Session::instance();
	}

	/**
	 * Render the loaded template.
	 */
	public function _render()
	{
		if(!isset($_SESSION['user']) || $this->session->get('user')=='')
		{
			$this->auto_render = true;
			$this->template->content = new View('login');
			$this->template->title = 'Log In';
		}
		if ($this->auto_render == TRUE)
		{
			// Render the template when the class is destroyed
			$this->template->render(TRUE);
		}
	}

	public function verify_logged_in()
	{
		$user = $this->session->get('user');
		if(!isset($user) || $user=='')
		{
			$this->json_error('Not Logged In','You must log in first!',9999);
		}
	}

	public function json_error($title,$text,$obj_id=0)
	{
		echo json_encode(array('message_type'=>'error','message_title'=>$title,'message_text'=>$text,'object_id'=>$obj_id));
		exit();
	}
	public function json_success($title,$text,$obj_id=0)
	{
		echo json_encode(array('message_type'=>'success','message_title'=>$title,'message_text'=>$text,'object_id'=>$obj_id));
		exit();
	}

} // End Template_Controller