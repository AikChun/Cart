<?php
App::uses('CartsItem', 'Cart.Model');
/**
 * CartsItem Test
 * 
 * @author Florian Krämer
 * @copyright 2012 - 2013 Florian Krämer
 * @license MIT
 */
class CartsItemTest extends CakeTestCase {
/**
 * Fixtures
 *
 * @var array
 */
	public $fixtures = array(
		'plugin.Cart.User',
		'plugin.Cart.Cart',
		'plugin.Cart.Item',
		'plugin.Cart.Order',
		'plugin.Cart.CartsItem',
	);

/**
 * startUp
 *
 * @return void
 */
	public function setUp() {
		$this->CartsItem = ClassRegistry::init('Cart.CartsItem');
	}

/**
 * tearDown
 *
 * @return void
 */
	public function tearDown() {
		ClassRegistry::flush();
		unset($this->CartsItem);
	}

/**
 * testInstance
 *
 * @return void
 */
	public function testInstance() {
		$this->assertTrue(is_a($this->CartsItem, 'CartsItem'));
	}

/**
 * testValidateItem
 *
 * @return void
 */
	public function testValidateItem() {
		$data = array(
			'foo' => 'bar');
		$result = $this->CartsItem->validateItem($data);
		//debug($this->CartsItem->invalidFields());
	}

/**
 * testMergeItems
 *
 * @return void
 */
	public function testMergeItems() {
		$data1 = array(
			'CartsItem' => array(
				0 => array(
					'foreign_key' => 'asf123',
					'model' => 'Foo')));

		$data2 = array(
			'CartsItem' => array(
				0 => array(
					'foreign_key' => 'ufsfasf123',
					'model' => 'Bar'),
				1 => array(
					'foreign_key' => 'asf123',
					'model' => 'Foo'),
				2 => array(
					'foreign_key' => '1111111',
					'model' => 'Foo')));

		$result = $this->CartsItem->mergeItems($data2, $data1);
		$this->assertEqual($result, array(
			'CartsItem' => array(
				0 => array(
					'foreign_key' => 'ufsfasf123',
					'model' => 'Bar'),
				1 => array(
					'foreign_key' => 'asf123',
					'model' => 'Foo'),
				2 => array(
					'foreign_key' => '1111111',
					'model' => 'Foo'))));

		$result = $this->CartsItem->mergeItems($data1, $data2);
		$this->assertEqual($result, array(
			'CartsItem' => array(
				0 => array(
					'foreign_key' => 'asf123',
					'model' => 'Foo'),
				1 => array(
					'foreign_key' => 'ufsfasf123',
					'model' => 'Bar'),
				2 => array(
					'foreign_key' => '1111111',
					'model' => 'Foo'))));
	}

	public function testRemoveItem() {
		$data = array(
			'foreign_key' => 'item-1',
			'model' => 'Item');
		$result = $this->CartsItem->removeItem('cart-1', $data);
		$this->assertTrue($result);

		$result = $this->CartsItem->find('count', array(
			'contain' => array(),
			'conditions' => array(
				'CartsItem.foreign_key' => 'item-1',
				'CartsItem.model' => 'Item',
				'CartsItem.cart_id' => 'cart-1')));

		$this->assertEqual($result, 0);
	}

}