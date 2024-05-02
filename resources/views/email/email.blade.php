<!DOCTYPE html>
<html>

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0,user-scalable=0">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,400;0,700;1,500&display=swap"
        rel="stylesheet">
    <title>Freelancer Travel</title>
    <style>
        .body {
            background: #f2f2f2;
            padding-top: 1px;
            font-family: sans-serif;
        }

        .header-box {
            background: #FFFFFF;
            padding: 20px 0;
        }

        .container-custom {
            max-width: 800px;
            margin: auto;
        }

        .header-top h1 {
            font-style: normal;
            font-weight: bold;
            font-size: 28px;
            line-height: 36px;
            text-align: center;
            color: #31B4B9;
        }

        .header-top .title-voucher {
            font-style: normal;
            font-weight: normal;
            font-size: 14px;
            line-height: 18px;
            text-align: center;
            color: #000000;
            font-weight: bold;
        }

        .date_of_purchase {
            font-style: normal;
            font-weight: normal;
            font-size: 14px;
            line-height: 18px;
            text-align: center;
            color: #4F4F4F;
        }

        .agent-box h3,
        .agent-box span {
            font-style: normal;
            font-weight: bold;
            font-size: 16px;
            line-height: 141.5%;
            /* or 23px */
            text-align: center;
            color: #000000;
        }

        .agent-box,
        .addition-box {
            margin-top: 30px;
            background: #fff;
            padding: 30px 30px;
            text-align: center;
        }

        .addition-box h3 {
            font-weight: bold;
            font-size: 18px;
            line-height: 23px;
            text-align: center;

            /* Black */

            color: #090A18;
        }

        .agent-box p {
            font-style: normal;
            font-weight: normal;
            font-size: 16px;
            line-height: 141.5%;
            text-align: center;
            color: #333333;
        }

        .lead h4 {
            font-style: normal;
            font-weight: bold;
            font-size: 16px;
            line-height: 21px;
            color: #090A18;
        }

        .lead p {
            font-style: normal;
            font-weight: normal;
            font-size: 16px;
            line-height: 21px;
            color: #333333;
            margin-bottom: 10px;
        }

        .lead a {
            font-style: normal;
            font-weight: normal;
            font-size: 16px;
            line-height: 21px;
            color: #333333;
            margin-bottom: 10px;
        }

        .voucher-box {
            margin-top: 30px;
            background: #fff;
            padding: 30px 30px;
            text-align: left;
            text-transform: uppercase;
            font-style: normal;
            margin-bottom: 45px
        }

        .voucher-box .text-term {
            text-transform: lowercase;
        }

        .voucher-box .text-term_hyperlink {
            color: #4990ef;
        }

        .voucher-box h4 {
            font-weight: bold;
            font-size: 16px;
            line-height: 160%;
            color: #31B4B9;
        }

        .voucher-box .voucher-head {
            border-bottom: 1px solid #E0E0E0;
        }

        .voucher-box h5 {
            font-weight: bold;
            font-size: 16px;
            line-height: 21px;
            color: #090A18;
        }

        .font-bold {
            font-weight: 600;
        }

        .voucher-box p {
            font-size: 14px;
            line-height: 160%;
            color: #333333;
            font-weight: normal;
        }

        .customer-detail {
            font-size: 14px;
            line-height: 160%;
            color: #333333;
            margin-bottom: 8px;
            font-weight: normal;
        }

        hr {
            color: #E0E0E0;
            width: calc(100%);
            height: 1px;
        }

        .term-condition {
            font-weight: bold;
            font-size: 16px;
            line-height: 160%;
            text-decoration-line: underline;
            color: #090A18;
        }

        .term-text {
            text-transform: none;
        }

        .footer-top {
            background: #090A18;
            padding: 20px;
            /* height: 140px; */
        }

        .footer-freelance {
            /* width: 50%; */
            text-align: center;
            margin: 0 auto;
        }

        .footer-freelance .logo-bottom {}

        .footer-freelance .logo-bottom img {
            width: 100%;
            max-width: 300px;
        }

        .logo img {
            width: 100%;
            max-width: 300px;
        }

        .footer-right .v349_637 {
            font-style: normal;
            font-weight: normal;
            font-size: 20px;
            line-height: 26px;
            width: 92%;
            color: #20DEE5;
            display: inline-block;
            text-align: center;
            margin-bottom: 10px;
        }

        .footer-left,
        .footer-right {
            /* width: 65%;
            margin-top: 20px; */
            text-align: center
        }

        .icon-item {
            text-align: center;
        }

        .icon-item a {
            margin-right: 20px;
        }

        .footer-bottom {
            background: #31B4B9;
            height: 36px;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            line-height: 160%;
            color: #FFFFFF;
            margin: 0;
            padding-top: 10px;
        }

        .voucher-booking-button {
            border: none;
            display: block;
            width: 21%;
            background: rgb(49, 180, 185);
            color: rgb(255, 255, 255) !important;
            text-decoration: none;
            text-align: center;
            font-style: normal;
            font-weight: bold;
            font-size: 16px;
            padding: 20px;
            border-radius: 0px;
            margin: 0 auto;
        }

        .text-center {
            text-align: center;
        }

        .mt-5 {
            margin-top: 2.5em;
        }

        .row {
            display: flex;
        }

        .col-lg-6 {
            width: 50%;
        }

        .col-lg-12 {
            width: 100%;
        }

        .voucher-in {
            margin-left: 20px;
            margin-right: 20px;
        }

        .text-lower {
            text-transform: initial;
        }

        .border-line {
            border-bottom: 1px solid #E0E0E0;
        }
    </style>
