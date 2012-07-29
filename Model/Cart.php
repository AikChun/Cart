<?php
App::uses('CartAppModel', 'Cart.Model');
App::uses('CakeEvent', 'Event');
App::uses('CakeEventManager', 'Event');
/**
 * Cart Model
 *
 * @author Florian Krämer
 * @copyright 2012 Florian Krämer
 * @license MIT
 */
class Cart extends CartAppModel {
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
 * @parma $itemData
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
 * @toto total sum
 * @todo taxes
 * @todo discounts/coupons
 */
	public function calculateCart($cartData = array()) {
		if (isset($cartData['CartsItem'])) {
			$cartData['Cart']['item_count'] = count($cartData['CartsItem']);
		} else {
			return $cartData['Cart']['total'] = 0.00;
		}

		$cart['Cart']['requires_shipping'] = $this->requiresShipping($cartData['CartsItem']);

		$cartData = $this->applyDiscounts($cartData);
		$cartData = $this->applyTaxRules($cartData);
		$cartData = $this->calculateTotals($cartData);
		return $cartData;
	}

/**
 *
 */
	public function applyTaxRules($cartData) {
		CakeEventManager::dispatch(new CakeEvent('Cart.applyTaxRules', $cartData));
		return $cartData;
	}

/**
 * 
 */
	public function applyDiscounts($cartData) {
		CakeEventManager::dispatch(new CakeEvent('Cart.applyDiscounts', $cartData));
		return $cartData;
	}

/**
 * 
 */
	public function calculateTotals($cartData) {
		$cartData['Cart']['total'] = 0.00;

		if (!empty($cartData['CartsItem'])) {
			foreach ($cartData['CartsItem'] as $key => $item) {
				$cartData['CartsItem'][$key]['total'] = (int) $item['quantity'] * (float) $item['price'];
				$cartData['Cart']['total'] += (float) $item['price'];
			}
		}

		return $cartData;
	}

/**
 * Synchronizes the session cart with the db cart items
 *
 * @todo finish me
 * @return 
 */
	public function syncWithSessionData($cartId, $cartItems) {
		$dbItems = $this->CartsItem->find('all', array(
			'contain' => array(),
			'conditions' => array(
				'CartsItem.cart_id' => $cartId)));

		foreach ($cartItems as $cartKey => $cartItem) {
			if (empty($dbItems)) {
				$this->CartsItem->save($cartItem);
			} else {
				foreach ($dbItems as $dbItem) {
					if ($dbItem['foreign_key'] == $cartItem['foreign_key']
						&& $dbItem['model'] == $cartItem['model']) {
						
					}
				}
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