# Grigoli_CheckoutResilience

### Module overview
The objetive of this module is improve checkout conversion throught agnostic processes. You could recover the cart and try purchases again without session was lose. The module is agnostic to payment method, flexible by admin configurations and customizable by magento programming techniques.  
### Key features
* Session cart can be recovered by checkout_onepage_failure.xml page throught a button. This button can redirect to configurable url or return to payment step (set by configuration).
* Payment attempts manually by user when the payment failed. You can set max_manual_retries and enable or disable this function in admin panel. 
* Automatic payment attempts for specific and configurable errors returned in payment response. The errors to detect automatic retries must be configurable. You can set max_automatic_retries and enable or disable this function
* Enable or disable function to turn on/off the module in admin panel.
### Technical Architecture
* 