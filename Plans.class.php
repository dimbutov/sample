<?php


class Plans extends Viewer
{

    var $layout = 'redesign/layout/default.html';

    function work($request)
    {
        global $smarty;
        if ($request['plan_id']) {
            $flats = $this->getFlatsByPlanId($request['plan_id']);

            $smarty->assign('built_in_progress', $flats[0]['built_in_progress']);
            $smarty->assign('house_name', $flats[0]['house_name']);
            $smarty->assign('plan_rooms', $flats[0]['rooms']);
            $smarty->assign('plan_square', $flats[0]['square_all']);
            $smarty->assign('plan_img', Plans::getPlanImg($flats[0]['plan_id']));
            $smarty->assign('flats', $flats);
            $smarty->site($this->layout, 'redesign/plans/detail.html');
        } else {
            $filter = FilterHelper::getFilterSettingsFrom($request);
            $plans = $this->getPlans($filter['filter_actual']);

            $smarty->assign('plans', $plans);
            $smarty->assign('filter_defaults', $filter['filter_defaults']);
            $smarty->assign('filter_actual', $filter['filter_actual']);
            $smarty->site($this->layout, 'redesign/plans/index.html');
        }
    }

    private function getPlans($filter)
    {
        global $db;

        $isStudio = isset($filter['rooms']) && ! empty(array_filter($filter['rooms'], function ($el) {
                return $el === 'studio';
            }));

        $isFlatAction = isset($filter['flat_actions']) ? array_filter($filter['flat_actions'], function ($el) {
            return is_numeric($el);
        }) : false;
        $isPlanFeature = isset($filter['plan_features']) ? array_filter($filter['plan_features'], function ($el) {
            return is_numeric($el);
        }) : false;

        $sql_add = '';

        if ($isStudio && empty($rooms))
            $sql_add .= " AND f.studio = 'on' ";
        elseif ( ! empty($rooms) && ! $isStudio)
            $sql_add .= " AND f.rooms IN (" . implode(',', $rooms) . ") ";

        $limit = isset($filter['offset']) && $filter['offset'] > 0 ? " LIMIT " . $filter['offset'] * 8 . ", 8" : ' LIMIT 0, 8';

        $plans = $db->get_array("SELECT f.id, f.plan_id, f.studio,...
																GROUP BY f.plan_id ORDER BY min_price" . $limit);

        $result = array_map(function ($el) {
            $el['rooms'] = $el['studio'] === 'on' ? 'studio' : $el['rooms'];
            $el['actions'] = $el['actions'] ? Flats::getActions($el['actions']) : '';
            $el['features'] = $this->getFeatures($el['plan_id']);
            $el['img'] = Plans::getPlanImg($el['plan_id']);
            $el['link'] = $this->getFlatLinkById($el['id']);
            return $el;
        }, $plans);

        return $result;
    }

    public static function getPlanImg($id)
    {
        $img = get_images($id, 26, 1);
        if ( ! $img)
            $img = get_images($id, 4, 1);

        return $img['url_big'];
    }

    private function getFlatsByPlanId($id)
    {
        global $db;

        $flats = $db->get_array("SELECT f.id, f.num, f.plan_id, f.studio,...
																ORDER BY f.price");

        return array_map(function ($el) {
            $el['rooms'] = $el['studio'] === 'on' ? 'studio' : $el['rooms'];
            $el['built_in_progress'] = $el['built_in_progress'] === 'on';
            return $el;
        }, $flats);

    }

    private function getFlatLinkById($id)
    {
        global $db;

        $flat = $db->get_data("SELECT f.id, f.num, f.flor, s.crm_id AS section_num,...
																ORDER BY f.price LIMIT 1");

        return "/k{$flat['kvartal']}/block{$flat['kvartal_block']}/{$flat['house_user_url']}/section{$flat['section_num']}/floor{$flat['flor']}/flat{$flat['num']}/";
    }

    private function getFeatures($plan_id)
    {
        global $db;

        $data = $db->get_array("SELECT * FROM plan_features WHERE id IN (SELECT feature_id FROM plans_plan_features WHERE plan_id = '{$plan_id}') ORDER BY name");

        return $data;
    }

    public function uploadMorePlans($request)
    {
        global $smarty, $db;

        $data = array();

        $filter = FilterHelper::getFilterSettingsFrom($request);
        $plans = $this->getPlans($filter['filter_actual']);

        foreach ($plans as $plan) {
            $smarty->assign('plan', $plan);
            $data[] = $smarty->fetch('redesign/plans/item.html');
        }

        echo json_encode($data);
    }

}
