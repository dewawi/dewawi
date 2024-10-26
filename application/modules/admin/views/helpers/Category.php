<?php
/**
* Class inserts neccery code for Toolbar	
*/
class Zend_View_Helper_Category extends Zend_View_Helper_Abstract{

	public function Category()
	{
		return $this;
	}

	public function subCategories($categories, $id, $buttons, $admin, $forms, $slugs, $seperator = '&nbsp;&nbsp;&boxur;&boxh;') { ?>
			<?php $i1 = 1; ?>
			<?php $count1 = count($categories[$id]['childs']); ?>
			<?php foreach($categories[$id]['childs'] as $child) : ?>
				<tr>
					<td>
						<?php if($admin) : ?>
							<input class="id" type="checkbox" value="<?php echo $categories[$child]['id'] ?>" name="id"/>
						<?php else : ?>
							<input class="id" type="hidden" value="<?php echo $categories[$child]['id'] ?>" name="id"/>
						<?php endif; ?>
					</td>
					<td class="id">
						<a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'category', 'action'=>'edit', 'id'=>$categories[$child]['id']));?>">
							<?php echo $this->view->escape($categories[$child]['id']); ?>
						</a>
					</td>
					<td class="title">
						<?php echo $seperator; ?> <span class="editable" data-name="title"><?php echo $categories[$child]['title']; ?></span>
					</td>
					<td class="subtitle">
						<span class="editable" data-name="subtitle"><?php echo $categories[$child]['subtitle']; ?></span>
					</td>
					<?php if($categories[$child]['type'] == 'shop') : ?>
						<td class="slug">
							<span class="editable" data-name="slug" data-value="0" data-type="input"><?php echo $slugs[$categories[$child]['id']]; ?></span>
						</td>
					<?php endif; ?>
					<td class="parentid">
						<span class="editable" data-name="parentid" data-value="<?php echo $categories[$child]['parentid']; ?>" data-type="select"><?php echo $categories[$categories[$child]['parentid']]['title']; ?></span>
					</td>
					<td class="ordering">
						<?php echo $categories[$child]['ordering']; ?>
					</td>
					<td class="activated">
						<?php echo $forms[$categories[$child]['id']]->activated; ?>
					</td>
					<td class="buttons">
						<?php echo $buttons['copy']; ?>
						<?php echo $buttons['delete']; ?>
						<?php if($i1>1) : ?>
							<?php echo $buttons['sortup']; ?>
						<?php endif; ?>
						<?php if($i1<$count1) : ?>
							<?php echo $buttons['sortdown']; ?>
						<?php endif; ?>
					</td>
				</tr>
				<?php ++$i1; ?>
				<?php if(isset($categories[$child]['childs'])) : ?>
					<?php $seperatorChild = '&nbsp;&nbsp;&nbsp;'.$seperator; ?>
					<?php $this->subCategories($categories, $child, $buttons, $admin, $forms, $slugs, $seperatorChild); ?>
				<?php endif; ?>
			<?php endforeach; ?>
		<?php }
}
