<?php
function bb_burndown_widget($post, $callback_args) {
    $burndowns = new Burndowns();
    $burndown_list = $burndowns->get_active_burndowns();
?>
<style>
table.burndown_summary {width: 100%; border-collapse: collapse;}
table.burndown_summary tr {cursor: pointer;}
table.burndown_summary td {padding: 5px 3px;}
table.burndown_summary td.status {text-align: center;}
table.burndown_summary td.number {text-align: right;}
table.burndown_summary tr.project_overview:not(:first-of-type) td {border-top: 1px solid #ccc;}
tr.project_graphs td>div {float: left; margin-right: 0.75rem;}

.status_spot {display: inline-block; width: 15px; height: 15px; background-color: red; border-radius: 50%; margin: 0 auto;}
.status_spot_0 {background-color: green;}
.status_spot_1 {background-color: yellow;}
</style>
<?php
    echo '<table class="burndown_summary">'."\n";
    $totals = array();
    $warnings = 0;
    $last_burndown = 0;
    foreach ($burndown_list as $burndown) { /* @var $burndown Burndown */
        echo '<tr class="burndown_overview" onclick="jQuery(\'#burndown_graph_'.$burndown->get_burndown_id().'\').toggle();">'."\n";
        if ($burndown->get_end_value(false) < 0) {
            $warnings++;
        }
        echo '<td class="number">'.$burndown->get_end_value().'</td>'."\n";
        echo '<td class="status"><span class="status_spot status_spot_'.$warnings.'"></span></td>'."\n"; // status spot
        echo '<td class="number">'.(int)date('W', $burndown->get_end_date()->getTimestamp()).'</td>'."\n"; // week number
        echo '</tr>'."\n";
        echo '<tr class="burndown_graph" id="burndown_graph_'.$burndown->get_burndown_id().'" style="display: none;">'."\n";
        echo '<td colspan="3">';
        $burndown->show_graph();
        echo '</td>'."\n";
        echo '</tr>'."\n";
    }
    echo '</table>'."\n";
}

function bb_burndown_add_dashboard_widgets() {
    wp_add_dashboard_widget('bb_burndown_widget', 'Burndowns', 'bb_burndown_widget');
}
add_action('wp_dashboard_setup', 'bb_burndown_add_dashboard_widgets');
