<?php
App::uses('AppBehavior', 'Model');
App::uses('CakeEventManager', 'Event');
/**
 * Buyable Behavior
 *
 * @author Florian Krämer
 * @copyright 2012 - 2013 Florian Krämer
 * @license MIT
 */
class BuyableBehavior extends ModelBehavior {
/**
 * Default settings
 * - recurring: Whether or not all items are recurring subscriptions [default: false]
 * - recurringField: Name of the boolean field containing 'is_recurring',
 * - priceField: Name of the field containing item's price [default: price]
 * - nameField: Name of the field containing item's name [default: $this->displayField]
 * - billingFrequencyField: Name of the field containing the billing frequency (if recurring) [default: billing_frequency]
 * - billingPeriodField: Name of the field containing the billing period (if recurring) [default: billing_period]
 * - maxQuantity: The maximum quantity of a single item an user can buy. Either an integer, or a field name (for a custom valueper row) [default: PHP_INT_MAX]
 *
 * @var array
 * @access protected
 */
	protected $_defaults = array(
		'allVirtual' => false,
		'virtualField' => 'virtual',
		'priceField' => 'price',
		'nameField' => '', // Initialized in setup()
		'currencyField' => 'currency',
		'recurring' => false,
		'recurringField' => 'is_recurring',
		'billingFrequencyField' => 'billing_frequency',
		'billingPeriodField' => 'billing_period',
		'defaultCurrency' => 'USD',
		'maxQuantity' => PHP_INT_MAX);

/**
 * Setup
 *
 * @param Model $Model
 * @param array $settings
 * @return void
 * @internal param AppModel $model
 */
	public function setup(Model $Model, $settings = array()) {
		if (!isset($this->settings[$Model->alias])) {
			$this->settings[$Model->alias] = $this->_defaults;
		}

		if (empty($this->settings[$Model->alias]['nameField'])) {
			$this->settings[$Model->alias]['nameField'] = $Model->displayField;
		}

		$this->settings[$Model->alias] = array_merge($this->settings[$Model->alias], $settings);

		$this->bindCartModel($Model);
	}

/**
 * Default method for additionalItemData model callback
 *
 * No additional data is returned
 *
 * @param Model $Model
 * @return mixed Data to be serialized as additional data for the current item, null otherwise
 * @access public
 */
	public function additionalBuyData(Model $Model) {
		return array();
	}

/**
 * Binds the cart association if no HABTM assoc named 'Cart' already exists.
 *
 * @param Model $Model
 * @return void
 */
	public function bindCartModel(Model $Model) {
		extract($this->settings[$Model->alias]);
		if (!isset($Model->hasAndBelongsToMany['Cart'])) {
			$Model->bindModel(array(
				'hasAndBelongsToMany' => array(
					'Cart' => array(
						'className' => 'Cart.Cart',
						'foreignKey' => 'foreign_key',
						'associationForeignKey' => 'cart_id',
						'joinTable' => 'carts_items',
						'with' => 'Cart.CartsItem'))), false);
		}
	}

/**
 * Checks if a model record exists
 *
 * @param Model $Model
 * @param array $data
 * @return bool
 */
	public function isBuyable(Model $Model, $data) {
		$Model->id = $data['CartsItem']['foreign_key'];
		return $Model->exists();
	}

/**
 * Model $Model, $data
 *
 * @param Model $Model
 * @param array $cartsItem
 * @return array
 */
	public function beforeAddToCart(Model $Model, $cartsItem) {
		$record = $Model->find('first', array(
			'contain' => array(),
			'conditions' => array(
				$Model->alias . '.' . $Model->primaryKey => $cartsItem['CartsItem']['foreign_key'])));

		return $this->composeItemData($Model, $record, $cartsItem);
	}

/**
 * Creates a cart compatible item data array from the data coming from beforeAddToCart
 *
 * @param Model $Model
 * @param array $record
 * @param array $cartsItem
 * @return array
 * @throws RuntimeException
 */
	public function composeItemData(Model $Model, $record, $cartsItem) {
		extract($this->settings[$Model->alias]);

		if (is_string($maxQuantity)) {
			if (!isset($record[$Model->alias][$maxQuantity])) {
				throw new RuntimeException(__d('cart', 'Invalid model field %s for maxQuantity!', $maxQuantity));
			}
			$maxQuantity = $record[$Model->alias][$maxQuantity];
		}

		$result = array(
			'quantity_limit' => $maxQuantity,
			'is_virtual' => $allVirtual,
			'model' => get_class($Model),
			'foreign_key' => $record[$Model->alias][$Model->primaryKey],
			'name' => $record[$Model->alias][$nameField],
			'price' => $record[$Model->alias][$priceField],
			'short_name' => $record[$Model->alias]['short_name'],
			'additional_data' => serialize($Model->additionalBuyData()));

		return Set::merge($cartsItem['CartsItem'], $result);
	}

}