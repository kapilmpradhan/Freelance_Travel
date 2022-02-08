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
use App\Jobs\sendMail;
use App\Jobs\sendAgent;
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $bookingOrder->request_url . "/order/" . $order['bookingReference'] . "/convertQuote");
curl_setopt($curl, CURLOPT_HTTPHEADER, array(
    "Authorization: Bearer " . $bookingOrder->accessToken . "",
    "Content-Type: application/json",
));
curl_setopt($curl, CURLOPT_POST, 1);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
$result = curl_exec($curl);
$header_data = curl_getinfo($curl);

$orderDetail = json_decode(getBookingDetail($bookingOrder->request_url, $bookingOrder->order_id, $bookingOrder->accessToken));
if (isset($orderDetail->bookingReference)) {
    $email = @$orderDetail->products[0]->redeemers[0]->email ?: "james.nguyen@adamosoft.com";
    $agentEmail = @$orderDetail->agentUser->email ?: "james.nguyen@adamosoft.com";
    // $agentEmail = "james.nguyen@adamosoft.com";
    dispatch(new sendMail( $email, $orderDetail ));
    dispatch(new sendAgent( $agentEmail, $orderDetail ));
}

switch ($order['slug']) {
    case 'customer':
        $rs_url = $bookingOrder->return_url . "?error=Something when wrong"; // thay reference id
        //redirect other view
        if (isset($orderDetail->bookingReference)) {
            $rs_url = route('bookingDetail', $order['bookingReference']); // thay reference id
        }
        header("Location:" . $rs_url);
        exit();
    case 'agent':
        if ( $order['status'] != 1 || !isset($orderDetail->bookingReference)) {
            $rs_url = $bookingOrder->return_url . "?" . $order['param'] . "&error=" . $order['message'];
            header("Location:" . $rs_url);
            exit();
        }
        if ( $header_data['http_code'] == 200 || $header_data['http_code'] == 201 ) {
            $rs_url = $bookingOrder->return_url . "?" . $order['param'];
            header("Location:{$rs_url}&orderId={$bookingOrder->order_id}&date=" . date("d M Y", strtotime(@$orderDetail->purchaseDate)));
            exit();
        }
        $dataFail = json_decode($result, true);
        $rs_url = $bookingOrder->return_url . "?" . $order['param'] . "&error=" . (@$dataFail['error_description'] ?: $dataFail['message']);
        header("Location:" . $rs_url);
        exit();
}
?>
