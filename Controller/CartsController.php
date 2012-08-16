<?php
App::uses('CartAppController', 'Cart.Controller');
App::uses('CakeEventManager', 'Event');
App::uses('CakeEvent', 'Event');
App::uses('PaymentProcessors', 'Cart.Payment');
/**
 * Carts Controller
 *
 * @author Florian Krämer
 * @copyright 2012 Florian Krämer
 */
class CartsController extends CartAppController {
/**
 * Components
 *
 * @var array
 */
	public $components = array(
		'Cart.CartManager',
		'Session');

/**
 * beforeFilter callback
 *
 * @return void
 */
	public function beforeFilter() {
		parent::beforeFilter();
		$this->Auth->allow('index', 'view', 'remove_item', 'checkout', 'callback');
	}

/**
 * Display all carts a user has, active one first
 *
 * @return void
 */
	public function index() {
		$this->paginate = array(
			'contain' => array(),
			'order' => array('Cart.active DESC'),
			'conditions' => array(
				'Cart.user_id' => $this->Auth->user('id')));
		$this->set('carts', $this->paginate());
	}

/**
 * Shows a cart for a user
 *
 * @param
 * @return void
 */
	public function view($cartId = null) {
		if (!empty($this->request->data)) {
			//debug($this->request->data);
			$cart = $this->CartManager->content();
			foreach ($this->request->data['CartsItem'] as $key => $cartItem) {
				$cartItem = Set::merge($cart['CartsItem'][$key], $cartItem);
				$this->CartManager->addItem($cartItem);
			}
		}

		$cart = $this->CartManager->content();

		$this->request->data = $cart;
		$this->set('cart', $cart);
		$this->set('paymentMethods', ClassRegistry::init('Cart.PaymentMethod')->getPaymentMethods());
	}

/**
 * Removes an item from the cart
 */
	public function remove_item() {
		if (!isset($this->request->named['model']) || !isset($this->request->named['id'])) {
			$this->Session->setFlash(__d('cart', 'Invalid cart item'));
			$this->redirect($this->referer());
		}

		$result = $this->CartManager->removeItem(array(
			'foreign_key' => $this->request->named['id'],
			'model' => $this->request->named['model']));

		if ($result) {
			$this->Session->setFlash(__d('cart', 'Item removed'));
		} else {
			$this->Session->setFlash(__d('cart', 'Could not remove item'));
		}

		$this->redirect($this->referer());
	}

/**
 * Default callback entry point for API callbacks for payment processors
 *
 * @param string $processor
 * @return void
 */
	public function callback($processor = Null, $action = null) {
		$this->log($_POST, 'cart-callback');
		$this->log($_GET, 'cart-callback');

		// @todo check for valid processor?
		//$Processor = PaymentProcessors::load($processor, array('request' => $this->request, 'response' => $this->response));
		if (empty($processor)) {
			$this->cakeError(404);
		}

		CakeEventManager::dispatch(new CakeEvent('Payment.callback', $this->request));
	}

/**
 * Triggers the checkout
 *
 * - checks if the cart is not empty
 * - checks if the payment processor is valid
 *
 * @param 
 * @return void
 */
	public function checkout($processor = null, $action = Null) {
		$processor = $this->__mapProcessorClass($processor);

		$cartData = $this->CartManager->content();

		if (empty($cartData['CartsItem'])) {
			$this->Session->setFlash(__d('cart', 'Your cart is empty.'));
			$this->redirect(array('action' => 'view'));
		}

		$this->__anonymousCheckoutIsAllowed();

		try {
			$Processor = PaymentProcessors::load($processor, array('request' => $this->request, 'response' => $this->response));
		} catch (Exception $e) {
			$this->Session->setFlash($e->getMessage());
			$this->redirect(array('action' => 'view'));
		}

		$Order = ClassRegistry::init('Cart.Order');
		$ApiLog = ClassRegistry::init('Cart.PaymentApiTransaction');
		$newOrder = $Order->createOrder($cartData, $processor);

		if ($newOrder) {
			$this->CartManager->emptyCart();
			$Processor->checkout($this, $newOrder);
		}

		$this->Session->setFlash(__d('cart', 'There was a problem creating your order.'));
		$this->redirect(array('action' => 'view'));
	}

/**
 * Last step for so called express checkout processors
 *
 * @return void
 */
	public function confirm_order($processor = null, $token = null) {
		$processor = $this->__mapProcessorClass($processor);

		try {
			$Processor = PaymentProcessors::load($processor, array('request' => $this->request, 'response' => $this->response));
		} catch (MissingPaymentProcessorException $e) {
			$this->Session->setFlash(__d('cart', 'The payment method does not exist!'));
			$this->redirect(array('action' => 'view'));
		} catch (Exception $e) {
			$this->Session->setFlash($e->getMessage());
			$this->redirect(array('action' => 'view'));
		}

		die(Debug($this->request));
		if (!empty($this->request->data)) {
			debug($this->request->data);

			if (!method_exists($Processor, 'confirmOrder')) {
				$this->Session->setFlash(__('Unsupported payment processor for this type of checkout!'));
				$this->redirect(array('action' => 'view'));
			}
			//$Processor->confirmOrder($this, $newOrder);
		}

	}

/**
 * Checks if the processor name is mapped in the static configure class or if 
 * it is mapped in the PaymentMethod model.
 *
 * The payment method model gives you greater flexibility to en/disable processors
 * on the fly through the admin backend.
 *
 * If no mapped processor classname is found it will return the passed name and
 * the PaymentProcessors::load() method will throw an exception if it cant find
 * that processor.
 *
 * @param string $processorAlias
 * @return string
 */
	protected function __mapProcessorClass($processorAlias) {
		$this->log($processorAlias, 'processor-alias');
		$this->log($this->request, 'processor-alias');
		$processorClass = Configure::read('Cart.PaymentMethod.'. $processorAlias . '.processor');
		if (!empty($processorClass)) {
			return $processorClass;
		}
		$processorClass = ClassRegistry::init('Cart.PaymentMethod')->getMappedClassName($processorAlias);
		if (!empty($processorClass)) {
			return $processorClass;
		}
		return $processorAlias;
	}

/**
 * __allowAnonymousCheckout
 *
 * @param boolean $redirect
 * @return boolean
 */
	protected function __anonymousCheckoutIsAllowed($redirect = true) {
		if (Configure::read('Cart.anonymousCheckout') === false && is_null($this->Auth->user())) {#
			$this->Session->setFlash(__d('cart', 'Sorry, but you have to login to check this cart out.'));
			if ($redirect) {
				$this->redirect(array('action' => 'view'));
			}
			return false;
		}
		return true;
	}

/**
 * 
 */
	public function admin_index() {
		$this->set('carts', $this->paginate());
	}

/**
 *
 */
	public function admin_delete($cartId = null) {
		$this->Cart->delete($cartId);
	}

}