</head>

<body class="body">
    <section class="header-top mt-5">
        <div class="container-custom">
            <div class="header-box voucher-in">
                <div class="logo text-center mb-4">
                    <img src="https://confirmation.freelance-travel.com/images/logo-2.png" alt="" srcset="">
                </div>
                <p class="title-voucher ">Booking Reference</p>
                <h1>{{ $data->bookingReference }}</h1>
                <h2 class="date_of_purchase">Date Of Purchase: {{ date('d M Y', strtotime(@$data->purchaseDate)) }}
                </h2>
            </div>
        </div>
    </section>
    <section class="agent-info">
        <div class="container-custom">
            <div class="agent-box">
                <h3>Hi {{ @$data->products[0]->redeemers[0]->name }},</h3>
                <p>Thank you for your Freelance Travel reservation.</p>
                <p> Please refer to your tickets which are attached to this email. Please read your tickets
                    thoroughly
                    to ensure everything is correct and you understand all additional information relating to your
                    reservation.</p>
                <p>We recommend you re-confirm all of your reservations 48 hours in advance using the phone number
                    listed on each ticket.</p>
                <p>Please contact your Freelance Travel agent <span>{{ @$data->agent->name }}</span> by phoning
                    <span>
                        {{ @$data->agent->phone }} </span> or emailing <span> {{ @$data->agent->email }} </span> if
                    you
                    require any
                    additional information
                    on your reservation.
                </p>
                <p>We hope you enjoy your travels!</p>
            </div>
        </div>
    </section>
    <section class="additional ">
        <div class="container-custom">
            <div class="addition-box voucher-in">
                <div class="row ">
                    <div @if (isset($data->products[0]->redeemers[1])) class="col-lg-6 col-12" @else class="col-lg-12" @endif>
                        <div class="lead">
                            <h4 class="name">Lead customer</h4>
                            <p>{{ @$data->products[0]->redeemers[0]->name }}</p>
                            <p>{{ @$data->products[0]->redeemers[0]->email }}</p>
                            <p>{{ @$data->products[0]->redeemers[0]->phone }}</p>
                        </div>
                    </div>
                    @if (isset($data->products[0]->redeemers[1]))
                        <div class="col-lg-6  col-12">
                            <div class="lead">
                                <h4 class="name">Additional customers</h4>
                                @foreach ($data->products[0]->redeemers as $redeemer)
                                    <p>{{ $redeemer->name }}</p>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="additional">
        <div class="container-custom">
            <div class="addition-box">
                <h3>TRAVEL VOUCHERS</h3>
            </div>
        </div>
    </section>
    <section class="voucher">
        <div class="container-custom">
            @foreach ($data->products as $product)
                <div class="voucher-box voucher-in">
                    <div class="voucher-head">
                        <h4>Voucher number: {{ $product->voucherNumber }}</h4>
                        <h5>{{ $product->name }}</h5>
                        <p><span class="font-bold">Fare Type:</span> {{ $product->fareName }}</p>
                        <p><span class="font-bold">TourCode:</span>{{ $product->tourCode }}</p>
                        <p><span class="font-bold">start location:</span> {{ $product->startLocation }}</p>
                        <p><span class="font-bold">End location:</span> {{ $product->endLocation }}</p>
                    </div>
                    <p><span class="font-bold">Quantity of fare type:</span> {{ $product->qty }}</p>
                    <p class="customer-detail"><span class="font-bold">Operator Booking Reference:</span></p>
                    @foreach ($product->redeemers as $redeemer)
                        @foreach ($redeemer->bookingDetails as $bookingDetail)
                            <p class="customer-detail">{{ $redeemer->name }} -
                                {{ @$bookingDetail->eBookingReferenceId }}</p>
                        @endforeach
                        <?php
                        $bookingCustomer = [];
                        foreach ($product->redeemers as $index => $redeemer) {
                            if ($index === 0) {
                                foreach ($redeemer->bookingDetails as $bookingDetail) {
                                    $bookingCustomer[] = [
                                        'eBookingReferenceId' => $bookingDetail->eBookingReferenceId,
                                        'customers' => $redeemer->name,
                                    ];
                                }
                            } else {
                                foreach ($redeemer->bookingDetails as $bookingDetail) {
                                    $key = array_search($bookingDetail->eBookingReferenceId, array_column($bookingCustomer, 'eBookingReferenceId'));
                                    $bookingCustomer[$key]['customers'] = $bookingCustomer[$key]['customers'] . ', ' . $redeemer->name;
                                }
                            }
                        }
                        ?>
                    @endforeach
                    @foreach ($product->redeemers[0]->bookingDetails as $index => $booking)
                        <div class="mt-4 border-line"></div>
                        <p>
                            <span class="font-bold">Customer:</span>
                            {{ $bookingCustomer[$index]['customers'] }}
                        </p>
                        <p>
                            <span class="font-bold">Booking date:</span>
                            {{ date('d M Y', strtotime(@$booking->travelDate) + 3600 * 10) }}
                        </p>
                        @if (isset($booking->commencementTime))
                            <p><span class="font-bold">Commencement Time:</span> {{ @$booking->commencementTime }}</p>
                        @endif

                        @if (isset($booking->pickupLocation))
                            <p> <span class="font-bold">Pick up location:
                                </span> {{ @$booking->pickupTime }} - {{ $booking->pickupLocation }}</p>
                        @endif
                    @endforeach
                    <div class="border-line"></div>
                    <p><span class="font-bold">Operator Name:</span> {{ @$product->supplier->name }}</p>
                    <p class="customer-detail"><span class="font-bold">Operator Phone:</span></p>
                    <p class="customer-detail">{{ @$product->supplier->email }}</p>
                    <p class="customer-detail">{{ @$product->supplier->phone }}</p>
                    <div class="border-line"></div>
                    <p><span class="font-bold">important infoRmation</span></p>
                    <p>{!! $product->instructions !!}</p>
                    @if (isset($product->redeemers[0]->bookingDetails[0]->levy))
                        <p><span class="font-bold">Pay on Arrival:</span>
                            {{ @$product->redeemers[0]->bookingDetails[0]->levy }}
                            x {{ count($product->redeemers) }}</p>
                    @endif
                    <!-- change here important info -->
                    <p>{{ @$product->redeemers[0]->bookingDetails[0]->comments }}</p>
                    <div class="border-line"></div>
                    <p class="text-lower"><span class="font-bold">Note for Operator:</span> This booking was made
                        through
                    </p>
                    <p class="text-lower"><span class="font-bold">WebsiteTravel / Global Travel Marketplace</span> has been paid in full (excluding any potential levies mentioned in the
                        important information above). If you require assistance in claiming this voucher, please contact
                        support@aagtech.io</p>
                </div>
            @endforeach

            <div class="voucher-button">
                <a href="{{ $data->customerOrderPageUrl }}" class="voucher-booking-button">More Booking Details</a>
            </div>

            <div class="voucher-box voucher-in">
                <a href="https://freelance-travel.com/booking-terms-and-conditions">
                    <h3 class="term-condition">TERMS & CONDITIONS</h3>
                </a>
                <p class="text-term">”Please<a class="text-term_hyperlink" href="https://freelance-travel.com/booking-terms-and-conditions"> click here </a>full terms and conditions”</p>
                <p class="term-text">These tickets expire on
                    ({{ date('d M Y', strtotime('+1 year', strtotime(@$data->purchaseDate))) }})
                </p>
            </div>
        </div>
    </section>
    @include('email.footer')
</body>

</html>