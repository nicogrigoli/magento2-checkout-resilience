<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\Model\Validator;

use Grigoli\CheckoutResilience\Model\Validator\OrderEligibilityValidator;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class OrderEligibilityValidatorTest extends TestCase
{
    /**
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface $loggerMock;

    /**
     * @var OrderEligibilityValidator
     */
    private OrderEligibilityValidator $validator;

    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->validator = new OrderEligibilityValidator($this->loggerMock);
    }

    public function testIsEligibleReturnsTrueWhenOrderIsNull(): void
    {
        $this->loggerMock->expects($this->never())
            ->method('warning');

        $this->assertTrue($this->validator->isEligible(null));
    }

    public function testIsEligibleReturnsTrueWhenOrderIsUnloaded(): void
    {
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getEntityId')->willReturn(null);
        $orderMock->method('getIncrementId')->willReturn(null);
        $orderMock->method('getState')->willReturn(null);
        $orderMock->method('hasInvoices')->willReturn(false);

        $this->loggerMock->expects($this->never())
            ->method('warning');

        $this->assertTrue($this->validator->isEligible($orderMock));
    }

    #[DataProvider('allowedOrderStatesDataProvider')]
    public function testIsEligibleReturnsTrueForAllowedOrderStatesWithoutInvoices(string $state): void
    {
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getEntityId')->willReturn(42);
        $orderMock->method('getState')->willReturn($state);
        $orderMock->method('hasInvoices')->willReturn(false);

        $this->loggerMock->expects($this->never())
            ->method('warning');

        $this->assertTrue($this->validator->isEligible($orderMock));
    }

    /**
     * @return array<string, array{state: string}>
     */
    public static function allowedOrderStatesDataProvider(): array
    {
        return [
            'canceled state' => ['state' => Order::STATE_CANCELED],
            'pending_payment state' => ['state' => Order::STATE_PENDING_PAYMENT],
            'pending state' => ['state' => OrderEligibilityValidator::STATE_PENDING],
            'new state (Magento default pending state)' => ['state' => Order::STATE_NEW],
        ];
    }

    #[DataProvider('deniedOrderStatesDataProvider')]
    public function testIsEligibleReturnsFalseAndLogsWarningForDeniedOrderStates(string $state): void
    {
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getEntityId')->willReturn(105);
        $orderMock->method('getIncrementId')->willReturn('000000105');
        $orderMock->method('getQuoteId')->willReturn(42);
        $orderMock->method('getState')->willReturn($state);
        $orderMock->method('hasInvoices')->willReturn(false);

        $this->loggerMock->expects($this->once())
            ->method('warning')
            ->with(
                'Quote restoration denied: order state is not eligible for restoration.',
                [
                    'quote_id' => 42,
                    'order_id' => 105,
                    'increment_id' => '000000105',
                    'state' => $state,
                    'has_invoices' => false,
                    'reason' => 'ineligible_order_state',
                ]
            );

        $this->assertFalse($this->validator->isEligible($orderMock));
    }

    /**
     * @return array<string, array{state: string}>
     */
    public static function deniedOrderStatesDataProvider(): array
    {
        return [
            'processing state' => ['state' => Order::STATE_PROCESSING],
            'complete state' => ['state' => Order::STATE_COMPLETE],
            'closed state' => ['state' => Order::STATE_CLOSED],
            'holded state' => ['state' => Order::STATE_HOLDED],
            'payment_review state' => ['state' => Order::STATE_PAYMENT_REVIEW],
        ];
    }

    #[DataProvider('invoicedOrderStatesDataProvider')]
    public function testIsEligibleReturnsFalseAndLogsWarningWhenOrderHasInvoices(?string $state): void
    {
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getEntityId')->willReturn(null);
        $orderMock->method('getIncrementId')->willReturn('000000105');
        $orderMock->method('getQuoteId')->willReturn(42);
        $orderMock->method('getState')->willReturn($state);
        $orderMock->method('hasInvoices')->willReturn(true);

        $this->loggerMock->expects($this->once())
            ->method('warning')
            ->with(
                'Quote restoration denied: associated order already has invoices.',
                [
                    'quote_id' => 42,
                    'order_id' => '000000105',
                    'increment_id' => '000000105',
                    'state' => $state,
                    'has_invoices' => true,
                    'reason' => 'order_has_invoices',
                ]
            );

        $this->assertFalse($this->validator->isEligible($orderMock));
    }

    /**
     * @return array<string, array{state: ?string}>
     */
    public static function invoicedOrderStatesDataProvider(): array
    {
        return [
            'pending state with invoice' => ['state' => OrderEligibilityValidator::STATE_PENDING],
            'pending_payment state with invoice' => ['state' => Order::STATE_PENDING_PAYMENT],
            'canceled state with invoice' => ['state' => Order::STATE_CANCELED],
            'processing state with invoice' => ['state' => Order::STATE_PROCESSING],
            'complete state with invoice' => ['state' => Order::STATE_COMPLETE],
        ];
    }

    public function testIsEligibleChecksBaseTotalInvoicedForOrderInterfaceImplementation(): void
    {
        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->method('getEntityId')->willReturn(77);
        $orderMock->method('getIncrementId')->willReturn('000000077');
        $orderMock->method('getQuoteId')->willReturn(15);
        $orderMock->method('getState')->willReturn(Order::STATE_PENDING_PAYMENT);
        $orderMock->method('getBaseTotalInvoiced')->willReturn(99.99);

        $this->loggerMock->expects($this->once())
            ->method('warning')
            ->with(
                'Quote restoration denied: associated order already has invoices.',
                [
                    'quote_id' => 15,
                    'order_id' => 77,
                    'increment_id' => '000000077',
                    'state' => Order::STATE_PENDING_PAYMENT,
                    'has_invoices' => true,
                    'reason' => 'order_has_invoices',
                ]
            );

        $this->assertFalse($this->validator->isEligible($orderMock));
    }
}
