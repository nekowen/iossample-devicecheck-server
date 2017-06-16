<?php
require_once "../vendor/autoload.php";
use Zenstruck\JWT\Token;
use Zenstruck\JWT\Signer\OpenSSL\ECDSA\ES256;
use \Ramsey\Uuid\Uuid;

//  _POSTからdeviceTokenを受け取り
//	deviceTokenは、DCDevice.current.generateTokenで得たDataをBase64エンコードしたもの
//	https://developer.apple.com/documentation/devicecheck/dcdevice
//	https://developer.apple.com/documentation/devicecheck/accessing_and_modifying_the_per_device_data
//
$deviceToken = (isset($_POST["deviceToken"]) ? $_POST["deviceToken"] : null);

function generateJWT($teamId, $keyId, $privateKeyFilePath) {
    $payload = [
        "iss" => $teamId,
        "iat" => time()
    ];

    $header = [
        "kid" => $keyId
    ];

    $token = new Token($payload, $header);
    return (string)$token->sign(new ES256(), $privateKeyFilePath);
}

function postRequest($url, $jwt, $bodyArray) {
    $body = json_encode($bodyArray);

    $header = [
        "Authorization: Bearer ". $jwt,
        "Content-Type: application/x-www-form-urlencoded",
        "Content-Length: ".strlen($body)
    ];

    $context = [
        "http" => [
            "method"  => "POST",
            "header"  => implode("\r\n", $header),
            "content" => $body
        ]
    ];

    return file_get_contents($url, false, stream_context_create($context));
}

$teamId = "TEAMID";
$keyId = "KEYID";
$privateKeyFilePath = "PRIVATE KEY FILE PATH";
$jwt = generateJWT($teamId, $keyId, $privateKeyFilePath);

//  情報の取得
$body = [
    "device_token" => $deviceToken,
    "transaction_id" => Uuid::uuid4()->toString(),
    "timestamp" => ceil(microtime(true)*1000) // time()だとだめでした
];
postRequest("https://api.development.devicecheck.apple.com/v1/query_two_bits", $jwt, $body);
//  まだ情報を設定していない場合は、"Failed to find bit state"と返されます。
//  設定している場合はこんな形で帰ってきます。
//  {"bit0":true,"bit1":false,"last_update_time":"2017-06"}


//  情報の設定
$body = [
    "device_token" => $deviceToken,
    "transaction_id" => Uuid::uuid4()->toString(),
    "timestamp" => ceil(microtime(true)*1000),
    "bit0" => true,
    "bit1" => false
];

postRequest("https://api.development.devicecheck.apple.com/v1/update_two_bits", $jwt, $body);