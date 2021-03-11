<!DOCTYPE html>
<html lang="en">
	<head>
		<!-- Required meta tags -->
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />

		<!-- Bootstrap CSS -->
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" integrity="sha384-B0vP5xmATw1+K9KRQjQERJvTumQW0nPEzvF6L/Z6nronJ3oUOFUFpCjEUQouq2+l" crossorigin="anonymous" />

		<title>Open Tender Rescrapping Monitor</title>
	</head>
	<body>
		<div class="container">
		<?php if (isset($statistic)): ?>
			<h1>Open Tender Rescrapping Monitor</h1>
			<table class="table table-striped">
				<thead>
					<th>#</th>
					<th>Year</th>
					<th>Target</th>
					<th>Completed</th>
					<th>Percentage</th>
					<th>Last Update <small>(Server Time UTC+7)</small></th>
				</thead>
				<tbody>
				<?php $i = 1; ?>
				<?php foreach ($statistic as $key => $value): ?>
					<tr>
						<td><?php echo $i; ?></td>
						<td><?php echo $value->years ?></td>
						<td><?php echo $value->target ?></td>
						<td><?php echo $value->completed ?></td>
						<td><?php echo number_format($value->percentage*100, 2) ?>%</td>
						<td><?php echo date('d M Y, H:i', strtotime($value->last_update)) ?></td>
						<td>
						<?php if (($value->years > 2010) AND ($value->years < 2019)): ?>
							<a href="<?php echo base_url('index.php/opentender/monitor/detail/'.$value->years) ?>" class="btn btn-outline-success" target="_blank">
								Detail Statistic
							</a>
						<?php endif ?>
						</td>
					</tr>
				<?php $i++; ?>
				<?php endforeach ?>
				</tbody>
			</table>
		<?php else: ?>
			<h1>Open Tender Rescrapping Monitor Detail</h1>
			<table class="table table-striped">
				<thead>
					<th>#</th>
					<th>Tier</th>
					<th>Total Data</th>
					<th>Target</th>
					<th>Scrapped</th>
					<th>Missing Link</th>
					<th>Response Success</th>
					<th>Response Failed</th>
					<th>Avg. Execution Time (Sec)</th>
				</thead>
				<tbody>
				<?php $i = 1; ?>
				<?php foreach ($detail_stats as $key => $value): ?>
					<tr>
						<td><?php echo $i; ?></td>
						<td><?php echo $value->tier ?></td>
						<td><?php echo $value->total_data ?></td>
						<td><?php echo $value->target ?></td>
						<td><?php echo $value->scrapped ?></td>
						<td><?php echo $value->missing_link ?></td>
						<td><?php echo $value->response_success ?></td>
						<td><?php echo $value->response_failed ?></td>
						<td><?php echo $value->avg_execution_time ?></td>
					</tr>
				<?php $i++; ?>
				<?php endforeach ?>
				</tbody>
			</table>
		<?php endif ?>
			<hr>
			<div class="float-right">
				<small class="text-muted">
					Data not 100% accurate due to technical issues while scrapping &mdash; @tryfatur
				</small>
			</div>
		</div>

		<!-- Optional JavaScript; choose one of the two! -->

		<!-- Option 1: jQuery and Bootstrap Bundle (includes Popper) -->
		<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-Piv4xVNRyMGpqkS2by6br4gNJ7DXjqk09RmUpJ8jgGtD7zP9yug3goQfGII0yAns" crossorigin="anonymous"></script>
		<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
	</body>
</html>