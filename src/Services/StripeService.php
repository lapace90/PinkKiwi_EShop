<?php


namespace App\Services;

use Stripe\Stripe;
use Stripe\Exception\ApiErrorException;
use Stripe\Checkout\Session;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class StripeService extends AbstractController
{

    public function createCheckoutSession(array $lineItems): Session
    {
        $stripeProducts = $this->prepareStripeProducts($lineItems);
        // dd($stripeProducts);
        $YOUR_DOMAIN = $_SERVER['HTTP_ORIGIN'];
        $stripeSecretKey = $this->getParameter('STRIPE_KEY');
        Stripe::setApiKey($stripeSecretKey);

        return Session::create([
            'line_items' => $stripeProducts,
            'mode' => 'payment',
            'success_url' => $YOUR_DOMAIN . '/account/order/thanks/{CHECKOUT_SESSION_ID}',
            'cancel_url' => $YOUR_DOMAIN . '/account/order/error/{CHECKOUT_SESSION_ID}',
        ]);
        
    }

    private function prepareStripeProducts(array $cartComplete): array
    {
        $stripeProducts = [];

        foreach ($cartComplete as $product) {
            $stripeProducts[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $product['product']->getName(),
                        'images' => [$product['product']->getPicture()]
                    ],
                    'unit_amount' => $product['product']->getPrice(),
                ],
                'quantity' => $product['quantity'],
            ];
        }

        return $stripeProducts;
    }
}
