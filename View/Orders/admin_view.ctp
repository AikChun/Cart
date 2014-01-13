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
