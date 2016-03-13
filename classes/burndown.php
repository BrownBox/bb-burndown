<?php
class Burndown {
    /**
     * Burndown ID
     * @var integer
     */
    private $burndown_id;

    /**
     * Burndown post object
     * @var WP_Post
     */
    private $burndown;

    /**
     * Number of days per point on graph
     * @var integer
     */
    private $graph_interval;

    /**
     * Collection of datapoints
     * @var array of WP_Post
     */
    private $datapoints = array();

    /**
     * Collection of datapoint values grouped by interval
     * @var array
     */
    private $graph_points = array();

    /**
     * String to show before values
     * @var string
     */
    private $prefix = '';

    /**
     * String to show after values
     * @var string
     */
    private $suffix = '';

    /**
     * Starting value
     * @var float
     */
    private $start_value;

    /**
     * End value
     * @var float
     */
    private $end_value;

    /**
     * Start date
     * @var DateTime
     */
    private $start_date;

    /**
     * End date - i.e. date of last datapoint
     * @var DateTime
     */
    private $end_date;

    /**
     * Constructor
     * @param string $burndown_id Optional. Post ID of burndown. If empty will use global $post.
     */
    public function __construct($burndown_id = null) {
        // @TODO add some error handling here if no post, or if it's not a burndown
        if (is_null($burndown_id)) {
            global $post;
            $this->burndown_id = $post->ID;
            $this->burndown = $post;
        } else {
            $this->burndown_id = $burndown_id;
            $this->burndown = get_post($this->burndown_id);
        }
        $burndown_meta = get_post_meta($this->burndown_id);
        $this->graph_interval = $burndown_meta['graph_interval'][0];
        $this->prefix = $burndown_meta['unit_of_measurement_prefix'][0];
        $this->suffix = $burndown_meta['unit_of_measurement_suffix'][0];
        $this->start_value = $this->end_value = $burndown_meta['start_value'][0];
        $this->process_datapoints();
    }

    private function process_datapoints() {
        $args = array(
                'post_type' => 'datapoint',
                'posts_per_page' => -1,
                'orderby' => 'date',
                'order' => 'ASC',
                'tax_query' => array(
                        array(
                                'taxonomy' => 'burndownascategory',
                                'field' => 'slug',
                                'terms' => $this->burndown_id,
                        ),
                ),
        );
        $datapoints = get_posts($args);

        $graph_points = $interval_vals = array();
        $burndown_date = new DateTime($this->burndown->post_date);
        $burndown_date->setTime(0, 0, 0);
        $this->start_date = clone $burndown_date;

        // Group datapoints by interval
        foreach ($datapoints as $datapoint) {
            $datapoint_date = new DateTime($datapoint->post_date);
            $datapoint_date->setTime(0, 0, 0);
            $this->end_date = clone $datapoint_date;
            $diff = $burndown_date->diff($datapoint_date)->days;
            $interval_group = ceil($diff/$this->graph_interval);
            if (!isset($interval_vals[$interval_group])) {
                $interval_vals[$interval_group] = 0;
            }
            $interval_vals[$interval_group] += get_post_meta($datapoint->ID, 'increase_amount', true) - get_post_meta($datapoint->ID, 'decrease_amount', true);
        }

        // Now put them into the series for graphing
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        $interval = new DateInterval('P'.$this->graph_interval.'D');
        $i = 0;
        while ($burndown_date <= $today || $i <= $interval_group) {
            $val = isset($interval_vals[$i]) ? $interval_vals[$i] : 0;
            $this->end_value += $val;
            $graph_points[$burndown_date->format('d/m/Y')] = $this->end_value;
            $burndown_date->add($interval);
            $i++;
        }

        $this->graph_points = $graph_points;
    }

    /**
     * Output the highcharts graph for this burndown
     */
    public function show_graph() {
?>
        <div><div class="bb_burndown" id="bb_burndown_<?php echo $this->burndown_id; ?>" data-sparkline="<?php echo implode(',', $this->graph_points); ?>" data-interval="<?php echo implode(',', array_keys($this->graph_points)); ?>" data-highcharts-chart="15"></div><span class="value"><?php echo $this->prefix.$this->end_value.$this->suffix; ?></span></div>
        <script type="text/javascript">
            jQuery(document).ready(function() {
                // Set up some variables
                var chart_holder = jQuery('div#bb_burndown_<?php echo $this->burndown_id; ?>');
                var chart = {};
                var stringdata, arr, categories, data;
                // Get intervals
                stringdata = chart_holder.data('interval');
                arr = stringdata.split(';');
                categories = arr[0].split(',');
                // Get values
                stringdata = chart_holder.data('sparkline');
                arr = stringdata.split(';');
                data = jQuery.map(arr[0].split(','), parseFloat);
                // Show the chart!
                chart_holder.highcharts('SparkLine', {
                    xAxis: {
                        categories: categories
                    },
                    series: [{
                        data: data
                    }],
                    tooltip: {
                        pointFormat: '<b><?php echo $this->prefix; ?>{point.y}<?php echo $this->suffix; ?></b>'
                    },
                    chart: chart
                });
            });
        </script>
<?php
    }

    public function get_burndown_id() {
        return $this->burndown->ID;
    }

    /**
     * Get start value
     * @param boolean $formatted
     * @return Mixed integer or string
     */
    public function get_start_value($formatted = true) {
        $value = $this->start_value;
        if ($formatted) {
            $value = $this->format_value($value);
        }
        return $value;
    }

    /**
     * Get end value
     * @param boolean $formatted
     * @return Mixed integer or string
     */
    public function get_end_value($formatted = true) {
        $value = $this->end_value;
        if ($formatted) {
            $value = $this->format_value($value);
        }
        return $value;
    }

    /**
     * Get start date
     * @return DateTime
     */
    public function get_start_date() {
        return $this->start_date;
    }

    /**
     * Get end date (i.e. date of most recent datapoint)
     * @return DateTime
     */
    public function get_end_date() {
        return $this->end_date;
    }

    private function format_value($value) {
        return $this->prefix.$value.$this->suffix;
    }
}
