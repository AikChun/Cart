<h2><?php echo __('Your order #%s', $order['Order']['id']); ?></h2>

<dl>
	<dt><?php echo __('Customer Name'); ?></dt>
	<dd><?php echo h($order['User']['full_name']); ?></dd>
	<dt><?php echo __('Email'); ?></dt>
	<dd><?php echo h($order['User']['email']); ?></dd>
	<dt><?php echo __('Invoice Number'); ?></dt>
	<dd><?php echo h($order['Order']['invoice_number']); ?></dd>
	<dt><?php echo __('Total'); ?></dt>
	<dd><?php echo h($order['Order']['total']); ?></dd>
	<dt><?php echo __('Created'); ?></dt>
	<dd><?php echo h($order['Order']['created']); ?></dd>
	<dt><?php echo __('Payment Method'); ?></dt>
	<dd><?php echo h($order['Order']['processor']); ?></dd>
	<dt><?php echo __('Payment Status'); ?></dt>
	<dd><?php echo h($order['Order']['payment_status']); ?></dd>
	<dt><?php echo __('Status'); ?></dt>
	<dd><?php if($order['Order']['status'] == '')
				echo "NULL";
			echo h($order['Order']['status']); ?></dd>
</dl>

<h2><?php echo __('Shipping address');
$shipping = $order['Order']['cart_snapshop']['ShippingAddress'];
?></h2>
<dl>
	<dt><?php echo __('First Name'); ?></dt>
	<dd><?php echo h($shipping['first_name']); ?></dd>
	<dt><?php echo __('Last Name'); ?></dt>
	<dd><?php echo h($shipping['last_name']); ?></dd>
	<dt><?php echo __('Address 1'); ?></dt>
	<dd><?php echo h($shipping['street']); ?></dd>
	<dt><?php echo __('Address 2'); ?></dt>
	<dd><?php echo h($shipping['street2']); ?></dd>
	<dt><?php echo __('Zip'); ?></dt>
	<dd><?php echo h($shipping['zip']); ?></dd>
	<dt><?php echo __('Country'); ?></dt>
	<dd><?php echo h($shipping['country']); ?></dd>
</dl>

<?php
	//debug($orderItems);
	//debug($order);
	//debug($this->layout);
?>

<h3><?php echo __('Ordered Items'); ?></h3>

<table class="table table-striped table-bordered table-condensed">
	<tr>
		<th><?php echo 'Name'; ?></th>
		<th><?php echo 'quantity'; ?></th>
		<th><?php echo 'metadata'; ?></th>
	</tr>
	<?php foreach ($order['Order']['cart_snapshop']['CartsItem'] as $item) : ?>
		<tr>
			<td>
				<?php echo h($item['name']); ?>
			</td>
			<td>
				<?php echo h($item['quantity']); ?>
			</td>
			<td>
				<?php echo h($item['metadata']); ?>
			</td>
		</tr>
	<?php endforeach; ?>
</table>
