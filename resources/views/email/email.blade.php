<!DOCTYPE html>
<html>
    <head>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,400;0,700;1,500&display=swap" rel="stylesheet">
        <title>Freelancer Travel</title>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
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
                    <h1>bookingReference</h1>
                    <h2 class="date_of_purchase">Date Of Purchase: {purchaseDate}</h2>
                </div>
            </div>
        </section>
        <section class="agent-info">
            <div class="container-custom">
                <div class="agent-box">
                    <h3>Hi {products.redeemers.name},</h3>
                    <p>Thank you for your Freelance Travel reservation.</p>
                    <p> Please refer to your tickets which are attached to this email. Please read your tickets thoroughly to ensure everything is correct and you understand all additional information relating to your reservation.</p>
                    <p>We recommend you re-confirm all of your reservations 48 hours in advance using the phone number listed on each ticket.</p>
                    <p>Please contact your Freelance Travel agent <span>{ agent name }</span> by phoning <span>{ agent phone}</span>  or emailing <span>{agent email}</span> if you require any additional information on your reservation.</p>
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
                        <div class="col-lg-6">
                            <div class="lead">
                                <h4 class="name">Lead customer</h4>
                                <p>{products.redeemers.name}</p>
                                <p>{products.redeemers.email}</p>
                                <p>{products.redeemers.phone}</p>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="lead">
                                <h4 class="name">Additional customers</h4>
                                <p>{products.redeemers.name}</p>
                                <p>{products.redeemers.name}</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
        <section class="voucher">
            <div class="container-custom">
                <div class="voucher-box">
                    <h4>Voucher number: {VOUCHERNUMBER}</h4>
                    <h5>{products.name}</h5>
                    <p><span class="font-bold">Fare Type:</span> {products.fareName}</p>
                    <p><span class="font-bold">TourCode:</span>{products.tourcode}</p>
                    <p><span class="font-bold">Quantity of fare type:</span> {products.qty}</p>
                    <p class="customer-detail"><span class="font-bold">Customer Details:</span></p>
                    <p class="customer-detail">{products.redeemers.name}</p>
                    <p class="customer-detail">{products.redeemers.name}</p>
                    <p class="customer-detail mb-4">{products.redeemers.name}</p>
                    <hr>
                    <p><span class="font-bold">Booking date:</span> {products.bookingdetails.travelDate}</p>
                    <p><span class="font-bold">Commencement Time:</span> {products.bookingdetails.pickupTime}</p>
                    <p><span class="font-bold">Pick up location</span><br/>
                    {products.bookingdetails.travelDate}</p>
                    <p><span class="font-bold">start location:</span> {products.startlocation}</p>
                    <p><span class="font-bold">End location:</span> {products.endLocation}</p>
                    <p class="customer-detail"><span class="font-bold">Operator Booking Reference:</span></p>
                    <p class="customer-detail">{products.redeemers.name} - {products.redeemersbookingdetails.ebookingReferenceId}</p>
                    <p class="customer-detail">{products.redeemers.name} - {products.redeemersbookingdetails.ebookingReferenceId}</p>
                    <p class="customer-detail mb-4">{products.redeemers.name} - {products.redeemersbookingdetails.ebookingReferenceId}</p>
                    <p><span class="font-bold">Operator Name:</span> {products.supplier.name}</p>
                    <p class="customer-detail"><span class="font-bold">Operator Phone:</span></p>
                    <p class="customer-detail">{products.supplier.EMAIL}</p>
                    <p class="customer-detail">{products.supplier.PHONE}</p>
                    <hr>
                    <p><span class="font-bold">important infoRmation</span></p>
                    <p>{products.instructions}</p>

                </div>
                <div class="voucher-box">
                    <h4>Voucher number: {VOUCHERNUMBER}</h4>
                    <h5>{products.name}</h5>
                    <p><span class="font-bold">Fare Type:</span> {products.fareName}</p>
                    <p><span class="font-bold">TourCode:</span>{products.tourcode}</p>
                    <p><span class="font-bold">Quantity of fare type:</span> {products.qty}</p>
                    <p class="customer-detail"><span class="font-bold">Customer Details:</span></p>
                    <p class="customer-detail">{products.redeemers.name}</p>
                    <p class="customer-detail">{products.redeemers.name}</p>
                    <p class="customer-detail mb-4">{products.redeemers.name}</p>
                    <hr>
                    <p><span class="font-bold">Booking date:</span> {products.bookingdetails.travelDate}</p>
                    <p><span class="font-bold">Commencement Time:</span> {products.bookingdetails.pickupTime}</p>
                    <p><span class="font-bold">Pick up location</span><br/>
                    {products.bookingdetails.travelDate}</p>
                    <p><span class="font-bold">start location:</span> {products.startlocation}</p>
                    <p><span class="font-bold">End location:</span> {products.endLocation}</p>
                    <p class="customer-detail"><span class="font-bold">Operator Booking Reference:</span></p>
                    <p class="customer-detail">{products.redeemers.name} - {products.redeemersbookingdetails.ebookingReferenceId}</p>
                    <p class="customer-detail">{products.redeemers.name} - {products.redeemersbookingdetails.ebookingReferenceId}</p>
                    <p class="customer-detail">{products.redeemers.name} - {products.redeemersbookingdetails.ebookingReferenceId}</p>
                    <p><span class="font-bold">Operator Name:</span> {products.supplier.name}</p>
                    <p class="customer-detail"><span class="font-bold">Operator Phone:</span></p>
                    <p class="customer-detail">{products.supplier.EMAIL}</p>
                    <p class="customer-detail">{products.supplier.PHONE}</p>
                    <hr>
                    <p><span class="font-bold">important infoRmation</span></p>
                    <p>{products.instructions}</p>
                </div>
                <div class="voucher-box">
                    <h3 class="term-condition">TERMS & CONDITIONS</h3>
                    <p class="term-text">These tickets expire on ( {purchaseDate}+1year)</p>
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
                                        <a href="https://www.facebook.com/freelancetravelinstantbookingsystem"><img src="{{ asset('images/facebook.png') }}" alt="" srcset=""></a>
                                        <a href="https://www.instagram.com/_freelancetravel"><img src="{{ asset('images/Group 292.png') }}" alt="" srcset=""></a>
                                        <a href=""><img src="{{ asset('images/brandico_twitter-bird.png') }}" alt="" srcset=""></a>
                                        <a href="https://www.linkedin.com/company/freelancetravel"><img src="{{ asset('images/akar-icons_linkedin-fill.png') }}" alt="" srcset=""></a>
                                    </div>
                                </div>
                              </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                Copyright © Freelance Travel.  All rights reserved
            </div>
        </section>
    </body>
</html>
