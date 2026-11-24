<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\Controller\Quote;

use Grigoli\CheckoutResilience\Controller\Quote\Restore;
use Grigoli\CheckoutResilience\Model\Config;
use Grigoli\CheckoutResilience\Model\Order\PreviousOrderProcessor;
use Grigoli\CheckoutResilience\Model\Resolver\RedirectUrlResolver;
use Grigoli\CheckoutResilience\Model\Validator\OrderEligibilityValidator;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for failure page quote restoration POST controller.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
#[AllowMockObjectsWithoutExpectations]
class RestoreTest extends TestCase
{
    /**
     * @var RequestInterface&MockObject
     */
    private RequestInterface $requestMock;

    /**
     * @var FormKeyValidator&MockObject
     */
    private FormKeyValidator $formKeyValidatorMock;

    /**
     * @var CheckoutSession&MockObject
     */
    private CheckoutSession $checkoutSessionMock;

    /**
     * @var RedirectFactory&MockObject
     */
    private RedirectFactory $resultRedirectFactoryMock;

    /**
     * @var Redirect&MockObject
     */
    private Redirect $resultRedirectMock;

    /**
     * @var ManagerInterface&MockObject
     */
    private ManagerInterface $messageManagerMock;

    /**
     * @var Config&MockObject
     */
    private Config $configMock;

    /**
     * @var RedirectUrlResolver&MockObject
     */
    private RedirectUrlResolver $redirectUrlResolverMock;

    /**
     * @var OrderEligibilityValidator&MockObject
     */
    private OrderEligibilityValidator $orderEligibilityValidatorMock;

    /**
     * @var CartRepositoryInterface&MockObject
     */
    private CartRepositoryInterface $cartRepositoryMock;

    /**
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface $loggerMock;

    /**
     * @var PreviousOrderProcessor&MockObject
     */
    private PreviousOrderProcessor $previousOrderProcessorMock;

    /**
     * @var Restore
     */
    private Restore $controller;

    protected function setUp(): void
    {
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->formKeyValidatorMock = $this->createMock(FormKeyValidator::class);
        $this->checkoutSessionMock = $this->createMock(CheckoutSession::class);
        $this->resultRedirectFactoryMock = $this->createMock(RedirectFactory::class);
        $this->resultRedirectMock = $this->createMock(Redirect::class);
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->configMock = $this->createMock(Config::class);
        $this->redirectUrlResolverMock = $this->createMock(RedirectUrlResolver::class);
        $this->orderEligibilityValidatorMock = $this->createMock(OrderEligibilityValidator::class);
        $this->cartRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->previousOrderProcessorMock = $this->createMock(PreviousOrderProcessor::class);

        $this->resultRedirectFactoryMock->method('create')
            ->willReturn($this->resultRedirectMock);

        $this->controller = new Restore(
            $this->requestMock,
            $this->formKeyValidatorMock,
            $this->checkoutSessionMock,
            $this->resultRedirectFactoryMock,
            $this->messageManagerMock,
            $this->configMock,
            $this->redirectUrlResolverMock,
            $this->orderEligibilityValidatorMock,
            $this->cartRepositoryMock,
            $this->loggerMock,
            $this->previousOrderProcessorMock
        );
    }

    public function testExecuteRedirectsToResolvedDestinationUrlOnSuccess(): void
    {
        $resolvedUrl = 'https://example.test/checkout/#payment';
        $orderMock = $this->createMock(Order::class);

        $this->formKeyValidatorMock->expects($this->once())
            ->method('validate')
            ->with($this->requestMock)
            ->willReturn(true);

        $this->configMock->expects($this->once())
            ->method('isFailurePageButtonEnabled')
            ->willReturn(true);

        $this->checkoutSessionMock->expects($this->once())
            ->method('getLastRealOrder')
            ->willReturn($orderMock);

        $this->orderEligibilityValidatorMock->expects($this->once())
            ->method('isEligible')
            ->with($orderMock)
            ->willReturn(true);

        $this->checkoutSessionMock->expects($this->once())
            ->method('restoreQuote')
            ->willReturn(true);

        $this->previousOrderProcessorMock->expects($this->once())
            ->method('process')
            ->with($orderMock);

        $this->redirectUrlResolverMock->expects($this->once())
            ->method('resolve')
            ->willReturn($resolvedUrl);

        $this->messageManagerMock->expects($this->never())
            ->method('addErrorMessage');

        $this->resultRedirectMock->expects($this->once())
            ->method('setUrl')
            ->with($resolvedUrl)
            ->willReturnSelf();

        $this->assertSame($this->resultRedirectMock, $this->controller->execute());
    }

