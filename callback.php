<?

//var_dump($_SERVER);
//var_dump($_POST);
session_start();

if ($_GET['state'] == $_SESSION['state'])
{

	// on envoie imm�diatement le code sur la box avant qu'il p�rime
	$script_url = "http://localhost/script/?exec=tesla_oauth.php&mode=verify&oauth_code=".$_GET['code'];
	$redirect_url = "/box_http_query.php?controller_id=".$_SESSION['attached_controller_id']."&url=".urlencode($script_url);
	header("Location: ".$redirect_url);
}
else
{
	die("Session error");
}

?>
