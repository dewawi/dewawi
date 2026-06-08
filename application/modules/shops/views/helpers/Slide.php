<?php

class Zend_View_Helper_Slide extends Zend_View_Helper_Abstract
{
	public function Slide(array $slideImages = []): string
	{
		if (empty($slideImages)) {
			return '';
		}

		ob_start();
		?>

		<section id="carousel" class="carousel slide" data-ride="carousel" data-interval="5000">

			<ol class="carousel-indicators">
				<?php foreach ($slideImages as $index => $slideImage) : ?>
					<li
						data-target="#carousel"
						data-slide-to="<?php echo $index; ?>"
						<?php echo $index === 0 ? 'class="active"' : ''; ?>>
					</li>
				<?php endforeach; ?>
			</ol>

			<div class="carousel-inner">

				<?php foreach ($slideImages as $index => $slideImage) : ?>

					<?php
					$imageUrl = $this->view->baseUrl()
						. '/media/slide/'
						. ltrim((string)($slideImage['url'] ?? ''), '/');

					$target = trim((string)($slideImage['target'] ?? ''));
					?>

					<div class="carousel-item<?php echo $index === 0 ? ' active' : ''; ?>">

						<?php if ($target !== '') : ?>
							<a href="<?php echo $this->view->escape($target); ?>">
						<?php endif; ?>

						<img
							src="<?php echo $this->view->escape($imageUrl); ?>"
							class="d-block w-100"
							alt="<?php echo $this->view->escape($slideImage['title'] ?? ''); ?>">

						<?php if ($target !== '') : ?>
							</a>
						<?php endif; ?>

						<div class="carousel-caption d-md-block text-left align-middle">

							<?php if (!empty($slideImage['title'])) : ?>
								<h2><?php echo $this->view->escape($slideImage['title']); ?></h2>
							<?php endif; ?>

							<?php if (!empty($slideImage['description'])) : ?>
								<p><?php echo $this->view->escape($slideImage['description']); ?></p>
							<?php endif; ?>

						</div>

					</div>

				<?php endforeach; ?>

			</div>

			<a class="carousel-control-prev" href="#carousel" role="button" data-slide="prev">
				<span class="carousel-control-prev-icon" aria-hidden="true"></span>
				<span class="sr-only">Previous</span>
			</a>

			<a class="carousel-control-next" href="#carousel" role="button" data-slide="next">
				<span class="carousel-control-next-icon" aria-hidden="true"></span>
				<span class="sr-only">Next</span>
			</a>

		</section>

		<?php

		return ob_get_clean();
	}
}
