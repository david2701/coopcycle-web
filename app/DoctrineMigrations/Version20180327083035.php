<?php declare(strict_types = 1);

namespace Application\Migrations;

use AppBundle\Sylius\Order\AdjustmentInterface;
use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180327083035 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs

        $settingsManager       = $this->container->get('coopcycle.settings_manager');
        $orderRepository       = $this->container->get('sylius.repository.order');
        $taxCategoryRepository = $this->container->get('sylius.repository.tax_category');
        $taxCalculator         = $this->container->get('sylius.tax_calculator');
        $adjustmentFactory     = $this->container->get('sylius.factory.adjustment');

        $defaultTaxCategoryCode = $settingsManager->get('default_tax_category');
        if (!$defaultTaxCategoryCode) {
            $this->write('<comment>Default tax category is not configured</comment>');
            return;
        }

        $taxCategory = $taxCategoryRepository->findOneBy([
            'code' => $defaultTaxCategoryCode
        ]);
        $taxRate = $taxCategory->getRates()->get(0);

        $orders = $orderRepository->findAll();

        foreach ($orders as $order) {
            foreach ($order->getItems() as $orderItem) {
                $taxAdjustment = $adjustmentFactory->createWithData(
                    AdjustmentInterface::TAX_ADJUSTMENT,
                    $taxRate->getName(),
                    (int) $taxCalculator->calculate($orderItem->getTotal(), $taxRate),
                    $neutral = true
                );

                $this->addSql('INSERT INTO sylius_adjustment (order_item_id, type, label, amount, is_neutral, is_locked, created_at, updated_at) VALUES (:order_item_id, :type, :label, :amount, :is_neutral, :is_locked, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [
                    'order_item_id' => $orderItem->getId(),
                    'type' => $taxAdjustment->getType(),
                    'label' => $taxAdjustment->getLabel(),
                    'amount' => $taxAdjustment->getAmount(),
                    'is_neutral' => $taxAdjustment->isNeutral() ? 't' : 'f',
                    'is_locked' => $taxAdjustment->isLocked() ? 't' : 'f',
                ]);
            }
        }
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
