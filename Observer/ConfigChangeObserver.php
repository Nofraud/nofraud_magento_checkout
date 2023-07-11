<?php

namespace NoFraud\Checkout\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;

class ConfigChangeObserver implements ObserverInterface
{
    const XML_PATH_TELEPHONE_SHOW = 'customer/address/telephone_show';

    const XML_PATH_OAUTH_CONSUMER_ENABLE = 'oauth/consumer/enable_integration_as_bearer';

    const XML_PATH_HYVA_BASED_THEME = 'nofraud/general/hyva_based_theme';

    protected $scopeConfig;

    protected $storeManager;

    protected $themeProvider;

    /**
     * ConfigChange constructor.
     * @param WriterInterface $configWriter
     */
    public function __construct(
        ScopeConfigInterface   $scopeConfig,
        WriterInterface        $configWriter,
        StoreManagerInterface  $storeManager,
        ThemeProviderInterface $themeProvider
    ) {
        $this->scopeConfig   = $scopeConfig;
        $this->configWriter  = $configWriter;
        $this->storeManager  = $storeManager;
        $this->themeProvider = $themeProvider;
    }

    public function execute(EventObserver $observer)
    {
        $storeScope     = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $Showtelephone  =   $this->scopeConfig->getValue(self::XML_PATH_TELEPHONE_SHOW, $storeScope);
        $currentThemeId = $this->scopeConfig->getValue(
            \Magento\Framework\View\DesignInterface::XML_PATH_THEME_ID,
            $storeScope,
            $this->storeManager->getStore()->getId()
        );
        $currentTheme = $this->themeProvider->getThemeById($currentThemeId);
        $parentTheme  = $this->themeProvider->getThemeById($currentTheme->getParentId());

        if ($Showtelephone != 'opt') {
            $this->configWriter->save(self::XML_PATH_TELEPHONE_SHOW, 'opt');
            $this->configWriter->save(self::XML_PATH_OAUTH_CONSUMER_ENABLE, '1');
        }

        if ($currentTheme->getCode() == "Hyva/default" || $currentTheme->getCode() == "Hyva/reset") {
            $this->configWriter->save(self::XML_PATH_HYVA_BASED_THEME, '1');
        } elseif ($parentTheme->getCode() == "Hyva/default" || $currentTheme->getCode() == "Hyva/reset") {
            $this->configWriter->save(self::XML_PATH_HYVA_BASED_THEME, '1');
        } else {
            $this->configWriter->save(self::XML_PATH_HYVA_BASED_THEME, '0');
        }
        return $this;
    }
}
