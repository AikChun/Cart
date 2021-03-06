<?php
App::uses('CartAppController', 'Cart.Controller');
/**
 * Orders Controller
 *
 * @author Florian Krämer
 * @copyright 2012 - 2013 Florian Krämer
 */
class OrdersController extends CartAppController {

/**
 * Components
 *
 * @var array
 */
	public $components = array(
		'Search.Prg');

/**
 * Preset fields for the search
 *
 * @var array
 */
	public $presetVars = array(
		array('field' => 'invoice_number', 'type' => 'value'),
		array('field' => 'username', 'type' => 'value'),
		array('field' => 'email', 'type' => 'value'),
		array('field' => 'total', 'type' => 'value'),
		array('field' => 'payment_status', 'type' => 'value'),
		array('field' => 'status', 'type' => 'value'),
		array('field' => 'created', 'type' => 'value'),
	);

/**
 * beforeFilter callback
 *
 * @return void
 */
	public function beforeFilter() {
		parent::beforeFilter();
		$this->Auth->allow('checkout');
		$this->Security->csrfCheck=false;
	}

/**
 * Lists orders for the current logged in user
 *
 * @return void
 */
	public function index() {
		$userId = $this->Auth->user('id');
		$this->paginate = array(
			'contain' => array(),
			'conditions' => array(
				'Order.status !=' => 'remove',
				'Order.user_id' => $userId),
			'order' => 'Order.created DESC');
		$this->set('orders', $this->paginate());
	}

/**
 * Displays a more detailed information about a single order for a user
 *
 * @param string $orderId Order UUID
 * @return void
 */
	public function view($orderId = null) {
		try {
			$this->set('order', $this->Order->view($orderId, $this->Auth->user('id')));
		} catch (Exception $e) {
			$this->Session->setFlash($e->getMessage());
			$this->redirect(array('action' => 'index'));
		}
	}

	public function cancel($orderId) {
	}

	public function admin_find() {
		$this->Prg->commonProcess();
		$this->Paginator->settings['conditions'] = $this->Order->parseCriteria($this->Prg->parsedParams());
		$this->Paginator->settings['conditions']['Order.status !='] = 'remove';
		$this->set('orders', $this->Paginator->paginate());
		$this->render('admin_index');
	}
/**
 * Lists all orders for an admin
 *
 * @return void
 */
	public function admin_index() {
			$this->paginate = array(
				'conditions' => array('Order.status !=' => 'remove'),
				'contain' => array(
					'User'),
				'order' => 'Order.created DESC');
			// $this->log($this->paginate());
			$this->set('orders', $this->paginate());
	}

/**
 * Displays a more detailed information about a single order
 *
 * @return void
 */
	public function admin_view($orderId = null) {
		try {
			$orderAdminView = $this->Order->adminView($orderId);
			$this->set('order', $orderAdminView);
		} catch (Exception $e) {
			$this->Session->setFlash($e->getMessage());
			$this->redirect(array('action' => 'index'));
		}
	}

/**
 *
 */
	public function admin_refund($orderId) {
		$order = $this->Order->find('first', array(
			'conditions' => array(
				'Order.id' => $orderId)));

		if ($this->request->is('post')) {
			$this->Order->refund($orderId);
		}

	}

/**
 * mark as remove
 */
	public function admin_remove($orderId) {
		if (!$this->Order->exists($orderId)) {
			throw new NotFoundException('No such order');
		}
		$referer = $this->referer();
		$result = false;
		if ($this->request->is('post')) {
			$result = $this->Order->updateStatus($orderId, 'remove');
		}
		if ($result) {
			$this->Session->setFlash('Order successfully removed');
		} else {
			$this->Session->setFlash('Unable to remove order');
		}
		$this->redirect($referer);
	}

}
