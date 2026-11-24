<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Model\Config\Backend;

use Grigoli\CheckoutResilience\Model\Config\Source\RedirectDestination;
use Grigoli\CheckoutResilience\Model\Resolver\RedirectUrlResolver;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Backend model that validates and normalizes the custom redirect URL on Admin configuration save.
 */
class CustomRedirectUrl extends Value
{
    /**
     * Constructor
     *
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param RedirectUrlResolver $redirectUrlResolver
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly RedirectUrlResolver $redirectUrlResolver,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Validate the custom redirect path/URL before saving configuration.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave(): static
    {
        $value = trim((string) $this->getValue());
        $redirectDestination = (string) $this->getFieldsetDataValue('redirect_destination');

        if ($value === '') {
            if ($redirectDestination === RedirectDestination::DESTINATION_CUSTOM) {
                throw new LocalizedException(
                    __('Custom Redirect Path / URL cannot be empty when Redirect Destination is set to Custom URL.')
                );
            }

            $this->setValue('');
            return parent::beforeSave();
        }

        if (!$this->redirectUrlResolver->isValidCustomUrl($value)) {
            throw new LocalizedException(
                __(
                    'Invalid Custom Redirect Path / URL. Please specify an internal relative path '
                    . 'or a URL matching the store domain.'
                )
            );
        }

        $this->setValue($value);

        return parent::beforeSave();
    }
}
