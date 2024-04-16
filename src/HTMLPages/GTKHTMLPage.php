<?php

class GTKHTMLPage
{
	public $messages        = [];
	public $user;
	public $session;
	public $authenticate = false;
	public $authorize    = false;
	public $authorizationDelegate; 
	public $headerPath;
	public $footerPath;

	public function __construct($options = [])
	{
	}

	public function setAuthenticate($toSet)
	{
		$this->authenticate = $toSet;
	}

	public function setAuthorize($toSet)
	{
		$this->authorize = $toSet;
	}



	//--- Process GET, POST, PUT, PATCH, renderBODY - Overidable Methods
	//----------------------------------------------------------
	//----------------------------------------------------------

	public function processGet($getObject)
	{
		$debug = false;

		if ($debug)
		{
			error_log("Process GET Basic: ".print_r($getObject, true));
		}
		// Does nothing..

		
	}

	public function processPost($postObject)
	{
		$debug = false;

		if ($debug)
		{
			error_log("Process POST Basic: ".print_r($postObject, true));
		}
		// Does nothing..
	}


	public function gtk_renderBody($get, $post, $server, $cookie, $session, $files, $env)
	{
		$toReturn = $this->renderMessages($get, $post, $server, $cookie, $session, $files, $env);

		if (method_exists($this, "renderBody"))
		{
			$toReturn .= $this->renderBody($get, $post, $server, $cookie, $session, $files, $env);
		}
		
		return $toReturn;
	}
	
	public function renderMessages($get, $post, $server, $cookie, $session, $files, $env)
	{
		$toReturn = "";

		if (count($this->messages) > 0)
		{
			$toReturn .= "<h1 class='font-bold'>";
			$toReturn .= "Mensajes";
			$toReturn .= "</h1>";
			$toReturn .= "<div>";
			foreach ($this->messages as $message)
			{
				$toReturn .= "<div>";
				if (is_string($message))
				{
					$toReturn .= $message;
				}
				else
				{
					$toReturn .= print_r($message, true);
				}
				$toReturn .= "</div>";
			}
			$toReturn .= "</div>";
		}

		return $toReturn;
	}

	public function processPUT(){}
	public function processPatch(){}


	//--- Header, Footer methods
	//---------------------------------------------------------
	//---------------------------------------------------------
	
	public function gtk_renderHeader($get, $post, $server, $cookie, $session, $files, $env)
	{
		$headerPath = $this->headerPath;

		if (!$headerPath)
		{
			$headerPath = dirname($_SERVER['DOCUMENT_ROOT'])."/templates/header.php";

		}

		require_once($headerPath);
	}

	public function gtk_renderFooter($get, $post, $server, $cookie, $session, $files, $env)
	{
		$footerPath = $this->footerPath;

		if (!$footerPath)
		{
			$footerPath = dirname($_SERVER['DOCUMENT_ROOT'])."/templates/footer.php";

		}

		require_once($footerPath);
	}

	public function handleNotAuthenticated($maybeCurrentUser, $maybeCurrentSession)
	{
		redirectToURL("/auth/login.php", null, [
		]);
		// die("No autenticado");
	}

	public function handleNotAuthorized($maybeCurrentUser, $maybeCurrentSession)
	{
		die("No autorizado.");
	}

	public function isAuthorized($maybeCurrentUser, $maybeCurrentSession)
	{
		return true;
	}

	public function render($get, $post, $server, $cookie, $session, $files, $env)
	{
		$debug = false;

		$maybeCurrentUser    = DataAccessManager::get("session")->getCurrentUser();
		$maybeCurrentSession = DataAccessManager::get("session")->getCurrentApacheSession([
			"requireValid" => true,
		]);

		$this->user    = $maybeCurrentUser;
		$this->session = $maybeCurrentSession;
		
		if ($debug)
		{
			error_log("`render` : Got current user: ".print_r($maybeCurrentUser, true));
			error_log("`render` : Got current session: ".print_r($maybeCurrentSession, true));
		}


		if ($this->authenticate)
		{

			/*
			$isAuthenticated = DataAccessManager::get("authentication_provider")->isAuthenticated();

			if (!$isAuthenticated)
			{
				return DataAccessManager::get("authentication_provider")->handleNegativeSituation();
			}

			if ($this->authorize)
			{
				$isAuthorized = DataAccessManager::get("authorization_provider")->isAuthorized($this);

				if (!$isAuthorized)
				{
					return DataAccessManager::get("authorization_provider")->handleNegativeSituation();
				}
			}
			*/
			if (!$maybeCurrentSession)
			{
				return $this->handleNotAuthenticated($maybeCurrentUser, $maybeCurrentSession);
			}

			if ($this->authorize)
			{
				$isAuthorized = $this->isAuthorized($maybeCurrentUser, $maybeCurrentSession);

				if (!$isAuthorized)
				{
					return $this->handleNotAuthorized($maybeCurrentUser, $maybeCurrentSession);
				}
			}
		}

		switch ($server["REQUEST_METHOD"])
		{
			case "GET":
				$this->processGet($get);
				break;
			case "POST":
				$this->processPost($post);
				break;
			case "PATCH":
				$this->processPatch($post);
				break;
			case "PUT":
				$this->processPut($post);
				break;
		}

		$text = "";
		$text .= $this->gtk_renderHeader($get, $post, $server, $cookie, $session, $files, $env);
		$text .= $this->gtk_renderBody($get, $post, $server, $cookie, $session, $files, $env);
		$text .= $this->gtk_renderFooter($get, $post, $server, $cookie, $session, $files, $env);
		return $text;
	}

	function htmlEscape($string)
	{
    	if ($string)
    	{
        	return htmlspecialchars($string);
    	}
	}

}

class GTKEditPage extends GTKHTMLPage
{

}
