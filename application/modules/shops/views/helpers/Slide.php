<?php

class Zend_View_Helper_Slide extends Zend_View_Helper_Abstract
{
	public function Slide(array $slideImages = []): string
	{
		if (empty($slideImages)) {
			return '';
		}
		$slides = [];
		foreach ($slideImages as $slideImage) {
			$imageUrl = $this->view->baseUrl() . '/media/slide/' . ltrim((string)($slideImage['url'] ?? ''), '/');
			$imageHtml = $this->view->SiteImage($imageUrl, (string)($slideImage['title'] ?? ''), [
				'class' => 'd-block w-100',
				'loading' => $slides ? 'lazy' : false,
				'fetchpriority' => $slides ? '' : 'high',
				'sizes' => '100vw',
			]);
			if ($imageHtml !== '') $slides[] = ['data' => $slideImage, 'image' => $imageHtml];
		}
		if (!$slides) return '';

		ob_start();
		?>

		<section id="carousel" class="carousel slide" data-ride="carousel" data-interval="5000">
			<button class="btn btn-light dw-carousel-toggle" type="button" data-carousel-toggle aria-controls="carousel" data-paused="false" data-pause-label="<?php echo $this->view->escape($this->view->translate('SHOPS_SLIDER_PAUSE')); ?>" data-play-label="<?php echo $this->view->escape($this->view->translate('SHOPS_SLIDER_PLAY')); ?>">
				<?php echo $this->view->translate('SHOPS_SLIDER_PAUSE'); ?>
			</button>

			<div class="carousel-indicators">
				<?php foreach ($slides as $index => $slide) : ?>
					<button type="button"
						data-target="#carousel"
						data-slide-to="<?php echo $index; ?>"
						class="dw-carousel-indicator<?php echo $index === 0 ? ' active' : ''; ?>"
						aria-label="<?php echo $this->view->escape(sprintf($this->view->translate('SHOPS_SLIDE'), $index + 1)); ?>"
						<?php echo $index === 0 ? 'aria-current="true"' : ''; ?>></button>
				<?php endforeach; ?>
			</div>

			<div class="carousel-inner">

				<?php foreach ($slides as $index => $slide) : ?>

					<?php
					$slideImage = $slide['data'];
					$target = trim((string)($slideImage['target'] ?? ''));
					?>

					<div class="carousel-item<?php echo $index === 0 ? ' active' : ''; ?>">

						<?php if ($target !== '') : ?>
							<a href="<?php echo $this->view->escape($target); ?>">
						<?php endif; ?>

						<?php echo $slide['image']; ?>

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

			<button class="carousel-control-prev" type="button" data-target="#carousel" data-slide="prev">
				<span class="carousel-control-prev-icon" aria-hidden="true"></span>
				<span class="sr-only"><?php echo $this->view->translate('SHOPS_PREVIOUS'); ?></span>
			</button>

			<button class="carousel-control-next" type="button" data-target="#carousel" data-slide="next">
				<span class="carousel-control-next-icon" aria-hidden="true"></span>
				<span class="sr-only"><?php echo $this->view->translate('SHOPS_NEXT'); ?></span>
			</button>

		</section>

		<?php

		return ob_get_clean();
	}
}
