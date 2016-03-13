<?php
class Burndowns {
    public function __construct() {
        // placeholder
    }

    /**
     * Gets all currently active (published) burndowns
     * @return array of Burndown objects
     */
    public function get_active_burndowns() {
        $args = $this->get_base_args();
        $burndowns = get_posts($args);
        $result = array();
        foreach ($burndowns as $burndown) {
            $result[] = new Burndown($burndown->ID);
        }
        return $result;
    }

    private function get_base_args() {
        return array(
                'post_type' => 'burndown',
                'posts_per_page' => -1,
                'suppress_filters' => false,
        );
    }
}
