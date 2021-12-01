<!DOCTYPE html>
<html>

<head>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,400;0,700;1,500&display=swap"
        rel="stylesheet">
    <title>Freelancer Travel</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <link rel="stylesheet" href=" {{ asset('css/email.css') }}" />
</head>

<body>
    <section class="header-top mt-5">
        <div class="container-custom">
            <div class="header-box">
                <div class="logo text-center mb-4">
                    <img src="{{ asset('images/Logo.png') }}" alt="" srcset="">
                </div>
                <p class="title-voucher ">Booking Reference</p>
                <h1>{{ $data->bookingReference }}</h1>
                <h2 class="date_of_purchase">Date Of Purchase: {{ date("d M Y", strtotime(@$data->purchaseDate)) }}</h2>
            </div>
        </div>
    </section>
    <section class="agent-info">
        <div class="container-custom">
            <div class="agent-box">
                <h3>Hi {{ @$data->products[0]->redeemers[0]->name }},</h3>
                <p>Thank you for your Freelance Travel reservation.</p>
                <p> Please refer to your tickets which are attached to this email. Please read your tickets thoroughly
                    to ensure everything is correct and you understand all additional information relating to your
                    reservation.</p>
                <p>We recommend you re-confirm all of your reservations 48 hours in advance using the phone number
                    listed on each ticket.</p>
                <p>Please contact your Freelance Travel agent <span>{{ @$data->agent->name }}</span> by phoning <span>
                        {{ @$data->agent->phone }} </span> or emailing <span> {{ @$data->agent->email }} </span> if you
                    require any
                    additional information
                    on your reservation.</p>
                <p>We hope you enjoy your travels!</p>
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
    <section class="additional">
        <div class="container-custom">
            <div class="addition-box">
                <div class="row">
                    <div @if(isset($data->products[0]->redeemers[1]))class="col-lg-6" @else class="col-lg-12" @endif>
                        <div class="lead">
                            <h4 class="name">Lead customer</h4>
                            <p>{{ @$data->products[0]->redeemers[0]->name }}</p>
                            <p>{{ @$data->products[0]->redeemers[0]->email }}</p>
                            <p>{{ @$data->products[0]->redeemers[0]->phone }}</p>
                        </div>
                    </div>
                    @if(isset($data->products[0]->redeemers[1]))
                    <div class="col-lg-6">
                        <div class="lead">
                            <h4 class="name">Additional customers</h4>
                            @foreach($data->products[0]->redeemers as $redeemer)
                            <p>{{ $redeemer->name }}</p>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
    <section class="voucher">
        <div class="container-custom">
            @foreach($data->products as $product)
            <div class="voucher-box">
                <h4>Voucher number: {{ $product->voucherNumber }}</h4>
                <h5>{{ $product->name }}</h5>
                <p><span class="font-bold">Fare Type:</span> {{ $product->fareName }}</p>
                <p><span class="font-bold">TourCode:</span>{{ $product->tourCode }}</p>
                <p><span class="font-bold">Quantity of fare type:</span> {{ $product->qty }}</p>
                <p class="customer-detail"><span class="font-bold">Customer Details:</span></p>
                @foreach($product->redeemers as $redeemer )
                <p class="customer-detail">{{ $redeemer->name }}</p>
                @endforeach
                <hr class="mt-4">
                @foreach($product->redeemers as $redeemer )
                @foreach($redeemer->bookingDetails as $bookingDetail )
                <p><span class="font-bold">Booking date:</span> {{ $bookingDetail->travelDate }}</p>
                <p><span class="font-bold">Commencement Time:</span> {{ $bookingDetail->pickupTime }}</p>
                <p> <span class="font-bold">Pick up location</span> {{ $bookingDetail->pickupLocation }}</p>
                @endforeach
                @endforeach
                <p><span class="font-bold">start location:</span> {{ $product->startLocation }}</p>
                <p><span class="font-bold">End location:</span> {{ $product->endLocation }}</p>
                <p class="customer-detail"><span class="font-bold">Operator Booking Reference:</span></p>
                @foreach($product->redeemers as $redeemer )
                @foreach($redeemer->bookingDetails as $bookingDetail )
                <p class="customer-detail">{{ $redeemer->name }} -
                    {{ $bookingDetail->eBookingReferenceId }}</p>
                @endforeach
                @endforeach
                <p><span class="font-bold">Operator Name:</span> {{ @$product->supplier->name }}</p>
                <p class="customer-detail"><span class="font-bold">Operator Phone:</span></p>
                <p class="customer-detail">{{ @$product->supplier->email }}</p>
                <p class="customer-detail">{{ @$product->supplier->phone }}</p>
                <hr>
                <p><span class="font-bold">important infoRmation</span></p>
                <p>{!! @$data->termsAndConditions !!}</p>

            </div>
            @endforeach
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
        <div class="footer-bottom">
            Copyright © Freelance Travel. All rights reserved
        </div>
    </section>
</body>

</html>
