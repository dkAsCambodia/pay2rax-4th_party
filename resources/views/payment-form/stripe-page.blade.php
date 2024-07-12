<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src='https://js.stripe.com/v2/' type='text/javascript'></script>
<form action="{{ route('Stripe: checkoutForm') }}" method="POST" class="StripePayment-form"
data-stripe-publishable-key="pk_test_51OmTf0KyHXidWMBJVXFlR4ui5cP3aZWIjaUiSCUD4acUcd0LdmYj8vd9afc9DV6pReTtKtf6ge2lgpe8MQvEsn060079WqGxO0">
    @csrf
    @foreach ($res as $key => $item)
        <input type="hidden" name="{{ $key }}" class="{{ $key }}" value="{{ $item }}">
    @endforeach
    <input style="display:none" type="submit" class="card-btn">

</form>

<script>
    window.onload = function() {
        $(".card-btn").trigger('click');
    }

        // checking card details valid or not START
        $(document).ready(function() {
            $('.card-btn').click(function(e) {
                var $form = $(".StripePayment-form");
                var expirationValue = $('.expiration').val();
                var card_month = expirationValue.substr(0, 2); // Extract the first two digits as month
                var card_year = expirationValue.substr(3, 2);
                e.preventDefault();
                Stripe.setPublishableKey($form.data('stripe-publishable-key'));
                Stripe.createToken({
                    number: $('.card_number').val(),
                    cvc: $('.cvv').val(),
                    exp_month: card_month,
                    exp_year: card_year
                }, stripeResponseHandler);
            });

            function stripeResponseHandler(status, response) {
                if (response.error) {
                    return response.error.message;
                } else {
                    // alert("correct");
                }
            }
        })
        // checking card details valid or not END
        // Generate StripeToken and call ajax to hit stripe payment gateway START
        $(document).ready(function() {
            var csrfToken = $('meta[name="csrf-token"]').attr('content');
            console.log("CSRF Token: " + csrfToken); // Log the CSRF token to verify it's correct

            $('.card-btn').click(function(e) {
                var $form = $(".StripePayment-form");
                // $form.on('submit', function(e) {

                    var expirationValue = $('.expiration').val();
                    var card_month = expirationValue.substr(0, 2); // Extract the first two digits as month
                    var card_year = expirationValue.substr(3, 2);

                    e.preventDefault();
                    Stripe.setPublishableKey($form.data('stripe-publishable-key'));
                    Stripe.createToken({
                        number: $('.card_number').val(),
                        cvc: $('.cvv').val(),
                        exp_month: card_month,
                        exp_year: card_year
                    }, stripeResponseHandler);
                // });
            });

            function stripeResponseHandler(status, response) {
                if (response.error) {
                    return response.error.message;
                } else {
                    // token contains id, last4, and card type
                    var token = response['id'];
                    // insert the token into the form so it gets submitted to the server
                    var $form = $(".StripePayment-form");
                    $form.append("<input type='hidden' name='stripeToken' value='" + token + "'/>");
                    // $form.get(0).submit();
                    var str = $(".StripePayment-form").serializeArray();

                    $.ajax({
                        url: "{{ route('Stripe: checkoutForm') }}",
                        type: "POST",
                        data: str,
                        success: function(response) {
                            console.log("Response: " + response);
                            document.write("Response : " + response);
                        // </script>
                       
                        // <?php 
                        //     foreach ($res as $key => $item) {
                        //         echo $key . " : " . $item . "<br/>";
                        //     }
                        // ?>
                        // <script>
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.log('AJAX request failed: ' + textStatus + ', ' + errorThrown);
                        }
                    });

                }
            }
        })
        // Generate StripeToken and call ajax to hit stripe payment gateway END
</script>
