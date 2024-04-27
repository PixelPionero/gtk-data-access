<?php


class SessionDataAccess extends DataAccess 
{
	public function currentUserHasPermission($permission)
	{
		$currentUser = $this->getCurrentUser();
		
		return DataAccessManager::get("persona")->hasPermission($permission, $currentUser);
	}
	public function currentUserHasOneOfPermissions($permissions)
	{
		$currentUser = $this->getCurrentUser();
		
		return DataAccessManager::get("persona")->hasOneOfPermissions(
			$permissions, $currentUser);
	}

	public function currentUserIsInGroups($groups)
	{
		$currentUser = $this->getCurrentUser();
		
		return DataAccessManager::get("persona")->isInGroup(
			$currentUser,
			$groups);
	}
	public function clearCurrentSession()
	{
		return $this->clearCurrentSessioAndRedirectTo("/auth/login.php");
	}
	public function clearCurrentSessioAndRedirectTo($sendWithRedirect = "/auth/login.php")
	{
		// Loop through all cookies and unset them
		foreach ($_COOKIE as $cookie_name => $cookie_value) 
		{
			setcookie($cookie_name, "", time() - 3600, "/");
		}
		
		header("Cache-Control: no-cache, must-revalidate"); // HTTP 1.1
		header("Pragma: no-cache");                         // HTTP 1.0
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");   // Date in the past


		// Notify successful logout and redirect to the home page after 3 seconds
		$message = 'Has cerrado sesión correctamente. En 3 segundos serás redirigido a la página de inicio.';
		$redirectURL = '/index.php'; // Change this to the URL of your home page
		
		echo "<!DOCTYPE html>
		<html lang='es'>
		<head>
			<meta charset='UTF-8'>
			<meta http-equiv='X-UA-Compatible' content='IE=edge'>
			<meta name='viewport' content='width=device-width, initial-scale=1.0'>
			<title>Cierre de sesión</title>
			<script>
				setTimeout(function() {
					window.location.href = '$redirectURL';
				}, 3000);
			</script>
		</head>
		<body>
			<p>$message</p>
			<p>Si no eres redirigido automáticamente, haz clic <a href='$redirectURL'>aquí</a>.</p>
		</body>
		</html>";
		die(); // Terminate the script execution
	}

	public function isDeveloper()
	{
		$currentUser = $this->getCurrentUserIfAny();
		
		return false;
	}

	public function getCurrentUser()
	{
		$debug = false;

		static $session     = null;
		static $currentUser = null;
		static $didCheck    = false;

		if (!$didCheck)
		{
			$session = $this->getCurrentApacheSession();
			if ($session)
			{
				$currentUser = $this->getUserFromSession($session);
			}
			
			if ($debug)
			{
				error_log("`getCurrentUser` - Got current user: ".print_r($currentUser, true));
				error_log("`getCurrentUser` - Got session: ".print_r($session, true));	
			}

			$didCheck = true;
		}

		return $currentUser;
	}


	public function getCurrentUserIfAny()
	{
		$session = $this->getCurrentApacheSession();

		$currentUser = null;

		if ($session)
		{
			$currentUser = $this->getUserFromSession($session);
		}

		return $currentUser;
	}


	public function getCurrentApacheUserOrSendToLoginWithRedirect($sendWithRedirect = null)
	{
		$debug = false;

		static $didSearchForSession = false;
		static $session 			= null;

		if (!$didSearchForSession)
		{
			$session = $this->getCurrentApacheSession();

			if ($debug)
			{
				error_log("Did search for session - got: ".print_r($session, true));
			}
		}

		if ($session)
		{
			if ($this->verifySession($session))
			{
				$user = $this->getUserFromSession($session);

				if ($debug)
				{
					error_log("`getUserFromSession` - got user: ".print_r($user, true));

				}

				return $user;
			}
			else
			{
				if ($debug)
				{
					error_log("Will redirect...");
				}
				redirectToURL("/auth/login.php", null, [
					"redirectTo" => $sendWithRedirect,
				]);
			}
		}
		else
		{
			return null;
		}
	}

	public function getUserFromSession($session)
	{
		$user_id = $this->valueForKey("user_id", $session);

		return DataAccessManager::get("persona")->getOne("id", $user_id);
	}
 

	public function getCurrentApacheSession($options = [
		"requireValid"	    => false,
		"redirectIfInvalid" => false,
		"redirectTo"        => null,
	])
	{
		$debug = false;

		if (isset($_COOKIE['AuthCookie']))
		{
			$authToken = $_COOKIE['AuthCookie'];

			if ($debug)
			{
				error_log("`getCurrentApacheSession` - Got auth token: ".$authToken);
			}
	
			$session = DataAccessManager::get("session")->getSessionById($authToken);

			if ($debug)
			{
				error_log("`getCurrentApacheSession` - Got session: ".$session);
			}

			$isValid = $this->verifySession($session);

			if ($debug)
			{
				error_log("Got `isValid`: ".$isValid);
			}

			if (!$isValid)
			{
				if (isTruthy($options["redirectIfInvalid"]))
				{
					redirectToURL("/auth/login.php", null, [
						"redirectTo" => $options["redirectTo"] ?? null,
					]);
					return null;
				}
	
				if (isTruthy($options["requireValid"]))
				{
					return null;
				}
			}

			if ($session)
			{
				$session["user"] = $this->getUserFromSession($session);
			}
			
			return $session;
		}
		else
		{
			return null;
		}

	}


