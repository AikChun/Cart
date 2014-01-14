<?php
App::uses('CartAppModel', 'Cart.Model');
App::uses('CakeEvent', 'Event');
App::uses('CakeEventManager', 'Event');
/**
 * Cart Model
 *
 * @author Florian Krämer
 * @copyright 2012 - 2013 Florian Krämer
 * @license MIT
 *
 * @property CartsItem CartsItem
 */
class Cart extends CartAppModel {

/**
 * belongsTo associations
 *
 * @var array
 */
	public $belongsTo = array(
		'User' => array(
			'className' => 'User'));

/**
 * hasMany associations
 *
 * @var array
 */
	public $hasMany = array(
		'CartsItem' => array(
			'className' => 'Cart.CartsItem'));

/**
 * Validation domain for translations
 *
 * @var string
 */
	public $validationDomain = 'cart';

/**
 * Validation parameters
 *
 * @var array
 */
	public $validate = array(
		'name' => array(
			'required' => array(
				'rule' => array('notEmpty'),
				'required' => true, 'allowEmpty' => false,
				'message' => 'Please enter a name for your cart.')),
		'user_id' => array(
			'required' => array(
				'rule' => array('notEmpty'),
				'required' => true, 'allowEmpty' => false,
				'message' => 'You have to add a user id.')));

/**
 * Checks if a cart is active or not
 *
 * @param string cart uuid
 * @return boolean
 */
	public function isActive($cartId = null) {
		if (empty($cartId)) {
			$cartId = $this->id;
		}

		$result = $this->find('count', array(
			'conditions' => array(
				$this->alias . '.active' => 1,
				$this->alias . '.' . $this->primaryKey => $cartId)));

		return ($result != 0);
	}

/**
 * Returns the active cart for a user, if there is no one it will create one and return it
 *
 * @param string user uuid
 * @return array
 */
	public function getActive($userId = null, $create = true) {
		$result = $this->find('first', array(
			'contain' => array(
				'CartsItem'),
			'conditions' => array(
				$this->alias . '.user_id' => $userId)));

		if (!empty($result)) {
			return $result;
		}

		if (!$create) {
			return false;
		}

		$this->create();
		$result = $this->save(array(
			$this->alias => array(
				'user_id' => $userId,
				'active' => 1,
				'name' => __d('cart', 'My cart'))));

		$result[$this->alias]['id'] = $this->getLastInsertId();
		return $result;
	}

/**
 * Returns a cart and its contents
 *
 * @param string $cartId
 * @param string $userId
 */
	public function view($cartId = null, $userId = null) {
		$result = $this->find('first', array(
			'contain' => array(
				'CartsItem'),
			'conditions' => array(
				$this->alias . '.user_id' => $userId)));

		$this->create();
		$result = $this->save(array(
			$this->alias => array(
				'user_id' => $userId,
				'active' => 1,
				'name' => __d('cart', 'My cart'))));
		$result[$this->alias]['id'] = $this->id;
		$result['CartItems'] = array();
		return $result;
	}

/**
 * Adds and updates an item if it already exists in the cart
 *
 * @param string $cartId
 * @param array $itemData
 */
	public function addItem($cartId, $itemData) {
		return $this->CartsItem->addItem($cartId, $itemData);
	}

/**
 * Called from the CartManagerComponent when an item is removed from the cart
 *
 * @param string $cartId Cart UUID
 * @param $itemData
 * @return bool
 */
	public function removeItem($cartId, $itemData) {
		return $this->CartsItem->removeItem($cartId, $itemData);
	}

/**
 * Drops the cart an all its items
 *
 * @return boolean
 */
	public function emptyCart($cartId) {
		return $this->delete($cartId);
	}

/**
 * Checks if one of the items in the cart is not flagged as a virtual item and 
 * requires by this shipping.
 *
 * Virtual means that it can be a download or a service or whatever else.
 *
 * @param array $cartItems Array of items in the cart
 * @return boolean
 */
	public function requiresShipping($cartItems = array()) {
		if (!empty($cartItems)) {
			foreach ($cartItems as $cartKey => $cartItem) {
				if (!isset($cartItem['virtual']) || isset($cartItem['virtual']) && $cartItem['virtual'] == 0) {
					return true;
				}
			}
		}
		return false;
	}

/**
 * Calculates the totals of a cart
 *
 * @todo taxes
 * @todo discounts/coupons
 */
	public function calculateCart($cartData = array()) {
		$Event = new CakeEvent('Cart.beforeCalculateCart', $this, array($cartData));
		CakeEventManager::instance()->dispatch($Event);

		if (isset($cartData['CartsItem'])) {
			$cartData[$this->alias]['item_count'] = count($cartData['CartsItem']);
		} else {
			return $cartData[$this->alias]['total'] = 0.00;
		}

		$cart[$this->alias]['requires_shipping'] = $this->requiresShipping($cartData['CartsItem']);

		$cartData = $this->applyDiscounts($cartData);
		$cartData = $this->applyTaxRules($cartData);
		$cartData = $this->calculateTotals($cartData);

		$Event = new CakeEvent('Cart.afterCalculateCart', $this, array($cartData));
		CakeEventManager::instance()->dispatch($Event);

		return $cartData;
	}

/**
 *
 */
	public function applyTaxRules($cartData) {
		$Event = new CakeEvent('Cart.applyTaxRules', $this, array($cartData));
		CakeEventManager::instance()->dispatch($Event);

		if ($Event->isStopped()) {

		}
		return $cartData;
	}

/**
 * 
 */
	public function applyDiscounts($cartData) {
		$Event = new CakeEvent('Cart.applyDiscounts', $this, array($cartData));
		CakeEventManager::instance()->dispatch($Event);
		if ($Event->isStopped()) {

		}
		return $cartData;
	}

/**
 * Calculates the total amount of the cart
 *
 * @param array $cartData
 * @return array
 */
	public function calculateTotals($cartData) {
		$cartData[$this->alias]['total'] = 0.00;
		$cartData[$this->alias]['total_price'] = 0.00;

		if (!empty($cartData['CartsItem'])) {
			foreach ($cartData['CartsItem'] as $key => $item) {

				$cartData['CartsItem'][$key]['total'] = (float) $item['quantity'] * (float) $item['price'];
				$cartData[$this->alias]['total'] += (float) $cartData['CartsItem'][$key]['total'];
				$cartData[$this->alias]['total_price'] += (float) $cartData['CartsItem'][$key]['total'];
			}
		}

		return $cartData;
	}

/**
 * Merges an array of item data with the cart items in the database.
 *
 * This method needs to be called during the login process before the redirect
 * happens but after the user was authenticated if you want to take the items
 * from the non-logged in user into the users database cart.
 *
 * @todo finish me
 *
 * @param integer|string $cartId
 * @param array $cartItems
 * @return void
 */
	public function mergeItems($cartId, $cartItems) {
		$dbItems = $this->CartsItem->find('all', array(
			'contain' => array(),
			'conditions' => array(
				'CartsItem.cart_id' => $cartId)));

		foreach ($cartItems as $cartKey => $cartItem) {
			$matched = false;
			foreach ($dbItems as $dbItem) {
				if ($dbItem['CartsItem']['model'] == $cartItem['model'] && $dbItem['CartsItem']['foreign_key'] == $cartItem['foreign_key']) {
					$this->CartsItem->save(array_merge($dbItem['CartsItem'], $cartItem));
					$matched = true;
					break;
				}
			}
			if ($matched === false) {
				$this->addItem($cartId, $cartItem);
			}
		}
	}

/**
 * 
 */
	public function confirmCheckout($data) {
		return (isset($data[$this->alias]['confirm_checkout']) && $data[$this->alias]['confirm_checkout'] == 1);
	}
}
