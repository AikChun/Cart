<?php
App::uses('CartAppModel', 'Cart.Model');
/**
 * Order Model
 *
 * @author Florian Krämer
 * @copyright 2012 Florian Krämer
 * @license MIT
 */
class Order extends CartAppModel {

/**
 * Behaviors
 *
 * @var array
 */
	public $actsAs = array(
		'Search.Searchable');

/**
 * Order status
 *
 * @var array
 */
	public $orderStatuses = array(
		'pending',
		'failed',
		'completed',
		'refunded',
		'partial-refunded');

/**
 * belongsTo associations
 *
 * @var array
 */
	public $belongsTo = array(
		'Cart' => array(
			'className' => 'Cart.Cart'),
		'User' => array(
			'className' => 'User'));

/**
 * belongsTo associations
 *
 * @var array
 */
	public $hasOne = array(
		'BillingAddress' => array(
			'className' => 'Cart.OrderAddress',
			'conditions' => array(
				'BillingAddress.type' => 'billing')),
		'ShippingAddress' => array(
			'className' => 'Cart.OrderAddress',
			'conditions' => array(
				'ShippingAddress.type' => 'shipping')));

/**
 * hasMany associations
 *
 * @var array
 */
	public $hasMany = array(
		'OrderItem' => array(
			'className' => 'Cart.OrderItem'));

/**
 * Validation rules
 *
 * @var array
 */
	public $validate = array(
		'total' => array(
			'numeric' => array(
				'rule' => array('numeric'),
				'message' => 'This must be a number')),
		'status' => array(
			'notEmpty' => array(
				'rule' => array('notEmpty'),
				'message' => 'The order requires a status')),
		'currency' => array(
			'notEmpty' => array(
				'rule' => array('notEmpty'),
				'message' => 'You must select a currency')),
		'processor' => array(
			'notEmpty' => array(
				'rule' => array('notEmpty'),
				'message' => 'The order requires a payment processor')),
		'cart_snapshot' => array(
			'notEmpty' => array(
				'rule' => array('notEmpty'),
				'message' => 'You must add the cart data to the order')));

/**
 * Filters args for search
 *
 * @var array
 */
	public $filterArgs = array(
		array('name' => 'username', 'type' => 'like', 'field' => 'User.username'),
		array('name' => 'email', 'type' => 'like', 'field' => 'User.email'),
		array('name' => 'invoice_number', 'type' => 'like'),
		array('name' => 'total', 'type' => 'value'),
		array('name' => 'created', 'type' => 'like'),
	);

/**
 * beforeSave callback
 *
 * @param array $options
 * @return boolean
 */
	public function beforeSave($options = array()) {
		if (!empty($this->data[$this->alias]['cart_snapshop']) && is_array($this->data[$this->alias]['cart_snapshop'])) {
			$this->data[$this->alias]['cart_snapshop'] = array_filter($this->data[$this->alias]['cart_snapshop']);
			$this->data[$this->alias]['cart_snapshop'] = serialize($this->data[$this->alias]['cart_snapshop']);
		}

		return true;
	}

/**
 * afterSave callback
 *
 * @var boolean $created
 */
	public function afterSave($created) {
		if ($created) {
			if (empty($this->data[$this->alias]['currency'])) {
				$this->data[$this->alias]['currency'] = Configure::read('Cart.defaultCurrency');
			}

			$this->data[$this->alias]['order_number'] = $this->orderNumber($this->data);
			$this->data[$this->alias]['invoice_number'] = $this->invoiceNumber($this->data);;
			$this->data[$this->alias][$this->primaryKey] = $this->getLastInsertId();

			$result = $this->save($this->data, array(
				'validate' => false,
				'callbacks' => false));

			$this->data = $result;
			CakeEventManager::instance()->dispatch(new CakeEvent('Order.created', $this, array($this->data)));
		}
	}

/**
 * afterFind callback
 *
 * @param array $results
 * @param bool $primary
 * @return array
 */
	public function afterFind($results, $primary = false) {
		$results = $this->unserializeCartSnapshot($results);
		return $results;
	}

/**
 * Unserializes the data in the cart_snapshot field when it is present
 *
 * @param $results
 * @internal param array $resuls
 * @return array modified results array
 */
	public function unserializeCartSnapshot($results) {
		if (!empty($results)) {
			foreach ($results as $key => $result) {
				if (isset($result[$this->alias]['cart_snapshop'])) {
					$results[$key][$this->alias]['cart_snapshop'] = unserialize($result[$this->alias]['cart_snapshop']);
				}
			}
		}
		return $results;
	}

/**
 * Returns the data for a user to view an order he made
 *
 * @param string $orderId Order UUID
 * @param string $userId User UUId
 * @return array
 * @throws NotFoundException
 */
	public function view($orderId = null, $userId = null) {
		$order = $this->find('first', array(
			'contain' => array(
				'OrderItem'),
			'conditions' => array(
				$this->alias . '.' . $this->primaryKey => $orderId,
				$this->alias . '.user_id' => $userId)));

		if (empty($order)) {
			throw new NotFoundException(__d('cart', 'The order does not exist.'));
		}
		return $order;
	}

/**
 * Returns the data for an order for the admin
 *
 * @param string $orderId Order UUID
 * @return array
 * @throws NotFoundException
 */
	public function adminView($orderId = null) {
		$order = $this->find('first', array(
			'contain' => array(
				'User',
				//'OrderItem'
			),
			'conditions' => array(
				$this->alias . '.' . $this->primaryKey => $orderId)));

		if (empty($order)) {
			throw new NotFoundException(__d('cart', 'The order does not exist.'));
		}
		return $order;
	}

/**
 * Validate Order
 *
 * Shipping and Billing Address validation if the cart requires shipping
 * by default true, it will get just validated and by this maybe set
 * to invalid, when the cart requires shipping
 *
 * @param
 * @return mixed
 */
	public function validateOrder($order) {
		$validBillingAddress = true;
		$validShippingAddress = true;

		if (isset($order['Cart']['requires_shipping']) && $order['Cart']['requires_shipping'] == 1) {
			$this->ShippingAddress->set($order);
			$validShippingAddress = $this->ShippingAddress->validates();

			if (isset($order['BillingAddress']['same_as_shipping']) && $order['BillingAddress']['same_as_shipping'] == 1) {
				$order['BillingAddress'] = $order['ShippingAddress'];
			} else {
				$this->BillingAddress->set($order);
				$validBillingAddress = $this->BillingAddress->validates();
			}
		}

		$this->set($order);
		$validOrder = $this->validates();

		if (!$validOrder || !$validBillingAddress || !$validShippingAddress) {
			return false;
		}

		return $order;
	}

/**
 * This method will create a new order record and does the validation work for
 * the different cases that might apply before you can issue a new order
 *
 * @param $cartData
 * @param $processorClass
 * @param string $paymentStatus
 * @internal param $
 * @internal param $
 * @internal param $
 * @return mixed Array with order data on success, false if not
 * @todo finish me
 */
	public function createOrder($cartData, $processorClass, $paymentStatus = 'pending') {
		$order = array(
			$this->alias => array(
				'processor' => $processorClass,
				'payment_status' => $paymentStatus,
				'cart_id' => empty($cartData['Cart']['id']) ? null : $cartData['Cart']['id'],
				'user_id' => empty($cartData['Cart']['user_id']) ? null : $cartData['Cart']['user_id'],
				'cart_snapshop' => $cartData,
				'total' => $cartData['Cart']['total']));

		$order = Set::merge($cartData, $order);

		CakeEventManager::instance()->dispatch(new CakeEvent('Order.beforeCreateOrder', $this, array($order)));

		$order = $this->validateOrder($order);
	
		if ($order === false) {
			return false;
		}
		$this->data = null;
		$this->create();
		$result = $this->save($order);

		$orderId = $this->getLastInsertId();
		$result[$this->alias][$this->primaryKey] = $orderId;

		foreach ($order['CartsItem'] as $item) {
			$item['order_id'] = $orderId;
			$this->OrderItem->create();
			$this->OrderItem->save($item);
		}

		if (isset($order['Cart']['requires_shipping']) && $order['Cart']['requires_shipping'] == 1) {
			$order['BillingAddress']['order_id'] = $orderId;
			$order['ShippingAddress']['order_id'] = $orderId;
			if(!isset($order['BillingAddress']['id'])) {
				$this->BillingAddress->create();
			}
			$this->BillingAddress->save($order);
			if(!isset($order['ShippingAddress']['id'])) {
				$this->ShippingAddress->create();	
			}
			$this->ShippingAddress->save($order);
		}

		if ($result) {
			$result[$this->alias][$this->primaryKey] = $orderId;
			CakeEventManager::instance()->dispatch(new CakeEvent('Order.created', $this, array($result)));
		}
		$result = Hash::merge($result, unserialize($result[$this->alias]['cart_snapshop']));

		return $result;
	}

/**
 * Generates an invoice number
 *
 * @param array $data Order data
 * @param $date
 * @return string
 */
	public function invoiceNumber($data = array(), $date = null) {
		$Event = new CakeEvent('Order.createInvoiceNumber', $this, array($data));
		CakeEventManager::instance()->dispatch($Event);
		if ($Event->isStopped()) {
			return $Event->data['result'];
		}

		if (empty($date)) {
			$date = date('Y-m-d');
		}

		$count = $this->find('count', array(
			'contain' => array(),
			'conditions' => array(
				$this->alias . '.created LIKE' => substr($date, 0, -2) .'%')));

		if ($count == 1) {
			$increment = $count;
		} else {
			$increment = $count + 1;
		}

		return str_replace('-', '', $date) . '-'. $increment;
	}

/**
 * Order number
 */
	public function orderNumber($data = array()) {
		return $this->find('count');
	}

/**
 * Update the payment status
 *
 * @param string $id Order id
 * @param string $newPaymentStatus the payment status
 * @return boolean true if successful, false otherwise
 */
	public function updatePaymentStatus($id = null, $newPaymentStatus = 'pending') {
		if ($id == null) {
			$id = $this->id;
		}
		if (in_array($newPaymentStatus, $this->orderStatuses)) {
			$fields     = array('Order.payment_status' => "'" . $newPaymentStatus . "'");
			$conditions = array('Order.id' => $id);
			$this->updateAll($fields, $conditions);
		}
		return false;
	}

}
