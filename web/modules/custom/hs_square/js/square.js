(function ($, Drupal, drupalSettings) {

    const appId = drupalSettings.appId;
    const locationId = drupalSettings.locationId;

    async function initializeCard(payments) {
        const card = await payments.card();
        await card.attach('#card-container');
        return card;
    }

// Call this function to send a payment token, buyer name, and other details
// to the project server code so that a payment can be created with
// Payments API
    async function createPayment(token) {

        const givenName = document.querySelector('[name="billing_address[given_name]"]');
        const familyName = document.querySelector('[name="billing_address[family_name]"]');
        let body = {
            sourceId: token,
            given_name: givenName.value,
            family_name: familyName.value,
            address_line1: document.querySelector('[name="billing_address[address_line1]"]').value,
            address_line2: document.querySelector('[name="billing_address[address_line2]"]').value,
            locality: document.querySelector('[name="billing_address[locality]"]').value,
            administrative_area: document.querySelector('[name="billing_address[administrative_area]"]').value,
            postal_code: document.querySelector('[name="billing_address[postal_code]"]').value,
        };

        if(body.given_name === "" || body.family_name === ""){
            givenName.style.borderColor = 'red';
            familyName.style.borderColor = 'red';
            throw new Error('Required field missing');
        }
        body = JSON.stringify(body);
        const paymentResponse = await fetch('/add-card', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body,
        });
        if (paymentResponse.ok) {
            return paymentResponse.json();
        }
        const errorBody = await paymentResponse.text();
        throw new Error(errorBody);
    }

// This function tokenizes a payment method.
// The ‘error’ thrown from this async function denotes a failed tokenization,
// which is due to buyer error (such as an expired card). It is up to the
// developer to handle the error and provide the buyer the chance to fix
// their mistakes.
    async function tokenize(paymentMethod) {
        const tokenResult = await paymentMethod.tokenize();
        if (tokenResult.status === 'OK') {
            return tokenResult.token;
        } else {
            let errorMessage = `Tokenization failed-status: ${tokenResult.status}`;
            if (tokenResult.errors) {
                errorMessage += ` and errors: ${JSON.stringify(
                  tokenResult.errors
                )}`;
            }
            throw new Error(errorMessage);
        }
    }

// Helper method for displaying the Payment Status on the screen.
// status is either SUCCESS or FAILURE;
    function displayPaymentResults(status) {
        const statusContainer = document.getElementById('payment-status-container');
        if (status === 'SUCCESS') {
            statusContainer.innerHTML = '<span>Card added successfully</span>'
            statusContainer.classList.remove('is-failure');
            statusContainer.classList.add('is-success');
            window.location.reload();
        } else {
            statusContainer.innerHTML = '<span>Failed to add card</span>'
            statusContainer.classList.remove('is-success');
            statusContainer.classList.add('is-failure');
        }

        statusContainer.style.visibility = 'visible';
    }


    document.addEventListener('DOMContentLoaded', async function () {
        if (!window.Square) {
            throw new Error('Square.js failed to load properly');
        }
        const payments = window.Square.payments(appId, locationId);
        let card;
        try {
            card = await initializeCard(payments);
        } catch (e) {
            console.error('Initializing Card failed', e);
            return;
        }

        async function handlePaymentMethodSubmission(event, paymentMethod) {
            event.preventDefault();
            try {
                // disable the submit button as we await tokenization and make a
                // payment request.
                cardButton.disabled = true;
                const token = await tokenize(paymentMethod);
                const paymentResults = await createPayment(token);
                displayPaymentResults('SUCCESS');
                console.debug('Card Added Successfully', paymentResults);
            } catch (e) {
                cardButton.disabled = false;
                displayPaymentResults('FAILURE');
                console.error(e.message);
            }
        }

        const cardButton = document.getElementById(
          'card-button'
        );
        cardButton.addEventListener('click', async function (event) {
            await handlePaymentMethodSubmission(event, card);
        });

    });
})(jQuery, Drupal, drupalSettings);