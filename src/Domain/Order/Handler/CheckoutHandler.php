<?php

namespace AppBundle\Domain\Order\Handler;

use AppBundle\Domain\Order\Command\Checkout;
use AppBundle\Domain\Order\Event;
use AppBundle\Payment\Gateway;
use AppBundle\Sylius\Order\OrderInterface;
use SimpleBus\Message\Recorder\RecordsMessages;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

class CheckoutHandler
{
    private $eventRecorder;
    private $orderNumberAssigner;
    private $gateway;

    public function __construct(
        RecordsMessages $eventRecorder,
        OrderNumberAssignerInterface $orderNumberAssigner,
        Gateway $gateway)
    {
        $this->eventRecorder = $eventRecorder;
        $this->orderNumberAssigner = $orderNumberAssigner;
        $this->gateway = $gateway;
    }

    private function getLastPayment(OrderInterface $order): ?PaymentInterface
    {
        if ($payment = $order->getLastPayment(PaymentInterface::STATE_CART)) {

            return $payment;
        }

        if ($payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING)) {

            return $payment;
        }

        return null;
    }

    public function __invoke(Checkout $command)
    {
        $order = $command->getOrder();
        $stripeToken = $command->getStripeToken();

        $payment = $this->getLastPayment($order);

        $isFreeOrder = null === $payment && !$order->isEmpty() && $order->getItemsTotal() > 0 && $order->getTotal() === 0;
        $isCashOnDelivery = null !== $payment && $payment->isCashOnDelivery();
        $isMercadopagoPaymentApproved = null !== $payment && 'approved' === $payment->getMercadopagoPaymentStatus();

        if ($isFreeOrder || $isCashOnDelivery || $isMercadopagoPaymentApproved) {
            $this->orderNumberAssigner->assignNumber($order);
            $this->eventRecorder->record(new Event\CheckoutSucceeded($order, $payment));

            return;
        }

        // TODO Check if $payment !== null

        $data = $command->getData();

        if (is_array($data)) {
            if (isset($data['mercadopagoPaymentMethod'])) {
                $payment->setMercadopagoPaymentMethod($data['mercadopagoPaymentMethod']);
            }
            if (isset($data['mercadopagoInstallments'])) {
                $payment->setMercadopagoInstallments($data['mercadopagoInstallments']);
            }
        }

        try {
            $this->orderNumberAssigner->assignNumber($order);
            $this->gateway->authorize($payment, ['token' => $stripeToken]);
            $this->eventRecorder->record(new Event\CheckoutSucceeded($order, $payment));
        } catch (\Exception $e) {
            $this->eventRecorder->record(new Event\CheckoutFailed($order, $payment, $e->getMessage()));
        }
    }
}
