<?php
require_once("Mage/Checkout/controllers/CartController.php");
class AES_Ajaxcart_IndexController extends Mage_Checkout_CartController{
    public function IndexAction() {
      
	  $this->loadLayout();   
	  $this->getLayout()->getBlock("head")->setTitle($this->__("Ajax Cart"));
	        $breadcrumbs = $this->getLayout()->getBlock("breadcrumbs");
      $breadcrumbs->addCrumb("home", array(
                "label" => $this->__("Home Page"),
                "title" => $this->__("Home Page"),
                "link"  => Mage::getBaseUrl()
		   ));

      $breadcrumbs->addCrumb("ajax cart", array(
                "label" => $this->__("Ajax Cart"),
                "title" => $this->__("Ajax Cart")
		   ));

      $this->renderLayout(); 
	  
    }
    
    public function addAction()
    {
        $cart   = $this->_getCart();
        $params = $this->getRequest()->getParams();
        if($params['isAjax'] == 1){
                   $response = array();
                    try {
                        if (isset($params['qty'])) {
                            $filter = new Zend_Filter_LocalizedToNormalized(
                            array('locale' => Mage::app()->getLocale()->getLocaleCode())
                            );
                            $params['qty'] = $filter->filter($params['qty']);
                        }
         
                        $product = $this->_initProduct();
                        $related = $this->getRequest()->getParam('related_product');
         
                        /**
                         * Check product availability
                         */
                        if (!$product) {
                            $response['status'] = 'ERROR';
                            $response['message'] = $this->__('Unable to find Product ID');
                        }
         
                        $cart->addProduct($product, $params);
                        if (!empty($related)) {
                            $cart->addProductsByIds(explode(',', $related));
                        }
         
                        $cart->save();
         
                        $this->_getSession()->setCartWasUpdated(true);
         
                        /**
                         * @todo remove wishlist observer processAddToCart
                         */
                        Mage::dispatchEvent('checkout_cart_add_product_complete',
                        array('product' => $product, 'request' => $this->getRequest(), 'response' => $this->getResponse())
                        );
         
                        if (!$this->_getSession()->getNoCartRedirect(true)) {
                            if (!$cart->getQuote()->getHasError()){
                                $message = $this->__('%s was added to your shopping cart.', Mage::helper('core')->htmlEscape($product->getName()));
                                $response['status'] = 'SUCCESS';
                                $response['message'] = $message;
        //New Code Here
                            }
                            else
                            {
                                $message = $this->__('%s was added to your shopping cart.', Mage::helper('core')->htmlEscape($product->getName()));
                                $response['status'] = 'SUCCESS';
                                $response['message'] = $message;
                            }
                        }
                    } catch (Mage_Core_Exception $e) {
                        $msg = "";
                        if ($this->_getSession()->getUseNotice(true)) {
                            $msg = $e->getMessage();
                        } else {
                            $messages = array_unique(explode("\n", $e->getMessage()));
                            foreach ($messages as $message) {
                                $msg .= $message.'<br/>';
                            }
                        }
                        foreach ($cart->getQuote()->getItemsCollection() as $item) {
                                    
                                    if ($product->getId() == $item->getProductId()) {
                                        $qty = $item->getQty() - $params['qty']; // check if greater then 0 or set it to what you want
                                        if($qty <= 0) {
                                            $cart->removeItem($item->getId())->save();    
                                        } else {
                                                $item->setQty($qty);
                                                $cart->save();
                                            }
                                    }
                                }    
                        $response['status'] = 'ERROR';
                        $response['message'] = $msg;
                    } catch (Exception $e) {
                        $response['status'] = 'ERROR';
                        $response['message'] = $this->__('Cannot add the item to shopping cart.');
                        Mage::logException($e);
                    }
                    
                    $this->loadLayout()->renderLayout();
                    $toplink = $this->getLayout()->getBlock('top.links')->toHtml();
                    $sidebar = $this->getLayout()->getBlock('cart_sidebar')->toHtml();
                    $response['toplink'] = $sidebar.$toplink;
                    
                    $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
                    return;
                }else{
                   return parent::addAction();
                }
         }
         
         /**
         * Update shopping cart data action
         */
        public function updatePostAction()
        {
            $updateAction = (string)$this->getRequest()->getParam('update_cart_action');
    
            switch ($updateAction) {
                case 'empty_cart':
                    $this->_emptyShoppingCart();
                    break;
                case 'update_qty':
                    $this->_updateShoppingCart();
                    break;
                default:
                    $this->_updateShoppingCart();
            }
            $msg = "Shopping cart was updated";
            $response['status'] = 'SUCCESS';
            $response['message'] = $msg;
            
            $this->loadLayout()->renderLayout();
            $toplink = $this->getLayout()->getBlock('top.links')->toHtml();
            $sidebar = $this->getLayout()->getBlock('cart_sidebar')->toHtml();
            $cartblock = $this->getLayout()->getBlock('checkout.cart')->toHtml();
            $response['cartblock'] = $cartblock;
            $response['toplink'] = $sidebar.$toplink;
            
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
            return;
        }
    
        /**
         * Update customer's shopping cart
         */
        protected function _updateShoppingCart()
        {
            try {
                $cartData = $this->getRequest()->getParam('cart');
                if (is_array($cartData)) {
                    $filter = new Zend_Filter_LocalizedToNormalized(
                        array('locale' => Mage::app()->getLocale()->getLocaleCode())
                    );
                    foreach ($cartData as $index => $data) {
                        if (isset($data['qty'])) {
                            $cartData[$index]['qty'] = $filter->filter(trim($data['qty']));
                        }
                    }
                    $cart = $this->_getCart();
                    if (! $cart->getCustomerSession()->getCustomer()->getId() && $cart->getQuote()->getCustomerId()) {
                        $cart->getQuote()->setCustomerId(null);
                    }
    
                    $cartData = $cart->suggestItemsQty($cartData);
                    $cart->updateItems($cartData)
                        ->save();
                }
                $this->_getSession()->setCartWasUpdated(true);
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError(Mage::helper('core')->escapeHtml($e->getMessage()));
            } catch (Exception $e) {
                $this->_getSession()->addException($e, $this->__('Cannot update shopping cart.'));
                Mage::logException($e);
            }
        }
    
        /**
         * Empty customer's shopping cart
         */
        protected function _emptyShoppingCart()
        {
            try {
                $this->_getCart()->truncate()->save();
                $this->_getSession()->setCartWasUpdated(true);
            } catch (Mage_Core_Exception $exception) {
                $this->_getSession()->addError($exception->getMessage());
            } catch (Exception $exception) {
                $this->_getSession()->addException($exception, $this->__('Cannot update shopping cart.'));
            }
        }
        
        /**
         * Delete shoping cart item action
         */
        public function deleteAction()
        {
            $id = (int) $this->getRequest()->getParam('id');
            if ($id) {
                try {
                    $this->_getCart()->removeItem($id)
                      ->save();
                } catch (Exception $e) {
                    $this->_getSession()->addError($this->__('Cannot remove the item.'));
                    Mage::logException($e);
                }
            }
            
            $msg = "Shopping cart was updated";
            $response['status'] = 'SUCCESS';
            $response['message'] = $msg;
            $this->loadLayout()->renderLayout();
            $toplink = $this->getLayout()->getBlock('top.links')->toHtml();
            $sidebar = $this->getLayout()->getBlock('cart_sidebar')->toHtml();
            $cartblock = $this->getLayout()->getBlock('checkout.cart')->toHtml();
            $response['cartblock'] = $cartblock;
            $response['toplink'] = $sidebar.$toplink;
            
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
            return;
        }
}