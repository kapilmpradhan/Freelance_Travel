<!DOCTYPE html>
<html>

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0,user-scalable=0">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,400;0,700;1,500&display=swap"
        rel="stylesheet">
    <title>Freelancer Travel</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <style>
        .body {
            background: #f2f2f2;
            padding-top: 1px;
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
        }

        .agent-box {
            margin-top: 0px;
        }

        .addition-box h3 {
            font-weight: bold;
            font-size: 18px;
            line-height: 23px;
            text-align: center;

            /* Black */

            color: #090A18;
        }

        .addition-box .box-title {
            text-align: left;
            padding-bottom: 15px;
            border-bottom: #f2f2f2 2px solid;
            margin-bottom: 15px;
            font-weight: bold;
        }

        .agent-box p {
            font-style: normal;
            font-weight: normal;
            font-size: 14px;
            line-height: 141.5%;
            text-align: center;
            color: #333333;
        }

        .agent-box .book-now {
            display: block;
            background-color: #31B4B9;
            width: 220px;
            margin: auto;
            margin-top: 35px;
            padding: 10px;
            color: white;
            text-decoration: none;
            text-align: center;
        }

        .lead h4,
        .lead b {
            font-style: normal;
            font-weight: bold;
            font-size: 14px;
            line-height: 21px;
            color: #090A18;
        }

        .lead p {
            font-style: normal;
            font-weight: normal;
            font-size: 13px;
            line-height: 30px;
            color: #333333;
            margin-bottom: 0;
        }

        .addition p {
            font-style: normal;
            font-weight: normal;
            font-size: 13px;
            line-height: 21px;
            color: #333333;
            margin-bottom: 0;
        }

        .lead a {
            font-style: normal;
            font-weight: normal;
            font-size: 16px;
            line-height: 21px;
            color: #333333;
            margin-bottom: 10px;
        }

        .lead span {
            color: #31B4B9;
            font-size: 14px;
            line-height: 21px;
            font-weight: bold;
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
            height: 120px;
        }

        .footer-freelance {
            display: flex;
        }

        .footer-freelance .logo-bottom {
            display: flex;
            height: 60px;
        }

        .footer-right .v349_637 {
            font-style: normal;
            font-weight: normal;
            font-size: 20px;
            line-height: 26px;
            width: 92%;
            color: #20DEE5;
            display: inline-block;
            text-align: right;
            margin-bottom: 10px;
        }

        .footer-left,
        .footer-right {
            width: 50%;
            margin-top: 30px;
        }

        .icon-item {
            text-align: right;
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

        .quote-item {
            padding: 7.5px 0
        }

        .quote-item .quote-image {
            width: 33%;
        }

        .quote-item .quote-image img {
            object-fit: cover;
            width: 160px;
            height: 160px;
        }

        .quote-item .item-content {
            padding: 15px 0
        }

        .quote-item .item-content .content {
            font-weight: bold;
        }

        .quote-item .item-content .content-date {
            color: #31B4B9;
            font-size: 18px;
        }

        .content-date img {
            margin-right: 5px
        }

        .col-lg-5 {
            width: 41%;
        }

        .col-lg-7 {
            width: 49%;
        }
    </style>
</head>

<body class="body">
    <section class="header-top mt-5">
        <div class="container-custom">
            <div class="header-box ">
                <div class="logo text-center">
                    <img src="{{ asset('images/Logo.png') }}" alt="" srcset="">
                </div>
                <div class="agent-box">
                    <h3>Hello {{ @$data->redeemers[0]['firstName'] }} {{ @$data->redeemers[0]['lastName'] }},</h3>
                    <p>Please find the details of your Freelance Travel quote from {{ @$data->agent['firstName'] }} {{
                        @$data->agent['lastName'] }} below.</p>
                    <p>To confirm your quote click ‘BOOK NOW’ to open a secure payment link. Please note that the secure
                        payment link is for one-time use only so please only click the link when you are ready to book.
                        When
                        you have completed payment your booking will be automatically confirmed and your tickets will be
                        emailed to you.</p>
                    <p>If you have any questions or require amendments to the quote, please contact your Freelance
                        Travel
                        Agent {{ @$data->agent['firstName'] }} {{ @$data->agent['lastName'] }} on {{
                        @$data->agent['emailAddress']
                        }}.</p>
                    @if(isset($data->paymentLink))
                    <a class="book-now" href="{{ $data->paymentLink }}">BOOK NOW</a>
                    @endif
                </div>
            </div>
        </div>
    </section>
    <section class="additional ">
        <div class="container-custom">
            <div class="addition-box ">
                <div class="box-title">
                    QUOTE DETAILS
                </div>
                <div class="row ">
                    <div class="col-lg-6 col-12">
                        <div class="lead">
                            <b>{{ @$data->redeemers[0]['firstName'] }} {{ @$data->redeemers[0]['lastName'] }}</b>
                            <p>{{ @$data->redeemers[0]['email'] }}</p>
                            <p>{{ @$data->redeemers[0]['phone'] }}</p>
                        </div>
                        <div class="addition">
                            @if (isset($data->redeemers))
                            @foreach ($data->redeemers as $index => $redeemer)
                            @if($index > 0)
                            <p>{{ @$redeemer['firstName'] }} {{ @$redeemer['lastName'] }}</p>
                            @endif
                            @endforeach
                            @endif
                        </div>
                    </div>

                    <div class="col-lg-6 col-12">
                        <div class="lead ">
                            <div class="row">
                                <div class="col-lg-5">
                                    <b class="name">Quote Reference:</b>
                                </div>
                                <div class="col-lg-7">
                                    <span>{{ @$data->bookingReference }}</span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-5">
                                    <p class="name">Number of Items:</p>
                                </div>
                                <div class="col-lg-7">
                                    <p>{{ @$data->quantity }} {{ @$data->quantity === 1 ? "item" : "items"}}</p>
                                </div>
                            </div>
                            <div class="row">

                                <div class="col-lg-5">
                                    <b class="name">Total Price:</b>
                                </div>
                                <div class="col-lg-7">
                                    <span>{{ @$data->totalCharged }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>
    <section class="additional ">
        <div class="container-custom">
            <div class="addition-box ">
                <div class="box-title">
                    QUOTE INCLUSIONS
                </div>
                @if(isset($data->products))
                @foreach($data->products as $product)
                <div class="quote-item">
                    <div class="row">
                        <div class="quote-image"> <img src="{{ @$product['tour']['productImagePath'] }}" alt=""></div>
                        <div class="col-lg-6">
                            <div class="item-content">
                                <p class="content">
                                    {{ @$product['fareName'] }}
                                </p>
                                <p class="content-date">
                                    <img src="{{ asset('images/fi_calendar.png') }}" alt="" />
                                    {{ @$product['date'] }}
                                </p>
                                <hr />
                                <p class="content">total: {{ @$product['quantity'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
                @endif
            </div>
        </div>
    </section>
    <section class="voucher">
        <div class="container-custom">
            <div class="voucher-box ">
                <a href="https://freelance-travel.com/booking-terms-and-conditions">
                    <h3 class="term-condition">TERMS & CONDITIONS</h3>
                </a>
                <p class="term-text">These tickets expire on (
                    {{ date("d M Y", strtotime('+1 year', strtotime(@$data->purchaseDate))) }}
                    )</p>
            </div>
        </div>
    </section>
    <section class="footer mt-5">
        <div class="footer-top">
            <div class="container-custom">
                <div class="footer-freelance">
                    <div class="footer-left">
                        <div class="logo-bottom">
                            <img src="{{ asset('images/Logo_white.png') }}" alt="" srcset="">
                        </div>
                    </div>
                    <div class="footer-right">
                        <div class="v349_636">
                            <span class="v349_637">Follow us on</span>
                            <div class="footer-icons">
                                <div class="icon-item">
                                    <a href="https://www.facebook.com/freelancetravelinstantbookingsystem"><img
                                            src="{{ asset('images/facebook.png') }}" alt="" srcset=""></a>
                                    <a href="https://www.instagram.com/_freelancetravel"><img
                                            src="{{ asset('images/Group 292.png') }}" alt="" srcset=""></a>
                                    <a href=""><img src="{{ asset('images/brandico_twitter-bird.png') }}" alt=""
                                            srcset=""></a>
                                    <a href="https://www.linkedin.com/company/freelancetravel"><img
                                            src="{{ asset('images/akar-icons_linkedin-fill.png') }}" alt=""
                                            srcset=""></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <p class="footer-bottom">
            Copyright © Freelance Travel. All rights reserved
        </p>
    </section>
</body>

</html>