	public function register()
	{	
		$columnMappings = [
			new GTKColumnMapping($this, "id",		 	    [ 
				"formLabel"    => "ID", 
				"isPrimaryKey" =>true, 
				"isUnique"     => true,
				"isAutoIncrement" => true,
				"type"         => "INTEGER",
			]),
			new GTKColumnMapping($this, "session_guid"),
			new GTKColumnMapping($this, "user_id",			[ "formLabel" => "User ID"]),
			new GTKColumnMapping($this, "created_at",	    [ "formLabel" => "Created At"]),
			new GTKColumnMapping($this, "valid_until",   	[ "formLabel" => "Valid Until"]),
			new GTKColumnMapping($this, "canceled",		[ "formLabel" => "Canceled"]),
		];

		$this->dataMapping = new GTKDataSetMapping($this, $columnMappings);

		/*
		$this->getDB()->query("CREATE TABLE IF NOT EXISTS {$this->tableName()} 
		  (id, 
		   user_id,
		   created_at,
		   valid_until,
		   canceled,
		   UNIQUE (id))");
		*/

		$this->defaultOrderByColumn = "created_at";
		$this->defaultOrderByOrder  = "DESC";


		
	}
	
	function guidv4($data = null) {
		// Generate 16 bytes (128 bits) of random data or use the data passed into the function.
		$data = $data ?? random_bytes(16);
		assert(strlen($data) == 16);
	
		// Set version to 0100
		$data[6] = chr(ord($data[6]) & 0x0f | 0x40);
		// Set bits 6-7 to 10
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80);
	
		// Output the 36 character UUID.
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}
	
	public function newSessionForUser($user)
	{
		$query = "INSERT INTO {$this->tableName()} 
			(session_guid,  user_id, created_at, valid_until,  canceled)
			VALUES
			(:session_guid,  :user_id,  :created_at, :valid_until,  :canceled)";
			
		$statement = $this->getDB()->prepare($query);
		
		$session_value = uniqid();

		$defaultSessionLength = 60 * 60 * 24 * 30; // 30 days

		$statement->bindValue(':session_guid', $session_value);
		// $statement->bindValue(':user_id_column_name',   DataAccessManager::get("persona")->dbColumnNameForKey("id"));
		$statement->bindValue(':user_id',     			DataAccessManager::get("persona")->valueForKey("id", $user));
		$statement->bindValue(':created_at', 		 	date(DATE_ATOM));
		$statement->bindValue(':valid_until', 			time() + $defaultSessionLength);
		$statement->bindValue(':canceled', 	  			0);
		
		// Execute the INSERT statement
		$result = $statement->execute();
		
		if ($result) 
		{
			return $session_value;
		} 
		else 
		{
			// INSERT failed
			// Handle the error
			echo 'INSERT FAILED';
			return 0;
		}
	}

	
	
	public function getSessionById($id) {
		$debug = false;
		
		$query = "SELECT * FROM {$this->tableName()} WHERE session_guid = :session_guid";
		
		if ($debug)
		{
			error_log("Running query...: ".$query);
		}
		
		$statement = $this->getDB()->prepare($query);
		
		
		$statement->bindValue(':session_guid', $id);
				
		$statement->execute();
		
        // Fetch the result
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

		if ($debug)
		{
			$toPrint = serialize($result);
			echo "Result: {$toPrint}<br/>";
		}

		if (count($result) > 1)
		{
			error_log("More than one session found for id: {$id}");
			die("Error. Favor avisar administador.");
		}
		else if (count($result) === 0)
		{
			return null;
		}


		$session = $result[0];

		$session["isValid"] = $this->verifySession($session);

		return $session;
	}

	public function isValid($session)
	{
		return $this->verifySession($session);
	}
	
	public function verifySession($session)
	{
		$debug = false;

		if (!$session)
		{
			return false;
		}

		if ($debug) { error_log("Verifying session: ".serialize($session)); }

		$validUntil = $session["valid_until"];

		if (!$validUntil)
		{
			return false;
		}

		if (time() > $validUntil)
		{
			if ($debug) { error_log("Time: ".time()." > valid_until: ".$session["valid_until"]); }
			return false;
		}
		
		if (isTruthy($session['canceled']))
		{
			return false;
		}

		return true;
	}

	public function cancelSession($session)
	{
		$sql = "UPDATE {$this->tableName()} SET canceled = 1 WHERE session_guid = :session_guid";

		$statement = $this->getDB()->prepare($sql);

		$statement->bindValue(':session_guid', $session["session_guid"]);

		$result = $statement->execute();

		if ($result) 
		{
			return true;
		} 
		else 
		{
			// INSERT failed
			// Handle the error
			// echo 'INSERT FAILED';
			return false;
		}
	}
}
