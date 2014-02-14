<?php defined('SYSPATH') OR die('No direct access allowed.');


class Help_Controller extends Template_Controller {

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

	public function index()
	{
		$this->auto_render = true;
		$this->template->content = new View('help');
		$this->template->title = 'Help';
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
