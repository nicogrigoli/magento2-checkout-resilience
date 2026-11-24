<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\Model\Order;

use Grigoli\CheckoutResilience\Model\Config;
use Grigoli\CheckoutResilience\Model\Order\PreviousOrderProcessor;
use Grigoli\CheckoutResilience\Model\Validator\OrderEligibilityValidator;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderStatusHistoryInterface;
use Magento\Sales\Api\Data\OrderStatusHistoryInterfaceFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for PreviousOrderProcessor order cancellation and audit history comment logging.
 */
#[AllowMockObjectsWithoutExpectations]
class PreviousOrderProcessorTest extends TestCase
{
    /**
     * @var Config&MockObject
     */
    private Config $configMock;

    /**
     * @var OrderManagementInterface&MockObject
     */
    private OrderManagementInterface $orderManagementMock;

    /**
     * @var OrderStatusHistoryInterfaceFactory&MockObject
     */
    private OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactoryMock;

    /**
     * @var OrderStatusHistoryInterface&MockObject
     */
    private OrderStatusHistoryInterface $statusHistoryMock;

    /**
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface $loggerMock;

    /**
     * @var PreviousOrderProcessor
     */
    private PreviousOrderProcessor $processor;

    protected function setUp(): void
    {
        $this->configMock = $this->createMock(Config::class);
        $this->orderManagementMock = $this->createMock(OrderManagementInterface::class);
        $this->orderStatusHistoryFactoryMock = $this->createMock(OrderStatusHistoryInterfaceFactory::class);
        $this->statusHistoryMock = $this->createMock(OrderStatusHistoryInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->orderStatusHistoryFactoryMock->method('create')
            ->willReturn($this->statusHistoryMock);

        $this->processor = new PreviousOrderProcessor(
            $this->configMock,
            $this->orderManagementMock,
            $this->orderStatusHistoryFactoryMock,
            $this->loggerMock
        );
    }

    public function testProcessDoesNothingWhenOrderIsNull(): void
    {
        $this->configMock->expects($this->never())
            ->method('isCancelPreviousOrderEnabled');

        $this->orderManagementMock->expects($this->never())
            ->method('cancel');

        $this->orderManagementMock->expects($this->never())
            ->method('addComment');

        $this->processor->process(null);
    }

    public function testProcessDoesNothingWhenOrderHasNoEntityId(): void
    {
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getEntityId')->willReturn(null);

        $this->configMock->expects($this->never())
            ->method('isCancelPreviousOrderEnabled');

        $this->orderManagementMock->expects($this->never())
            ->method('cancel');

        $this->orderManagementMock->expects($this->never())
            ->method('addComment');

        $this->processor->process($orderMock);
    }

    #[DataProvider('openOrderStatesDataProvider')]
    public function testProcessCancelsOpenCancelableOrderAndAddsAuditCommentWhenConfigIsEnabled(
        string $state
    ): void {
        $orderId = 105;
        $incrementId = '000000105';
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getEntityId')->willReturn($orderId);
        $orderMock->method('getIncrementId')->willReturn($incrementId);
        $orderMock->method('getState')->willReturn($state);
        $orderMock->expects($this->once())
            ->method('canCancel')
            ->willReturn(true);

        $this->configMock->expects($this->once())
            ->method('isCancelPreviousOrderEnabled')
            ->willReturn(true);

        $this->orderManagementMock->expects($this->once())
            ->method('cancel')
            ->with($orderId)
            ->willReturn(true);

        $this->statusHistoryMock->expects($this->once())
            ->method('setComment')
            ->with(PreviousOrderProcessor::AUDIT_COMMENT_CANCELED_AND_RESTORED)
            ->willReturnSelf();

        $this->statusHistoryMock->expects($this->once())
            ->method('setIsCustomerNotified')
            ->with(0)
            ->willReturnSelf();

        $this->statusHistoryMock->expects($this->once())
            ->method('setIsVisibleOnFront')
            ->with(0)
            ->willReturnSelf();

        $this->statusHistoryMock->expects($this->once())
            ->method('setEntityName')
            ->with(Order::ENTITY)
            ->willReturnSelf();

        $this->orderManagementMock->expects($this->once())
            ->method('addComment')
            ->with($orderId, $this->statusHistoryMock)
            ->willReturn(true);

        $this->loggerMock->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(
                function (string $message, array $context) use ($orderId, $incrementId): void {
                    $this->assertSame(
                        [
                            'order_id' => $orderId,
                            'increment_id' => $incrementId,
                        ],
                        $context
                    );
                    $this->assertContains(
                        $message,
                        [
                            'Previous pending order automatically canceled upon quote restoration.',
                            'Internal audit comment added to previous order upon quote restoration.',
                        ]
                    );
                }
            );

        $this->processor->process($orderMock);
    }

    /**
     * @return array<string, array{state: string}>
     */
    public static function openOrderStatesDataProvider(): array
    {
        return [
            'pending state' => ['state' => OrderEligibilityValidator::STATE_PENDING],
            'pending_payment state' => ['state' => Order::STATE_PENDING_PAYMENT],
            'new state' => ['state' => Order::STATE_NEW],
            'uppercase pending_payment state' => ['state' => 'PENDING_PAYMENT'],
        ];
    }

    public function testProcessDoesNotCancelOpenOrderWhenConfigIsDisabledButStillAddsAuditComment(): void
    {
        $orderId = 106;
        $incrementId = '000000106';
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getEntityId')->willReturn($orderId);
        $orderMock->method('getIncrementId')->willReturn($incrementId);
        $orderMock->method('getState')->willReturn(Order::STATE_PENDING_PAYMENT);

        $this->configMock->expects($this->once())
            ->method('isCancelPreviousOrderEnabled')
            ->willReturn(false);

        $orderMock->expects($this->never())
            ->method('canCancel');

        $this->orderManagementMock->expects($this->never())
            ->method('cancel');

        $this->statusHistoryMock->expects($this->once())
            ->method('setComment')
            ->with(PreviousOrderProcessor::AUDIT_COMMENT_RESTORED)
            ->willReturnSelf();

        $this->statusHistoryMock->expects($this->once())
            ->method('setIsCustomerNotified')
            ->with(0)
            ->willReturnSelf();

        $this->statusHistoryMock->expects($this->once())
            ->method('setIsVisibleOnFront')
            ->with(0)
            ->willReturnSelf();

        $this->statusHistoryMock->expects($this->once())
            ->method('setEntityName')
            ->with(Order::ENTITY)
            ->willReturnSelf();

        $this->orderManagementMock->expects($this->once())
            ->method('addComment')
            ->with($orderId, $this->statusHistoryMock)
            ->willReturn(true);

        $this->loggerMock->expects($this->once())
            ->method('info')
            ->with(
                'Internal audit comment added to previous order upon quote restoration.',
                [
                    'order_id' => $orderId,
                    'increment_id' => $incrementId,
                ]
            );

        $this->processor->process($orderMock);
    }

    public function testProcessDoesNotCancelAlreadyCanceledOrderAndAddsInternalAuditComment(): void
    {
        $orderId = 107;
        $incrementId = '000000107';
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getEntityId')->willReturn($orderId);
        $orderMock->method('getIncrementId')->willReturn($incrementId);
        $orderMock->method('getState')->willReturn(Order::STATE_CANCELED);

        $this->configMock->expects($this->once())
            ->method('isCancelPreviousOrderEnabled')
            ->willReturn(true);

        $this->orderManagementMock->expects($this->never())
            ->method('cancel');

        $this->statusHistoryMock->expects($this->once())
            ->method('setComment')
            ->with(PreviousOrderProcessor::AUDIT_COMMENT_RESTORED)
            ->willReturnSelf();

        $this->statusHistoryMock->expects($this->once())
            ->method('setIsCustomerNotified')
            ->with(0)
            ->willReturnSelf();

        $this->statusHistoryMock->expects($this->once())
            ->method('setIsVisibleOnFront')
            ->with(0)
            ->willReturnSelf();

        $this->statusHistoryMock->expects($this->once())
            ->method('setEntityName')
            ->with(Order::ENTITY)
            ->willReturnSelf();

        $this->orderManagementMock->expects($this->once())
            ->method('addComment')
            ->with($orderId, $this->statusHistoryMock)
            ->willReturn(true);

        $this->processor->process($orderMock);
    }

    public function testProcessDoesNotCancelOpenOrderWhenCanCancelReturnsFalse(): void
    {
        $orderId = 108;
        $incrementId = '000000108';
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getEntityId')->willReturn($orderId);
        $orderMock->method('getIncrementId')->willReturn($incrementId);
        $orderMock->method('getState')->willReturn(Order::STATE_PENDING_PAYMENT);
        $orderMock->expects($this->once())
            ->method('canCancel')
            ->willReturn(false);

        $this->configMock->expects($this->once())
            ->method('isCancelPreviousOrderEnabled')
            ->willReturn(true);

        $this->orderManagementMock->expects($this->never())
            ->method('cancel');

        $this->statusHistoryMock->expects($this->once())
            ->method('setComment')
            ->with(PreviousOrderProcessor::AUDIT_COMMENT_RESTORED)
            ->willReturnSelf();

        $this->orderManagementMock->expects($this->once())
            ->method('addComment')
            ->with($orderId, $this->statusHistoryMock)
            ->willReturn(true);

        $this->processor->process($orderMock);
    }

    public function testProcessCancelsOrderInterfaceInstanceWithoutCanCancelMethodWhenInOpenState(): void
    {
        $orderId = 109;
        $incrementId = '000000109';
        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->method('getEntityId')->willReturn($orderId);
        $orderMock->method('getIncrementId')->willReturn($incrementId);
        $orderMock->method('getState')->willReturn(Order::STATE_PENDING_PAYMENT);

        $this->configMock->expects($this->once())
            ->method('isCancelPreviousOrderEnabled')
            ->willReturn(true);

        $this->orderManagementMock->expects($this->once())
            ->method('cancel')
            ->with($orderId)
            ->willReturn(true);

        $this->statusHistoryMock->expects($this->once())
            ->method('setComment')
            ->with(PreviousOrderProcessor::AUDIT_COMMENT_CANCELED_AND_RESTORED)
            ->willReturnSelf();

        $this->orderManagementMock->expects($this->once())
            ->method('addComment')
            ->with($orderId, $this->statusHistoryMock)
            ->willReturn(true);

        $this->processor->process($orderMock);
    }

    public function testProcessLogsErrorWhenCancelThrowsExceptionAndStillAddsAuditComment(): void
    {
        $orderId = 110;
        $incrementId = '000000110';
        $exception = new \RuntimeException('Unable to cancel order');
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getEntityId')->willReturn($orderId);
        $orderMock->method('getIncrementId')->willReturn($incrementId);
        $orderMock->method('getState')->willReturn(Order::STATE_PENDING_PAYMENT);
        $orderMock->method('canCancel')->willReturn(true);

        $this->configMock->expects($this->once())
            ->method('isCancelPreviousOrderEnabled')
            ->willReturn(true);

        $this->orderManagementMock->expects($this->once())
            ->method('cancel')
            ->with($orderId)
            ->willThrowException($exception);

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with(
                'Failed to automatically cancel previous order upon quote restoration: Unable to cancel order',
                [
                    'order_id' => $orderId,
                    'increment_id' => $incrementId,
                    'exception' => $exception,
                ]
            );

        $this->statusHistoryMock->expects($this->once())
            ->method('setComment')
            ->with(PreviousOrderProcessor::AUDIT_COMMENT_RESTORED)
            ->willReturnSelf();

        $this->orderManagementMock->expects($this->once())
            ->method('addComment')
            ->with($orderId, $this->statusHistoryMock)
            ->willReturn(true);

        $this->processor->process($orderMock);
    }

    public function testProcessLogsErrorWhenAddCommentThrowsException(): void
    {
        $orderId = 111;
        $incrementId = '000000111';
        $exception = new \RuntimeException('Database write failed');
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getEntityId')->willReturn($orderId);
        $orderMock->method('getIncrementId')->willReturn($incrementId);
        $orderMock->method('getState')->willReturn(Order::STATE_CANCELED);

        $this->configMock->expects($this->once())
            ->method('isCancelPreviousOrderEnabled')
            ->willReturn(true);

        $this->orderManagementMock->expects($this->once())
            ->method('addComment')
            ->with($orderId, $this->statusHistoryMock)
            ->willThrowException($exception);

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with(
                'Failed to add internal audit comment to previous order upon quote restoration: Database write failed',
                [
                    'order_id' => $orderId,
                    'increment_id' => $incrementId,
                    'exception' => $exception,
                ]
            );

        $this->processor->process($orderMock);
    }
}
