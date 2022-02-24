<!DOCTYPE html>
<html>

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0,user-scalable=0">

    <title>Freelancer Travel</title>
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
            background: #fff;
            padding: 20px 30px;
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
    </style>
</head>

<body class="body">
    <section class="header-top mt-5">
        <div class="container-custom">
            <div class="header-box">
                <div class="logo text-center mb-4">
                    <img src="{{ asset('images/Logo.png') }}" alt="" srcset="">
                </div>
                <div class="agent-box">
                    <h3>Hi {{ @$data->agentUser->name }},</h3>
                    <p>Your customer {{ @$data->products[0]->redeemers[0]->name }} has now successfully paid for booking
                        {{ @$data->bookingReference }}.</p>
                    <p> You should now find this booking in “My Bookings”.</p>
                    <p>We will shortly send your customer an email with their vouchers.</p>
                    <p>We recommend that you check in with your customer to make sure they received their vouchers and
                        all details on the booking are correct.
                    </p>
                </div>
            </div>
        </div>
    </section>
    <section class="voucher">
        <div class="container-custom">
            <div class="voucher-box">
                <h3 class="term-condition">TERMS & CONDITIONS</h3>
                <p class="term-text">These tickets expire on ({{ date("d M Y", strtotime('+1 year',
                    strtotime(@$data->purchaseDate)) ); }})</p>
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
