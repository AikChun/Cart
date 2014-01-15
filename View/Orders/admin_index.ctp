<h2><?php echo __d('cart', 'Orders'); ?></h2>

<?php
	echo $this->Form->create('Order', array(
		'url' => array_merge(array('action' => 'find'), $this->params['pass'])
	));
	echo $this->Form->input('invoice_number', array(
		'label' => __('Invoice Number')));
	echo $this->Form->input('total', array(
		'label' => __('Total')));
	echo $this->Form->input('username', array(
		'label' => __('Username')));
	echo $this->Form->input('email', array(
		'label' => __('Email')));
	echo $this->Form->input('payment_status', array(
		'label' => __('Payment Status')));
	echo $this->Form->input('status', array(
		'label' => __('Status')));
	echo $this->Form->end(__d('cart', 'Search'));
?>

<?php if (!empty($orders)) : ?>
	<?php echo $this->element('paging'); ?>
	<table class="table table-striped table-bordered table-condensed">
		<thead>
			<tr>
				<th><?php echo $this->Paginator->sort('total', __('Total')); ?></th>
				<th><?php echo $this->Paginator->sort('invoice_number', __('Invoice #')); ?></th>
				<th><?php echo $this->Paginator->sort('processor', __('Payment Method')); ?></th>
				<th><?php echo $this->Paginator->sort('status', __('Status')); ?></th>
				<th><?php echo $this->Paginator->sort('payment_status', __('Payment Status')); ?></th>
				<th><?php echo $this->Paginator->sort('created', __('Created')); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($orders as $order) : ?>
				<tr>
					<td>
						<?php
							$total = $this->Number->currency($order['Order']['total'], $order['Order']['currency']);
							echo $this->Html->link($total, array(
								'action' => 'view', $order['Order']['id']));
						?>
					</td>
					<td><?php echo $order['Order']['invoice_number']; ?></td>
					<td><?php echo $order['Order']['processor']; ?></td>
					<td><?php echo $order['Order']['status']; ?></td>
					<td><?php echo $order['Order']['payment_status']; ?></td>
					<td><?php echo $order['Order']['created']; ?></td>
					<td>
						<?php
							echo $this->Html->link(__d('cart', 'view'), array('action' => 'view', $order['Order']['id'])) . ' | ';
							echo $this->Html->link(__d('cart', 'refund'), array('action' => 'refund', $order['Order']['id']));
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php else: ?>
	<p><?php echo __d('cart', 'No orders'); ?></p>
<?php endif; ?>

