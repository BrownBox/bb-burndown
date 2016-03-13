<?php get_header(); ?>
<div class="row-wrapper no-class" id="row-content">
    <div class="row-inner-wrapper row" id="row-inner-content">
        <article class="small-24 medium-24 large-24 column">
<?php
$burndown = new Burndown();
$burndown->show_graph();
?>
        </article>
    </div>
</div>
<?php get_footer(); ?>