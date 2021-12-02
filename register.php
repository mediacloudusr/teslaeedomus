<?
# This file is part of Tesla Car Plugin for Eedomus <https://github.com/mediacloudusr/teslaeedomus>.
# It connects to the Tesla API and reports back data to Eedomus.
# Copyright (C) 2021 mediacloudusr
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with This program. If not, see <http://www.gnu.org/licenses/>.

function sdk_random_bytes($bytes)
{
    $buf = '';
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

// $verifier_bytes = sdk_random_bytes(86);
// $code_verifier = trim(strtr(base64_encode($verifier_bytes), "+/", "-_"), "=");

$code_verifier = 'MTI4NTQyMzc2NjFhNTUzMGQ4YTQ2NjkuNjQzMzM1MjEzNTc5OTMxOTI2MWE1NTMwZDhhNGEwNC4yOTA5NTA0MTIwMjM0ODEwMzE2MWE1NTMwZDhhNGI';
$controller_id = $_GET['attached_controller_id'];

// let's make the code verifier unique using the controller id but it can be guessed by the main script 
$code_verifier = $controller_id . substr($code_verifier, 0, 86 - strlen($controller_id));

$challenge_bytes = hash("sha256", $code_verifier, true);
$code_challenge = trim(strtr(base64_encode($challenge_bytes), "+/", "-_"), "=");

$_SESSION['code_verifier'] = $code_verifier;
$_SESSION['code_challenge'] = $code_challenge;

//$redirect_uri = 'https://secure.eedomus.com/sdk/plugins/teslaapp/callback.php';

// Tesla API does not support another URL.
$redirect_uri = 'https://auth.tesla.com/void/callback';

$_SESSION['state'] = md5(uniqid(rand(), TRUE)); //CSRF protection
$_SESSION['attached_controller_id'] = $_GET['attached_controller_id'];

$scope = 'openid email offline_access';

$dialog_url = 'https://auth.tesla.com/oauth2/v3/authorize?client_id=' . $client_id . '&redirect_uri=' . urlencode($redirect_uri) . '&code_challenge' . $code_challenge . '&code_challenge_method=S256&response_type=code&scope=' . $scope . '&state=' . $_SESSION['state'];

header('Location: ' . $dialog_url);

//<a href='<? echo $dialog_url; 
?>'>register</a>

?>