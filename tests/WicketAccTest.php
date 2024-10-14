<?php

use PHPUnit\Framework\TestCase;
use WicketAcc\WicketAcc;

// Require our main plugin file
require_once __DIR__ . '/../class-wicket-acc-main.php';

class WicketAccMainTest extends TestCase
{
    protected $wicketAcc;

    protected function setUp(): void
    {
        $this->wicketAcc = new WicketAcc();
    }

    public function testAccIndexSlugs()
    {
        $expected = [
            'en' => 'my-account',
            'fr' => 'mon-compte',
            'es' => 'mi-cuenta',
        ];
        $this->assertEquals($expected, $this->wicketAcc->acc_index_slugs);
    }

    public function testAccWcIndexSlugs()
    {
        $expected = [
            'en' => 'wc-account',
            'fr' => 'wc-compte',
            'es' => 'wc-cuenta',
        ];
        $this->assertEquals($expected, $this->wicketAcc->acc_wc_index_slugs);
    }

    public function testAccPagesMap()
    {
        $expected = [
            'edit-profile' => 'Edit Profile',
            'events' => 'My Events',
            'jobs' => 'My Jobs',
            'job-post' => 'Post a Job',
            'change-password' => 'Change Password',
            'organization-management' => 'Organization Management',
            'acc_global-headerbanner' => 'Global Header-Banner',
            'add-payment-method' => 'Add Payment Method',
            'set-default-payment-method' => 'Set Default Payment Method',
            'orders' => 'Orders',
            'view-order' => 'View Order',
            'downloads' => 'Downloads',
            'edit-account' => 'Edit Account',
            'edit-address' => 'Edit Address',
            'payment-methods' => 'Payment Methods',
            'subscriptions' => 'Subscriptions',
        ];
        $this->assertEquals($expected, $this->wicketAcc->acc_pages_map);
    }

    public function testAccWcEndpoints()
    {
        $expected = [
            'order-pay' => [
                'en' => 'order-pay',
                'fr' => 'ordre-paiement',
                'es' => 'orden-pago',
            ],
            'order-received' => [
                'en' => 'order-received',
                'fr' => 'ordre-recibida',
                'es' => 'orden-recibida',
            ],
            'add-payment-method' => [
                'en' => 'add-payment-method',
                'fr' => 'ajouter-mode-paiement',
                'es' => 'agregar-medio-pago',
            ],
            'set-default-payment-method' => [
                'en' => 'set-default-payment-method',
                'fr' => 'definir-mode-paiement-defaut',
                'es' => 'establecer-medio-pago-principal',
            ],
            'orders' => [
                'en' => 'orders',
                'fr' => 'commandes',
                'es' => 'ordenes',
            ],
            'view-order' => [
                'en' => 'view-order',
                'fr' => 'afficher-commande',
                'es' => 'ver-orden',
            ],
            'downloads' => [
                'en' => 'downloads',
                'fr' => 'telechargements',
                'es' => 'descargas',
            ],
            'edit-account' => [
                'en' => 'edit-account',
                'fr' => 'editer-compte',
                'es' => 'editar-cuenta',
            ],
            'edit-address' => [
                'en' => 'edit-address',
                'fr' => 'editer-adresse',
                'es' => 'editar-dirección',
            ],
            'payment-methods' => [
                'en' => 'payment-methods',
                'fr' => 'modes-de-paiement',
                'es' => 'medios-de-pago',
            ],
            'customer-logout' => [
                'en' => 'customer-logout',
                'fr' => 'deconnexion',
                'es' => 'cerrar-sesion',
            ],
            'subscriptions' => [
                'en' => 'subscriptions',
                'fr' => 'souscriptions',
                'es' => 'suscripciones',
            ],
        ];
        $this->assertEquals($expected, $this->wicketAcc->acc_wc_endpoints);
    }

    public function testAccPreferWcEndpoints()
    {
        $expected = [
            'add-payment-method',
            'payment-methods',
        ];
        $this->assertEquals($expected, $this->wicketAcc->acc_prefer_wc_endpoints);
    }
}
