<? 


function sdk_random_bytes($bytes)
    {   $buf = '';
        $execCount = 0;

        /**
         * Let's not let it loop forever. If we run N times and fail to
         * get N bytes of random data, then CAPICOM has failed us.
         */
        do {
            $buf .= (string) uniqid(rand(), TRUE);
            if (strlen($buf) >= $bytes) {
                /**
                 * Return our random entropy buffer here:
                 */
                return (string) substr($buf, 0, $bytes);
            }
            ++$execCount;
        } while ($execCount < $bytes);

        /**
         * If we reach here, PHP has failed us.
         */
       
    }


session_start();

// Pour tester : https://secure.eedomus.com/sdk/plugins/teslaapp/register.php

$client_id = "ownerapi";

$verifier_bytes = sdk_random_bytes(86);
$code_verifier = trim(strtr(base64_encode($verifier_bytes), "+/", "-_"), "=");

$challenge_bytes = hash("sha256", $code_verifier, true);
$code_challenge = trim(strtr(base64_encode($challenge_bytes), "+/", "-_"), "=");

$_SESSION['code_verifier'] = $code_verifier;
$_SESSION['code_challenge'] = $code_challenge;

//$redirect_uri = 'https://secure.eedomus.com/sdk/plugins/teslaapp/callback.php';
$redirect_uri = 'https://auth.tesla.com/void/callback';

$_SESSION['state'] = md5(uniqid(rand(), TRUE)); //CSRF protection
$_SESSION['attached_controller_id'] = $_GET['attached_controller_id'];

$scope = 'openid email offline_access';

$dialog_url = 'https://auth.tesla.com/oauth2/v3/authorize?client_id='.$client_id.'&redirect_uri='.urlencode($redirect_uri).'&code_challenge'.$code_challenge.'&code_challenge_method=S256&response_type=code&scope='.$scope.'&state='.$_SESSION['state'] ;

header('Location: '.$dialog_url);

//<a href='<? echo $dialog_url; ?>'>register</a>

?>