    public function testExecuteRestoresQuoteByLastQuoteIdWhenNoAssociatedOrderWasCreated(): void
    {
        $resolvedUrl = 'https://example.test/checkout/#payment';
        $emptyOrderMock = $this->createMock(Order::class);
        $emptyOrderMock->method('getEntityId')->willReturn(null);
        $emptyOrderMock->method('getIncrementId')->willReturn(null);
        $quoteMock = $this->createMock(Quote::class);

        $this->formKeyValidatorMock->expects($this->once())
            ->method('validate')
            ->with($this->requestMock)
            ->willReturn(true);

        $this->configMock->expects($this->once())
            ->method('isFailurePageButtonEnabled')
            ->willReturn(true);

        $this->checkoutSessionMock->expects($this->once())
            ->method('getLastRealOrder')
            ->willReturn($emptyOrderMock);

        $this->orderEligibilityValidatorMock->expects($this->once())
            ->method('isEligible')
            ->with($emptyOrderMock)
            ->willReturn(true);

        $this->checkoutSessionMock->expects($this->once())
            ->method('restoreQuote')
            ->willReturn(false);

        $this->checkoutSessionMock->method('__call')
            ->willReturnCallback(
                static fn (string $method): ?int => $method === 'getLastQuoteId' ? 42 : null
            );

        $this->cartRepositoryMock->expects($this->once())
            ->method('get')
            ->with(42)
            ->willReturn($quoteMock);

        $quoteMock->expects($this->once())
            ->method('setIsActive')
            ->with(1)
            ->willReturnSelf();

        $quoteMock->expects($this->once())
            ->method('setReservedOrderId')
            ->with(null)
            ->willReturnSelf();

        $this->cartRepositoryMock->expects($this->once())
            ->method('save')
            ->with($quoteMock);

        $this->checkoutSessionMock->expects($this->once())
            ->method('replaceQuote')
            ->with($quoteMock)
            ->willReturnSelf();

        $this->redirectUrlResolverMock->expects($this->once())
            ->method('resolve')
            ->willReturn($resolvedUrl);

        $this->resultRedirectMock->expects($this->once())
            ->method('setUrl')
            ->with($resolvedUrl)
            ->willReturnSelf();

        $this->assertSame($this->resultRedirectMock, $this->controller->execute());
    }

    public function testExecuteRedirectsToCartWhenOrderIsNotEligibleForRestoration(): void
    {
        $orderMock = $this->createMock(Order::class);

        $this->formKeyValidatorMock->expects($this->once())
            ->method('validate')
            ->with($this->requestMock)
            ->willReturn(true);

        $this->configMock->expects($this->once())
            ->method('isFailurePageButtonEnabled')
            ->willReturn(true);

        $this->checkoutSessionMock->expects($this->once())
            ->method('getLastRealOrder')
            ->willReturn($orderMock);

        $this->orderEligibilityValidatorMock->expects($this->once())
            ->method('isEligible')
            ->with($orderMock)
            ->willReturn(false);

        $this->checkoutSessionMock->expects($this->never())
            ->method('restoreQuote');

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with(__('We could not restore your cart. Please add your items again.'));

        $this->resultRedirectMock->expects($this->once())
            ->method('setPath')
            ->with('checkout/cart')
            ->willReturnSelf();

        $this->assertSame($this->resultRedirectMock, $this->controller->execute());
    }

    public function testExecuteRedirectsToCartWhenFormKeyIsInvalid(): void
    {
        $this->formKeyValidatorMock->expects($this->once())
            ->method('validate')
            ->with($this->requestMock)
            ->willReturn(false);

        $this->configMock->expects($this->never())
            ->method('isFailurePageButtonEnabled');

        $this->checkoutSessionMock->expects($this->never())
            ->method('restoreQuote');

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with(__('Invalid Form Key. Please refresh the page.'));

        $this->resultRedirectMock->expects($this->once())
            ->method('setPath')
            ->with('checkout/cart')
            ->willReturnSelf();

        $this->assertSame($this->resultRedirectMock, $this->controller->execute());
    }

    public function testExecuteRedirectsToCartWhenFeatureIsDisabled(): void
    {
        $this->formKeyValidatorMock->expects($this->once())
            ->method('validate')
            ->with($this->requestMock)
            ->willReturn(true);

        $this->configMock->expects($this->once())
            ->method('isFailurePageButtonEnabled')
            ->willReturn(false);

        $this->checkoutSessionMock->expects($this->never())
            ->method('restoreQuote');

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with(__('Quote restoration is currently disabled.'));

        $this->resultRedirectMock->expects($this->once())
            ->method('setPath')
            ->with('checkout/cart')
            ->willReturnSelf();

        $this->assertSame($this->resultRedirectMock, $this->controller->execute());
    }

