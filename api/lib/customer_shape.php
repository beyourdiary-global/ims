<?php
/**
 * Shared row shaping for the customer endpoints.
 * Loaded by api/customers.php and api/customer.php.
 */

if (!defined('CMS_API_ENTRY')) {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo '{"ok":false,"error":{"code":"direct_access_forbidden","message":"This file cannot be requested directly."}}';
    exit;
}

if (!function_exists('cmsApiShapeCustomer')) {
    /**
     * Normalise one raw shopee_customer_info row into a stable shape.
     *
     * @param array $row           Raw row from shopee_customer_info.
     * @param array $customerTags  tag_map entry for this customer.
     * @param array $customerLabels label_map entry for this customer.
     * @param array $lookupMaps    dataset lookup_maps (pic / country / brand / series).
     */
    function cmsApiShapeCustomer($row, $customerTags, $customerLabels, $lookupMaps)
    {
        $row = is_array($row) ? $row : array();
        $lookupMaps = is_array($lookupMaps) ? $lookupMaps : array();

        $resolved = array();
        foreach (array('pic' => 'pic_name', 'country' => 'country_name', 'brand' => 'brand_name', 'series' => 'series_name') as $field => $alias) {
            $rawValue = isset($row[$field]) ? trim((string) $row[$field]) : '';
            $resolved[$alias] = $rawValue;
            if ($rawValue !== '' && isset($lookupMaps[$field][$rawValue])) {
                $resolved[$alias] = (string) $lookupMaps[$field][$rawValue];
            }
        }

        $tags = array();
        foreach ((array) $customerTags as $tagRow) {
            $tags[] = array(
                'tag_id' => isset($tagRow['tag_id']) ? (int) $tagRow['tag_id'] : 0,
                'name' => isset($tagRow['name']) ? (string) $tagRow['name'] : '',
                'remark' => isset($tagRow['remark']) ? (string) $tagRow['remark'] : '',
            );
        }

        $labels = array();
        foreach (array('segmentation', 'level', 'repeat') as $labelType) {
            if (!isset($customerLabels[$labelType]) || !is_array($customerLabels[$labelType])) {
                continue;
            }
            $meta = $customerLabels[$labelType];
            $labels[$labelType] = array(
                'id' => isset($meta['id']) ? (int) $meta['id'] : 0,
                'name' => isset($meta['name']) ? (string) $meta['name'] : '',
            );
        }

        return array(
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'buyer_username' => isset($row['buyer_username']) ? (string) $row['buyer_username'] : '',
            'contact_no' => isset($row['contact_no']) ? (string) $row['contact_no'] : '',
            'country' => isset($row['country']) ? (string) $row['country'] : '',
            'country_name' => $resolved['country_name'],
            'brand' => isset($row['brand']) ? (string) $row['brand'] : '',
            'brand_name' => $resolved['brand_name'],
            'series' => isset($row['series']) ? (string) $row['series'] : '',
            'series_name' => $resolved['series_name'],
            'pic' => isset($row['pic']) ? (string) $row['pic'] : '',
            'pic_name' => $resolved['pic_name'],
            'remark' => isset($row['remark']) ? (string) $row['remark'] : '',
            'birthday' => isset($row['birthday']) ? (string) $row['birthday'] : '',
            'birthday_year' => isset($row['birthday_year']) ? $row['birthday_year'] : null,
            'birthday_month' => isset($row['birthday_month']) ? $row['birthday_month'] : null,
            'birthday_day' => isset($row['birthday_day']) ? $row['birthday_day'] : null,
            'status' => isset($row['status']) ? (string) $row['status'] : '',
            'tags' => $tags,
            'labels' => $labels,
        );
    }
}
