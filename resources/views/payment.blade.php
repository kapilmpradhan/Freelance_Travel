<!DOCTYPE html>
<html>

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}" />
</head>

<body>
    <div id="loading-wrapper">
        <div id="loading-text">LOADING</div>
        <div id="loading-content"></div>
    </div>
</body>

</html>
<?php
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, "".$order['request_url']."/order/".$order['bookingReference']."/convertQuote");
curl_setopt($curl, CURLOPT_HTTPHEADER, array(
    "Authorization: Bearer ".$token."",
    "Content-Type: application/json",
));
curl_setopt($curl, CURLOPT_POST, 1);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
$result = curl_exec($curl);
$header_data = curl_getinfo($curl);
$url = $order['return_url'];
if ( $order['status'] != 1 ) {
    $rs_url = $url . "?".$order['param']."&error=".$order['message'];
    header("Location:".$rs_url."");
    exit();
}
if ( $header_data['http_code'] == 200 || $header_data['http_code'] == 201 ) {
    switch ($order['slug']) {
        case 'customer':
            $url = "";
            break;
        case 'agent':
            $url = $order['request_url'];
            break;
    }
    $rs_url = $url."?".$order['param'];
    header("Location:".$rs_url."");
    exit();
}
$dataFail = json_decode($result, true);
$rs_url = $url."?".$order['param']."&error=".(($dataFail['error_description']) ? $dataFail['error_description'] : $dataFai['message']);
header("Location:".$rs_url."");
exit();
?>