    public function testExecuteRedirectsToCartWithErrorMessageWhenRestoreQuoteReturnsFalse(): void
    {
        $orderMock = $this->createMock(Order::class);

        $this->formKeyValidatorMock->expects($this->once())
            ->method('validate')
            ->with($this->requestMock)
            ->willReturn(true);

        $this->configMock->expects($this->once())
            ->method('isFailurePageButtonEnabled')
            ->willReturn(true);

        $this->checkoutSessionMock->expects($this->once())
            ->method('getLastRealOrder')
            ->willReturn($orderMock);

        $this->orderEligibilityValidatorMock->expects($this->once())
            ->method('isEligible')
            ->with($orderMock)
            ->willReturn(true);

        $this->checkoutSessionMock->expects($this->once())
            ->method('restoreQuote')
            ->willReturn(false);

        $this->loggerMock->expects($this->once())
            ->method('warning')
            ->with(
                'Unable to restore quote from checkout failure page: no restorable order or quote found in session.'
            );

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with(__('We could not restore your cart. Please add your items again.'));

        $this->resultRedirectMock->expects($this->once())
            ->method('setPath')
            ->with('checkout/cart')
            ->willReturnSelf();

        $this->assertSame($this->resultRedirectMock, $this->controller->execute());
    }

    public function testExecuteRedirectsToCartWhenFallbackQuoteByLastQuoteIdDoesNotExist(): void
    {
        $emptyOrderMock = $this->createMock(Order::class);
        $emptyOrderMock->method('getEntityId')->willReturn(null);
        $emptyOrderMock->method('getIncrementId')->willReturn(null);

        $this->formKeyValidatorMock->expects($this->once())
            ->method('validate')
            ->with($this->requestMock)
            ->willReturn(true);

        $this->configMock->expects($this->once())
            ->method('isFailurePageButtonEnabled')
            ->willReturn(true);

        $this->checkoutSessionMock->expects($this->once())
            ->method('getLastRealOrder')
            ->willReturn($emptyOrderMock);

        $this->orderEligibilityValidatorMock->expects($this->once())
            ->method('isEligible')
            ->with($emptyOrderMock)
            ->willReturn(true);

        $this->checkoutSessionMock->expects($this->once())
            ->method('restoreQuote')
            ->willReturn(false);

        $this->checkoutSessionMock->method('__call')
            ->willReturnCallback(
                static fn (string $method): ?int => $method === 'getLastQuoteId' ? 42 : null
            );

        $this->cartRepositoryMock->expects($this->once())
            ->method('get')
            ->with(42)
            ->willThrowException(new NoSuchEntityException(__('No such entity with cartId = 42')));

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with(__('We could not restore your cart. Please add your items again.'));

        $this->resultRedirectMock->expects($this->once())
            ->method('setPath')
            ->with('checkout/cart')
            ->willReturnSelf();

        $this->assertSame($this->resultRedirectMock, $this->controller->execute());
    }

    public function testExecuteHandlesUnexpectedExceptionGracefully(): void
    {
        $exception = new \RuntimeException('Database connection error');
        $orderMock = $this->createMock(Order::class);

        $this->formKeyValidatorMock->expects($this->once())
            ->method('validate')
            ->with($this->requestMock)
            ->willReturn(true);

        $this->configMock->expects($this->once())
            ->method('isFailurePageButtonEnabled')
            ->willReturn(true);

        $this->checkoutSessionMock->expects($this->once())
            ->method('getLastRealOrder')
            ->willReturn($orderMock);

        $this->orderEligibilityValidatorMock->expects($this->once())
            ->method('isEligible')
            ->with($orderMock)
            ->willReturn(true);

        $this->checkoutSessionMock->expects($this->once())
            ->method('restoreQuote')
            ->willThrowException($exception);

        $this->loggerMock->expects($this->once())
            ->method('critical')
            ->with(
                'Unexpected error while restoring quote from checkout failure page: Database connection error',
                ['exception' => $exception]
            );

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with(__('An error occurred while restoring your cart. Please try again.'));

        $this->resultRedirectMock->expects($this->once())
            ->method('setPath')
            ->with('checkout/cart')
            ->willReturnSelf();

        $this->assertSame($this->resultRedirectMock, $this->controller->execute());
    }
}
