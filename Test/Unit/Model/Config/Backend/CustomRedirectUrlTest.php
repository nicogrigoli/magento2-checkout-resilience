<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\Model\Config\Backend;

use Grigoli\CheckoutResilience\Model\Config\Backend\CustomRedirectUrl;
use Grigoli\CheckoutResilience\Model\Config\Source\RedirectDestination;
use Grigoli\CheckoutResilience\Model\Resolver\RedirectUrlResolver;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CustomRedirectUrlTest extends TestCase
{
    /**
     * @var RedirectUrlResolver&MockObject
     */
    private RedirectUrlResolver $redirectUrlResolverMock;

    /**
     * @var CustomRedirectUrl
     */
    private CustomRedirectUrl $backendModel;

    protected function setUp(): void
    {
        $contextMock = $this->createMock(Context::class);
        $eventManagerMock = $this->createMock(EventManagerInterface::class);
        $contextMock->method('getEventDispatcher')->willReturn($eventManagerMock);

        $registryMock = $this->createMock(Registry::class);
        $scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $cacheTypeListMock = $this->createMock(TypeListInterface::class);
        $this->redirectUrlResolverMock = $this->createMock(RedirectUrlResolver::class);

        $this->backendModel = new CustomRedirectUrl(
            $contextMock,
            $registryMock,
            $scopeConfigMock,
            $cacheTypeListMock,
            $this->redirectUrlResolverMock
        );
    }

    public function testBeforeSaveTrimsAndAllowsValidCustomUrl(): void
    {
        $this->backendModel->setValue('  custom-recovery-page  ');
        $this->backendModel->setData(
            'fieldset_data',
            ['redirect_destination' => RedirectDestination::DESTINATION_CUSTOM]
        );

        $this->redirectUrlResolverMock->expects($this->once())
            ->method('isValidCustomUrl')
            ->with('custom-recovery-page')
            ->willReturn(true);

        $this->backendModel->beforeSave();

        $this->assertSame('custom-recovery-page', $this->backendModel->getValue());
    }

    public function testBeforeSaveAllowsEmptyValueWhenDestinationIsNotCustom(): void
    {
        $this->backendModel->setValue('   ');
        $this->backendModel->setData(
            'fieldset_data',
            ['redirect_destination' => RedirectDestination::DESTINATION_CHECKOUT_PAYMENT]
        );

        $this->redirectUrlResolverMock->expects($this->never())
            ->method('isValidCustomUrl');

        $this->backendModel->beforeSave();

        $this->assertSame('', $this->backendModel->getValue());
    }

    public function testBeforeSaveThrowsLocalizedExceptionWhenEmptyAndDestinationIsCustom(): void
    {
        $this->backendModel->setValue('   ');
        $this->backendModel->setData(
            'fieldset_data',
            ['redirect_destination' => RedirectDestination::DESTINATION_CUSTOM]
        );

        $this->redirectUrlResolverMock->expects($this->never())
            ->method('isValidCustomUrl');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Custom Redirect Path / URL cannot be empty when Redirect Destination is set to Custom URL.'
        );

        $this->backendModel->beforeSave();
    }

    public function testBeforeSaveThrowsLocalizedExceptionWhenCustomUrlIsInvalidOrExternal(): void
    {
        $this->backendModel->setValue('https://evil.com');
        $this->backendModel->setData(
            'fieldset_data',
            ['redirect_destination' => RedirectDestination::DESTINATION_CUSTOM]
        );

        $this->redirectUrlResolverMock->expects($this->once())
            ->method('isValidCustomUrl')
            ->with('https://evil.com')
            ->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Invalid Custom Redirect Path / URL. Please specify an internal relative path '
            . 'or a URL matching the store domain.'
        );

        $this->backendModel->beforeSave();
    }
}
